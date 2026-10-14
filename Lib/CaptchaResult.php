<?php

/**
 * The result of a CAPTCHA verification.
 *
 * Error codes are the provider's `error-codes` plus the codes added by this plugin
 * (see the `ERROR_*` constants).
 */
class CaptchaResult
{
    /**
     * No token was sent with the request.
     */
    public const ERROR_MISSING_RESPONSE = 'missing-input-response';

    /**
     * The token is malformed (e.g. too long). It was not sent to the provider.
     */
    public const ERROR_INVALID_RESPONSE = 'invalid-input-response';

    /**
     * The secret key is not configured. It was not sent to the provider.
     */
    public const ERROR_MISSING_SECRET = 'missing-input-secret';

    /**
     * The verify endpoint could not be reached, timed out or returned a non-2xx status.
     */
    public const ERROR_UNAVAILABLE = 'verification-unavailable';

    /**
     * The verify endpoint returned a body that is not a JSON object.
     */
    public const ERROR_INVALID_JSON = 'invalid-json';

    /**
     * The provider returned `success: false` without any error code.
     */
    public const ERROR_UNKNOWN = 'unknown-error';

    /**
     * The `hostname` in the response does not match the configured hostname.
     */
    public const ERROR_HOSTNAME_MISMATCH = 'hostname-mismatch';

    /**
     * The `action` in the response does not match the configured action.
     */
    public const ERROR_ACTION_MISMATCH = 'action-mismatch';

    /**
     * The reCAPTCHA v3 `score` is missing or lower than the configured threshold.
     */
    public const ERROR_SCORE_TOO_LOW = 'score-too-low';

    /**
     * Whether the verification succeeded.
     *
     * @var bool
     */
    protected bool $success;

    /**
     * Error codes.
     *
     * @var array<string>
     */
    protected array $errors;

    /**
     * Decoded response of the verify endpoint.
     *
     * @var array<string, mixed>
     */
    protected array $data;

    /**
     * Constructor.
     *
     * @param bool $success Whether the verification succeeded.
     * @param array<string> $errors Error codes.
     * @param array<string, mixed> $data Decoded response of the verify endpoint.
     */
    public function __construct(bool $success, array $errors = [], array $data = [])
    {
        $this->success = $success && $errors === [];
        $this->errors = array_values(array_unique($errors));
        $this->data = $data;
    }

    /**
     * Create a failed result.
     *
     * @param array<string>|string $errors Error code(s).
     * @param array<string, mixed> $data Decoded response of the verify endpoint, if any.
     * @return self
     */
    public static function failure(array|string $errors, array $data = []): self
    {
        return new self(false, (array)$errors, $data);
    }

    /**
     * Whether the verification succeeded.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Error codes. Empty when the verification succeeded.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * The decoded response of the verify endpoint (empty when it was not called).
     *
     * @param string|null $key A key to read (e.g. `hostname`, `challenge_ts`), or null for all data.
     * @return mixed
     */
    public function getData(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? null;
    }

    /**
     * The hostname the challenge was solved on.
     *
     * @return string|null
     */
    public function getHostname(): ?string
    {
        $hostname = $this->getData('hostname');

        return is_string($hostname) ? $hostname : null;
    }

    /**
     * The action returned by the provider (Turnstile, reCAPTCHA v3).
     *
     * @return string|null
     */
    public function getAction(): ?string
    {
        $action = $this->getData('action');

        return is_string($action) ? $action : null;
    }

    /**
     * The score returned by the provider (reCAPTCHA v3, hCaptcha Enterprise).
     *
     * @return float|null
     */
    public function getScore(): ?float
    {
        $score = $this->getData('score');

        return is_int($score) || is_float($score) ? (float)$score : null;
    }
}
