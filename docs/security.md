# Security

## Security Notes

- **Fail closed.** A missing token or secret key, a network error, a timeout, a non-2xx response
  and an invalid JSON body all fail the verification. `success` must be the boolean `true`.
- **Single use.** Tokens can be verified only once and expire (Turnstile: 300 seconds,
  reCAPTCHA and hCaptcha: 120 seconds). The component caches the result per request, so
  `verify()` after the automatic verification does not send the token again.
- **Hostname and action.** Set `hostname` (or `true` in the component) so tokens solved on another
  site are rejected, and `action` to bind a token to a form.
- **Secrets.** The secret key and the tokens are never logged. Requests are sent as
  `application/x-www-form-urlencoded` POST with TLS peer verification (`HttpSocket` defaults).
  Failures are logged as warnings in the `captcha` scope without the secret or the token.
- **Client IP and proxies.** The client IP is sent as `remoteip`. By default it is
  `CakeRequest::clientIp()` with `$safe = true`, i.e. `REMOTE_ADDR`. Behind a reverse proxy or a
  load balancer this is the proxy's address. Set `'trustProxy' => true` only when the proxy
  overwrites `X-Forwarded-For` (and `X-Forwarded-Host` for `'hostname' => true`); otherwise
  clients can forge these headers. Invalid IP addresses are not sent.
- **Verify on the server.** The widget alone does not protect anything. Always verify the token
  with the component (or `CaptchaVerifier`) before acting on the request.

## Content Security Policy

Pass a nonce to output it on the script tags (the provider script and the reCAPTCHA v3 inline
script):

```php
Configure::write('Captcha.nonce', $nonce);
// or
public $helpers = ['Captcha.Captcha' => ['nonce' => $nonce]];
// or
echo $this->Captcha->widget(['nonce' => $nonce]);
```

The providers also need these sources:

| Provider | Sources |
| --- | --- |
| Turnstile | `script-src` and `frame-src` `https://challenges.cloudflare.com` |
| reCAPTCHA | `script-src` `https://www.google.com/recaptcha/` `https://www.gstatic.com/recaptcha/`; `frame-src` `https://www.google.com/recaptcha/` `https://recaptcha.google.com/recaptcha/` |
| hCaptcha | `script-src`, `frame-src`, `style-src` and `connect-src` `https://hcaptcha.com` `https://*.hcaptcha.com` |
