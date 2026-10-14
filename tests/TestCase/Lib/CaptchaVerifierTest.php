<?php

App::uses('CaptchaVerifier', 'Captcha.Lib');
App::uses('TurnstileProvider', 'Captcha.Lib/Provider');
App::uses('RecaptchaV2Provider', 'Captcha.Lib/Provider');
App::uses('RecaptchaV3Provider', 'Captcha.Lib/Provider');
App::uses('HcaptchaProvider', 'Captcha.Lib/Provider');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('BaseLog', 'Log/Engine');

class CaptchaTestLog extends BaseLog
{
    /**
     * @var array<string>
     */
    public static array $messages = [];

    public function write($type, $message): void
    {
        static::$messages[] = $type . ': ' . $message;
    }
}

class CaptchaVerifierTestVerifier extends CaptchaVerifier
{
    public function getHttp(): HttpSocket
    {
        return $this->http();
    }
}

class CaptchaVerifierTest extends CakeTestCase
{
    /**
     * @var HttpSocket&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $http;

    public function setUp(): void
    {
        parent::setUp();
        $this->http = $this->getMockBuilder(HttpSocket::class)->onlyMethods(['post'])->getMock();
        CakeLog::drop('captcha_test');
        CaptchaTestLog::$messages = [];
        CakeLog::config('captcha_test', [
            'engine' => 'CaptchaTest',
            'types' => ['warning'],
            'scopes' => ['captcha'],
        ]);
    }

    public function tearDown(): void
    {
        CakeLog::drop('captcha_test');
        parent::tearDown();
    }

    protected function response(string $body, int $code = 200): HttpSocketResponse
    {
        $response = new HttpSocketResponse();
        $response->code = $code;
        $response->body = $body;

        return $response;
    }

    protected function turnstile(array $config = []): TurnstileProvider
    {
        return new TurnstileProvider($config + [
            'siteKey' => '1x00000000000000000000AA',
            'secretKey' => '1x0000000000000000000000000000000AA',
        ]);
    }

    /**
     * @return array<string>
     */
    protected function logged(): array
    {
        return CaptchaTestLog::$messages;
    }

