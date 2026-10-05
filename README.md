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
- Updated order number format: cp_<invoice>_<timestamp>_<random>

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

## Order number format

The gateway now sends CorvusPay `order_number` in this format:

```text
cp_184_1791218847_a8f21c
```

Meaning:
- `cp_` = CorvusPay prefix
- `184` = WHMCS invoice ID
- `1791218847` = timestamp
- `a8f21c` = random suffix

## Security

Do not commit your CorvusPay Security Key. Enter it only in the WHMCS gateway configuration.

## Scope

This v0.2.0 release keeps the one-time card payment flow and updates the order number format to match the requested CorvusPay-style identifier. Tokenized cards, recurring charges, and refund API support remain out of scope for this release.

## Disclaimer

This is an unofficial integration. Test thoroughly in the CorvusPay test environment before production use.
