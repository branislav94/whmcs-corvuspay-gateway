# WHMCS CorvusPay Gateway

Unofficial CorvusPay payment gateway module for WHMCS.

## Version

0.2.0

## Features

- Basic CorvusPay card checkout
- Test and production environments
- HMAC-SHA256 request signing
- Return signature validation
- WHMCS invoice payment registration
- Duplicate transaction protection
- Optional webhook callback processing for asynchronous confirmations

## Installation

Copy:

```text
modules/gateways/corvuspay.php
modules/gateways/corvuspay/return.php
```

into the matching paths in your WHMCS installation.

Activate **CorvusPay** in WHMCS and configure:

- Store ID
- Security Key
- Optional Webhook Secret
- Test Mode
- Language

## CorvusPay return URLs

Configure the merchant account with:

```text
Success:
https://YOUR-WHMCS-DOMAIN/modules/gateways/corvuspay/return.php?status=success

Failure:
https://YOUR-WHMCS-DOMAIN/modules/gateways/corvuspay/return.php?status=failed
```

Use HTTP `POST`.

## CorvusPay webhook callback support

The same return endpoint can also accept a signed CorvusPay callback payload for asynchronous confirmation. When a valid callback arrives, the module verifies the signature and applies the invoice payment if it has not already been processed.

## Security

Do not commit your CorvusPay Security Key or Webhook Secret. Enter them only in the WHMCS gateway configuration.

## Scope

This v0.2.0 release keeps the one-time card payment flow and adds webhook processing for asynchronous confirmations. Tokenized cards, recurring charges, and refund API support remain out of scope for this release.

## Disclaimer

This is an unofficial integration. Test thoroughly in the CorvusPay test environment before production use.
