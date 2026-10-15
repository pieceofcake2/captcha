# テスト

プロバイダーはテスト用のキーを公開しています。開発時やブラウザーでのテストではそれを使ってください。
本番環境では決して使ってはいけません。

**Cloudflare Turnstile** ([テスト](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)):

| サイトキー | 動作 |
| --- | --- |
| `1x00000000000000000000AA` | 常に成功 (表示あり) |
| `2x00000000000000000000AB` | 常にブロック (表示あり) |
| `1x00000000000000000000BB` | 常に成功 (非表示) |
| `2x00000000000000000000BB` | 常にブロック (非表示) |
| `3x00000000000000000000FF` | 対話型のチャレンジを強制 (表示あり) |

| シークレットキー | 動作 |
| --- | --- |
| `1x0000000000000000000000000000000AA` | 常に成功 |
| `2x0000000000000000000000000000000AA` | 常に失敗 (`invalid-input-response`) |
| `3x0000000000000000000000000000000AA` | 「トークンは使用済み」エラー (`timeout-or-duplicate`) |

ダミーのサイトキーはトークン `XXXX.DUMMY.TOKEN.XXXX` を生成し、これを受け付けるのはテスト用の
シークレットキーだけです。検証レスポンスには `"hostname": "example.com"` が含まれるので、テスト用の
キーでは `hostname` を設定しないでください。

**reCAPTCHA v2** ([FAQ](https://developers.google.com/recaptcha/docs/faq)): サイトキー
`6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`、シークレットキー `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`。
すべての検証が成功し、ウィジェットには警告が表示されます。reCAPTCHA v3 にはテスト用のキーがないので、
テスト用に別のキーを作成してください。

**hCaptcha** ([テスト用のキー](https://docs.hcaptcha.com/#integration-testing-test-keys)): サイトキー
`10000000-ffff-ffff-ffff-000000000001`、シークレットキー `0x0000000000000000000000000000000000000000`、
レスポンストークン `10000000-aaaa-bbbb-cccc-000000000001`。チャレンジは表示されず、常に成功します。

ユニットテストでは、テストの外にリクエストが出ないように `HttpSocket` をモックします。

```php
$http = $this->getMockBuilder(HttpSocket::class)->onlyMethods(['post'])->getMock();
$response = new HttpSocketResponse();
$response->code = 200;
$response->body = '{"success":true,"hostname":"example.com"}';
$http->method('post')->willReturn($response);

$this->Controller->Captcha->setHttpSocket($http);
```
