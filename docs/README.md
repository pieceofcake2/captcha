# Captcha Plugin Documentation

The Captcha plugin adds CAPTCHA widgets to CakePHP 2 forms and verifies the tokens on the server.
It supports Cloudflare Turnstile, Google reCAPTCHA v2 and v3, and hCaptcha, and you can add your
own provider.

- [Installation](#installation)
- [Configuration](#configuration)
- [Rendering the Widget (CaptchaHelper)](#rendering-the-widget-captchahelper)
- [Verifying the Token (CaptchaComponent)](#verifying-the-token-captchacomponent)
- [Providers](#providers)
- [Error Codes](#error-codes)
- [Content Security Policy](#content-security-policy)
- [Testing](#testing)
- [Security Notes](#security-notes)
- [Custom Providers](#custom-providers)
- [References](#references)

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
[pieceofcake2/app](https://github.com/pieceofcake2/app) skeleton, put them in `config/.env`
(or set real environment variables in production) and read them with `env()`:

```dotenv
# config/.env
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
| `provider` | `turnstile` | `turnstile`, `recaptcha_v2` (alias `recaptcha`), `recaptcha_v3`, `hcaptcha`, or a class name (see [Custom Providers](#custom-providers)). |
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
| `nonce` | | CSP nonce added to the script tags (see [Content Security Policy](#content-security-policy)). |
| widget options | | Provider specific options such as `theme` and `size` (see [Providers](#providers)). |

## Rendering the Widget (CaptchaHelper)

Add the helper to your controller (or `AppController`). Settings are merged over the `Captcha`
configuration:

```php
public $helpers = ['Captcha.Captcha' => ['theme' => 'light']];
```

### `widget(array $options = [])`

Renders the widget. Call it inside a form:

```php
echo $this->Form->create('Contact');
echo $this->Form->input('message');
echo $this->Captcha->widget();
echo $this->Form->end('Send');
```

```html
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async="async" defer="defer"></script>
<div class="cf-turnstile" data-sitekey="1x00000000000000000000AA" data-theme="light"></div>
```

The provider script adds a hidden field with the token to the form (`cf-turnstile-response`,
`g-recaptcha-response` or `h-captcha-response`).

Options:

- Widget options of the provider, e.g. `['theme' => 'dark', 'size' => 'compact', 'action' => 'login']`.
  They override the configuration.
- `script`: The provider script is output before the first widget. Pass `false` when you output it
  yourself with `script()`, or an array of `script()` options.
- `nonce`: CSP nonce for the script tags.
- Any other key is an HTML attribute (`id`, `class` is appended, `data-*`, ...).

When `FormHelper` is loaded, the widget unlocks the token fields (`FormHelper::unlockField()`),
so forms protected by `SecurityComponent` are not black-holed.

### `script(array $options = [])`

Returns the provider `<script>` tag, once per page. Use it when you want the script in the
`<head>`:

```php
$this->Captcha->script(['block' => 'script']);
```

| Option | Default | Description |
| --- | --- | --- |
| `block` | `false` | Append the tag to this view block instead of returning it. |
| `async`, `defer` | `true` | Add the `async` / `defer` attributes. |
| `nonce` | `nonce` setting | CSP nonce. |
| `render` | | `explicit` to render the widgets yourself with the provider's JavaScript API. |
| `onload` | | Name of a global function called when the script is loaded. |
| `language` | `language` setting | `hl` parameter (reCAPTCHA, hCaptcha). |
| `query` | | Additional query parameters, e.g. `['recaptchacompat' => 'off']` for hCaptcha. |
| `once` | `true` | Output the tag only once. |

### `provider()`

Returns the configured `CaptchaProviderInterface` instance.

## Verifying the Token (CaptchaComponent)

```php
public $components = [
    'Captcha.Captcha' => [
        'actions' => ['add', 'login'],
    ],
];
```

### Automatic verification

For the actions in `actions` (`['*']` for all actions), the component verifies the token in
`startup()`, after `beforeFilter()` and before the action. Only the methods in `methods` are
verified, so the action can still show the form on GET.

| Setting | Default | Description |
| --- | --- | --- |
| `actions` | `[]` | Actions verified automatically. `['*']` for all actions. |
| `methods` | `['post', 'put', 'patch']` | HTTP methods verified automatically. |
| `onFailure` | `redirect` | `redirect`: set a flash message and redirect. `exception`: throw `exception`. `none`: only record the result. |
| `flash` | `CAPTCHA verification failed. Please try again.` | Flash message for `redirect`. `false` for none. |
| `flashOptions` | `[]` | Options for `FlashComponent::set()`, e.g. `['element' => 'error']`. |
| `redirect` | referer | Redirect URL for `redirect`. |
| `exception` | `ForbiddenException` | Exception class for `exception`, e.g. `BadRequestException`. |
| `trustProxy` | `false` | Read the client IP (and the host for `'hostname' => true`) from the proxy headers. See [Security Notes](#security-notes). |

Any other setting is provider configuration and overrides `Configure::read('Captcha')`.

`redirect` discards the submitted data. To redisplay the form with the data, use
`'onFailure' => 'none'` and check the result in the action:

```php
public function add()
{
    if ($this->request->is('post')) {
        if (!$this->Captcha->verify()) {
            $this->Contact->invalidate('captcha', __('Please complete the CAPTCHA.'));
        } elseif ($this->Contact->save($this->request->data)) {
            return $this->redirect(['action' => 'index']);
        }
    }
}
```

`verify()` reuses the result of the automatic verification, so the token is sent to the
provider only once (tokens are single use).

### Manual verification

```php
if (!$this->Captcha->verify()) {
    $errors = $this->Captcha->getErrors(); // e.g. ['timeout-or-duplicate']
}
```

| Method | Description |
| --- | --- |
| `verify(?string $token = null, array $settings = []): bool` | Verify the token. The token defaults to the provider's field in the request data. `$settings` overrides the configuration for this call, e.g. `['action' => 'login']`. |
| `getErrors(): array` | Error codes of the last verification. |
| `getResult(): ?CaptchaResult` | The last result: `isSuccess()`, `getErrors()`, `getHostname()`, `getAction()`, `getScore()`, `getData($key)` (the decoded verify response). |
| `token(): ?string` | The token in the request data. |
| `requiresVerification(): bool` | Whether the current request is verified automatically. |
| `provider(array $settings = [])` | The configured provider. |
| `setHttpSocket(?HttpSocket $http)` | Use this `HttpSocket` for the verify requests (e.g. a mock in tests). |

### Verifying outside a controller

```php
App::uses('CaptchaFactory', 'Captcha.Lib');
App::uses('CaptchaVerifier', 'Captcha.Lib');

$verifier = new CaptchaVerifier(CaptchaFactory::provider(['action' => 'login']));
$result = $verifier->verify($token, $clientIp);
```

## Providers

### Cloudflare Turnstile (`turnstile`)

Widget options: `action`, `cData`, `theme` (`auto`, `light`, `dark`), `size` (`normal`,
`flexible`, `compact`), `language`, `appearance` (`always`, `execute`, `interaction-only`),
`execution` (`render`, `execute`), `tabindex`, `retry`, `retryInterval`, `refreshExpired`,
`refreshTimeout`, `feedbackEnabled`, and the callbacks `callback`, `errorCallback`,
`expiredCallback`, `timeoutCallback`, `beforeInteractiveCallback`, `afterInteractiveCallback`,
`unsupportedCallback` (names of global functions). They are rendered as the
[`data-*` attributes](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/).

- `action` is at most 32 characters and `cData` at most 255 characters (alphanumeric, `_` and `-`).
  When `action` is configured, the action in the verify response must match it.
- Tokens are at most 2048 characters, valid for 300 seconds, and can be verified once.
  Longer tokens are rejected without a request.
- Every verify request carries an `idempotency_key` (a UUID), so `retry` can resend the same
  request safely.

### Google reCAPTCHA v2 (`recaptcha_v2`)

Renders the "I'm not a robot" checkbox. Widget options: `theme` (`light`, `dark`), `size`
(`normal`, `compact`), `tabindex`, `callback`, `expiredCallback`, `errorCallback`. `language`
is sent as the `hl` script parameter. The response does not contain the action, so `action` is
not checked. The invisible reCAPTCHA (`size` `invisible`) needs your own JavaScript to call
`grecaptcha.execute()`.

Where www.google.com is not accessible, set `scriptUrl` to
`https://www.recaptcha.net/recaptcha/api.js` and `verifyUrl` to
`https://www.recaptcha.net/recaptcha/api/siteverify`.

### Google reCAPTCHA v3 (`recaptcha_v3`)

reCAPTCHA v3 has no widget. `widget()` renders a hidden `g-recaptcha-response` input and a small
script. When the form is submitted, the script calls `grecaptcha.execute()` with the action,
stores the token in the input and submits the form again (keeping the clicked submit button).
Tokens are fetched on submit because they expire after two minutes.

```php
echo $this->Captcha->widget(['action' => 'contact']);
```

```php
public $components = ['Captcha.Captcha' => ['actions' => ['add'], 'action' => 'contact', 'scoreThreshold' => 0.7]];
```

- `action` defaults to `submit`. It may contain alphanumeric characters, `/` and `_`. The action
  in the verify response must match the configured action, so configure the same action in the
  component as in the widget.
- `scoreThreshold` defaults to `0.5`. A response without a score or with a lower score fails with
  `score-too-low`. Read the score with `$this->Captcha->getResult()->getScore()`, e.g. to require
  additional verification instead of rejecting the request. Set `scoreThreshold` to `false` to
  make the decision yourself.

### hCaptcha (`hcaptcha`)

Widget options: `theme` (`light`, `dark`), `size` (`normal`, `compact`), `tabindex`, `callback`,
`expiredCallback`, `chalexpiredCallback`, `openCallback`, `closeCallback`, `errorCallback`.
`language` is sent as the `hl` script parameter.

- The site key is sent to the verify endpoint (`sitekey`), as hCaptcha recommends, so tokens
  issued for another site key are rejected.
- The hCaptcha script also adds `g-recaptcha-response` unless it is loaded with
  `recaptchacompat=off`: `$this->Captcha->widget(['script' => ['query' => ['recaptchacompat' => 'off']]])`.
- The hCaptcha Enterprise `score` is not checked (a higher hCaptcha score means a more likely
  bot). Read it with `getResult()->getScore()`.

## Error Codes

`getErrors()` returns the provider's `error-codes` (e.g. `invalid-input-response`,
`timeout-or-duplicate`, `invalid-input-secret`, hCaptcha's `already-seen-response`) and the
codes added by the plugin:

| Code | Description |
| --- | --- |
| `missing-input-response` | No token was submitted. No request is sent. |
| `invalid-input-response` | The token is longer than the provider allows. No request is sent. |
| `missing-input-secret` | `secretKey` is not configured. No request is sent and a warning is logged. |
| `verification-unavailable` | Network error, timeout or non-2xx response from the verify endpoint. |
| `invalid-json` | The verify endpoint returned something other than a JSON object. |
| `unknown-error` | `success` was not `true` and there were no error codes. |
| `hostname-mismatch` | The `hostname` in the response does not match `hostname`. |
| `action-mismatch` | The `action` in the response does not match `action`. |
| `score-too-low` | reCAPTCHA v3 score is missing or below `scoreThreshold`. |

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

## Testing

The providers publish test keys. Use them in development and in browser tests. They must never
be used in production.

**Cloudflare Turnstile** ([testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)):

| Site key | Behaviour |
| --- | --- |
| `1x00000000000000000000AA` | Always passes (visible) |
| `2x00000000000000000000AB` | Always blocks (visible) |
| `1x00000000000000000000BB` | Always passes (invisible) |
| `2x00000000000000000000BB` | Always blocks (invisible) |
| `3x00000000000000000000FF` | Forces an interactive challenge (visible) |

| Secret key | Behaviour |
| --- | --- |
| `1x0000000000000000000000000000000AA` | Always passes |
| `2x0000000000000000000000000000000AA` | Always fails (`invalid-input-response`) |
| `3x0000000000000000000000000000000AA` | Yields a "token already spent" error (`timeout-or-duplicate`) |

The dummy site keys produce the token `XXXX.DUMMY.TOKEN.XXXX`, which only the test secret keys
accept. The verify response contains `"hostname": "example.com"`, so do not set `hostname` with
the test keys.

**reCAPTCHA v2** ([FAQ](https://developers.google.com/recaptcha/docs/faq)): site key
`6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`, secret key `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`.
All verifications pass and the widget shows a warning. reCAPTCHA v3 has no test keys; create a
separate key for testing.

**hCaptcha** ([test keys](https://docs.hcaptcha.com/#integration-testing-test-keys)): site key
`10000000-ffff-ffff-ffff-000000000001`, secret key `0x0000000000000000000000000000000000000000`,
response token `10000000-aaaa-bbbb-cccc-000000000001`. They never show a challenge and always pass.

In unit tests, mock `HttpSocket` so no request leaves the test:

```php
$http = $this->getMockBuilder(HttpSocket::class)->onlyMethods(['post'])->getMock();
$response = new HttpSocketResponse();
$response->code = 200;
$response->body = '{"success":true,"hostname":"example.com"}';
$http->method('post')->willReturn($response);

$this->Controller->Captcha->setHttpSocket($http);
```

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

## Custom Providers

Set `provider` to a class that implements `CaptchaProviderInterface`. Extending
`BaseCaptchaProvider` gives you the common behaviour:

```php
// src/Lib/MyCaptchaProvider.php (legacy layout: app/Lib/MyCaptchaProvider.php)
App::uses('BaseCaptchaProvider', 'Captcha.Lib/Provider');

class MyCaptchaProvider extends BaseCaptchaProvider
{
    protected string $defaultScriptUrl = 'https://captcha.example.com/api.js';
    protected string $defaultVerifyUrl = 'https://captcha.example.com/siteverify';
    protected string $widgetClass = 'my-captcha';
    protected string $field = 'my-captcha-response';
    protected array $widgetOptions = ['theme' => 'data-theme'];

    public function name(): string
    {
        return 'my_captcha';
    }
}
```

```php
Configure::write('Captcha.provider', 'MyCaptchaProvider'); // or 'MyPlugin.MyCaptchaProvider'
```

The class is loaded with `App::uses()` from `Lib` (or the plugin's `Lib`) unless it is already
loaded. It is constructed with the merged configuration array.

## References

- Cloudflare Turnstile: [client-side rendering](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/),
  [widget configurations](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/),
  [server-side validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/),
  [testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/),
  [Content Security Policy](https://developers.cloudflare.com/turnstile/reference/content-security-policy/)
- Google reCAPTCHA: [v2 checkbox](https://developers.google.com/recaptcha/docs/display),
  [v3](https://developers.google.com/recaptcha/docs/v3),
  [verifying the user's response](https://developers.google.com/recaptcha/docs/verify),
  [FAQ (test keys, CSP)](https://developers.google.com/recaptcha/docs/faq)
- hCaptcha: [developer guide](https://docs.hcaptcha.com/),
  [configuration](https://docs.hcaptcha.com/configuration)
