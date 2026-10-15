# Testing

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
