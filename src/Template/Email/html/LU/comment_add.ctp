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
use App\Utility\Purifier;
use Cake\Core\Configure;
use Cake\Routing\Router;

$creator = $body['creator'];
$comment = $body['comment'];
$resource = $body['resource'];

echo $this->element('Email/module/avatar',[
    'url' => Router::url(DS . $creator->profile->avatar->url['small'], true),
    'text' => $this->element('Email/module/avatar_text', [
        'username' => Purifier::clean($creator->username),
        'first_name' => Purifier::clean($creator->profile->first_name),
        'last_name' => Purifier::clean($creator->profile->last_name),
        'datetime' => $comment->created,
        'text' => __('{0} commented on {1}', null, Purifier::clean($resource->name))
    ])
]);

if (Configure::read('passbolt.email.show.comment')) {
    echo $this->element('Email/module/text', [
        'text' => Purifier::clean($comment->content)
    ]);
}

echo $this->element('Email/module/button', [
    'url' => Router::url('/', true),
    'text' => __('log in passbolt')
]);
