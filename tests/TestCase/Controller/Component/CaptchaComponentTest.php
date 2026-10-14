<?php

App::uses('Controller', 'Controller');
App::uses('ComponentCollection', 'Controller');
App::uses('CaptchaComponent', 'Captcha.Controller/Component');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpSocketResponse', 'Network/Http');

class CaptchaComponentTestController extends Controller
{
    public $uses = [];
}

class CaptchaComponentTest extends CakeTestCase
{
    /**
     * @var CaptchaComponentTestController&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $Controller;

    /**
     * @var CaptchaComponent
     */
    protected $Captcha;

    /**
     * @var HttpSocket&\PHPUnit\Framework\MockObject\MockObject
     */
    protected $http;

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('Captcha', [
            'provider' => 'turnstile',
            'siteKey' => '1x00000000000000000000AA',
            'secretKey' => '1x0000000000000000000000000000000AA',
        ]);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_HOST'] = 'example.com:8080';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_HOST']);
        $this->http = $this->getMockBuilder(HttpSocket::class)->onlyMethods(['post'])->getMock();
    }

    public function tearDown(): void
    {
        Configure::delete('Captcha');
        CakeSession::destroy();
        parent::tearDown();
    }

    protected function setUpComponent(array $settings = [], string $action = 'add', array $data = ['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']): void
    {
        $request = new CakeRequest('/posts/' . $action, false);
        $request->addParams(['controller' => 'posts', 'action' => $action, 'plugin' => null]);
        $request->data = $data;

        $this->Controller = $this->getMockBuilder(CaptchaComponentTestController::class)
            ->setConstructorArgs([$request, new CakeResponse()])
            ->onlyMethods(['redirect', 'referer'])
            ->getMock();
        $this->Controller->method('referer')->willReturn('/posts/add');

        $collection = new ComponentCollection();
        $collection->init($this->Controller);
        $this->Captcha = new CaptchaComponent($collection, $settings);
        $this->Captcha->setHttpSocket($this->http);
        $this->Captcha->initialize($this->Controller);
    }

    protected function response(string $body): HttpSocketResponse
    {
        $response = new HttpSocketResponse();
        $response->code = 200;
        $response->body = $body;

        return $response;
    }

    protected function passResponse(): HttpSocketResponse
    {
        return $this->response('{"success":true,"hostname":"example.com","action":"","cdata":""}');
    }

    protected function failResponse(): HttpSocketResponse
    {
        return $this->response('{"success":false,"error-codes":["invalid-input-response"]}');
    }

    public function testVerifyFromRequestData(): void
    {
        $this->setUpComponent();
        $this->http->expects($this->once())
            ->method('post')
            ->with(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                $this->callback(function ($data) {
                    $this->assertSame('XXXX.DUMMY.TOKEN.XXXX', $data['response']);
                    $this->assertSame('192.0.2.10', $data['remoteip']);

                    return true;
                }),
            )
            ->willReturn($this->passResponse());

        $this->assertNull($this->Captcha->getResult());
        $this->assertSame([], $this->Captcha->getErrors());

        $this->assertTrue($this->Captcha->verify());
        $this->assertSame([], $this->Captcha->getErrors());
        $this->assertSame('example.com', $this->Captcha->getResult()->getHostname());

        // The token is sent to the provider only once
        $this->assertTrue($this->Captcha->verify());
    }

    public function testVerifyFailure(): void
    {
        $this->setUpComponent();
        $this->http->method('post')->willReturn($this->failResponse());

        $this->assertFalse($this->Captcha->verify());
        $this->assertSame(['invalid-input-response'], $this->Captcha->getErrors());
    }

    public function testVerifyMissingToken(): void
    {
        $this->setUpComponent([], 'add', ['cf-turnstile-response' => ['array']]);
        $this->http->expects($this->never())->method('post');

        $this->assertNull($this->Captcha->token());
        $this->assertFalse($this->Captcha->verify());
        $this->assertSame(['missing-input-response'], $this->Captcha->getErrors());
    }

    public function testVerifyExplicitTokenAndSettings(): void
    {
        $this->setUpComponent();
        $this->http->expects($this->exactly(2))
            ->method('post')
            ->with($this->anything(), $this->callback(fn($data) => $data['response'] === 'other-token'))
            ->willReturn($this->response('{"success":true,"action":"login"}'));

        $this->assertTrue($this->Captcha->verify('other-token', ['action' => 'login']));
        $this->assertFalse($this->Captcha->verify('other-token', ['action' => 'signup']));
        $this->assertSame(['action-mismatch'], $this->Captcha->getErrors());
    }

    public function testComponentSettingsOverrideConfigure(): void
    {
        $this->setUpComponent([
            'provider' => 'hcaptcha',
            'siteKey' => '10000000-ffff-ffff-ffff-000000000001',
            'secretKey' => '0x0000000000000000000000000000000000000000',
            'className' => 'Captcha.Captcha',
        ], 'add', ['h-captcha-response' => '10000000-aaaa-bbbb-cccc-000000000001']);

        $provider = $this->Captcha->provider();
        $this->assertInstanceOf('HcaptchaProvider', $provider);
        $this->assertNull($provider->getConfig('className'));
        $this->assertNull($provider->getConfig('onFailure'));
        $this->assertSame('10000000-aaaa-bbbb-cccc-000000000001', $this->Captcha->token());

        $this->http->expects($this->once())
            ->method('post')
            ->with('https://api.hcaptcha.com/siteverify', $this->callback(fn($data) => $data['secret'] === '0x0000000000000000000000000000000000000000'))
            ->willReturn($this->response('{"success":true}'));
        $this->assertTrue($this->Captcha->verify());
    }

    public function testClientIp(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.1';
        $this->setUpComponent();
        $this->http->expects($this->once())
            ->method('post')
            ->with($this->anything(), $this->callback(fn($data) => $data['remoteip'] === '192.0.2.10'))
            ->willReturn($this->passResponse());
        $this->assertTrue($this->Captcha->verify());
    }

    public function testClientIpTrustProxy(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.1';
        $this->setUpComponent(['trustProxy' => true]);
        $this->http->expects($this->once())
            ->method('post')
            ->with($this->anything(), $this->callback(fn($data) => $data['remoteip'] === '198.51.100.7'))
            ->willReturn($this->passResponse());
        $this->assertTrue($this->Captcha->verify());
    }

    public function testInvalidClientIpIsNotSent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '';
        $this->setUpComponent();
        $this->http->expects($this->once())
            ->method('post')
            ->with($this->anything(), $this->callback(fn($data) => !array_key_exists('remoteip', $data)))
            ->willReturn($this->passResponse());
        $this->assertTrue($this->Captcha->verify());
    }

    public function testHostnameFromRequest(): void
    {
        $this->setUpComponent(['hostname' => true]);
        $this->assertSame('example.com', $this->Captcha->provider()->getConfig('hostname'));

        $this->http->method('post')->willReturnOnConsecutiveCalls(
            $this->passResponse(),
            $this->response('{"success":true,"hostname":"evil.example"}'),
        );
        $this->assertTrue($this->Captcha->verify());
        $this->assertFalse($this->Captcha->verify('another-token'));
        $this->assertSame(['hostname-mismatch'], $this->Captcha->getErrors());
    }

    public function testHostnameFromRequestTrustProxy(): void
    {
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'www.example.com';
        $this->setUpComponent(['hostname' => true, 'trustProxy' => true]);

        $this->assertSame('www.example.com', $this->Captcha->provider()->getConfig('hostname'));
    }

    public function testHostnameFromConfigure(): void
    {
        Configure::write('Captcha.hostname', true);
        $this->setUpComponent();

        $this->assertSame('example.com', $this->Captcha->provider()->getConfig('hostname'));
    }

    public function testHostnameUnknownFailsClosed(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $this->setUpComponent(['hostname' => true]);
        $this->http->method('post')->willReturn($this->passResponse());

        $this->assertFalse($this->Captcha->verify());
        $this->assertSame(['hostname-mismatch'], $this->Captcha->getErrors());
    }

    public function testStartupWithoutActions(): void
    {
        $this->setUpComponent();
        $this->http->expects($this->never())->method('post');
        $this->Controller->expects($this->never())->method('redirect');

        $this->assertFalse($this->Captcha->requiresVerification());
        $this->Captcha->startup($this->Controller);
        $this->assertNull($this->Captcha->getResult());
    }

    public function testStartupOtherAction(): void
    {
        $this->setUpComponent(['actions' => ['add']], 'index');
        $this->http->expects($this->never())->method('post');

        $this->assertFalse($this->Captcha->requiresVerification());
        $this->Captcha->startup($this->Controller);
    }

    public function testStartupGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->setUpComponent(['actions' => ['add']]);
        $this->http->expects($this->never())->method('post');

        $this->assertFalse($this->Captcha->requiresVerification());
        $this->Captcha->startup($this->Controller);
    }

    public function testStartupMethods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->setUpComponent(['actions' => ['add']]);
        $this->assertFalse($this->Captcha->requiresVerification());

        $this->setUpComponent(['actions' => ['add'], 'methods' => ['DELETE']]);
        $this->assertTrue($this->Captcha->requiresVerification());

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->setUpComponent(['actions' => ['*']], 'edit');
        $this->assertTrue($this->Captcha->requiresVerification());
    }

    public function testStartupSuccess(): void
    {
        $this->setUpComponent(['actions' => ['add']]);
        $this->http->expects($this->once())->method('post')->willReturn($this->passResponse());
        $this->Controller->expects($this->never())->method('redirect');

        $this->Captcha->startup($this->Controller);

        $this->assertTrue($this->Captcha->getResult()->isSuccess());
        // The action can check the result again without another request
        $this->assertTrue($this->Captcha->verify());
        $this->assertNull(CakeSession::read('Message.flash'));
    }

    public function testStartupFailureRedirectsToReferer(): void
    {
        $this->setUpComponent(['actions' => ['add']]);
        $this->http->method('post')->willReturn($this->failResponse());
        $this->Controller->expects($this->once())->method('redirect')->with('/posts/add');

        $this->Captcha->startup($this->Controller);

        $flash = CakeSession::read('Message.flash');
        $this->assertSame('CAPTCHA verification failed. Please try again.', $flash[0]['message']);
        $this->assertSame(['invalid-input-response'], $this->Captcha->getErrors());
    }

    public function testStartupFailureRedirectSettings(): void
    {
        $this->setUpComponent([
            'actions' => ['add'],
            'flash' => 'Are you a robot?',
            'flashOptions' => ['element' => 'error', 'key' => 'captcha'],
            'redirect' => ['action' => 'index'],
        ]);
        $this->http->method('post')->willReturn($this->failResponse());
        $this->Controller->expects($this->once())->method('redirect')->with(['action' => 'index']);

        $this->Captcha->startup($this->Controller);

        $flash = CakeSession::read('Message.captcha');
        $this->assertSame('Are you a robot?', $flash[0]['message']);
        $this->assertSame('Flash/error', $flash[0]['element']);
    }

    public function testStartupFailureWithoutFlash(): void
    {
        $this->setUpComponent(['actions' => ['add'], 'flash' => false]);
        $this->http->method('post')->willReturn($this->failResponse());
        $this->Controller->expects($this->once())->method('redirect');

        $this->Captcha->startup($this->Controller);

        $this->assertNull(CakeSession::read('Message.flash'));
    }

    public function testStartupFailureException(): void
    {
        $this->setUpComponent(['actions' => ['add'], 'onFailure' => 'exception']);
        $this->http->method('post')->willReturn($this->failResponse());
        $this->Controller->expects($this->never())->method('redirect');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('CAPTCHA verification failed.');

        $this->Captcha->startup($this->Controller);
    }

    public function testStartupFailureCustomException(): void
    {
        $this->setUpComponent(['actions' => ['add'], 'onFailure' => 'exception', 'exception' => 'BadRequestException']);
        $this->http->method('post')->willReturn($this->failResponse());

        $this->expectException(BadRequestException::class);

        $this->Captcha->startup($this->Controller);
    }

    public function testStartupFailureNone(): void
    {
        $this->setUpComponent(['actions' => ['add'], 'onFailure' => 'none']);
        $this->http->expects($this->once())->method('post')->willReturn($this->failResponse());
        $this->Controller->expects($this->never())->method('redirect');

        $this->Captcha->startup($this->Controller);

        $this->assertFalse($this->Captcha->verify());
        $this->assertSame(['invalid-input-response'], $this->Captcha->getErrors());
    }

    public function testControllerFromCollection(): void
    {
        $request = new CakeRequest('/posts/add', false);
        $request->data = ['cf-turnstile-response' => 'token'];
        $controller = new CaptchaComponentTestController($request, new CakeResponse());
        $collection = new ComponentCollection();
        $collection->init($controller);
        $component = new CaptchaComponent($collection);

        $this->assertSame('token', $component->token());
    }
}
