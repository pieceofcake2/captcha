# Rendering the Widget (CaptchaHelper)

Add the helper to your controller (or `AppController`). Settings are merged over the `Captcha`
configuration:

```php
public $helpers = ['Captcha.Captcha' => ['theme' => 'light']];
```

## `widget(array $options = [])`

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

## `script(array $options = [])`

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

## `provider()`

Returns the configured `CaptchaProviderInterface` instance.