    public function testVerifySuccess(): void
    {
        $this->http->expects($this->once())
            ->method('post')
            ->with(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                $this->callback(function ($data) {
                    $this->assertSame('1x0000000000000000000000000000000AA', $data['secret']);
                    $this->assertSame('XXXX.DUMMY.TOKEN.XXXX', $data['response']);
                    $this->assertSame('192.0.2.1', $data['remoteip']);
                    $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data['idempotency_key']);

                    return true;
                }),
                ['header' => ['Accept' => 'application/json'], 'redirect' => false],
            )
            ->willReturn($this->response('{"success":true,"challenge_ts":"2026-10-14T00:00:00.000Z","hostname":"example.com","error-codes":[],"action":"","cdata":""}'));

        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);
        $result = $verifier->verify(' XXXX.DUMMY.TOKEN.XXXX ', '192.0.2.1');

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->getErrors());
        $this->assertSame('example.com', $result->getHostname());
        $this->assertSame([], $this->logged());
    }

    public function testVerifyProviderFailure(): void
    {
        $this->http->method('post')
            ->willReturn($this->response('{"success":false,"error-codes":["invalid-input-response"]}'));

        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);
        $result = $verifier->verify('token');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['invalid-input-response'], $result->getErrors());
        $this->assertFalse($result->getData('success'));
    }

    public function testVerifyHostnameMismatch(): void
    {
        $this->http->method('post')
            ->willReturn($this->response('{"success":true,"hostname":"evil.example"}'));

        $verifier = new CaptchaVerifier($this->turnstile(['hostname' => 'example.com']), $this->http);
        $result = $verifier->verify('token');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['hostname-mismatch'], $result->getErrors());
    }

    public function testVerifyActionMismatch(): void
    {
        $this->http->method('post')
            ->willReturn($this->response('{"success":true,"hostname":"example.com","action":"signup"}'));

        $verifier = new CaptchaVerifier($this->turnstile(['action' => 'login']), $this->http);

        $this->assertSame(['action-mismatch'], $verifier->verify('token')->getErrors());
    }

    public function testVerifyRecaptchaV3Score(): void
    {
        $this->http->expects($this->exactly(2))
            ->method('post')
            ->with('https://www.google.com/recaptcha/api/siteverify', ['secret' => 'secret', 'response' => 'token'])
            ->willReturnOnConsecutiveCalls(
                $this->response('{"success":true,"score":0.9,"action":"submit","hostname":"example.com"}'),
                $this->response('{"success":true,"score":0.3,"action":"submit","hostname":"example.com"}'),
            );

        $verifier = new CaptchaVerifier(new RecaptchaV3Provider(['secretKey' => 'secret']), $this->http);

        $result = $verifier->verify('token');
        $this->assertTrue($result->isSuccess());
        $this->assertSame(0.9, $result->getScore());
        $this->assertSame('submit', $result->getAction());

        $result = $verifier->verify('token');
        $this->assertFalse($result->isSuccess());
        $this->assertSame(['score-too-low'], $result->getErrors());
        $this->assertSame(0.3, $result->getScore());
    }

    public function testVerifyHcaptchaSendsSitekey(): void
    {
        $this->http->expects($this->once())
            ->method('post')
            ->with('https://api.hcaptcha.com/siteverify', [
                'secret' => '0x0000000000000000000000000000000000000000',
                'response' => '10000000-aaaa-bbbb-cccc-000000000001',
                'sitekey' => '10000000-ffff-ffff-ffff-000000000001',
            ])
            ->willReturn($this->response('{"success":true,"hostname":"dummy-key-pass","credit":false}'));

        $verifier = new CaptchaVerifier(new HcaptchaProvider([
            'siteKey' => '10000000-ffff-ffff-ffff-000000000001',
            'secretKey' => '0x0000000000000000000000000000000000000000',
        ]), $this->http);

        $this->assertTrue($verifier->verify('10000000-aaaa-bbbb-cccc-000000000001')->isSuccess());
    }

    public function testVerifyMissingToken(): void
    {
        $this->http->expects($this->never())->method('post');
        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);

        foreach ([null, '', '   ', ['token'], 123] as $token) {
            $result = $verifier->verify($token);
            $this->assertFalse($result->isSuccess());
            $this->assertSame(['missing-input-response'], $result->getErrors());
        }
    }

    public function testVerifyTokenTooLong(): void
    {
        $this->http->expects($this->never())->method('post');
        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);

        $this->assertSame(['invalid-input-response'], $verifier->verify(str_repeat('a', 2049))->getErrors());
    }

    public function testVerifyMissingSecret(): void
    {
        $this->http->expects($this->never())->method('post');
        $verifier = new CaptchaVerifier($this->turnstile(['secretKey' => '']), $this->http);

        $this->assertSame(['missing-input-secret'], $verifier->verify('token')->getErrors());
        $this->assertCount(1, $this->logged());
        $this->assertStringContainsString('Captcha (turnstile): secretKey is not configured', $this->logged()[0]);
    }

    public function testVerifyNetworkError(): void
    {
        $this->http->expects($this->once())
            ->method('post')
            ->willThrowException(new SocketException('Connection timed out'));

        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);
        $result = $verifier->verify('secret-looking-token');

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['verification-unavailable'], $result->getErrors());
        $logs = implode("\n", $this->logged());
        $this->assertStringContainsString('SocketException: Connection timed out', $logs);
        $this->assertStringNotContainsString('secret-looking-token', $logs);
        $this->assertStringNotContainsString('1x0000000000000000000000000000000AA', $logs);
    }

    public function testVerifyFalseResponse(): void
    {
        $this->http->method('post')->willReturn(false);
        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);

        $this->assertSame(['verification-unavailable'], $verifier->verify('token')->getErrors());
        $this->assertStringContainsString('returned HTTP error', $this->logged()[0]);
    }

    public function testVerifyHttpError(): void
    {
        $this->http->method('post')->willReturn($this->response('{"success":true}', 500));
        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);

        $this->assertSame(['verification-unavailable'], $verifier->verify('token')->getErrors());
        $this->assertStringContainsString('returned HTTP 500', $this->logged()[0]);
    }

    public function testVerifyRetryKeepsIdempotencyKey(): void
    {
        $keys = [];
        $this->http->expects($this->exactly(3))
            ->method('post')
            ->willReturnCallback(function ($url, $data) use (&$keys) {
                $keys[] = $data['idempotency_key'];
                if (count($keys) === 1) {
                    throw new SocketException('Connection reset');
                }
                if (count($keys) === 2) {
                    return $this->response('Bad Gateway', 502);
                }

                return $this->response('{"success":true}');
            });

        $verifier = new CaptchaVerifier($this->turnstile(['retry' => 2]), $this->http);

        $this->assertTrue($verifier->verify('token')->isSuccess());
        $this->assertCount(1, array_unique($keys));
    }

    public function testVerifyRetryExhausted(): void
    {
        $this->http->expects($this->exactly(2))->method('post')->willReturn(false);
        $verifier = new CaptchaVerifier($this->turnstile(['retry' => 1]), $this->http);

        $this->assertSame(['verification-unavailable'], $verifier->verify('token')->getErrors());
    }

    public function testVerifyInvalidJson(): void
    {
        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);
        foreach (['<html>', '', 'true', '"success"', '[]', '[true]', '{"success":true'] as $body) {
            $http = $this->getMockBuilder(HttpSocket::class)->onlyMethods(['post'])->getMock();
            $http->method('post')->willReturn($this->response($body));
            $verifier = new CaptchaVerifier($this->turnstile(), $http);

            $this->assertSame(['invalid-json'], $verifier->verify('token')->getErrors(), $body);
        }
    }

    public function testVerifyEmptyObject(): void
    {
        $this->http->method('post')->willReturn($this->response('{}'));
        $verifier = new CaptchaVerifier($this->turnstile(), $this->http);

        $this->assertSame(['unknown-error'], $verifier->verify('token')->getErrors());
    }

    public function testDefaultHttpSocket(): void
    {
        $verifier = new CaptchaVerifierTestVerifier($this->turnstile());
        $http = $verifier->getHttp();
        $this->assertInstanceOf(HttpSocket::class, $http);
        $this->assertSame(10, $http->config['timeout']);
        $this->assertTrue($http->config['ssl_verify_peer']);
        $this->assertSame($http, $verifier->getHttp());

        $verifier = new CaptchaVerifierTestVerifier($this->turnstile(['timeout' => 3]));
        $this->assertSame(3, $verifier->getHttp()->config['timeout']);

        $verifier = new CaptchaVerifierTestVerifier($this->turnstile(['timeout' => 'x']));
        $this->assertSame(10, $verifier->getHttp()->config['timeout']);
    }
}
