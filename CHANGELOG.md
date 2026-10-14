# CHANGELOG

## Unreleased

- Initial release
- `CaptchaHelper` renders the provider script and widget (`script()`, `widget()`), with CSP nonce support and `SecurityComponent` field unlocking
- `CaptchaComponent` verifies tokens (`verify()`, `getErrors()`, `getResult()`) and can verify configured actions automatically (flash and redirect, exception, or none)
- Providers: Cloudflare Turnstile, Google reCAPTCHA v2 and v3, hCaptcha, and custom providers implementing `CaptchaProviderInterface`
- Verification fails closed on network errors, timeouts, non-2xx responses and invalid JSON, and can check `hostname`, `action` and the reCAPTCHA v3 `score`
