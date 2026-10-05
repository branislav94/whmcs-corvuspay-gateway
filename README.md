# WHMCS CorvusPay Gateway

Unofficial CorvusPay payment gateway module for WHMCS.

## Version

0.1.0

## Features

- Basic CorvusPay card checkout
- Test and production environments
- HMAC-SHA256 request signing
- Return signature validation
- WHMCS invoice payment registration
- Duplicate transaction protection

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

## Security

Do not commit your CorvusPay Security Key. Enter it only in the WHMCS gateway configuration.

## Scope

This v0.1.0 release implements basic one-time card payments only. It does not include tokenized cards, recurring charges, refund API support, or webhook processing.

## Disclaimer

This is an unofficial integration. Test thoroughly in the CorvusPay test environment before production use.
