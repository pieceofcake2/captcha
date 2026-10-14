<?php

App::uses('TurnstileProvider', 'Captcha.Lib/Provider');
App::uses('RecaptchaV2Provider', 'Captcha.Lib/Provider');
App::uses('RecaptchaV3Provider', 'Captcha.Lib/Provider');
App::uses('HcaptchaProvider', 'Captcha.Lib/Provider');
App::uses('CaptchaResult', 'Captcha.Lib');

class ProvidersTest extends CakeTestCase
{
    public function testTurnstile(): void
    {
        $provider = new TurnstileProvider([
            'siteKey' => '1x00000000000000000000AA',
            'secretKey' => '1x0000000000000000000000000000000AA',
            'theme' => 'dark',
            'action' => 'login',
            'cData' => 'abc',
            'feedbackEnabled' => false,
            'size' => '',
        ]);

        $this->assertSame('turnstile', $provider->name());
        $this->assertSame(CaptchaProviderInterface::MODE_WIDGET, $provider->mode());
        $this->assertSame('cf-turnstile-response', $provider->responseField());
        $this->assertSame(['cf-turnstile-response'], $provider->postedFields());
        $this->assertSame(2048, $provider->maxTokenLength());
        $this->assertTrue($provider->supportsIdempotencyKey());
        $this->assertSame('https://challenges.cloudflare.com/turnstile/v0/api.js', $provider->scriptUrl());
        $this->assertSame(
            'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad',
            $provider->scriptUrl(['render' => 'explicit', 'onload' => 'onTurnstileLoad', 'language' => 'ja']),
        );
        $this->assertSame('https://challenges.cloudflare.com/turnstile/v0/siteverify', $provider->verifyUrl());

        $this->assertSame([
            'class' => 'cf-turnstile my-captcha',
            'data-sitekey' => '1x00000000000000000000AA',
            'data-action' => 'login',
            'data-cdata' => 'abc',
            'data-theme' => 'light',
            'data-language' => 'ja',
            'data-feedback-enabled' => 'false',
            'id' => 'captcha',
        ], $provider->widgetAttributes(['theme' => 'light', 'language' => 'ja', 'class' => 'my-captcha', 'id' => 'captcha']));

        $this->assertSame([
            'secret' => '1x0000000000000000000000000000000AA',
            'response' => 'XXXX.DUMMY.TOKEN.XXXX',
            'remoteip' => '192.0.2.1',
            'idempotency_key' => 'key',
        ], $provider->verifyData('XXXX.DUMMY.TOKEN.XXXX', '192.0.2.1', ['idempotencyKey' => 'key']));
        $this->assertSame([
            'secret' => '1x0000000000000000000000000000000AA',
            'response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ], $provider->verifyData('XXXX.DUMMY.TOKEN.XXXX', ''));
    }

    public function testCustomUrls(): void
    {
        $provider = new RecaptchaV2Provider([
            'scriptUrl' => 'https://www.recaptcha.net/recaptcha/api.js?trustedtypes=true',
            'verifyUrl' => 'https://www.recaptcha.net/recaptcha/api/siteverify',
        ]);

        $this->assertSame('https://www.recaptcha.net/recaptcha/api.js?trustedtypes=true&hl=ja', $provider->scriptUrl(['language' => 'ja']));
        $this->assertSame('https://www.recaptcha.net/recaptcha/api/siteverify', $provider->verifyUrl());
    }

    public function testRecaptchaV2(): void
    {
        $provider = new RecaptchaV2Provider([
            'siteKey' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
            'secretKey' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
            'language' => 'ja',
            'size' => 'compact',
            'action' => 'ignored',
        ]);

        $this->assertSame('recaptcha_v2', $provider->name());
        $this->assertSame('g-recaptcha-response', $provider->responseField());
        $this->assertFalse($provider->supportsIdempotencyKey());
        $this->assertSame(10000, $provider->maxTokenLength());
        $this->assertSame('https://www.google.com/recaptcha/api.js?hl=ja', $provider->scriptUrl());
        $this->assertSame(
            'https://www.google.com/recaptcha/api.js?onload=init&hl=en&foo=bar',
            $provider->scriptUrl(['onload' => 'init', 'language' => 'en', 'query' => ['foo' => 'bar']]),
        );
        $this->assertSame('https://www.google.com/recaptcha/api/siteverify', $provider->verifyUrl());
        $this->assertSame([
            'class' => 'g-recaptcha',
            'data-sitekey' => '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI',
            'data-size' => 'compact',
            'data-tabindex' => '3',
        ], $provider->widgetAttributes(['tabindex' => 3]));
        $this->assertSame([
            'secret' => '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe',
            'response' => 'token',
            'remoteip' => '2001:db8::1',
        ], $provider->verifyData('token', '2001:db8::1', ['idempotencyKey' => 'ignored']));

        // reCAPTCHA v2 does not return the action, so it is not checked
        $this->assertSame([], $provider->checkResponse(['success' => true, 'hostname' => 'example.com']));
    }

    public function testRecaptchaV3(): void
    {
        $provider = new RecaptchaV3Provider(['siteKey' => 'v3-site', 'secretKey' => 'v3-secret', 'scoreThreshold' => null]);

        $this->assertSame('recaptcha_v3', $provider->name());
        $this->assertSame(CaptchaProviderInterface::MODE_SCORE, $provider->mode());
        $this->assertSame('submit', $provider->getConfig('action'));
        $this->assertSame(0.5, $provider->getConfig('scoreThreshold'));
        $this->assertSame('https://www.google.com/recaptcha/api.js?render=v3-site', $provider->scriptUrl(['render' => 'explicit']));
        $this->assertSame([
            'type' => 'hidden',
            'name' => 'g-recaptcha-response',
            'value' => '',
            'data-sitekey' => 'v3-site',
            'data-action' => 'login',
            'id' => 'token',
        ], $provider->widgetAttributes(['action' => 'login', 'id' => 'token', 'class' => '']));

        $this->assertSame([], $provider->checkResponse(['success' => true, 'score' => 0.9, 'action' => 'submit']));
        $this->assertSame([], $provider->checkResponse(['success' => true, 'score' => 0.5, 'action' => 'submit']));
        $this->assertSame(['score-too-low'], $provider->checkResponse(['success' => true, 'score' => 0.1, 'action' => 'submit']));
        $this->assertSame(['score-too-low'], $provider->checkResponse(['success' => true, 'action' => 'submit']));
        $this->assertSame(['score-too-low'], $provider->checkResponse(['success' => true, 'score' => '0.9', 'action' => 'submit']));
        $this->assertSame(['action-mismatch'], $provider->checkResponse(['success' => true, 'score' => 0.9, 'action' => 'login']));
        $this->assertSame(['action-mismatch', 'score-too-low'], $provider->checkResponse(['success' => true, 'score' => 0, 'action' => null]));
        $this->assertSame(['timeout-or-duplicate'], $provider->checkResponse(['success' => false, 'error-codes' => ['timeout-or-duplicate']]));
    }

    public function testRecaptchaV3ScoreThreshold(): void
    {
        $provider = new RecaptchaV3Provider(['scoreThreshold' => 0.7, 'action' => 'login']);
        $this->assertSame(['score-too-low'], $provider->checkResponse(['success' => true, 'score' => 0.6, 'action' => 'login']));
        $this->assertSame([], $provider->checkResponse(['success' => true, 'score' => 1, 'action' => 'login']));

        $provider = new RecaptchaV3Provider(['scoreThreshold' => false, 'action' => 'login']);
        $this->assertSame([], $provider->checkResponse(['success' => true, 'action' => 'login']));
    }

    public function testHcaptcha(): void
    {
        $provider = new HcaptchaProvider([
            'siteKey' => '10000000-ffff-ffff-ffff-000000000001',
            'secretKey' => '0x0000000000000000000000000000000000000000',
            'theme' => 'dark',
        ]);

        $this->assertSame('hcaptcha', $provider->name());
        $this->assertSame('h-captcha-response', $provider->responseField());
        $this->assertSame(['h-captcha-response', 'g-recaptcha-response'], $provider->postedFields());
        $this->assertSame(
            'https://js.hcaptcha.com/1/api.js?hl=ja&recaptchacompat=off',
            $provider->scriptUrl(['language' => 'ja', 'query' => ['recaptchacompat' => 'off']]),
        );
        $this->assertSame('https://api.hcaptcha.com/siteverify', $provider->verifyUrl());
        $this->assertSame([
            'class' => 'h-captcha',
            'data-sitekey' => '10000000-ffff-ffff-ffff-000000000001',
            'data-theme' => 'dark',
        ], $provider->widgetAttributes());
        $this->assertSame([
            'secret' => '0x0000000000000000000000000000000000000000',
            'response' => '10000000-aaaa-bbbb-cccc-000000000001',
            'remoteip' => '192.0.2.1',
            'sitekey' => '10000000-ffff-ffff-ffff-000000000001',
        ], $provider->verifyData('10000000-aaaa-bbbb-cccc-000000000001', '192.0.2.1'));

        $provider = new HcaptchaProvider(['secretKey' => 'secret']);
        $this->assertSame(['secret' => 'secret', 'response' => 'token'], $provider->verifyData('token'));
    }

    public function testCheckResponseFailure(): void
    {
        $provider = new TurnstileProvider();

        $this->assertSame(['invalid-input-response'], $provider->checkResponse(['success' => false, 'error-codes' => ['invalid-input-response']]));
        $this->assertSame(['unknown-error'], $provider->checkResponse(['success' => false, 'error-codes' => []]));
        $this->assertSame(['unknown-error'], $provider->checkResponse(['success' => false, 'error-codes' => 'bad']));
        $this->assertSame(['unknown-error'], $provider->checkResponse(['success' => false, 'error-codes' => [1, null]]));
        $this->assertSame(['unknown-error'], $provider->checkResponse([]));
        // success must be the boolean true
        $this->assertSame(['unknown-error'], $provider->checkResponse(['success' => 'true']));
        $this->assertSame(['unknown-error'], $provider->checkResponse(['success' => 1]));
        $this->assertSame([], $provider->checkResponse(['success' => true]));
    }

    public function testCheckResponseHostname(): void
    {
        $provider = new TurnstileProvider(['hostname' => 'Example.com']);
        $this->assertSame([], $provider->checkResponse(['success' => true, 'hostname' => 'example.COM']));
        $this->assertSame(['hostname-mismatch'], $provider->checkResponse(['success' => true, 'hostname' => 'evil.example']));
        $this->assertSame(['hostname-mismatch'], $provider->checkResponse(['success' => true, 'hostname' => '']));
        $this->assertSame(['hostname-mismatch'], $provider->checkResponse(['success' => true]));

        $provider = new HcaptchaProvider(['hostname' => ['example.com', 'www.example.com']]);
        $this->assertSame([], $provider->checkResponse(['success' => true, 'hostname' => 'www.example.com']));
        $this->assertSame(['hostname-mismatch'], $provider->checkResponse(['success' => true, 'hostname' => 'example.org']));

        $provider = new HcaptchaProvider(['hostname' => []]);
        $this->assertSame([], $provider->checkResponse(['success' => true, 'hostname' => 'example.org']));
    }

    public function testCheckResponseAction(): void
    {
        $provider = new TurnstileProvider(['action' => 'login']);
        $this->assertSame([], $provider->checkResponse(['success' => true, 'action' => 'login']));
        $this->assertSame(['action-mismatch'], $provider->checkResponse(['success' => true, 'action' => 'signup']));
        $this->assertSame(['action-mismatch'], $provider->checkResponse(['success' => true]));

        $provider = new TurnstileProvider();
        $this->assertSame([], $provider->checkResponse(['success' => true, 'action' => 'anything']));

        $provider = new HcaptchaProvider(['action' => 'login']);
        $this->assertSame([], $provider->checkResponse(['success' => true]));
    }
}
