<?php

namespace dokuwiki\plugin\oauth\test;

use dokuwiki\plugin\oauth\UserExtrasManager;
use DokuWikiTest;

/**
 * @group plugin_oauth
 * @group plugins
 */
class UserExtrasManagerTest extends DokuWikiTest
{
    protected $pluginsEnabled = ['oauth'];

    /** @var UserExtrasManager */
    private $manager;

    /** @var string */
    private $extrasFile;

    public function setUp(): void
    {
        parent::setUp();
        global $conf;
        $this->extrasFile = $conf['savedir'] . '/users.extras.php';
        $this->manager = new UserExtrasManager();
    }

    public function tearDown(): void
    {
        if (file_exists($this->extrasFile)) {
            unlink($this->extrasFile);
        }
        parent::tearDown();
    }

    /**
     * Test if the extras file is created if it does not exist.
     */
    public function test_getAllUserExtras_createsFile()
    {
        $this->assertFileDoesNotExist($this->extrasFile);
        $result = $this->manager->getAllUserExtras();
        $this->assertFileExists($this->extrasFile);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test saving extra data for a new user.
     */
    public function test_saveUserExtras_newUser()
    {
        $user = 'testuser';
        $data = [
            'name' => 'Test User',
            'mail' => 'test@example.com',
            'extras' => [
                'claim1' => 'value1',
                'claim2' => 'value2',
            ],
        ];

        $this->manager->saveUserExtras($user, $data);

        $contents = file_get_contents($this->extrasFile);
        $this->assertStringContainsString('testuser:{"claim1":"value1","claim2":"value2"}', $contents);
    }

    /**
     * Test updating extra data for an existing user.
     */
    public function test_saveUserExtras_existingUser_merge()
    {
        $user = 'testuser';
        $initialExtras = ['claim1' => 'initial_value'];
        io_saveFile($this->extrasFile, $user . ':' . json_encode($initialExtras) . "\n");

        $changes = [
            'extras' => [
                'claim1' => 'updated_value',
                'claim2' => 'new_value',
            ],
        ];

        $this->manager->saveUserExtras($user, $changes, false);

        $extras = $this->manager->getUserExtras($user);
        $this->assertEquals('updated_value', $extras['claim1']);
        $this->assertEquals('new_value', $extras['claim2']);
    }

    /**
     * Test replacing extra data for an existing user.
     */
    public function test_saveUserExtras_existingUser_replace()
    {
        $user = 'testuser';
        $initialExtras = ['claim1' => 'initial_value', 'claim3' => 'tobekept'];
        io_saveFile($this->extrasFile, $user . ':' . json_encode($initialExtras) . "\n");

        $changes = [
            'extras' => [
                'claim1' => 'updated_value',
                'claim2' => 'new_value',
            ],
        ];

        $this->manager->saveUserExtras($user, $changes, true);

        $extras = $this->manager->getUserExtras($user);
        $this->assertEquals('updated_value', $extras['claim1']);
        $this->assertEquals('new_value', $extras['claim2']);
        $this->assertArrayNotHasKey('claim3', $extras);
    }

    /**
     * Test deleting extra data for a single user.
     */
    public function test_deleteUserExtras()
    {
        $user1 = 'user1';
        $user2 = 'user2';
        io_saveFile($this->extrasFile, $user1 . ':{"foo":"bar"}' . "\n");
        io_saveFile($this->extrasFile, $user2 . ':{"baz":"qux"}' . "\n", true);

        $this->manager->deleteUserExtras($user1);

        $contents = file_get_contents($this->extrasFile);
        $this->assertStringNotContainsString($user1, $contents);
        $this->assertStringContainsString($user2, $contents);
    }

    /**
     * Test deleting extra data for multiple users.
     */
    public function test_deleteUsersExtras()
    {
        $user1 = 'user1';
        $user2 = 'user2';
        $user3 = 'user3';
        io_saveFile($this->extrasFile, $user1 . ':{"foo":"bar"}' . "\n");
        io_saveFile($this->extrasFile, $user2 . ':{"baz":"qux"}' . "\n", true);
        io_saveFile($this->extrasFile, $user3 . ':{"quux":"corge"}' . "\n", true);


        $this->manager->deleteUsersExtras([$user1, $user3]);

        $contents = file_get_contents($this->extrasFile);
        $this->assertStringNotContainsString($user1, $contents);
        $this->assertStringContainsString($user2, $contents);
        $this->assertStringNotContainsString($user3, $contents);
    }


    /**
     * Test merging extra data into user data.
     */
    public function test_mergeAllUsersExtras()
    {
        $user = 'testuser';
        $extras = ['claim1' => 'value1'];
        io_saveFile($this->extrasFile, $user . ':' . json_encode($extras) . "\n");

        $users = [
            'testuser' => [
                'name' => 'Test User',
                'mail' => 'test@example.com',
                'grps' => ['user'],
            ],
        ];

        $this->manager->mergeAllUsersExtras($users);

        $this->assertArrayHasKey('extras', $users[$user]);
        $this->assertEquals($extras, $users[$user]['extras']);
    }

    /**
     * Test getting extra data for a non-existent user.
     */
    public function test_getUserExtras_nonExistentUser()
    {
        $extras = $this->manager->getUserExtras('nonexistent');
        $this->assertEmpty($extras);
    }

    /**
     * Test extracting extra data from user data.
     */
    public function test_extractExtras()
    {
        $userdata = [
            'name' => 'Test User',
            'extras' => [
                'claim1' => 'value1',
            ],
        ];

        $extras = $this->manager->extractExtras($userdata, true);

        $this->assertEquals(['claim1' => 'value1'], $extras);
        $this->assertArrayNotHasKey('extras', $userdata);
    }
}
