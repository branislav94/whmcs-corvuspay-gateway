# Changelog

## 0.2.0
- Updated CorvusPay order number format to cp_<invoice>_<timestamp>_<random>
- Kept the existing checkout flow compatible with the new identifier format
- Improved invoice ID extraction on return validation

## 0.1.0
- Initial working WHMCS CorvusPay gateway
- Test/production checkout support
- HMAC-SHA256 signing and return validation
- WHMCS invoice payment registration
