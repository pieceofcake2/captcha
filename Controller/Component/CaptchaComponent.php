<?php

App::uses('Component', 'Controller');
App::uses('CaptchaFactory', 'Captcha.Lib');
App::uses('CaptchaVerifier', 'Captcha.Lib');
App::uses('CaptchaResult', 'Captcha.Lib');

/**
 * Verifies CAPTCHA tokens sent with the request.
 *
 * Settings (in addition to the provider configuration, which overrides `Configure::read('Captcha')`):
 *
 * - `actions`: Controller actions verified automatically on `startup()`. `['*']` for all actions.
 * - `methods`: HTTP methods verified automatically (default `['post', 'put', 'patch']`).
 * - `onFailure`: What to do when the automatic verification fails:
 *   `redirect` (default) sets a flash message and redirects, `exception` throws,
 *   `none` only records the result (check it with `verify()` or `getErrors()`).
 * - `flash`: Flash message for `redirect`. False to disable.
 * - `flashOptions`: Options for `FlashComponent::set()`.
 * - `redirect`: URL for `redirect`. Defaults to the referer.
 * - `exception`: Exception class for `exception` (default `ForbiddenException`).
 * - `trustProxy`: Read the client IP from `X-Forwarded-For` / `Client-IP` (default false).
 *   Enable it only behind a reverse proxy that overwrites these headers.
 * - `hostname`: Expected hostname(s). `true` uses the host of the current request.
 *
 * @property FlashComponent $Flash
 */
class CaptchaComponent extends Component
{
    public $components = ['Flash'];

    /**
     * Default settings.
     *
     * @var array<string, mixed>
     */
    protected array $defaults = [
        'actions' => [],
        'methods' => ['post', 'put', 'patch'],
        'onFailure' => 'redirect',
        'flash' => null,
        'flashOptions' => [],
        'redirect' => null,
        'exception' => 'ForbiddenException',
        'trustProxy' => false,
    ];

    /**
     * The controller.
     */
    protected ?Controller $controller = null;

    /**
     * HTTP client to use for verification.
     */
    protected ?HttpSocket $http = null;

    /**
     * Results by token, so that a token is sent to the provider only once per request.
     *
     * @var array<string, CaptchaResult>
     */
    protected array $results = [];

    /**
     * The last result.
     */
    protected ?CaptchaResult $result = null;

    /**
     * Constructor.
     *
     * @param ComponentCollection $collection The component collection.
     * @param array<string, mixed> $settings Settings.
     */
    public function __construct(ComponentCollection $collection, $settings = [])
    {
        parent::__construct($collection, $settings + $this->defaults);
    }

    /**
     * Keep a reference to the controller.
     *
     * @param Controller $controller The controller.
     * @return void
     */
    public function initialize(Controller $controller)
    {
        $this->controller = $controller;
    }

    /**
     * Verify the token automatically for the configured actions.
     *
     * @param Controller $controller The controller.
     * @return void
     * @throws CakeException When the verification fails and `onFailure` is `exception`.
     */
    public function startup(Controller $controller)
    {
        $this->controller = $controller;
        if (!$this->requiresVerification()) {
            return;
        }
        if ($this->verify()) {
            return;
        }

        $this->handleFailure();
    }

    /**
     * Whether the current request is verified automatically.
     *
     * @return bool
     */
    public function requiresVerification(): bool
    {
        $request = $this->request();
        $actions = (array)$this->settings['actions'];
        if ($actions === []) {
            return false;
        }
        $action = (string)($request->params['action'] ?? '');
        if (!in_array('*', $actions, true) && !in_array($action, $actions, true)) {
            return false;
        }

        return $request->is(array_map('strtolower', (array)$this->settings['methods']));
    }

    /**
     * Verify a token.
     *
     * @param string|null $token The token. Defaults to the provider's field in the request data.
     * @param array<string, mixed> $settings Provider settings for this call, e.g. `['action' => 'login']`.
     * @return bool
     */
    public function verify(?string $token = null, array $settings = []): bool
    {
        $provider = $this->provider($settings);
        if ($token === null) {
            $token = $this->token($provider);
        }

        $key = $provider->name() . "\0" . md5(serialize($provider->getConfig())) . "\0" . (string)$token;
        if (!isset($this->results[$key])) {
            $verifier = new CaptchaVerifier($provider, $this->http);
            $this->results[$key] = $verifier->verify($token, $this->clientIp());
        }
        $this->result = $this->results[$key];

        return $this->result->isSuccess();
    }

    /**
     * The result of the last verification.
     *
     * @return CaptchaResult|null
     */
    public function getResult(): ?CaptchaResult
    {
        return $this->result;
    }

    /**
     * Error codes of the last verification.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->result ? $this->result->getErrors() : [];
    }

    /**
     * The configured provider.
     *
     * @param array<string, mixed> $settings Settings for this call.
     * @return CaptchaProviderInterface
     */
    public function provider(array $settings = []): CaptchaProviderInterface
    {
        $config = CaptchaFactory::config($settings + array_diff_key($this->settings, $this->defaults + ['className' => true]));
        if (($config['hostname'] ?? null) === true) {
            // Fail closed when the host is unknown: [''] never matches a response hostname
            $config['hostname'] = $this->requestHost() ?? [''];
        }

        return CaptchaFactory::provider($config);
    }

    /**
     * The token in the request data.
     *
     * @param CaptchaProviderInterface|null $provider The provider.
     * @return string|null
     */
    public function token(?CaptchaProviderInterface $provider = null): ?string
    {
        $provider = $provider ?? $this->provider();
        $token = $this->request()->data($provider->responseField());

        return is_string($token) ? $token : null;
    }

    /**
     * Use this HTTP client for the verify requests (for example a mock in tests).
     *
     * @param HttpSocket|null $http The HTTP client, or null to create one per verification.
     * @return void
     */
    public function setHttpSocket(?HttpSocket $http): void
    {
        $this->http = $http;
    }

    /**
     * Handle a failed automatic verification.
     *
     * @return void
     * @throws CakeException When `onFailure` is `exception`.
     */
    protected function handleFailure(): void
    {
        switch ($this->settings['onFailure']) {
            case 'none':
                return;
            case 'exception':
                $class = (string)$this->settings['exception'];

                throw new $class(__d('captcha', 'CAPTCHA verification failed.'));
            default:
                $message = $this->settings['flash'] ?? __d('captcha', 'CAPTCHA verification failed. Please try again.');
                if ($message !== false) {
                    $this->Flash->set($message, (array)$this->settings['flashOptions']);
                }
                $controller = $this->controller();
                $controller->redirect($this->settings['redirect'] ?? $controller->referer());
        }
    }

    /**
     * The client IP address.
     *
     * @return string|null
     */
    protected function clientIp(): ?string
    {
        $ip = (string)$this->request()->clientIp(!$this->settings['trustProxy']);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    /**
     * The host of the current request without the port.
     *
     * @return string|null
     */
    protected function requestHost(): ?string
    {
        $host = (string)$this->request()->host((bool)$this->settings['trustProxy']);
        $host = preg_replace('/:\d+$/', '', $host);

        return $host !== '' ? $host : null;
    }

    /**
     * The controller.
     *
     * @return Controller
     * @throws CakeException When the component is not initialized.
     */
    protected function controller(): Controller
    {
        if ($this->controller === null) {
            $this->controller = $this->_Collection->getController();
        }

        return $this->controller;
    }

    /**
     * The current request.
     *
     * @return CakeRequest
     */
    protected function request(): CakeRequest
    {
        return $this->controller()->request;
    }
}
