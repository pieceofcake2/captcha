<?php

App::uses('BaseCaptchaProvider', 'Captcha.Lib/Provider');

/**
 * Cloudflare Turnstile.
 *
 * @link https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
 * @link https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 */
class TurnstileProvider extends BaseCaptchaProvider
{
    protected string $defaultScriptUrl = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    protected string $defaultVerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected string $widgetClass = 'cf-turnstile';

    protected string $field = 'cf-turnstile-response';

    protected bool $returnsAction = true;

    protected array $widgetOptions = [
        'action' => 'data-action',
        'cData' => 'data-cdata',
        'theme' => 'data-theme',
        'size' => 'data-size',
        'language' => 'data-language',
        'appearance' => 'data-appearance',
        'execution' => 'data-execution',
        'tabindex' => 'data-tabindex',
        'retry' => 'data-retry',
        'retryInterval' => 'data-retry-interval',
        'refreshExpired' => 'data-refresh-expired',
        'refreshTimeout' => 'data-refresh-timeout',
        'feedbackEnabled' => 'data-feedback-enabled',
        'callback' => 'data-callback',
        'errorCallback' => 'data-error-callback',
        'expiredCallback' => 'data-expired-callback',
        'timeoutCallback' => 'data-timeout-callback',
        'beforeInteractiveCallback' => 'data-before-interactive-callback',
        'afterInteractiveCallback' => 'data-after-interactive-callback',
        'unsupportedCallback' => 'data-unsupported-callback',
    ];

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'turnstile';
    }

    /**
     * Turnstile tokens are at most 2048 characters long.
     *
     * @return int
     */
    public function maxTokenLength(): int
    {
        return 2048;
    }

    /**
     * @inheritDoc
     */
    public function verifyData(string $token, ?string $remoteIp = null, array $options = []): array
    {
        $data = parent::verifyData($token, $remoteIp, $options);
        if (!empty($options['idempotencyKey'])) {
            $data['idempotency_key'] = (string)$options['idempotencyKey'];
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function supportsIdempotencyKey(): bool
    {
        return true;
    }
}
