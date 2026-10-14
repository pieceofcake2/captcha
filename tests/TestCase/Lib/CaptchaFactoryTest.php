<?php

App::uses('CaptchaFactory', 'Captcha.Lib');
App::uses('BaseCaptchaProvider', 'Captcha.Lib/Provider');

class CaptchaFactoryTestProvider extends BaseCaptchaProvider
{
    public function name(): string
    {
        return 'custom';
    }
}

class CaptchaFactoryTest extends CakeTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Configure::delete('Captcha');
    }

    public function testConfigDefaults(): void
    {
        $this->assertSame(['provider' => 'turnstile'], CaptchaFactory::config());
    }

    public function testConfigMergesConfigureAndSettings(): void
    {
        Configure::write('Captcha', ['provider' => 'hcaptcha', 'siteKey' => 'site', 'secretKey' => 'secret']);

        $config = CaptchaFactory::config(['siteKey' => 'other']);

        $this->assertSame(['provider' => 'hcaptcha', 'siteKey' => 'other', 'secretKey' => 'secret'], $config);
    }

    public function testProviderAliases(): void
    {
        $this->assertInstanceOf('TurnstileProvider', CaptchaFactory::provider());
        $this->assertInstanceOf('TurnstileProvider', CaptchaFactory::provider(['provider' => 'Turnstile']));
        $this->assertInstanceOf('RecaptchaV2Provider', CaptchaFactory::provider(['provider' => 'recaptcha']));
        $this->assertInstanceOf('RecaptchaV2Provider', CaptchaFactory::provider(['provider' => 'recaptcha_v2']));
        $this->assertInstanceOf('RecaptchaV3Provider', CaptchaFactory::provider(['provider' => 'recaptcha_v3']));
        $this->assertInstanceOf('HcaptchaProvider', CaptchaFactory::provider(['provider' => 'hcaptcha']));
    }

    public function testProviderReceivesConfig(): void
    {
        Configure::write('Captcha', ['siteKey' => 'site', 'theme' => 'dark']);

        $provider = CaptchaFactory::provider(['theme' => 'light']);

        $this->assertSame('site', $provider->getConfig('siteKey'));
        $this->assertSame('light', $provider->getConfig('theme'));
    }

    public function testCustomProviderClass(): void
    {
        $provider = CaptchaFactory::provider(['provider' => 'CaptchaFactoryTestProvider']);

        $this->assertInstanceOf('CaptchaFactoryTestProvider', $provider);
        $this->assertSame('custom', $provider->name());
    }

    public function testCustomProviderNotFound(): void
    {
        $this->expectException(CakeException::class);
        $this->expectExceptionMessage('Captcha provider "Captcha.MissingProvider" is not found');

        CaptchaFactory::provider(['provider' => 'Captcha.MissingProvider']);
    }

    public function testCustomProviderNotImplementingInterface(): void
    {
        $this->expectException(CakeException::class);

        CaptchaFactory::provider(['provider' => 'Captcha.CaptchaResult']);
    }
}
