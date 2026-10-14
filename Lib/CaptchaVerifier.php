<?php

App::uses('CaptchaProviderInterface', 'Captcha.Lib/Provider');
App::uses('CaptchaResult', 'Captcha.Lib');
App::uses('CakeText', 'Utility');
App::uses('HttpSocket', 'Network/Http');

/**
 * Verifies a CAPTCHA token with the provider's verify endpoint.
 *
 * Fails closed: a missing token or secret, a network error, a timeout, a non-2xx status
 * and a body that is not a JSON object are all reported as a failed verification.
 * The secret key and the token are never logged.
 *
 * Configuration keys read from the provider:
 *
 * - `timeout`: Request timeout in seconds (default 10).
 * - `retry`: Number of retries after a network error or a 5xx status (default 0).
 *   Turnstile requests carry an idempotency key so a retry is safe.
 */
class CaptchaVerifier
{
    /**
     * The provider.
     */
    protected CaptchaProviderInterface $provider;

    /**
     * HTTP client. Created on demand when not injected.
     */
    protected ?HttpSocket $http;

    /**
     * Constructor.
     *
     * @param CaptchaProviderInterface $provider The provider.
     * @param HttpSocket|null $http HTTP client to use instead of a new `HttpSocket`.
     */
    public function __construct(CaptchaProviderInterface $provider, ?HttpSocket $http = null)
    {
        $this->provider = $provider;
        $this->http = $http;
    }

    /**
     * Verify a token.
     *
     * @param mixed $token The token sent by the client.
     * @param string|null $remoteIp The client IP address.
     * @return CaptchaResult
     */
    public function verify(mixed $token, ?string $remoteIp = null): CaptchaResult
    {
        if (!is_string($token) || trim($token) === '') {
            return CaptchaResult::failure(CaptchaResult::ERROR_MISSING_RESPONSE);
        }
        $token = trim($token);
        if (strlen($token) > $this->provider->maxTokenLength()) {
            return CaptchaResult::failure(CaptchaResult::ERROR_INVALID_RESPONSE);
        }
        if ((string)$this->provider->getConfig('secretKey') === '') {
            $this->log('secretKey is not configured');

            return CaptchaResult::failure(CaptchaResult::ERROR_MISSING_SECRET);
        }

        $options = [];
        $retry = max(0, (int)$this->provider->getConfig('retry'));
        if ($this->provider->supportsIdempotencyKey()) {
            $options['idempotencyKey'] = CakeText::uuid();
        }
        $data = $this->provider->verifyData($token, $remoteIp, $options);

        $attempt = 0;
        do {
            $response = $this->request($data);
        } while ($response === null && $attempt++ < $retry);

        if ($response === null) {
            return CaptchaResult::failure(CaptchaResult::ERROR_UNAVAILABLE);
        }

        $json = json_decode($response, true);
        if (!is_array($json) || !(json_decode($response) instanceof stdClass)) {
            $this->log('the verify endpoint returned an invalid JSON body');

            return CaptchaResult::failure(CaptchaResult::ERROR_INVALID_JSON);
        }

        $errors = $this->provider->checkResponse($json);

        return new CaptchaResult($errors === [], $errors, $json);
    }

    /**
     * POST the data to the verify endpoint.
     *
     * @param array<string, string> $data Form fields.
     * @return string|null The response body, or null on a network error or a non-2xx status.
     */
    protected function request(array $data): ?string
    {
        try {
            $response = $this->http()->post($this->provider->verifyUrl(), $data, [
                'header' => ['Accept' => 'application/json'],
                'redirect' => false,
            ]);
        } catch (Exception $e) {
            $this->log(sprintf('the verify request failed (%s: %s)', get_class($e), $e->getMessage()));

            return null;
        }

        if (!$response || !$response->isOk()) {
            $this->log(sprintf('the verify endpoint returned HTTP %s', $response ? $response->code : 'error'));

            return null;
        }

        return (string)$response->body();
    }

    /**
     * The HTTP client.
     *
     * @return HttpSocket
     */
    protected function http(): HttpSocket
    {
        if ($this->http === null) {
            $timeout = $this->provider->getConfig('timeout');
            $this->http = new HttpSocket([
                'timeout' => is_numeric($timeout) && $timeout > 0 ? (int)$timeout : 10,
            ]);
        }

        return $this->http;
    }

    /**
     * Log a warning. Never pass the secret key or the token.
     *
     * @param string $message The message.
     * @return void
     */
    protected function log(string $message): void
    {
        CakeLog::warning(sprintf('Captcha (%s): %s', $this->provider->name(), $message), ['captcha']);
    }
}
