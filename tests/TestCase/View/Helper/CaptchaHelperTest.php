<?php

App::uses('View', 'View');
App::uses('Controller', 'Controller');
App::uses('CaptchaHelper', 'Captcha.View/Helper');
App::uses('FormHelper', 'View/Helper');

class CaptchaHelperTest extends CakeTestCase
{
    /**
     * @var View
     */
    protected $View;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Captcha', [
            'provider' => 'turnstile',
            'siteKey' => '1x00000000000000000000AA',
            'secretKey' => '1x0000000000000000000000000000000AA',
        ]);
        $request = new CakeRequest('/posts/add', false);
        $request->addParams(['controller' => 'posts', 'action' => 'add', 'plugin' => null]);
        $request->webroot = '/';
        $request->base = '';
        $controller = new Controller($request, new CakeResponse());
        $this->View = new View($controller);
    }

    public function tearDown(): void
    {
        Configure::delete('Captcha');
        parent::tearDown();
    }

    protected function helper(array $settings = []): CaptchaHelper
    {
        return new CaptchaHelper($this->View, $settings);
    }

    public function testScript(): void
    {
        $Captcha = $this->helper();

        $this->assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async="async" defer="defer"></script>',
            $Captcha->script(),
        );
        $this->assertSame('', $Captcha->script());
    }

    public function testScriptOptions(): void
    {
        $Captcha = $this->helper();

        $this->assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&amp;onload=onTurnstileLoad" nonce="abc&quot;123"></script>',
            $Captcha->script(['render' => 'explicit', 'onload' => 'onTurnstileLoad', 'async' => false, 'defer' => false, 'nonce' => 'abc"123']),
        );
        $this->assertNotSame('', $Captcha->script(['once' => false, 'render' => 'explicit', 'onload' => 'onTurnstileLoad']));
    }

    public function testScriptBlock(): void
    {
        $Captcha = $this->helper();

        $this->assertSame('', $Captcha->script(['block' => 'script']));
        $this->assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async="async" defer="defer"></script>',
            $this->View->fetch('script'),
        );
    }

    public function testScriptNonceFromSettingsAndConfigure(): void
    {
        $this->assertStringContainsString(' nonce="from-settings"', $this->helper(['nonce' => 'from-settings'])->script());

        Configure::write('Captcha.nonce', 'from-configure');
        $this->assertStringContainsString(' nonce="from-configure"', $this->helper()->script());
        $this->assertSame('from-configure', Configure::read('Captcha.nonce'));
        $this->assertNull($this->helper()->provider()->getConfig('className'));
    }

    public function testWidgetTurnstile(): void
    {
        $Captcha = $this->helper(['theme' => 'dark']);

        $this->assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async="async" defer="defer"></script>'
            . '<div class="cf-turnstile" data-sitekey="1x00000000000000000000AA" data-action="login" data-theme="dark" data-language="ja"></div>',
            $Captcha->widget(['action' => 'login', 'language' => 'ja']),
        );

        // The script is output only once
        $this->assertSame(
            '<div class="cf-turnstile mb-3" data-sitekey="1x00000000000000000000AA" data-theme="light" id="captcha" data-foo="&lt;b&gt;"></div>',
            $Captcha->widget(['theme' => 'light', 'class' => 'mb-3', 'id' => 'captcha', 'data-foo' => '<b>']),
        );
    }

    public function testWidgetWithoutScript(): void
    {
        $Captcha = $this->helper();
        $Captcha->script(['block' => 'script']);

        $this->assertSame(
            '<div class="cf-turnstile" data-sitekey="1x00000000000000000000AA"></div>',
            $Captcha->widget(),
        );
        $this->assertSame(
            '<div class="cf-turnstile" data-sitekey="1x00000000000000000000AA"></div>',
            $this->helper()->widget(['script' => false]),
        );
    }

    public function testWidgetScriptOptions(): void
    {
        $out = $this->helper()->widget(['script' => ['block' => 'script'], 'nonce' => 'n0nce']);

        $this->assertSame('<div class="cf-turnstile" data-sitekey="1x00000000000000000000AA"></div>', $out);
        $this->assertStringContainsString('nonce="n0nce"', $this->View->fetch('script'));
    }

    public function testWidgetRecaptchaV2(): void
    {
        $Captcha = $this->helper([
            'provider' => 'recaptcha_v2',
            'siteKey' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
            'language' => 'ja',
        ]);

        $this->assertSame(
            '<script src="https://www.google.com/recaptcha/api.js?hl=ja" async="async" defer="defer"></script>'
            . '<div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>',
            $Captcha->widget(),
        );
    }

    public function testWidgetHcaptcha(): void
    {
        $Captcha = $this->helper([
            'provider' => 'hcaptcha',
            'siteKey' => '10000000-ffff-ffff-ffff-000000000001',
        ]);

        $this->assertSame(
            '<script src="https://js.hcaptcha.com/1/api.js?recaptchacompat=off" async="async" defer="defer"></script>'
            . '<div class="h-captcha" data-sitekey="10000000-ffff-ffff-ffff-000000000001" data-size="compact"></div>',
            $Captcha->widget(['size' => 'compact', 'script' => ['query' => ['recaptchacompat' => 'off']]]),
        );
    }

    public function testWidgetRecaptchaV3(): void
    {
        $Captcha = $this->helper([
            'provider' => 'recaptcha_v3',
            'siteKey' => 'v3-site-key',
            'nonce' => 'n0nce',
        ]);

        $out = $Captcha->widget(['action' => 'login']);

        $this->assertStringStartsWith(
            '<script src="https://www.google.com/recaptcha/api.js?render=v3-site-key" async="async" defer="defer" nonce="n0nce"></script>'
            . '<input type="hidden" name="g-recaptcha-response" value="" data-sitekey="v3-site-key" data-action="login" id="captcha-1">'
            . '<script nonce="n0nce" type="text/javascript">',
            $out,
        );
        $this->assertStringContainsString('document.getElementById("captcha-1")', $out);
        $this->assertStringContainsString('grecaptcha.execute(', $out);
        $this->assertStringContainsString('form.requestSubmit(submitter)', $out);

        $out = $Captcha->widget(['id' => '</script><b>']);
        $this->assertStringStartsWith('<input type="hidden" name="g-recaptcha-response" value="" data-sitekey="v3-site-key" data-action="submit" id="&lt;/script&gt;&lt;b&gt;">', $out);
        $this->assertStringContainsString('document.getElementById("\\u003C\\/script\\u003E\\u003Cb\\u003E")', $out);
        $this->assertStringNotContainsString('</script><b>', $out);

        $out = $this->helper(['provider' => 'recaptcha_v3', 'siteKey' => 'v3-site-key'])->widget(['script' => false]);
        $this->assertStringContainsString('<script type="text/javascript">', $out);
    }

    public function testWidgetUnlocksFields(): void
    {
        $this->View->loadHelper('Form');
        $this->View->request->params['_Token'] = ['key' => 'testKey', 'unlockedFields' => []];

        $this->helper()->widget();
        $this->assertSame(['cf-turnstile-response'], $this->View->Form->unlockField());

        $this->helper(['provider' => 'hcaptcha'])->widget();
        $this->assertSame(['cf-turnstile-response', 'h-captcha-response', 'g-recaptcha-response'], $this->View->Form->unlockField());
    }

    public function testWidgetWithoutSiteKey(): void
    {
        Configure::delete('Captcha.siteKey');

        $this->expectException(CakeException::class);
        $this->expectExceptionMessage('Captcha siteKey is not configured.');

        $this->helper()->widget();
    }
}
