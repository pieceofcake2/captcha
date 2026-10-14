<?php
/**
 * All Captcha plugin tests
 */

class AllCaptchaTest extends CakeTestCase
{
    /**
     * Suite define the tests for this suite
     *
     * @return CakeTestSuite
     */
    public static function suite(): CakeTestSuite
    {
        $suite = new CakeTestSuite('All Captcha test');

        $path = CakePlugin::path('Captcha') . 'tests' . DS . 'TestCase' . DS;
        $suite->addTestDirectoryRecursive($path);

        return $suite;
    }
}
