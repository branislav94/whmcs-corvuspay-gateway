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
    unset($params['signature'], $params['sig'], $params['hash'], $params['authorization']);
    ksort($params, SORT_STRING);

    $data = '';
    foreach ($params as $key => $value) {
        $data .= $key . (string) $value;
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

function corvuspay_get_payload(): array
{
    $rawInput = file_get_contents('php://input');
    $payload = $_POST;

    if (is_string($rawInput) && trim($rawInput) !== '') {
        $decoded = json_decode($rawInput, true);

        if (is_array($decoded)) {
            $payload = $decoded;
        } else {
            parse_str($rawInput, $payload);
        }
    }

    return is_array($payload) ? $payload : [];
}

function corvuspay_process_webhook(array $gatewayParams, array $payload): bool
{
    $receivedSignature = '';
    foreach (['signature', 'sig', 'hash', 'authorization'] as $key) {
        if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
            $receivedSignature = strtolower(trim((string) $payload[$key]));
            break;
        }
    }

    if ($receivedSignature === '') {
        return false;
    }

    $secretKey = trim((string) ($gatewayParams['webhookSecret'] ?? $gatewayParams['secretKey'] ?? ''));
    if ($secretKey === '') {
        return false;
    }

    $expectedSignature = corvuspay_return_signature($payload, $secretKey);
    if (
        strlen($receivedSignature) !== 64 ||
        !ctype_xdigit($receivedSignature) ||
        !hash_equals(strtolower($expectedSignature), $receivedSignature)
    ) {
        return false;
    }

    $status = strtolower((string) ($payload['status'] ?? $payload['payment_status'] ?? $payload['paymentStatus'] ?? ''));
    if (!in_array($status, ['success', 'completed', 'approved', 'paid', 'succeeded'], true)) {
        return true;
    }

    $orderNumber = trim((string) ($payload['order_number'] ?? $payload['orderNumber'] ?? ''));
    if ($orderNumber === '') {
        return false;
    }

    $invoiceId = 0;
    if (preg_match('/^cp_(\d+)_(\d+)_[a-f0-9]{6}$/i', $orderNumber, $matches)) {
        $invoiceId = (int) $matches[1];
    }

    if ($invoiceId <= 0 && isset($payload['invoice_id'])) {
        $invoiceId = (int) $payload['invoice_id'];
    }

    if ($invoiceId <= 0 && isset($payload['invoiceId'])) {
        $invoiceId = (int) $payload['invoiceId'];
    }

    if ($invoiceId <= 0) {
        return false;
    }

    $approvalCode = trim((string) ($payload['approval_code'] ?? $payload['approvalCode'] ?? ''));
    $transactionId = sprintf(
        'CP-%s-%s',
        $orderNumber,
        preg_replace('/[^A-Za-z0-9_-]/', '', $approvalCode !== '' ? $approvalCode : $orderNumber)
    );

    checkCbInvoiceID($invoiceId, $gatewayParams['name']);
    checkCbTransID($transactionId);

    logTransaction(
        $gatewayParams['name'],
        [
            'order_number' => $orderNumber,
            'status' => $status,
            'transaction_id' => $transactionId,
            'signature' => $receivedSignature,
        ],
        'Webhook Successful'
    );

    addInvoicePayment(
        $invoiceId,
        $transactionId,
        0,
        0,
        $gatewayParams['paymentmethod']
    );

    return true;
}

$payload = corvuspay_get_payload();
$status = isset($_GET['status']) ? strtolower((string) $_GET['status']) : (isset($payload['status']) ? strtolower((string) $payload['status']) : '');

if ($status === '' && !empty($payload) && isset($payload['signature'])) {
    if (!corvuspay_process_webhook($gatewayParams, $payload)) {
        logTransaction($gatewayParams['name'], $payload, 'Invalid Webhook Signature');
        http_response_code(403);
        exit('Invalid CorvusPay webhook signature.');
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

$orderNumber = isset($_POST['order_number']) ? trim((string) $_POST['order_number']) : '';
$invoiceId = 0;

if (preg_match('/^cp_(\d+)_(\d+)_[a-f0-9]{6}$/i', $orderNumber, $matches)) {
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
