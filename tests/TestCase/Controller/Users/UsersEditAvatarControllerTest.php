<?php
/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SARL (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SARL (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.0.0
 */
namespace App\Test\TestCase\Controller\Users;

use App\Test\Lib\AppIntegrationTestCase;
use App\Utility\UuidFactory;
use Burzum\FileStorage\Event\ImageProcessingListener;
use Burzum\FileStorage\Event\LocalFileStorageListener;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;

class UsersEditAvatarControllerTest extends AppIntegrationTestCase
{
    public $localFileStorageListener = null;
    public $imageProcessingListener = null;

    public $fixtures = ['app.Base/users', 'app.Base/roles', 'app.Base/profiles', 'app.Base/gpgkeys', 'app.Base/groups_users', 'app.Base/avatars'];

    public function setUp()
    {
        parent::setUp();
        $this->Avatars = TableRegistry::get('Avatars');
    }

    public function testUsersEditAvatarSuccess()
    {
        $adaAvatar = PASSBOLT_TEST_DATA_AVATAR_PATH . DS . 'ada.png';

        $ireneAvatar = $this->Avatars->find()
            ->where(['user_id' => UuidFactory::uuid('user.id.irene')])
            ->count();
        $this->assertEquals(0, $ireneAvatar, 'Before the test, Irene should not have any avatar');

        $this->authenticateAs('irene');
        $data = [
            'id' => UuidFactory::uuid('user.id.irene'),
            'profile' => [
                'avatar' => [
                    'file' => [
                        'tmp_name' => $adaAvatar,
                        'name' => 'irene.png',
                    ]
                ]
            ]
        ];
        $this->postJson('/users/' . UuidFactory::uuid('user.id.irene') . '.json?api-version=v1', $data);
        $this->assertSuccess();

        $ireneAvatar = $this->Avatars
            ->find()
            ->where(['user_id' => UuidFactory::uuid('user.id.irene')]);

        $this->assertEquals(1, $ireneAvatar->count(), 'After the test, Irene should have an avatar');
        $this->assertEquals('Local', $ireneAvatar->first()->adapter, 'Avatar adapter should be set to Local');
        $this->assertEquals('Avatar', $ireneAvatar->first()->model, 'File Storage model should be set to Avatar');
        $this->assertEquals('irene.png', $ireneAvatar->first()->filename, 'Avatar name should be set to irene.png');
        $this->assertEquals(UuidFactory::uuid('profile.id.irene'), $ireneAvatar->first()->foreign_key, 'foreign_key should be the one of irene profile');
        $this->assertNotEmpty($ireneAvatar->first()->path);
        $this->assertTrue(file_exists(Configure::read('ImageStorage.basePath') . DS . $ireneAvatar->first()->path));
    }

    public function testUsersEditAvatarWrongFileFormat()
    {
        $filesDirectory = ROOT . DS . 'plugins' . DS . 'PassboltTestData' . DS . 'data';
        $pdfFile = $filesDirectory . DS . 'sample.pdf';

        $avatarCountsBefore = $this->Avatars->find()->count();

        $this->authenticateAs('irene');
        $data = [
            'id' => UuidFactory::uuid('user.id.irene'),
            'profile' => [
                'avatar' => [
                    'file' => [
                        'tmp_name' => $pdfFile,
                        'name' => 'sample.pdf',
                    ]
                ]
            ]
        ];
        $this->postJson('/users/' . UuidFactory::uuid('user.id.irene') . '.json?api-version=v1', $data);
        $this->assertError(400, 'Could not validate user data.');
        $this->assertNotEmpty($this->_responseJsonBody->User->profile->avatar->file->extension);

        $avatarCountsAfter = $this->Avatars->find()->count();
        $this->assertEquals($avatarCountsBefore, $avatarCountsAfter, "The number of avatars in db should be same before and after the test");
    }

    public function testUsersEditAvatarNoDataProvided()
    {
        $this->authenticateAs('irene');
        $data = [
            'id' => UuidFactory::uuid('user.id.irene'),
            'profile' => [
                'avatar' => []
            ]
        ];
        $this->postJson('/users/' . UuidFactory::uuid('user.id.irene') . '.json?api-version=v1', $data);
        $this->assertError(400, 'Could not validate user data.');
        $this->assertNotEmpty($this->_responseJsonBody->User->profile->avatar->file->_required);
    }

    public function testUsersEditAvatarCantOverrideData()
    {
        $adaAvatar = PASSBOLT_TEST_DATA_AVATAR_PATH . DS . 'ada.png';

        $this->authenticateAs('irene');
        $data = [
            'id' => UuidFactory::uuid('user.id.irene'),
            'profile' => [
                'avatar' => [
                    'file' => [
                        'tmp_name' => $adaAvatar,
                        'name' => 'irene.png',
                    ],
                    'user_id' => UuidFactory::uuid('user.id.whatever'),
                    'foreign_key' => UuidFactory::uuid('profile.id.whatever'),
                    'model' => 'Test',
                    'filename' => 'test.jpg',
                    'filesize' => '10024',
                    'mime_type' => 'pdf',
                    'extension' => 'jpg',
                    'hash' => '12345',
                    'path' => '/test/test1',
                    'adapter' => 'TestAdapter'
                ]
            ]
        ];
        $this->postJson('/users/' . UuidFactory::uuid('user.id.irene') . '.json?api-version=v1', $data);
        $this->assertSuccess();

        $ireneAvatar = $this->Avatars
            ->find()
            ->orderDesc('created')
            ->first();

        $data = $data['profile']['avatar'];
        $this->assertNotEquals($data['user_id'], $ireneAvatar->user_id);
        $this->assertNotEquals($data['foreign_key'], $ireneAvatar->foreign_key);
        $this->assertNotEquals($data['model'], $ireneAvatar->model);
        $this->assertNotEquals($data['filename'], $ireneAvatar->filename);
        $this->assertEquals('irene.png', $ireneAvatar->filename);
        $this->assertNotEquals($data['filesize'], $ireneAvatar->filesize);
        $this->assertNotEquals($data['mime_type'], $ireneAvatar->mime_type);
        $this->assertNotEquals($data['extension'], $ireneAvatar->extension);
        $this->assertNotEquals($data['hash'], $ireneAvatar->hash);
        $this->assertNotEquals($data['path'], $ireneAvatar->path);
        $this->assertNotEquals($data['adapter'], $ireneAvatar->adapter);
    }
}
