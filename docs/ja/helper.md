# ウィジェットの表示 (CaptchaHelper)

コントローラー (または `AppController`) にヘルパーを追加します。設定は `Captcha` の設定の上に
マージされます。

```php
public $helpers = ['Captcha.Captcha' => ['theme' => 'light']];
```

<a id="widgetarray-options--"></a>

## `widget(array $options = [])`

ウィジェットを出力します。フォームの中で呼び出してください。

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

プロバイダーのスクリプトは、トークンを入れた hidden フィールド (`cf-turnstile-response`、
`g-recaptcha-response` または `h-captcha-response`) をフォームに追加します。

オプション:

- プロバイダーのウィジェットのオプション。例: `['theme' => 'dark', 'size' => 'compact', 'action' => 'login']`。
  設定を上書きします。
- `script`: プロバイダーのスクリプトは最初のウィジェットの前に出力されます。`script()` で自分で
  出力する場合は `false` を、そうでなければ `script()` のオプションの配列を渡します。
- `nonce`: script タグに付ける CSP の nonce。
- それ以外のキーは HTML の属性です (`id`、`class` (追加されます)、`data-*` など)。

`FormHelper` が読み込まれている場合、ウィジェットはトークンのフィールドのロックを解除する
(`FormHelper::unlockField()`) ので、`SecurityComponent` で保護されたフォームがブラックホールに
送られることはありません。

<a id="scriptarray-options--"></a>

## `script(array $options = [])`

プロバイダーの `<script>` タグを、ページごとに一度だけ返します。スクリプトを `<head>` に
置きたい場合に使います。

```php
$this->Captcha->script(['block' => 'script']);
```

| オプション | デフォルト | 説明 |
| --- | --- | --- |
| `block` | `false` | タグを返す代わりに、このビューブロックに追加します。 |
| `async`、`defer` | `true` | `async` / `defer` 属性を追加します。 |
| `nonce` | `nonce` の設定 | CSP の nonce。 |
| `render` | | `explicit` にすると、プロバイダーの JavaScript API で自分でウィジェットを表示します。 |
| `onload` | | スクリプトが読み込まれたときに呼び出されるグローバル関数の名前。 |
| `language` | `language` の設定 | `hl` パラメータ (reCAPTCHA、hCaptcha)。 |
| `query` | | 追加のクエリパラメータ。例: hCaptcha の `['recaptchacompat' => 'off']`。 |
| `once` | `true` | タグを一度だけ出力します。 |

<a id="provider"></a>

## `provider()`

設定された `CaptchaProviderInterface` のインスタンスを返します。
