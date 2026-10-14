<?php

App::uses('AppHelper', 'View/Helper');
App::uses('CaptchaFactory', 'Captcha.Lib');

/**
 * Renders the CAPTCHA script and widget.
 *
 * Settings are the provider configuration (they override `Configure::read('Captcha')`) and:
 *
 * - `nonce`: CSP nonce added to the `<script>` tags. Defaults to `Configure::read('Captcha.nonce')`.
 *
 * @property HtmlHelper $Html
 */
class CaptchaHelper extends AppHelper
{
    public $helpers = ['Html'];

    /**
     * Script URLs already output.
     *
     * @var array<string, bool>
     */
    protected array $loadedScripts = [];

    /**
     * Counter for generated element IDs.
     */
    protected int $counter = 0;

    /**
     * The provider, created on first use.
     */
    protected ?CaptchaProviderInterface $provider = null;

    /**
     * The configured provider.
     *
     * @return CaptchaProviderInterface
     */
    public function provider(): CaptchaProviderInterface
    {
        if ($this->provider === null) {
            $this->provider = CaptchaFactory::provider(array_diff_key($this->settings, ['nonce' => true, 'className' => true]));
        }

        return $this->provider;
    }

    /**
     * Output the provider script tag (once per page).
     *
     * ### Options
     *
     * - `render`: `explicit` to render the widgets with JavaScript yourself.
     * - `onload`: Name of a global function called when the script is loaded.
     * - `language`: Language (`hl`) for reCAPTCHA and hCaptcha. Turnstile uses the `language` widget option.
     * - `query`: Additional query parameters, e.g. `['recaptchacompat' => 'off']` for hCaptcha.
     * - `nonce`: CSP nonce. Defaults to the `nonce` setting.
     * - `async`, `defer`: Default true.
     * - `block`: Append the tag to this view block (e.g. `script`) instead of returning it.
     * - `once`: Output the script only once (default true).
     *
     * @param array<string, mixed> $options Options.
     * @return string The tag, or an empty string when it was already output or appended to a block.
     */
    public function script(array $options = []): string
    {
        $options += ['async' => true, 'defer' => true, 'block' => false, 'once' => true, 'nonce' => $this->nonce()];

        $url = $this->provider()->scriptUrl($options);
        if ($options['once'] && isset($this->loadedScripts[$url])) {
            return '';
        }
        $this->loadedScripts[$url] = true;

        $attributes = ['src' => $url];
        foreach (['async', 'defer'] as $attribute) {
            if ($options[$attribute]) {
                $attributes[$attribute] = $attribute;
            }
        }
        if (!empty($options['nonce'])) {
            $attributes['nonce'] = (string)$options['nonce'];
        }

        $tag = $this->Html->tag('script', '', $attributes);
        if ($options['block']) {
            $this->_View->append((string)$options['block'], $tag);

            return '';
        }

        return $tag;
    }

    /**
     * Output the widget.
     *
     * For Turnstile, reCAPTCHA v2 and hCaptcha this is the widget container. For reCAPTCHA v3
     * it is a hidden input and a script that fetches a token when the form is submitted.
     *
     * Call it inside a form. When `FormHelper` is loaded, the token fields are unlocked for
     * `SecurityComponent`.
     *
     * ### Options
     *
     * - Widget options of the provider, e.g. `theme`, `size`, `action`, `language`.
     * - `script`: Output the provider script before the widget unless it was already output (default true).
     *   Pass an array to use it as the options of `script()`.
     * - `nonce`: CSP nonce for the script tags.
     * - Other keys are HTML attributes (`id`, `class`, ...).
     *
     * @param array<string, mixed> $options Options.
     * @return string
     * @throws CakeException When `siteKey` is not configured.
     */
    public function widget(array $options = []): string
    {
        $provider = $this->provider();
        if ((string)$provider->getConfig('siteKey') === '') {
            throw new CakeException('Captcha siteKey is not configured.');
        }

        $nonce = $options['nonce'] ?? $this->nonce();
        $script = $options['script'] ?? true;
        unset($options['nonce'], $options['script']);

        $this->unlockFields($provider);

        $out = '';
        if ($script !== false) {
            $out .= $this->script((is_array($script) ? $script : []) + ['nonce' => $nonce]);
        }

        if ($provider->mode() !== CaptchaProviderInterface::MODE_SCORE) {
            return $out . $this->Html->tag('div', '', $provider->widgetAttributes($options));
        }

        $options['id'] = $options['id'] ?? 'captcha-' . ++$this->counter;
        $out .= $this->Html->tag('input', null, $provider->widgetAttributes($options));
        $scriptOptions = ['safe' => false] + ($nonce ? ['nonce' => (string)$nonce] : []);

        return $out . $this->Html->scriptBlock($this->scoreScript((string)$options['id']), $scriptOptions);
    }

    /**
     * JavaScript that fetches a reCAPTCHA v3 token when the form is submitted.
     *
     * @param string $id The ID of the hidden input.
     * @return string
     */
    protected function scoreScript(string $id): string
    {
        $id = json_encode($id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        return <<<JS
(function () {
    var input = document.getElementById({$id});
    var form = input && input.form;
    if (!form) {
        return;
    }
    var submitting = false;
    form.addEventListener('submit', function (event) {
        if (submitting || typeof grecaptcha === 'undefined') {
            return;
        }
        event.preventDefault();
        var submitter = event.submitter && event.submitter.form === form ? event.submitter : null;
        grecaptcha.ready(function () {
            grecaptcha.execute(input.getAttribute('data-sitekey'), {action: input.getAttribute('data-action')}).then(function (token) {
                input.value = token;
                submitting = true;
                try {
                    if (form.requestSubmit) {
                        form.requestSubmit(submitter);
                    } else {
                        form.submit();
                    }
                } finally {
                    submitting = false;
                }
            });
        });
    });
})();
JS;
    }

    /**
     * Unlock the token fields for SecurityComponent.
     *
     * @param CaptchaProviderInterface $provider The provider.
     * @return void
     */
    protected function unlockFields(CaptchaProviderInterface $provider): void
    {
        if (!$this->_View->Helpers->loaded('Form')) {
            return;
        }
        /** @var FormHelper $form */
        $form = $this->_View->Helpers->load('Form');
        foreach ($provider->postedFields() as $field) {
            $form->unlockField($field);
        }
    }

    /**
     * The CSP nonce from the settings or the configuration.
     *
     * @return string|null
     */
    protected function nonce(): ?string
    {
        $nonce = $this->settings['nonce'] ?? Configure::read('Captcha.nonce');

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
