<?php

namespace dokuwiki\plugin\oauth\test;

use DokuWikiTest;
use auth_plugin_oauth;

/**
 * @group plugin_oauth
 * @group plugins
 */
class GetUserByCustomClaimsTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['oauth'];

    /** @var auth_plugin_oauth */
    private $auth;

    public function setUp(): void
    {
        global $conf;

        parent::setUp();

        $this->auth = new auth_plugin_oauth();

        $conf['authtype'] = 'oauth';
        $conf['plugin']['oauth']['user-linking-claims'] = 'sub:extras.sub, username:user';

        // any random additional user to make sure the tests don't show false positives with single users
        // claims linking previously had a bug that would correctly match extra fields on the first user only
        $this->auth->createUser('foobar', 'password', 'Foo Bar', 'foobar@example.com');
        $this->auth->createUser('oauthtestuser', 'password', 'Test User', 'oauthtest@example.com');
    }

    public function test_getUserByCustomClaims_bySub()
    {
        $this->auth->modifyUser('oauthtestuser', ['extras' => ['sub' => '12345']]);

        $claims = ['sub' => '12345'];
        $user = $this->auth->getUserByCustomClaims($claims);
        $this->assertEquals('oauthtestuser', $user);
    }

    public function test_getUserByCustomClaims_bySubMoreUsers()
    {
        $this->auth->createUser('foobar1', 'password', 'Foo Bar 1', 'foobar1@example.com');
        $this->auth->modifyUser('oauthtestuser', ['extras' => ['sub' => '12345']]);

        $claims = ['sub' => '12345'];
        $user = $this->auth->getUserByCustomClaims($claims);
        $this->assertEquals('oauthtestuser', $user);
    }

    public function test_getUserByCustomClaims_byUsername()
    {
        $claims = ['username' => 'oauthtestuser'];
        $user = $this->auth->getUserByCustomClaims($claims);
        $this->assertEquals('oauthtestuser', $user);
    }

    public function test_getUserByCustomClaims_noMatch()
    {
        $this->auth->modifyUser('oauthtestuser', ['extras' => ['sub' => '12345']]);

        $claims = ['sub' => '67890'];
        $user = $this->auth->getUserByCustomClaims($claims);
        $this->assertFalse($user);
    }

    public function test_getUserByCustomClaims_emptyClaims()
    {
        $claims = [];
        $user = $this->auth->getUserByCustomClaims($claims);
        $this->assertFalse($user);
    }

    public function test_getUserByCustomClaims_emptyConfig()
    {
        global $conf;
        $conf['plugin']['oauth']['user-linking-claims'] = '';

        $this->auth->modifyUser('oauthtestuser', ['extras' => ['sub' => '12345']]);

        $claims = ['sub' => '12345'];
        $user = $this->auth->getUserByCustomClaims($claims);
        $this->assertFalse($user);
    }
}
