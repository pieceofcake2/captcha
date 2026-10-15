# プロバイダー

<a id="cloudflare-turnstile-turnstile"></a>

## Cloudflare Turnstile (`turnstile`)

ウィジェットのオプション: `action`、`cData`、`theme` (`auto`、`light`、`dark`)、`size` (`normal`、
`flexible`、`compact`)、`language`、`appearance` (`always`、`execute`、`interaction-only`)、
`execution` (`render`、`execute`)、`tabindex`、`retry`、`retryInterval`、`refreshExpired`、
`refreshTimeout`、`feedbackEnabled` と、コールバック `callback`、`errorCallback`、
`expiredCallback`、`timeoutCallback`、`beforeInteractiveCallback`、`afterInteractiveCallback`、
`unsupportedCallback` (グローバル関数の名前)。これらは
[`data-*` 属性](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/)
として出力されます。

- `action` は最大 32 文字、`cData` は最大 255 文字です (英数字、`_` と `-`)。
  `action` を設定した場合、検証レスポンスのアクションがそれと一致する必要があります。
- トークンは最大 2048 文字で、300 秒間有効で、一度だけ検証できます。
  それより長いトークンは、リクエストを送信せずに拒否されます。
- すべての検証リクエストには `idempotency_key` (UUID) が付くので、`retry` で同じリクエストを
  安全に再送できます。

<a id="google-recaptcha-v2-recaptcha_v2"></a>

## Google reCAPTCHA v2 (`recaptcha_v2`)

「私はロボットではありません」チェックボックスを表示します。ウィジェットのオプション: `theme`
(`light`、`dark`)、`size` (`normal`、`compact`)、`tabindex`、`callback`、`expiredCallback`、
`errorCallback`。`language` はスクリプトの `hl` パラメータとして送信されます。レスポンスには
アクションが含まれないので、`action` はチェックされません。invisible reCAPTCHA
(`size` が `invisible`) では、`grecaptcha.execute()` を呼び出す JavaScript を自分で書く必要があります。

www.google.com にアクセスできない環境では、`scriptUrl` を
`https://www.recaptcha.net/recaptcha/api.js` に、`verifyUrl` を
`https://www.recaptcha.net/recaptcha/api/siteverify` に設定してください。

<a id="google-recaptcha-v3-recaptcha_v3"></a>

## Google reCAPTCHA v3 (`recaptcha_v3`)

reCAPTCHA v3 にはウィジェットがありません。`widget()` は hidden の `g-recaptcha-response` input と
小さなスクリプトを出力します。フォームが送信されると、スクリプトはアクションを指定して
`grecaptcha.execute()` を呼び出し、トークンを input に格納して、フォームを再度送信します
(クリックされた送信ボタンは維持されます)。トークンは 2 分で期限切れになるので、送信時に取得します。

```php
echo $this->Captcha->widget(['action' => 'contact']);
```

```php
public $components = ['Captcha.Captcha' => ['actions' => ['add'], 'action' => 'contact', 'scoreThreshold' => 0.7]];
```

- `action` のデフォルトは `submit` です。英数字、`/` と `_` を使えます。検証レスポンスの
  アクションは設定したアクションと一致する必要があるので、コンポーネントにもウィジェットと
  同じアクションを設定してください。
- `scoreThreshold` のデフォルトは `0.5` です。スコアのないレスポンスや、スコアがそれより低い
  レスポンスは `score-too-low` で失敗します。スコアは `$this->Captcha->getResult()->getScore()` で
  読み取れます。たとえば、リクエストを拒否する代わりに追加の確認を求める場合に使います。
  自分で判定する場合は、`scoreThreshold` を `false` に設定してください。

<a id="hcaptcha-hcaptcha"></a>

## hCaptcha (`hcaptcha`)

ウィジェットのオプション: `theme` (`light`、`dark`)、`size` (`normal`、`compact`)、`tabindex`、`callback`、
`expiredCallback`、`chalexpiredCallback`、`openCallback`、`closeCallback`、`errorCallback`。
`language` はスクリプトの `hl` パラメータとして送信されます。

- hCaptcha の推奨に従って、サイトキーが検証エンドポイントに送信される (`sitekey`) ので、
  別のサイトキーで発行されたトークンは拒否されます。
- hCaptcha のスクリプトは、`recaptchacompat=off` を付けて読み込まない限り、
  `g-recaptcha-response` も追加します:
  `$this->Captcha->widget(['script' => ['query' => ['recaptchacompat' => 'off']]])`。
- hCaptcha Enterprise の `score` はチェックされません (hCaptcha のスコアは、高いほどボットの
  可能性が高いことを意味します)。`getResult()->getScore()` で読み取ってください。

<a id="custom-providers"></a>

## カスタムプロバイダー

`provider` に `CaptchaProviderInterface` を実装したクラスを設定します。`BaseCaptchaProvider` を
継承すると、共通の動作を利用できます。

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

クラスがまだ読み込まれていなければ、`Lib` (またはプラグインの `Lib`) から `App::uses()` で
読み込まれます。クラスは、マージされた設定の配列を引数に生成されます。

<a id="references"></a>

## リファレンス

- Cloudflare Turnstile: [クライアントサイドでの表示](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/)、
  [ウィジェットの設定](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/)、
  [サーバーサイドでの検証](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)、
  [テスト](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)、
  [Content Security Policy](https://developers.cloudflare.com/turnstile/reference/content-security-policy/)
- Google reCAPTCHA: [v2 チェックボックス](https://developers.google.com/recaptcha/docs/display)、
  [v3](https://developers.google.com/recaptcha/docs/v3)、
  [ユーザーのレスポンスの検証](https://developers.google.com/recaptcha/docs/verify)、
  [FAQ (テスト用のキー、CSP)](https://developers.google.com/recaptcha/docs/faq)
- hCaptcha: [開発者ガイド](https://docs.hcaptcha.com/)、
  [設定](https://docs.hcaptcha.com/configuration)
