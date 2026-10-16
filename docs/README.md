# Captcha Plugin Documentation

The Captcha plugin adds CAPTCHA widgets to CakePHP 2 forms and verifies the tokens on the server.
It supports Cloudflare Turnstile, Google reCAPTCHA v2 and v3, and hCaptcha, and you can add your
own provider.

- [Installation](#installation)
- [Configuration](#configuration)
- [Rendering the Widget (CaptchaHelper)](helper.md)
- [Verifying the Token (CaptchaComponent)](component.md) and the error codes
- [Providers](providers.md), custom providers and references
- [Security](security.md): security notes and Content Security Policy
- [Testing](testing.md)

## Installation

Install the plugin with [Composer](https://getcomposer.org/):

```bash
composer require pieceofcake2/captcha
```

The plugin has the `cakephp-plugin` type, so it is installed into your plugins directory by
`composer/installers`. Set `installer-paths` in your application's `composer.json`:

| Layout | `installer-paths` |
| --- | --- |
| Modern (`config/`, `src/`, `templates/`, `plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| Legacy (`app/Config/`, `app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`). If you
already use `CakePlugin::loadAll()`, this is not necessary:

```php
CakePlugin::load('Captcha');
```

## Configuration

Configure the provider and the keys with `Configure::write('Captcha', [...])`, for example in
`config/bootstrap.php`. Do not commit the keys. With the
[pieceofcake2/app](https://github.com/pieceofcake2/app) skeleton, put them in `config/.env.local`,
which is ignored by Git (the committed `config/.env` holds only shared defaults), or set real
environment variables in production, and read them with `env()`:

```dotenv
# config/.env.local
CAPTCHA_SITE_KEY="0x4AAAAAAA..."
CAPTCHA_SECRET_KEY="0x4AAAAAAA..."
```

```php
Configure::write('Captcha', [
    'provider' => 'turnstile',
    'siteKey' => env('CAPTCHA_SITE_KEY'),
    'secretKey' => env('CAPTCHA_SECRET_KEY'),
]);
```

The helper and the component settings are merged over this configuration, so you can use
different settings (even a different provider) per controller.

| Key | Default | Description |
| --- | --- | --- |
| `provider` | `turnstile` | `turnstile`, `recaptcha_v2` (alias `recaptcha`), `recaptcha_v3`, `hcaptcha`, or a class name (see [Custom Providers](providers.md#custom-providers)). |
| `siteKey` | | The public site key. Rendered into the page. |
| `secretKey` | | The secret key. Sent only to the verify endpoint. |
| `action` | | Action name. Rendered into the widget (Turnstile, reCAPTCHA v3) and compared with the `action` in the verify response. reCAPTCHA v3 defaults to `submit`. |
| `hostname` | | Expected hostname, or a list of hostnames. When set, a token solved on another hostname fails. In the component, `true` uses the host of the current request. |
| `scoreThreshold` | `0.5` | reCAPTCHA v3 only. The minimum score (0.0 to 1.0). `false` disables the check. |
| `timeout` | `10` | Timeout of the verify request in seconds. |
| `retry` | `0` | Number of retries after a network error or a non-2xx response. Turnstile requests carry an idempotency key, so a retry is safe. |
| `language` | | Widget language. Turnstile: `data-language`. reCAPTCHA and hCaptcha: the `hl` script parameter. |
| `scriptUrl` | provider's | Override the script URL, e.g. `https://www.recaptcha.net/recaptcha/api.js`. |
| `verifyUrl` | provider's | Override the verify endpoint, e.g. `https://www.recaptcha.net/recaptcha/api/siteverify`. |
| `nonce` | | CSP nonce added to the script tags (see [Content Security Policy](security.md#content-security-policy)). |
| widget options | | Provider specific options such as `theme` and `size` (see [Providers](providers.md)). |

## Origin

This package was created by [pieceofcake2](https://github.com/pieceofcake2), which also maintains
it. It is not a fork of another project and is not based on one.
