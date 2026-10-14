<?php

App::uses('CaptchaProviderInterface', 'Captcha.Lib/Provider');

/**
 * Builds the CAPTCHA provider from `Configure::read('Captcha')` and local settings.
 */
class CaptchaFactory
{
    /**
     * Provider aliases.
     *
     * @var array<string, string>
     */
    public const PROVIDERS = [
        'turnstile' => 'TurnstileProvider',
        'recaptcha' => 'RecaptchaV2Provider',
        'recaptcha_v2' => 'RecaptchaV2Provider',
        'recaptcha_v3' => 'RecaptchaV3Provider',
        'hcaptcha' => 'HcaptchaProvider',
    ];

    /**
     * Merge the `Captcha` configuration with local settings.
     *
     * @param array<string, mixed> $settings Settings that take precedence over `Configure::read('Captcha')`.
     * @return array<string, mixed>
     */
    public static function config(array $settings = []): array
    {
        $config = Configure::read('Captcha');

        return array_merge(
            ['provider' => 'turnstile'],
            is_array($config) ? $config : [],
            $settings,
        );
    }

    /**
     * Create the provider.
     *
     * `provider` is an alias (`turnstile`, `recaptcha_v2`, `recaptcha_v3`, `hcaptcha`), a loaded
     * class name, or a class in `Lib` (`MyProvider` or `Plugin.MyProvider`) implementing
     * `CaptchaProviderInterface`.
     *
     * @param array<string, mixed> $settings Settings that take precedence over `Configure::read('Captcha')`.
     * @return CaptchaProviderInterface
     * @throws CakeException When the provider class does not exist or is not a provider.
     */
    public static function provider(array $settings = []): CaptchaProviderInterface
    {
        $config = static::config($settings);
        $name = (string)$config['provider'];

        if (isset(static::PROVIDERS[strtolower($name)])) {
            $class = static::PROVIDERS[strtolower($name)];
            App::uses($class, 'Captcha.Lib/Provider');
        } else {
            [$plugin, $class] = pluginSplit($name, true);
            if (!class_exists($class)) {
                App::uses($class, $plugin . 'Lib');
            }
        }

        if (!class_exists($class) || !is_subclass_of($class, 'CaptchaProviderInterface')) {
            throw new CakeException(sprintf('Captcha provider "%s" is not found or does not implement CaptchaProviderInterface.', $name));
        }

        return new $class($config);
    }
}
