<?php

App::uses('CaptchaResult', 'Captcha.Lib');

class CaptchaResultTest extends CakeTestCase
{
    public function testSuccess(): void
    {
        $result = new CaptchaResult(true, [], [
            'success' => true,
            'hostname' => 'example.com',
            'action' => 'login',
            'score' => 0.9,
            'challenge_ts' => '2026-10-14T00:00:00Z',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame([], $result->getErrors());
        $this->assertSame('example.com', $result->getHostname());
        $this->assertSame('login', $result->getAction());
        $this->assertSame(0.9, $result->getScore());
        $this->assertSame('2026-10-14T00:00:00Z', $result->getData('challenge_ts'));
        $this->assertNull($result->getData('missing'));
        $this->assertSame(true, $result->getData()['success']);
    }

    public function testSuccessWithErrorsIsFailure(): void
    {
        $result = new CaptchaResult(true, ['hostname-mismatch', 'hostname-mismatch']);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['hostname-mismatch'], $result->getErrors());
    }

    public function testFailure(): void
    {
        $result = CaptchaResult::failure(CaptchaResult::ERROR_UNAVAILABLE);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['verification-unavailable'], $result->getErrors());
        $this->assertSame([], $result->getData());
        $this->assertNull($result->getHostname());
        $this->assertNull($result->getAction());
        $this->assertNull($result->getScore());
    }

    public function testIntegerScore(): void
    {
        $result = new CaptchaResult(true, [], ['score' => 1, 'hostname' => ['x'], 'action' => 1]);

        $this->assertSame(1.0, $result->getScore());
        $this->assertNull($result->getHostname());
        $this->assertNull($result->getAction());
    }
}
