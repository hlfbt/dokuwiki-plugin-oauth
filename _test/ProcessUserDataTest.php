<?php

namespace dokuwiki\plugin\oauth\test;

use DokuWikiTest;
use dokuwiki\plugin\oauth\OAuthManager;
use dokuwiki\plugin\oauth\Exception;
use auth_plugin_oauth;

/**
 * @group plugin_oauth
 * @group plugins
 */
class ProcessUserDataTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['oauth'];

    /** @var OAuthManager */
    private $manager;
    /** @var auth_plugin_oauth */
    private $auth;

    private $servicename = 'oauthtest';

    public function setUp(): void
    {
        global $conf, $auth;

        parent::setUp();

        $this->manager = new OAuthManager();
        $this->auth = new auth_plugin_oauth();

        $conf['authtype'] = 'oauth';
        $auth = $this->auth;

        $this->auth->createUser('oauthtestuser', 'password', 'Test User', 'test@example.com', [$this->servicename]);
    }

    // this is not very elegant, but mocking everything up to the point of the processUserData call would be very cumbersome and fragile.
    protected function getOAuthManagerMethod(string $name): \ReflectionMethod
    {
        $class = new \ReflectionClass($this->manager);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    protected function invokeOAuthManagerMethod(string $name, array $args = [])
    {
        $method = $this->getOAuthManagerMethod($name);

        return $method->invokeArgs($this->manager, $args);
    }

    protected function setConf(string $key, $value)
    {
        global $conf;
        $conf['plugin']['oauth'][$key] = $value;
    }

    public function test_linkByMail_enabled()
    {
        $this->setConf('disable-mail-linking', 0);
        $this->setConf('user-linking-claims', '');

        $userdata = [
            'user' => 'newuser',
            'name' => 'New User',
            'mail' => 'test@example.com',
            'grps' => [],
        ];

        $result = $this->invokeOAuthManagerMethod('processUserData', [$userdata, $this->servicename]);
        $this->assertEquals('oauthtestuser', $result['user'], 'User should be linked by email');
    }

    public function test_linkByMail_disabled()
    {
        $this->setConf('disable-mail-linking', 1);
        $this->setConf('user-linking-claims', '');
        $this->setConf('register-on-auth', 1);

        $userdata = [
            'user' => 'newuser',
            'name' => 'New User',
            'mail' => 'test@example.com',
            'grps' => [],
        ];

        try {
            $result = $this->invokeOAuthManagerMethod('processUserData', [$userdata, $this->servicename]);
        } catch (Exception $oauthError) {
            $result = $oauthError->getMessage();
        }

        // the correct behavior here is:
        // MUST NOT link 'newuser' to 'oauthtestuser' -> try to create 'newuser' -> fail due to unique email restriction
        // this may seem counterintuitive, but it aligns with the old behavior and may reduce errors on improperly configured instances
        // this test should be altered accordingly if the unique email constraint changes or becomes configurable as well
        $this->assertEquals('generic create error', $result, 'User registration should fail due to duplicate email');
    }

    public function test_linkByClaim_overridesMail()
    {
        $this->setConf('disable-mail-linking', 1);
        $this->setConf('user-linking-claims', 'sub:extras.sub');

        $this->auth->createUser('claimuser', 'password', 'Claim User', 'claim@example.com', [$this->servicename]);
        $this->auth->modifyUser('claimuser', ['extras' => ['sub' => '12345']]);

        $userdata = [
            'user' => 'newuser',
            'name' => 'New User',
            'mail' => 'test@example.com',
            'grps' => [],
            'sub' => '12345',
        ];

        $result = $this->invokeOAuthManagerMethod('processUserData', [$userdata, $this->servicename]);
        $this->assertEquals('claimuser', $result['user'], 'User should be linked by claim, not by email');
    }
}
