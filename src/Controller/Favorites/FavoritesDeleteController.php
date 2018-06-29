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

namespace App\Controller\Favorites;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\NotFoundException;
use Cake\Validation\Validation;

class FavoritesDeleteController extends AppController
{
    /**
     * Unmark a resource as favorite.
     *
     * @param string $id The identifier of favorite to delete.
     * @throws BadRequestException
     * @throws NotFoundException
     * @return void
     */
    public function delete($id = null)
    {
        // Check request sanity
        if (!Validation::uuid($id)) {
            throw new BadRequestException(__('The favorite id is not valid.'));
        }
        $this->loadModel('Favorites');

        // Retrieve the favorite.
        try {
            $favorite = $this->Favorites->get($id);
        } catch (RecordNotFoundException $e) {
            throw new NotFoundException(__('The favorite does not exist.'));
        }

        // Delete the favorite.
        $this->Favorites->delete($favorite, ['Favorites.user_id' => $this->User->id()]);
        $this->_handleDeleteErrors($favorite);

        $this->success(__('The favorite was deleted.'));
    }

    /**
     * Manage delete errors
     *
     * @param \Cake\Datasource\EntityInterface $favorite favorite
     * @return void
     */
    private function _handleDeleteErrors($favorite)
    {
        $errors = $favorite->getErrors();
        if (!empty($errors)) {
            if (isset($errors['user_id']['is_owner'])) {
                throw new NotFoundException(__('The favorite does not exist.'));
            }
            throw new BadRequestException(__('Could not delete favorite.'));
        }
    }
}
