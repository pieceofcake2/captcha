# セキュリティ

<a id="security-notes"></a>

## セキュリティに関する注意

- **フェイルクローズ。** トークンやシークレットキーがない場合、ネットワークエラー、タイムアウト、
  2xx 以外のレスポンス、不正な JSON の本文は、すべて検証の失敗になります。`success` は真偽値の
  `true` である必要があります。
- **一度だけ使用可能。** トークンは一度しか検証できず、期限切れになります (Turnstile: 300 秒、
  reCAPTCHA と hCaptcha: 120 秒)。コンポーネントはリクエストごとに結果をキャッシュするので、
  自動検証の後に `verify()` を呼んでもトークンが再度送信されることはありません。
- **ホスト名とアクション。** 別のサイトで解かれたトークンが拒否されるように `hostname`
  (コンポーネントでは `true` も可) を設定し、トークンをフォームに結び付けるには `action` を設定します。
- **シークレット。** シークレットキーとトークンがログに記録されることはありません。リクエストは
  `application/x-www-form-urlencoded` の POST として、TLS のピア検証付きで送信されます
  (`HttpSocket` のデフォルト)。失敗は、シークレットやトークンを含めずに、`captcha` スコープの
  警告としてログに記録されます。
- **クライアントの IP とプロキシ。** クライアントの IP は `remoteip` として送信されます。デフォルトでは
  `$safe = true` を指定した `CakeRequest::clientIp()`、つまり `REMOTE_ADDR` です。リバースプロキシや
  ロードバランサーの後ろでは、これはプロキシのアドレスになります。`'trustProxy' => true` は、
  プロキシが `X-Forwarded-For` (`'hostname' => true` の場合は `X-Forwarded-Host` も) を上書きする
  場合にだけ設定してください。そうでないと、クライアントがこれらのヘッダーを偽装できてしまいます。
  不正な IP アドレスは送信されません。
- **サーバーで検証する。** ウィジェットだけでは何も保護されません。リクエストを処理する前に、
  必ずコンポーネント (または `CaptchaVerifier`) でトークンを検証してください。

<a id="content-security-policy"></a>

## Content Security Policy

nonce を渡すと、script タグ (プロバイダーのスクリプトと reCAPTCHA v3 のインラインスクリプト) に
出力されます。

```php
Configure::write('Captcha.nonce', $nonce);
// or
public $helpers = ['Captcha.Captcha' => ['nonce' => $nonce]];
// or
echo $this->Captcha->widget(['nonce' => $nonce]);
```

プロバイダーには、次のソースも必要です。

| プロバイダー | ソース |
| --- | --- |
| Turnstile | `script-src` と `frame-src` に `https://challenges.cloudflare.com` |
| reCAPTCHA | `script-src` に `https://www.google.com/recaptcha/` `https://www.gstatic.com/recaptcha/`、`frame-src` に `https://www.google.com/recaptcha/` `https://recaptcha.google.com/recaptcha/` |
| hCaptcha | `script-src`、`frame-src`、`style-src` と `connect-src` に `https://hcaptcha.com` `https://*.hcaptcha.com` |
