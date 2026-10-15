# トークンの検証 (CaptchaComponent)

```php
public $components = [
    'Captcha.Captcha' => [
        'actions' => ['add', 'login'],
    ],
];
```

<a id="automatic-verification"></a>

## 自動検証

`actions` に含まれるアクション (すべてのアクションの場合は `['*']`) では、コンポーネントが
`startup()` の中で、`beforeFilter()` の後、アクションの前にトークンを検証します。検証されるのは
`methods` に含まれるメソッドだけなので、アクションは GET でフォームを表示できます。

| 設定 | デフォルト | 説明 |
| --- | --- | --- |
| `actions` | `[]` | 自動的に検証するアクション。すべてのアクションの場合は `['*']`。 |
| `methods` | `['post', 'put', 'patch']` | 自動的に検証する HTTP メソッド。 |
| `onFailure` | `redirect` | `redirect`: フラッシュメッセージを設定してリダイレクトします。`exception`: `exception` を投げます。`none`: 結果を記録するだけです。 |
| `flash` | `CAPTCHA verification failed. Please try again.` | `redirect` のときのフラッシュメッセージ。`false` で表示しません。 |
| `flashOptions` | `[]` | `FlashComponent::set()` のオプション。例: `['element' => 'error']`。 |
| `redirect` | リファラー | `redirect` のときのリダイレクト先 URL。 |
| `exception` | `ForbiddenException` | `exception` のときの例外クラス。例: `BadRequestException`。 |
| `trustProxy` | `false` | クライアントの IP (と `'hostname' => true` のときのホスト) をプロキシのヘッダーから読み取ります。[セキュリティに関する注意](security.md#security-notes) をご覧ください。 |

それ以外の設定はプロバイダーの設定で、`Configure::read('Captcha')` を上書きします。

`redirect` では送信されたデータは破棄されます。データを入れたままフォームを再表示するには、
`'onFailure' => 'none'` を使い、アクションの中で結果をチェックします。

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

`verify()` は自動検証の結果を再利用するので、トークンがプロバイダーに送信されるのは一度だけです
(トークンは一度しか使えません)。

<a id="manual-verification"></a>

## 手動での検証

```php
if (!$this->Captcha->verify()) {
    $errors = $this->Captcha->getErrors(); // e.g. ['timeout-or-duplicate']
}
```

| メソッド | 説明 |
| --- | --- |
| `verify(?string $token = null, array $settings = []): bool` | トークンを検証します。トークンのデフォルトは、リクエストデータの中のプロバイダーのフィールドです。`$settings` はこの呼び出しに限って設定を上書きします。例: `['action' => 'login']`。 |
| `getErrors(): array` | 最後の検証のエラーコード。 |
| `getResult(): ?CaptchaResult` | 最後の結果: `isSuccess()`、`getErrors()`、`getHostname()`、`getAction()`、`getScore()`、`getData($key)` (デコードされた検証レスポンス)。 |
| `token(): ?string` | リクエストデータの中のトークン。 |
| `requiresVerification(): bool` | 現在のリクエストが自動的に検証されるかどうか。 |
| `provider(array $settings = [])` | 設定されたプロバイダー。 |
| `setHttpSocket(?HttpSocket $http)` | 検証リクエストにこの `HttpSocket` を使います (例: テストでのモック)。 |

<a id="verifying-outside-a-controller"></a>

## コントローラーの外での検証

```php
App::uses('CaptchaFactory', 'Captcha.Lib');
App::uses('CaptchaVerifier', 'Captcha.Lib');

$verifier = new CaptchaVerifier(CaptchaFactory::provider(['action' => 'login']));
$result = $verifier->verify($token, $clientIp);
```

<a id="error-codes"></a>

## エラーコード

`getErrors()` は、プロバイダーの `error-codes` (例: `invalid-input-response`、
`timeout-or-duplicate`、`invalid-input-secret`、hCaptcha の `already-seen-response`) と、
プラグインが追加するコードを返します。

| コード | 説明 |
| --- | --- |
| `missing-input-response` | トークンが送信されませんでした。リクエストは送信されません。 |
| `invalid-input-response` | トークンがプロバイダーの許容する長さを超えています。リクエストは送信されません。 |
| `missing-input-secret` | `secretKey` が設定されていません。リクエストは送信されず、警告がログに記録されます。 |
| `verification-unavailable` | 検証エンドポイントでのネットワークエラー、タイムアウト、または 2xx 以外のレスポンス。 |
| `invalid-json` | 検証エンドポイントが JSON オブジェクト以外のものを返しました。 |
| `unknown-error` | `success` が `true` ではなく、エラーコードもありませんでした。 |
| `hostname-mismatch` | レスポンスの `hostname` が `hostname` と一致しません。 |
| `action-mismatch` | レスポンスの `action` が `action` と一致しません。 |
| `score-too-low` | reCAPTCHA v3 のスコアがないか、`scoreThreshold` を下回っています。 |
