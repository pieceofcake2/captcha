<?php

App::uses('BaseCaptchaProvider', 'Captcha.Lib/Provider');

/**
 * hCaptcha.
 *
 * The site key is sent to the verify endpoint (`sitekey`), so a token issued for another
 * site key is rejected (`sitekey-secret-mismatch`).
 *
 * @link https://docs.hcaptcha.com/
 * @link https://docs.hcaptcha.com/configuration
 */
class HcaptchaProvider extends BaseCaptchaProvider
{
    protected string $defaultScriptUrl = 'https://js.hcaptcha.com/1/api.js';

    protected string $defaultVerifyUrl = 'https://api.hcaptcha.com/siteverify';

    protected string $widgetClass = 'h-captcha';

    protected string $field = 'h-captcha-response';

    protected array $widgetOptions = [
        'theme' => 'data-theme',
        'size' => 'data-size',
        'tabindex' => 'data-tabindex',
        'callback' => 'data-callback',
        'expiredCallback' => 'data-expired-callback',
        'chalexpiredCallback' => 'data-chalexpired-callback',
        'openCallback' => 'data-open-callback',
        'closeCallback' => 'data-close-callback',
        'errorCallback' => 'data-error-callback',
    ];

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'hcaptcha';
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

    /**
     * hCaptcha also adds `g-recaptcha-response` unless the script is loaded with `recaptchacompat=off`.
     *
     * @return array<string>
     */
    public function postedFields(): array
    {
        return [$this->field, 'g-recaptcha-response'];
    }

    /**
     * @inheritDoc
     */
    public function verifyData(string $token, ?string $remoteIp = null, array $options = []): array
    {
        $data = parent::verifyData($token, $remoteIp, $options);
        $siteKey = (string)$this->getConfig('siteKey');
        if ($siteKey !== '') {
            $data['sitekey'] = $siteKey;
        }

        return $data;
    }
}
