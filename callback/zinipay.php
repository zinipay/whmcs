<?php
/* zinipay WHMCS Gateway
 *
 * Copyright (c) 2024 zinipay
 * Website: https://zinipay.com
 * Developer: https://github.com/codewithsiam
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Config\Setting;

// Get inputs
$invoiceId = $_GET['invoice'] ?? null;
$zinipayInvoiceId = $_REQUEST['invoiceId'] ?? null;
$hostname = $_GET['host'] ?? null;

$gatewayModuleName = "zinipay";
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Get stored config
$apikey = $gatewayParams['apiKey'] ?? '';
$currencyRate = $gatewayParams['currency_rate'] ?? '110';

echo "<pre>Invoice ID Hosting:\n";
print_r($invoiceId);
echo "</pre>";


// Check required params
if (!$invoiceId || !$zinipayInvoiceId || !$apikey) {
    echo "❌ Missing required parameters.";
    exit;
}

// Prepare request
$postData = json_encode(["invoiceId" => $zinipayInvoiceId]);

$headers = [
    'Content-Type: application/json',
    'zini-api-key: ' . $apikey,
];

$url = "https://api.zinipay.com/v1/payment/verify";

// Send CURL request
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($curl);
$curl_error = curl_error($curl);
curl_close($curl);

$responseData = json_decode($response, true);

// Process payment
if (isset($responseData['status']) && $responseData['status'] === "COMPLETED") {
   $transactionId = $responseData['invoice_id'] ?? uniqid('zinipay_');
$paymentAmount = $responseData['amount'] ?? 0; // BDT
$paymentFee = $responseData['fee'] ?? 0;
$invoiceIdFromMetaData = $responseData['metadata']['invoice_id'] ?? $invoiceId;

// Fetch order data
$orderData = localAPI('GetOrders', [
    'invoiceid' => $invoiceIdFromMetaData,
], 'admin1');

// Extract currency symbol (prefix), amount, and currency rate
$orderCurrency = $orderData['orders']['order'][0]['currencyprefix'] ?? '';
$orderAmount = $orderData['orders']['order'][0]['amount'] ?? 0;
$currencyRate = $gatewayParams['currency_rate'] ?? 100;


$convertedAmount = $paymentAmount;
// :::::::::::::::: extra security: uncomment below code and comment convertedAmount variable ::::::::::::::::::

// // Convert if currency is USD
// if ($orderCurrency == '$') {
//     $convertedAmount = $paymentAmount / $currencyRate;

//     // Check if converted amount ≈ order amount (within margin)
//     if (abs($convertedAmount - $orderAmount) > 0.01) {
//     exit;
//     }
// } else {
//     $convertedAmount = $paymentAmount;
// }

// echo "<pre>Order data:\n";
// print_r($orderData);
// echo "</pre>";

// Prevent duplicate transaction
checkCbTransID($transactionId);


// Apply payment using the converted amount
addInvoicePayment(
    $invoiceIdFromMetaData,
    $transactionId,
    $convertedAmount,
    $paymentFee,
    $gatewayModuleName
);


    // Redirect to invoice page
    $systemUrl = Setting::getValue('SystemURL');
    echo '<script>location.href = "' . $systemUrl . '/viewinvoice.php?id=' . $invoiceIdFromMetaData . '";</script>';
    exit;

} else {
    echo "❌ Payment verification failed or status not COMPLETED.";
}
