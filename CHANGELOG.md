# Changelog

All notable changes to `laravel-licensing-client` will be documented in this file.

## 2.0.0 - 2026-04-08

### Features
- Certificate chain verification (Ed25519) for offline key rotation support
- Public key bundle application from server responses to token validator
- Token validator initialization from stored bundle on boot
- PASETO issuer claim (`iss`) validation
- Clock skew tolerance (±60s, configurable) for token expiration and not-before checks
- Not-before (`nbf`) claim validation
- Entitlements storage and retrieval API
- Additional token claims exposed: `licensable_type`, `licensable_id`, `not_before`, `issuer`, `entitlements`

### Improvements
- Improved HTTP error code mapping (400, 5xx status codes)
- Full compatibility with `laravel-licensing` server v2.0.0

### CI
- Added PHP 8.5 to test matrix
- Added Laravel 13 to test matrix

### Breaking Changes
- Tokens with invalid issuer are now rejected (configure `licensing-client.issuer` to match your server)
- Tokens with certificate chain in footer are now verified against the root public key

**Full Changelog**: https://github.com/masterix21/laravel-licensing-client/compare/v1.0.0...2.0.0

## 1.0.0 - 2025-09-16

**Full Changelog**: https://github.com/masterix21/laravel-licensing-client/commits/v1.0.0
