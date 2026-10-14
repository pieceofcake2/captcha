<?php

App::uses('CaptchaProviderInterface', 'Captcha.Lib/Provider');
App::uses('CaptchaResult', 'Captcha.Lib');

/**
 * Common behaviour of the CAPTCHA providers.
 *
 * Configuration keys used by all providers:
 *
 * - `siteKey`: The public site key (rendered into the page).
 * - `secretKey`: The secret key (sent to the verify endpoint only).
 * - `scriptUrl`: Override the provider script URL.
 * - `verifyUrl`: Override the verify endpoint URL.
 * - `hostname`: Expected hostname(s). When set, a response with another `hostname` fails.
 * - `action`: Action name. Rendered into the widget and, for providers that return it,
 *   compared with the `action` in the response.
 * - `language`: Widget language.
 * - Widget options listed in `$widgetOptions` (e.g. `theme`, `size`).
 */
abstract class BaseCaptchaProvider implements CaptchaProviderInterface
{
    /**
     * Default script URL.
     */
    protected string $defaultScriptUrl = '';

    /**
     * Default verify endpoint URL.
     */
    protected string $defaultVerifyUrl = '';

    /**
     * CSS class of the widget container.
     */
    protected string $widgetClass = '';

    /**
     * POST field holding the token.
     */
    protected string $field = '';

    /**
     * Whether the verify response contains the `action` set on the client.
     */
    protected bool $returnsAction = false;

    /**
     * Map of option name => data attribute of the widget.
     *
     * @var array<string, string>
     */
    protected array $widgetOptions = [];

    /**
     * Configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $config Configuration.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function mode(): string
    {
        return self::MODE_WIDGET;
    }

    /**
     * @inheritDoc
     */
    public function scriptUrl(array $options = []): string
    {
        $query = $this->scriptQuery($options);
        if (!empty($options['query']) && is_array($options['query'])) {
            $query = array_merge($query, $options['query']);
        }
        $query = array_filter($query, fn($value) => $value !== null && $value !== '' && $value !== false);

        $url = (string)($this->getConfig('scriptUrl') ?: $this->defaultScriptUrl);
        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Query parameters of the script URL.
     *
     * @param array<string, mixed> $options Script options.
     * @return array<string, mixed>
     */
    protected function scriptQuery(array $options): array
    {
        return [
            'render' => $options['render'] ?? null,
            'onload' => $options['onload'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function widgetAttributes(array $options = []): array
    {
        $attributes = ['data-sitekey' => (string)$this->getConfig('siteKey')];
        foreach ($this->widgetOptions as $option => $attribute) {
            $value = array_key_exists($option, $options) ? $options[$option] : $this->getConfig($option);
            unset($options[$option]);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $attributes[$attribute] = (string)$value;
        }

        $class = $this->widgetClass;
        if (!empty($options['class'])) {
            $class = trim($class . ' ' . $options['class']);
        }
        unset($options['class']);

        return ['class' => $class] + $attributes + $options;
    }

    /**
     * @inheritDoc
     */
    public function responseField(): string
    {
        return $this->field;
    }

    /**
     * @inheritDoc
     */
    public function postedFields(): array
    {
        return [$this->field];
    }

    /**
     * @inheritDoc
     */
    public function maxTokenLength(): int
    {
        return 10000;
    }

    /**
     * @inheritDoc
     */
    public function verifyUrl(): string
    {
        return (string)($this->getConfig('verifyUrl') ?: $this->defaultVerifyUrl);
    }

    /**
     * @inheritDoc
     */
    public function verifyData(string $token, ?string $remoteIp = null, array $options = []): array
    {
        $data = [
            'secret' => (string)$this->getConfig('secretKey'),
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $data['remoteip'] = $remoteIp;
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function supportsIdempotencyKey(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function checkResponse(array $response): array
    {
        if (($response['success'] ?? null) !== true) {
            $errors = $response['error-codes'] ?? [];
            $errors = is_array($errors) ? array_values(array_filter($errors, 'is_string')) : [];

            return $errors ?: [CaptchaResult::ERROR_UNKNOWN];
        }

        $errors = [];
        if (!$this->checkHostname($response['hostname'] ?? null)) {
            $errors[] = CaptchaResult::ERROR_HOSTNAME_MISMATCH;
        }
        if (!$this->checkAction($response['action'] ?? null)) {
            $errors[] = CaptchaResult::ERROR_ACTION_MISMATCH;
        }

        return $errors;
    }

    /**
     * Compare the response hostname with the `hostname` configuration.
     *
     * @param mixed $hostname The hostname in the response.
     * @return bool True when no hostname is configured or it matches.
     */
    protected function checkHostname(mixed $hostname): bool
    {
        $expected = $this->getConfig('hostname');
        if ($expected === null || $expected === '' || $expected === [] || $expected === false) {
            return true;
        }
        if (!is_string($hostname) || $hostname === '') {
            return false;
        }

        $expected = array_map(fn($host) => strtolower((string)$host), (array)$expected);

        return in_array(strtolower($hostname), $expected, true);
    }

    /**
     * Compare the response action with the `action` configuration.
     *
     * @param mixed $action The action in the response.
     * @return bool True when the provider does not return actions, no action is configured, or it matches.
     */
    protected function checkAction(mixed $action): bool
    {
        $expected = $this->getConfig('action');
        if (!$this->returnsAction || $expected === null || $expected === '') {
            return true;
        }

        return is_string($action) && $action === (string)$expected;
    }
}
