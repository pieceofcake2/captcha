# Verifying the Token (CaptchaComponent)

```php
public $components = [
    'Captcha.Captcha' => [
        'actions' => ['add', 'login'],
    ],
];
```

## Automatic verification

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
| `trustProxy` | `false` | Read the client IP (and the host for `'hostname' => true`) from the proxy headers. See [Security Notes](security.md#security-notes). |

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

## Manual verification

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

## Verifying outside a controller

```php
App::uses('CaptchaFactory', 'Captcha.Lib');
App::uses('CaptchaVerifier', 'Captcha.Lib');

$verifier = new CaptchaVerifier(CaptchaFactory::provider(['action' => 'login']));
$result = $verifier->verify($token, $clientIp);
```

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
