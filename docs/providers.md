# Providers

## Cloudflare Turnstile (`turnstile`)

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

## Google reCAPTCHA v2 (`recaptcha_v2`)

Renders the "I'm not a robot" checkbox. Widget options: `theme` (`light`, `dark`), `size`
(`normal`, `compact`), `tabindex`, `callback`, `expiredCallback`, `errorCallback`. `language`
is sent as the `hl` script parameter. The response does not contain the action, so `action` is
not checked. The invisible reCAPTCHA (`size` `invisible`) needs your own JavaScript to call
`grecaptcha.execute()`.

Where www.google.com is not accessible, set `scriptUrl` to
`https://www.recaptcha.net/recaptcha/api.js` and `verifyUrl` to
`https://www.recaptcha.net/recaptcha/api/siteverify`.

## Google reCAPTCHA v3 (`recaptcha_v3`)

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

## hCaptcha (`hcaptcha`)

Widget options: `theme` (`light`, `dark`), `size` (`normal`, `compact`), `tabindex`, `callback`,
`expiredCallback`, `chalexpiredCallback`, `openCallback`, `closeCallback`, `errorCallback`.
`language` is sent as the `hl` script parameter.

- The site key is sent to the verify endpoint (`sitekey`), as hCaptcha recommends, so tokens
  issued for another site key are rejected.
- The hCaptcha script also adds `g-recaptcha-response` unless it is loaded with
  `recaptchacompat=off`: `$this->Captcha->widget(['script' => ['query' => ['recaptchacompat' => 'off']]])`.
- The hCaptcha Enterprise `score` is not checked (a higher hCaptcha score means a more likely
  bot). Read it with `getResult()->getScore()`.

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
