<?php

App::uses('BaseCaptchaProvider', 'Captcha.Lib/Provider');

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox).
 *
 * Set `scriptUrl` to `https://www.recaptcha.net/recaptcha/api.js` and `verifyUrl` to
 * `https://www.recaptcha.net/recaptcha/api/siteverify` where www.google.com is not accessible.
 *
 * @link https://developers.google.com/recaptcha/docs/display
 * @link https://developers.google.com/recaptcha/docs/verify
 */
class RecaptchaV2Provider extends BaseCaptchaProvider
{
    protected string $defaultScriptUrl = 'https://www.google.com/recaptcha/api.js';

    protected string $defaultVerifyUrl = 'https://www.google.com/recaptcha/api/siteverify';

    protected string $widgetClass = 'g-recaptcha';

    protected string $field = 'g-recaptcha-response';

    protected array $widgetOptions = [
        'theme' => 'data-theme',
        'size' => 'data-size',
        'tabindex' => 'data-tabindex',
        'callback' => 'data-callback',
        'expiredCallback' => 'data-expired-callback',
        'errorCallback' => 'data-error-callback',
    ];

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'recaptcha_v2';
    }

    /**
     * @inheritDoc
     */
    protected function scriptQuery(array $options): array
    {
        return parent::scriptQuery($options) + [
            'hl' => $options['language'] ?? $this->getConfig('language'),
        ];
    }
}
