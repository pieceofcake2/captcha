<?php

App::uses('RecaptchaV2Provider', 'Captcha.Lib/Provider');
App::uses('CaptchaResult', 'Captcha.Lib');

/**
 * Google reCAPTCHA v3 (score based, no widget).
 *
 * The helper renders a hidden input and a script that fetches a token with
 * `grecaptcha.execute()` when the form is submitted.
 *
 * Additional configuration:
 *
 * - `action`: Action name (default `submit`). Only alphanumeric characters, `/` and `_`.
 *   The response action must match it.
 * - `scoreThreshold`: Minimum score from 0.0 to 1.0 (default 0.5).
 *
 * @link https://developers.google.com/recaptcha/docs/v3
 * @link https://developers.google.com/recaptcha/docs/verify
 */
class RecaptchaV3Provider extends RecaptchaV2Provider
{
    protected bool $returnsAction = true;

    protected array $widgetOptions = [
        'action' => 'data-action',
    ];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Configuration.
     */
    public function __construct(array $config = [])
    {
        parent::__construct(array_filter($config, fn($value) => $value !== null) + [
            'action' => 'submit',
            'scoreThreshold' => 0.5,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'recaptcha_v3';
    }

    /**
     * @inheritDoc
     */
    public function mode(): string
    {
        return self::MODE_SCORE;
    }

    /**
     * @inheritDoc
     */
    protected function scriptQuery(array $options): array
    {
        return [
            'render' => (string)$this->getConfig('siteKey'),
            'onload' => $options['onload'] ?? null,
            'hl' => $options['language'] ?? $this->getConfig('language'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function widgetAttributes(array $options = []): array
    {
        $attributes = parent::widgetAttributes($options);
        unset($attributes['class']);

        return ['type' => 'hidden', 'name' => $this->field, 'value' => ''] + $attributes;
    }

    /**
     * @inheritDoc
     */
    public function checkResponse(array $response): array
    {
        $errors = parent::checkResponse($response);
        if (($response['success'] ?? null) !== true) {
            return $errors;
        }

        $threshold = $this->getConfig('scoreThreshold');
        if ($threshold !== false) {
            $score = $response['score'] ?? null;
            if (!is_int($score) && !is_float($score) || $score < (float)$threshold) {
                $errors[] = CaptchaResult::ERROR_SCORE_TOO_LOW;
            }
        }

        return $errors;
    }
}
