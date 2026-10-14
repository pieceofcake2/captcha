<?php

/**
 * A CAPTCHA provider.
 *
 * Implementations are constructed with the merged configuration
 * (`new $class(array $config)`). Extend `BaseCaptchaProvider` to get the common behaviour.
 */
interface CaptchaProviderInterface
{
    /**
     * The widget is a visible (or managed) container rendered by the provider script.
     */
    public const MODE_WIDGET = 'widget';

    /**
     * There is no widget. The token is fetched with JavaScript when the form is submitted.
     */
    public const MODE_SCORE = 'score';

    /**
     * Read a configuration value.
     *
     * @param string|null $key The key, or null for the whole configuration.
     * @return mixed
     */
    public function getConfig(?string $key = null): mixed;

    /**
     * The provider name, e.g. `turnstile`.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Either `MODE_WIDGET` or `MODE_SCORE`.
     *
     * @return string
     */
    public function mode(): string;

    /**
     * The URL of the provider script.
     *
     * @param array<string, mixed> $options `render`, `onload`, `language` and `query` (extra query parameters).
     * @return string
     */
    public function scriptUrl(array $options = []): string;

    /**
     * HTML attributes of the widget element.
     *
     * In `MODE_WIDGET` this is the container `<div>`, in `MODE_SCORE` the hidden `<input>`.
     *
     * @param array<string, mixed> $options Widget options merged over the configuration.
     *   Unknown keys are returned as HTML attributes.
     * @return array<string, mixed>
     */
    public function widgetAttributes(array $options = []): array;

    /**
     * The name of the POST field that holds the token.
     *
     * @return string
     */
    public function responseField(): string;

    /**
     * Names of all fields the widget may add to the form (for `FormHelper::unlockField()`).
     *
     * @return array<string>
     */
    public function postedFields(): array;

    /**
     * The maximum accepted token length. Longer tokens are rejected without a request.
     *
     * @return int
     */
    public function maxTokenLength(): int;

    /**
     * The URL of the server-side verify endpoint.
     *
     * @return string
     */
    public function verifyUrl(): string;

    /**
     * Form fields POSTed to the verify endpoint.
     *
     * @param string $token The token sent by the client.
     * @param string|null $remoteIp The client IP address.
     * @param array<string, mixed> $options `idempotencyKey` (Turnstile only).
     * @return array<string, string>
     */
    public function verifyData(string $token, ?string $remoteIp = null, array $options = []): array;

    /**
     * Whether the endpoint accepts an idempotency key, so a failed request can be retried safely.
     *
     * @return bool
     */
    public function supportsIdempotencyKey(): bool;

    /**
     * Check the decoded verify response.
     *
     * @param array<string, mixed> $response The decoded JSON response.
     * @return array<string> Error codes. Empty when the token is valid.
     */
    public function checkResponse(array $response): array;
}
