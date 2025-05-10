<?php
/* ZiNiPay WHMCS Plugin
 *
 * Copyright (c) 2024 codewithsiam
 * Website: https://zinipay.com/
 * Developer: https://github.com/codewithsiam
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function zinipay_MetaData()
{
    return array(
        'DisplayName' => 'ZiNiPay',
        'APIVersion' => '1.0',
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function zinipay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'zinipay',
        ),
        'apiKey' => array(
            'FriendlyName' => 'Brand API Key',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '',
            'Description' => 'Enter Your Brand API Key',
        ),
        'currency_rate' => array(
            'FriendlyName' => 'Currency Rate',
            'Type' => 'text',
            'Size' => '150',
            'Default' => '110',
            'Description' => 'Enter USD conversion rate (e.g., 110)',
        ),
    );
}

function zinipay_link($params)
{
    if (empty($params['invoiceid'])) {
        return "Error: Invalid invoice context (invoice ID not found).";
    }

    // Handle payment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
        $response = zinipay_payment_url($params);

        if ($response->status && !empty($response->payment_url)) {
            header('Location: ' . $response->payment_url);
            exit();
        } else {
            return '<div class="alert alert-danger">' . htmlspecialchars($response->message) . '</div>';
        }
    }

    // Show payment button
    return '<form method="POST">
        <input class="btn btn-primary" name="pay" type="submit" value="' . $params['langpaynow'] . '" />
    </form>';
}

function zinipay_payment_url($params)
{
    if (empty($params['invoiceid'])) {
        return (object)[
            'status' => false,
            'message' => 'Missing invoice ID',
        ];
    }

    $cus_name = trim($params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname']);
    $cus_name = !empty($cus_name) ? $cus_name : 'guest';
    $cus_email = $params['clientdetails']['email'] ?? 'guest@gmail.com';

    $apikey = $params['apiKey'];
    $currency_rate = $params['currency_rate'];
    $invoiceId = $params['invoiceid'];

    $amount = $params['amount'];
    if ($params['currency'] === "USD") {
        $amount *= $currency_rate;
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $systemUrl = $protocol . $_SERVER['HTTP_HOST'];

    $webhook_url = $systemUrl . '/modules/gateways/callback/zinipay.php?invoice=' . $invoiceId;
    $success_url = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
    $cancel_url = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;

    $payload = array(
        "cus_name"      => $cus_name,
        "cus_email"     => $cus_email,
        "amount"        => $amount,
        "metadata"      => ['invoice_id' => $invoiceId],
        "webhook_url"   => $webhook_url,
        "redirect_url"  => $success_url,
        "cancel_url"    => $cancel_url,
    );

    $headers = array(
        'Content-Type: application/json',
        'zini-api-key: ' . $apikey,
    );

    $ch = curl_init("https://api.zinipay.com/v1/payment/create");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return (object)[
            'status' => false,
            'message' => "cURL Error: " . $error
        ];
    }

    $res = json_decode($response, true);

    if (!empty($res['status']) && !empty($res['payment_url'])) {
        return (object)[
            'status' => true,
            'payment_url' => $res['payment_url']
        ];
    }

    return (object)[
        'status' => false,
        'message' => $res['message'] ?? 'Unknown error from ZiNiPay'
    ];
}
