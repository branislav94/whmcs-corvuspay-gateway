<?php

$whmcsRoot = dirname(__DIR__, 3);

require_once $whmcsRoot . '/init.php';
require_once $whmcsRoot . '/includes/gatewayfunctions.php';
require_once $whmcsRoot . '/includes/invoicefunctions.php';

$gatewayModuleName = 'corvuspay';
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    http_response_code(503);
    exit('CorvusPay gateway is not active.');
}

function corvuspay_return_signature(array $params, string $secretKey): string
{
    unset($params['signature']);
    ksort($params, SORT_STRING);

    $data = '';
    foreach ($params as $key => $value) {
        $data .= $key . $value;
    }

    return hash_hmac('sha256', $data, $secretKey);
}

function corvuspay_redirect_to_invoice(int $invoiceId): void
{
    global $CONFIG;

    $baseUrl = !empty($CONFIG['SystemURL']) ? rtrim($CONFIG['SystemURL'], '/') : '';

    if ($invoiceId > 0 && $baseUrl !== '') {
        header('Location: ' . $baseUrl . '/viewinvoice.php?id=' . $invoiceId);
        exit;
    }

    if ($baseUrl !== '') {
        header('Location: ' . $baseUrl . '/clientarea.php');
        exit;
    }

    exit('Payment processed.');
}

$status = isset($_GET['status']) ? strtolower((string) $_GET['status']) : '';
$orderNumber = isset($_POST['order_number']) ? trim((string) $_POST['order_number']) : '';
$invoiceId = 0;

if (preg_match('/^WHMCS-(\d+)-\d+-[a-f0-9]{6}$/i', $orderNumber, $matches)) {
    $invoiceId = (int) $matches[1];
}

if ($status !== 'success') {
    logTransaction(
        $gatewayParams['name'],
        ['status' => 'cancelled', 'order_number' => $orderNumber],
        'Cancelled'
    );

    corvuspay_redirect_to_invoice($invoiceId);
}

$language = isset($_POST['language']) ? trim((string) $_POST['language']) : '';
$approvalCode = isset($_POST['approval_code']) ? trim((string) $_POST['approval_code']) : '';
$receivedSignature = isset($_POST['signature']) ? strtolower(trim((string) $_POST['signature'])) : '';

if (
    $invoiceId <= 0 ||
    $orderNumber === '' ||
    $language === '' ||
    $approvalCode === '' ||
    $receivedSignature === ''
) {
    logTransaction($gatewayParams['name'], $_POST, 'Missing Required Parameters');
    http_response_code(400);
    exit('Invalid CorvusPay response.');
}

$signatureParams = [
    'approval_code' => $approvalCode,
    'language'      => $language,
    'order_number'  => $orderNumber,
];

$expectedSignature = corvuspay_return_signature(
    $signatureParams,
    $gatewayParams['secretKey']
);

if (
    strlen($receivedSignature) !== 64 ||
    !ctype_xdigit($receivedSignature) ||
    !hash_equals(strtolower($expectedSignature), $receivedSignature)
) {
    logTransaction($gatewayParams['name'], $_POST, 'Invalid Signature');
    http_response_code(403);
    exit('Invalid CorvusPay signature.');
}

checkCbInvoiceID($invoiceId, $gatewayParams['name']);

$transactionId = sprintf(
    'CP-%s-%s',
    $orderNumber,
    preg_replace('/[^A-Za-z0-9_-]/', '', $approvalCode)
);

checkCbTransID($transactionId);

logTransaction(
    $gatewayParams['name'],
    [
        'order_number'  => $orderNumber,
        'language'      => $language,
        'approval_code' => $approvalCode,
        'signature'     => $receivedSignature,
    ],
    'Successful'
);

addInvoicePayment(
    $invoiceId,
    $transactionId,
    0,
    0,
    $gatewayParams['paymentmethod']
);

corvuspay_redirect_to_invoice($invoiceId);
