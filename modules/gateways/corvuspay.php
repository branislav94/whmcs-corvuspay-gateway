<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function corvuspay_MetaData()
{
    return [
        'DisplayName' => 'CorvusPay',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function corvuspay_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'CorvusPay',
        ],
        'storeId' => [
            'FriendlyName' => 'Store ID',
            'Type' => 'text',
            'Size' => '20',
            'Description' => 'CorvusPay Store ID',
        ],
        'secretKey' => [
            'FriendlyName' => 'Security Key',
            'Type' => 'password',
            'Size' => '60',
            'Description' => 'CorvusPay secret security key',
        ],
        'webhookSecret' => [
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'password',
            'Size' => '60',
            'Description' => 'Optional CorvusPay webhook secret for callback validation',
        ],
        'testMode' => [
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Use CorvusPay test environment',
        ],
        'language' => [
            'FriendlyName' => 'Language',
            'Type' => 'dropdown',
            'Options' => [
                'sr' => 'Serbian',
                'en' => 'English',
                'hr' => 'Croatian',
            ],
            'Default' => 'sr',
        ],
    ];
}

function corvuspay_calculateSignature(array $params, string $secretKey): string
{
    unset($params['signature']);
    ksort($params, SORT_STRING);

    $data = '';
    foreach ($params as $key => $value) {
        $data .= $key . $value;
    }

    return hash_hmac('sha256', $data, $secretKey);
}

function corvuspay_link($params)
{
    $storeId   = trim($params['storeId']);
    $secretKey = trim($params['secretKey']);
    $testMode  = !empty($params['testMode']) && $params['testMode'] === 'on';
    $language  = !empty($params['language']) ? $params['language'] : 'sr';

    $invoiceId = (int) $params['invoiceid'];
    $currency  = strtoupper($params['currency']);
    $amount    = number_format((float) $params['amount'], 2, '.', '');

    if ($storeId === '' || $secretKey === '') {
        return '<div class="alert alert-danger">CorvusPay gateway is not configured.</div>';
    }

    $timestamp = time();

    try {
        $random = bin2hex(random_bytes(3));
    } catch (\Throwable $e) {
        $random = substr(md5(uniqid('', true)), 0, 6);
    }

    $orderNumber = sprintf('cp_%d_%d_%s', $invoiceId, $timestamp, $random);

    $paymentParams = [
        'version'          => '1.4',
        'store_id'         => $storeId,
        'order_number'     => $orderNumber,
        'language'         => $language,
        'currency'         => $currency,
        'amount'           => $amount,
        'cart'             => 'WHMCS Invoice #' . $invoiceId,
        'require_complete' => 'false',
    ];

    $paymentParams['signature'] = corvuspay_calculateSignature($paymentParams, $secretKey);

    $checkoutUrl = $testMode
        ? 'https://wallet.test.corvuspay.com/checkout/'
        : 'https://wallet.corvuspay.com/checkout/';

    $html = '<form method="post" action="' . htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8') . '">';

    foreach ($paymentParams as $name => $value) {
        $html .= '<input type="hidden" name="' .
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8') .
            '" value="' .
            htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') .
            '">';
    }

    $buttonText = !empty($params['langpaynow']) ? $params['langpaynow'] : 'Pay by card';

    $html .= '<button type="submit" class="btn btn-primary">';
    $html .= htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
    $html .= '</button>';
    $html .= '</form>';

    return $html;
}
