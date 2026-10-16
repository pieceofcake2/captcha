# Captcha プラグインドキュメント

Captcha プラグインは、CakePHP 2 のフォームに CAPTCHA ウィジェットを追加し、サーバーでトークンを
検証します。Cloudflare Turnstile、Google reCAPTCHA v2 と v3、hCaptcha に対応しており、
独自のプロバイダーを追加することもできます。

- [インストール](#installation)
- [設定](#configuration)
- [ウィジェットの表示 (CaptchaHelper)](helper.md)
- [トークンの検証 (CaptchaComponent)](component.md) とエラーコード
- [プロバイダー](providers.md)、カスタムプロバイダーとリファレンス
- [セキュリティ](security.md): セキュリティに関する注意と Content Security Policy
- [テスト](testing.md)

<a id="installation"></a>

## インストール

[Composer](https://getcomposer.org/) でプラグインをインストールします。

```bash
composer require pieceofcake2/captcha
```

プラグインのタイプは `cakephp-plugin` なので、`composer/installers` によってプラグインディレクトリに
インストールされます。アプリケーションの `composer.json` で `installer-paths` を設定してください。

| レイアウト | `installer-paths` |
| --- | --- |
| モダン (`config/`、`src/`、`templates/`、`plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| レガシー (`app/Config/`、`app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

`config/bootstrap.php` (レガシーレイアウトでは `app/Config/bootstrap.php`) でプラグインを読み込みます。
すでに `CakePlugin::loadAll()` を使っている場合は不要です。

```php
CakePlugin::load('Captcha');
```

<a id="configuration"></a>

## 設定

プロバイダーとキーは、たとえば `config/bootstrap.php` で `Configure::write('Captcha', [...])` を使って
設定します。キーはコミットしないでください。
[pieceofcake2/app](https://github.com/pieceofcake2/app) スケルトンでは、キーを Git で無視される
`config/.env.local` に書き (コミットする `config/.env` には共有のデフォルトだけを書きます)、本番環境では
実際の環境変数を設定して、`env()` で読み込みます。

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

ヘルパーとコンポーネントの設定はこの設定の上にマージされるので、コントローラーごとに異なる設定
(異なるプロバイダーでも) を使えます。

| キー | デフォルト | 説明 |
| --- | --- | --- |
| `provider` | `turnstile` | `turnstile`、`recaptcha_v2` (エイリアス `recaptcha`)、`recaptcha_v3`、`hcaptcha`、またはクラス名 ([カスタムプロバイダー](providers.md#custom-providers) を参照)。 |
| `siteKey` | | 公開されるサイトキー。ページに出力されます。 |
| `secretKey` | | シークレットキー。検証エンドポイントにだけ送信されます。 |
| `action` | | アクション名。ウィジェット (Turnstile、reCAPTCHA v3) に出力され、検証レスポンスの `action` と比較されます。reCAPTCHA v3 のデフォルトは `submit` です。 |
| `hostname` | | 期待するホスト名、またはホスト名のリスト。設定すると、別のホスト名で解かれたトークンは失敗します。コンポーネントでは、`true` で現在のリクエストのホストを使います。 |
| `scoreThreshold` | `0.5` | reCAPTCHA v3 のみ。最小スコア (0.0 から 1.0)。`false` でチェックを無効にします。 |
| `timeout` | `10` | 検証リクエストのタイムアウト (秒)。 |
| `retry` | `0` | ネットワークエラーまたは 2xx 以外のレスポンスの後のリトライ回数。Turnstile のリクエストには冪等キーが付くので、リトライしても安全です。 |
| `language` | | ウィジェットの言語。Turnstile: `data-language`。reCAPTCHA と hCaptcha: スクリプトの `hl` パラメータ。 |
| `scriptUrl` | プロバイダーのもの | スクリプトの URL を上書きします。例: `https://www.recaptcha.net/recaptcha/api.js`。 |
| `verifyUrl` | プロバイダーのもの | 検証エンドポイントを上書きします。例: `https://www.recaptcha.net/recaptcha/api/siteverify`。 |
| `nonce` | | script タグに追加する CSP の nonce ([Content Security Policy](security.md#content-security-policy) を参照)。 |
| ウィジェットのオプション | | `theme` や `size` など、プロバイダー固有のオプション ([プロバイダー](providers.md) を参照)。 |

<a id="origin"></a>

## 由来

このパッケージは [pieceofcake2](https://github.com/pieceofcake2) が作成し、メンテナンスしています。
他のプロジェクトのフォークではなく、他のプロジェクトをもとにしたものでもありません。
