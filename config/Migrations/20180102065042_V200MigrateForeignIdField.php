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

use Migrations\AbstractMigration;

class V200MigrateForeignIdField extends AbstractMigration
{
    /**
     * Up
     *
     * @return void
     */
    public function up()
    {
        // foreign_id is not used anywhere else except in favorites and comments.
        // we want to stick to the conventions, hence we rename it to
        // foreign_key

        $this->table('favorites')
            ->renameColumn('foreign_id', 'foreign_key')
            ->save();

        $this->table('comments')
            ->renameColumn('foreign_id', 'foreign_key')
            ->save();
    }
}
