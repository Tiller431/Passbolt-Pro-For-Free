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
namespace App\Model\Table;

use App\Model\Entity\Avatar;
use App\Model\Entity\Role;
use App\Model\Entity\User;
use App\Model\Rule\IsNotSoleManagerOfGroupOwningSharedResourcesRule;
use App\Model\Rule\IsNotSoleManagerOfNonEmptyGroupRule;
use App\Model\Rule\IsNotSoleOwnerOfSharedResourcesRule;
use Cake\Core\Configure;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validation;
use Cake\Validation\Validator;

/**
 * Users Model
 *
 * @property \App\Model\Table\RolesTable|\Cake\ORM\Association\BelongsTo $Roles
 * @property \App\Model\Table\FileStorageTable|\Cake\ORM\Association\HasMany $FileStorage
 * @property \App\Model\Table\GpgkeysTable|\Cake\ORM\Association\HasMany $Gpgkeys
 * @property \App\Model\Table\PermissionsTable|\Cake\ORM\Association\HasMany $Permissions
 * @property \App\Model\Table\ProfilesTable|\Cake\ORM\Association\HasMany $Profiles
 * @property \App\Model\Table\GroupsUsersTable|\Cake\ORM\Association\HasMany $GroupsUsers
 * @property \App\Model\Table\GroupsTable|\Cake\ORM\Association\BelongsToMany $Groups
 *
 * @method \App\Model\Entity\User get($primaryKey, $options = [])
 * @method \App\Model\Entity\User newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\User[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\User[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\User findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UsersTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Roles', [
            'foreignKey' => 'role_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('AuthenticationTokens', [
            'foreignKey' => 'user_id'
        ]);
        $this->hasMany('FileStorage', [
            'foreignKey' => 'user_id'
        ]);
        $this->hasOne('Gpgkeys', [
            'foreignKey' => 'user_id'
        ]);
        $this->hasOne('Profiles', [
            'foreignKey' => 'user_id',
        ]);
        $this->hasMany('GroupsUsers', [
            'foreignKey' => 'user_id'
        ]);
        $this->belongsToMany('Groups', [
            'through' => 'GroupsUsers'
        ]);
        $this->hasMany('Permissions', [
            'foreignKey' => 'aro_foreign_key'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->uuid('id', __('User id by must be a valid UUID.'))
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('username', 'create', __('A username is required.'))
            ->notEmpty('username', __('A username is required.'))
            ->maxLength('username', 255, __('The username length should be maximum {0} characters.', 255))
            ->email('username', Configure::read('passbolt.email.validate.mx'), __('The username should be a valid email address.'));

        $validator
            ->boolean('active')
            ->notEmpty('active');

        $validator
            ->uuid('role_id')
            ->requirePresence('role_id', 'create')
            ->notEmpty('role_id');

        $validator
            ->boolean('deleted')
            ->notEmpty('deleted');

        $validator
            ->requirePresence('profile', 'create')
            ->notEmpty('profile');

        return $validator;
    }

    /**
     * Register validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationRegister(Validator $validator)
    {
        return $this->validationDefault($validator);
    }

    /**
     * Update validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationUpdate(Validator $validator)
    {
        return $this->validationDefault($validator);
    }

    /**
     * Register validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationRecover(Validator $validator)
    {
        $validator
            ->requirePresence('username', 'create', __('A username is required.'))
            ->notEmpty('username', __('A username is required.'))
            ->maxLength('username', 255, __('The username length should be maximum 254 characters.'))
            ->email('username', Configure::read('passbolt.email.validate.mx'), __('The username should be a valid email address.'));

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        // Add rule
        $rules->add($rules->isUnique(['username', 'deleted']), 'uniqueUsername', [
            'message' => __('This username is already in use.')
        ]);
        $rules->add($rules->existsIn(['role_id'], 'Roles'), 'validRole', [
            'message' => __('This is not a valid role.')
        ]);

        // Delete rules
        $rules->addDelete(new IsNotSoleOwnerOfSharedResourcesRule(), 'soleOwnerOfSharedResource', [
            'errorField' => 'id',
            'message' => __('You need to transfer the ownership for the shared passwords owned by this user before deleting this user.')
        ]);
        $rules->addDelete(new IsNotSoleManagerOfNonEmptyGroupRule(), 'soleManagerOfNonEmptyGroup', [
            'errorField' => 'id',
            'message' => __('You need to transfer the user group manager role to other users before deleting this user.')
        ]);
        $rules->addDelete(new IsNotSoleManagerOfGroupOwningSharedResourcesRule(), 'soleManagerOfGroupOwnerOfSharedResource', [
            'errorField' => 'id',
            'message' => __('This user is the only admin of one (or more) group that is the sole owner of shared resources.')
        ]);

        return $rules;
    }

    /**
     * Build the query that fetches data for user index
     *
     * @param string $role name
     * @param array $options filters
     * @throws \InvalidArgumentException if no role is specified
     * @return Query
     */
    public function findIndex(string $role, array $options = null)
    {
        $query = $this->find();

        // Options must contain a role
        if (!isset($role)) {
            $msg = __('User table findIndex should have a role set in options.');
            throw new \InvalidArgumentException($msg);
        }
        if (!$this->Roles->isValidRoleName($role)) {
            throw new \InvalidArgumentException(__('The role name is not valid.'));
        }

        // Default associated data
        $containDefault = ['Profiles', 'Gpgkeys', 'Roles', 'GroupsUsers'];
        $containWhiteList = ['LastLoggedIn'];
        if (!isset($options['contain']) || (!is_array($options['contain']))) {
            $contain = $containDefault;
        } else {
            $containOptions = [];
            foreach ($options['contain'] as $option => $value) {
                if ($value == 1) {
                    $containOptions[] = $option;
                }
            }
            $contain = array_merge($containDefault, array_intersect($containOptions, $containWhiteList));
        }

        // If contains Profiles, then include Avatars too.
        if (in_array('Profiles', $contain)) {
            $contain['Profiles'] = AvatarsTable::addContainAvatar();
            unset($contain[array_search('Profiles', $contain)]);
        }

        if (in_array('LastLoggedIn', $contain)) {
            $query = $this->_containLastLoggedIn($query);
            unset($contain[array_search('LastLoggedIn', $contain)]);
        }

        $query->contain($contain);

        // Filter out guests and deleted users
        $query->where([
            'Users.deleted' => false,
            'Roles.name <>' => Role::GUEST
        ]);

        // If user is admin, we allow seeing inactive users via the 'is-active' filter
        if ($role === Role::ADMIN) {
            if (isset($options['filter']['is-active'])) {
                $query->where(['Users.active' => $options['filter']['is-active']]);
            }
        } else {
            // otherwise we only show active users
            $query->where(['Users.active' => true]);
        }

        // If searching for a name or username
        if (isset($options['filter']['search']) && count($options['filter']['search'])) {
            $query = $this->_filterQueryBySearch($query, $options['filter']['search'][0]);
        }

        // If searching by group id
        if (isset($options['filter']['has-groups']) && count($options['filter']['has-groups'])) {
            $query = $this->_filterQueryByGroupsUsers($query, $options['filter']['has-groups']);
        }

        // If searching by resource access
        if (isset($options['filter']['has-access']) && count($options['filter']['has-access'])) {
            $query = $this->_filterQueryByResourceAccess($query, $options['filter']['has-access'][0]);
        }

        // If searching by resource the user do not have a direct permission for
        if (isset($options['filter']['has-not-permission']) && count($options['filter']['has-not-permission'])) {
            $query = $this->_filterQueryByHasNotPermission($query, $options['filter']['has-not-permission'][0]);
        }

        // Ordering options
        if (isset($options['order'])) {
            $query->order($options['order']);
        }

        return $query;
    }

    /**
     * Find view
     *
     * @param string $userId uuid
     * @param string $roleName role name
     * @throws \InvalidArgumentException if the role name or user id are not valid
     * @return Query
     */
    public function findView(string $userId, string $roleName)
    {
        if (!Validation::uuid($userId)) {
            throw new \InvalidArgumentException(__('The user id should be a valid uuid.'));
        }
        if (!$this->Roles->isValidRoleName($roleName)) {
            throw new \InvalidArgumentException(__('The role name is not valid.'));
        }

        // Same rule than index apply with a specific id requested
        return $this->findIndex($roleName)->where(['Users.id' => $userId]);
    }

    /**
     * Find delete
     *
     * @param string $userId uuid
     * @param string $roleName role name
     * @throws \InvalidArgumentException if the role name or user id are not valid
     * @return Query
     */
    public function findDelete(string $userId, string $roleName)
    {
        if (!Validation::uuid($userId)) {
            throw new \InvalidArgumentException(__('The user id should be a valid uuid.'));
        }
        if (!$this->Roles->isValidRoleName($roleName)) {
            throw new \InvalidArgumentException(__('The role name is not valid.'));
        }

        return $this->findIndex($roleName)->where(['Users.id' => $userId]);
    }

    /**
     * Build the query that fetches the user data during authentication
     *
     * @param Query $query a query instance
     * @param array $options options
     * @throws \Exception if fingerprint id is not set
     * @return Query $query
     */
    public function findAuth(Query $query, array $options)
    {
        // Options must contain an id
        if (!isset($options['fingerprint'])) {
            throw new \Exception(__('User table findAuth should have a fingerprint id set in options.'));
        }

        // auth query is always done as guest
        // Use default index option (active:true, deleted:false) and contains
        $query = $this->findIndex(Role::GUEST)
            ->where(['Gpgkeys.fingerprint' => $options['fingerprint']]);

        return $query;
    }

    /**
     * Build the query that fetches data for user recovery form
     *
     * @param string $username email of user to retrieve
     * @param array $options options
     * @throws \InvalidArgumentException if the username is not an email
     * @return \Cake\ORM\Query
     */
    public function findRecover(string $username, array $options = [])
    {
        if (!Validation::email($username, Configure::read('passbolt.email.validate.mx'))) {
            throw new \InvalidArgumentException(__('The username should be a valid email.'));
        }
        // show active first and do not count deleted ones
        $query = $this->find()
            ->where(['Users.username' => $username, 'Users.deleted' => false])
            ->contain([
                'Roles',
                'Profiles' => AvatarsTable::addContainAvatar()
            ])
            ->order(['Users.active' => 'DESC']);

        return $query;
    }

    /**
     * Build the query that fetches data for user setup start
     *
     * @param string $userId uuid
     * @throws \InvalidArgumentException if the user id is not a uuid
     * @return object $user entity
     */
    public function findSetup($userId)
    {
        if (!Validation::uuid($userId)) {
            throw new \InvalidArgumentException(__('The user id should be a valid uuid.'));
        }

        // show active first and do not count deleted ones
        $user = $this->find()
            ->contain(['Roles', 'Profiles', 'Roles'])
            ->where([
                'Users.id' => $userId,
                'Users.deleted' => false, // forbid deleted users to start setup
                'Users.active' => false // forbid users that have completed the setup to retry
            ])
            ->first();

        return $user;
    }

    /**
     * Build the query that checks data for user setup start/completion
     *
     * @param string $userId uuid
     * @throws \InvalidArgumentException if the user id is not a uuid
     * @return object $user entity
     */
    public function findSetupRecover(string $userId)
    {
        if (!Validation::uuid($userId)) {
            throw new \InvalidArgumentException(__('The user id should be a valid uuid.'));
        }

        // show active first and do not count deleted ones
        $user = $this->find()
            ->contain(['Roles', 'Profiles', 'Roles'])
            ->where([
                'Users.id' => $userId,
                'Users.deleted' => false, // forbid deleted users to start setup
                'Users.active' => true // forbid users that have not completed the setup to recover
            ])
            ->first();

        return $user;
    }

    /**
     * Event fired before request data is converted into entities
     * Set user to inactive and not deleted on register
     *
     * @param \Cake\Event\Event $event event
     * @param \ArrayObject $data data
     * @param \ArrayObject $options options
     * @return void
     */
    public function beforeMarshal(\Cake\Event\Event $event, \ArrayObject $data, \ArrayObject $options)
    {
        // Do not allow the user to set these flags during registration
        if (isset($options['validate']) && $options['validate'] === 'register') {
            // Only admin can set the user role on registration
            if (!isset($data['role_id']) || $options['currentUserRole'] !== Role::ADMIN) {
                $data['role_id'] = $this->Roles->getIdByName(Role::USER);
            }
        }
    }

    /**
     * Add last_logged_in contain element.
     * Basically, add a placeholder to the entity that will be treated
     * in a virtual field in the User entity.
     *
     * @param Query $query query
     * @return Query
     */
    private function _containLastLoggedIn(\Cake\ORM\Query $query)
    {
        $query->formatResults(function ($results) {
            return $results->map(function ($row) {
                $row[User::LAST_LOGGED_IN_PLACEHOLDER] = '';

                return $row;
            });
        });

        return $query;
    }

    /**
     * Filter a Groups query by groups users.
     *
     * @param \Cake\ORM\Query $query The query to augment.
     * @param array<string> $groupsIds The users to filter the query on.
     * @param bool $areManager (optional) Should the users be only managers ? Default false.
     * @return \Cake\ORM\Query $query
     */
    private function _filterQueryByGroupsUsers(\Cake\ORM\Query $query, array $groupsIds, bool $areManager = false)
    {
        // If there is only one group use a left join
        if (count($groupsIds) == 1) {
            $query->leftJoinWith('GroupsUsers');
            $query->where(['GroupsUsers.group_id' => $groupsIds[0]]);
            if ($areManager) {
                $query->where(['GroupsUsers.is_admin' => true]);
            }

            return $query;
        }

        // Otherwise use a subquery to find all the users that are members of all the listed groups
        $subQuery = $this->GroupsUsers->find()
            ->select([
                'GroupsUsers.user_id',
                'count' => $query->func()->count('GroupsUsers.user_id')
            ])
            ->where(['GroupsUsers.group_id IN' => $groupsIds])
            ->group('GroupsUsers.user_id')
            ->having(['count' => count($groupsIds)]);

        // Execute the sub query and extract the user ids.
        $matchingUserIds = Hash::extract($subQuery->toArray(), '{n}.user_id');

        // Filter the query.
        if (empty($matchingUserIds)) {
            // if no user match all groups it should return nobody
            $query->where(['true' => false]);
        } else {
            $query->where(['Users.id IN' => $matchingUserIds]);
        }

        return $query;
    }

    /**
     * Filter a Users query by resource access.
     * Only the users who have a permission (Read/Update/Owner) to access a resource should be returned by the query.
     *
     * By instance :
     * $query = $Users->find()->where('Users.username LIKE' => '%@passbolt.com');
     * _filterQueryByResourceAccess($query, 'RESOURCE_UUID');
     *
     * Should filter all the users with a passbolt username who have a permission to access the resource identified by
     * RESOURCE_UUID.
     *
     * @param \Cake\ORM\Query $query The query to augment.
     * @param string $resourceId The resource the users must have access.
     * @throws \InvalidArgumentException if the ressourceId is not a valid uuid
     * @return \Cake\ORM\Query $query
     */
    private function _filterQueryByResourceAccess(\Cake\ORM\Query $query, string $resourceId)
    {
        if (!Validation::uuid($resourceId)) {
            throw new \InvalidArgumentException(__('The resource id should be a valid uuid.'));
        }

        // The query requires a join with Permissions not constraint with the default condition added by the HasMany
        // relationship : Users.id = Permissions.aro_foreign_key.
        // The join will be used in relation to Groups as well, to find the users inherited permissions from Groups.
        // To do so, add an extra join.
        $query->join([
            'table' => $this->association('Permissions')->getTable(),
            'alias' => 'PermissionsFilterAccess',
            'type' => 'INNER',
            'conditions' => ['PermissionsFilterAccess.aco_foreign_key' => $resourceId],
        ]);

        // Subquery to retrieve the groups the user is member of.
        $groupsSubquery = $this->Groups->find()
            ->innerJoinWith('GroupsUsers')
            ->select('Groups.id')
            ->where([
                'Groups.deleted' => false,
                'GroupsUsers.user_id = Users.id'
            ]);

        // Use distinct to avoid duplicate as it can happen that a user is member of two groups which
        // both have a permission for the same resource
        return $query->distinct()
            // Filter on the users who have a direct permissions.
            // Or on users who are members of a group which have permissions.
            ->where(
                ['OR' => [
                    ['PermissionsFilterAccess.aro_foreign_key = Users.id'],
                    ['PermissionsFilterAccess.aro_foreign_key IN' => $groupsSubquery]
                ]]
            );
    }

    /**
     * Filter a Users query by search.
     * Search on the following fields :
     * - Users.username
     * - Users.Profile.first_name
     * - Users.Profile.last_name
     *
     * By instance :
     * $query = $Users->find();
     * $Users->_filterQueryBySearch($query, 'ada');
     *
     * Should filter all the users with a username or a name containing ada.
     *
     * @param \Cake\ORM\Query $query The query to augment.
     * @param string $search The string to search.
     * @return \Cake\ORM\Query $query
     */
    private function _filterQueryBySearch(\Cake\ORM\Query $query, string $search)
    {
        $search = '%' . $search . '%';

        return $query->where(['OR' => [
            ['Users.username LIKE' => $search],
            ['Profiles.first_name LIKE' => $search],
            ['Profiles.last_name LIKE' => $search]
        ]]);
    }

    /**
     * Filter a Users query by users that don't have permission for a resource.
     *
     * By instance :
     * $query = $Users->find();
     * $Users->_filterQueryByHasNotPermission($query, 'ada');
     *
     * Should filter all the users that do not have a permission for apache.
     *
     * @param \Cake\ORM\Query $query The query to augment.
     * @param string $resourceId The resource to search potential users for.
     * @throws \InvalidArgumentException if the resource id is not a valid uuid
     * @return \Cake\ORM\Query $query
     */
    private function _filterQueryByHasNotPermission(\Cake\ORM\Query $query, string $resourceId)
    {
        if (!Validation::uuid($resourceId)) {
            throw new \InvalidArgumentException(__('The resource id should be a valid uuid.'));
        }

        $permissionQuery = $this->Permissions->find()
            ->select(['Permissions.aro_foreign_key'])
            ->where([
                'Permissions.aro' => 'User',
                'Permissions.aco_foreign_key' => $resourceId
            ]);

        // Filter on the users who do not have yet a permission.
        return $query->where(['Users.id NOT IN' => $permissionQuery]);
    }

    /**
     * Return a user entity
     *
     * @param array $data the request data
     * @param string $roleName the role of the user building the entity
     * @throws \InvalidArgumentException if role name is not valid
     * @return \App\Model\Entity\User
     */
    public function buildEntity(array $data, string $roleName)
    {
        if (!$this->Roles->isValidRoleName($roleName)) {
            $msg = __('The role name should be from the list of allowed role names');
            throw new \InvalidArgumentException($msg);
        }

        return $this->newEntity(
            $data,
            [
                'validate' => 'register',
                'accessibleFields' => [
                    'username' => true,
                    'deleted' => true,
                    'profile' => true,
                    'role_id' => true, // Overridded in beforMarshal if current user is not admin
                ],
                'associated' => [
                    'Profiles' => [
                        'validate' => 'register',
                        'accessibleFields' => [
                            'first_name' => true,
                            'last_name' => true
                        ]
                    ]
                ],
                'currentUserRole' => $roleName
            ]
        );
    }

    /**
     * Edit a given entity with the prodived data according to the permission of the current user role
     * Only allow editing the first_name and last_name
     * Also allow editing the role_id but only if admin
     * Other changes such as active or username are not permitted
     *
     * @param \App\Model\Entity\User $user User
     * @param array $data request data
     * @param string $roleName role name for example Role::User or Role::ADMIN
     * @return object the patched user entity
     */
    public function editEntity(\App\Model\Entity\User $user, array $data, string $roleName)
    {
        $accessibleUserFields = [
            'active' => false,
            'deleted' => false,
            'created' => false,
            'username' => false,
            'role_id' => false,
            'profile' => true,
            'gpgkey' => false,
        ];
        // only admins can set roles
        if ($roleName === Role::ADMIN) {
            $accessibleUserFields['role_id'] = true;
        }

        $accessibleProfileFields = [
            'user_id' => false,
            'created' => false,
            'first_name' => true,
            'last_name' => true,
            'avatar' => true,
        ];

        // Populates fields required for Avatar, if needed.
        if (!empty(Hash::get($data, 'profile.avatar'))) {
            if (!empty(Hash::get($data, 'profile.avatar.file'))) {
                $data['profile']['avatar']['user_id'] = $user->id;
                $data['profile']['avatar']['foreign_key'] = $user->profile->id;
                // Force creation of new Avatar.
                $user->profile->avatar = new Avatar();
            } else {
                // If file is not provided, nothing else should be. We simply delete the whole entry.
                unset($data['profile']['avatar']);
                $user->profile->avatar = null;
            }
        }

        $entity = $this->patchEntity($user, $data, [
                'validate' => 'update',
                'accessibleFields' => $accessibleUserFields,
                'associated' => [
                    'Profiles' => [
                        'validate' => 'update',
                        'accessibleFields' => $accessibleProfileFields,
                        'associated' => [
                            'Avatars'
                        ]
                    ]
                ],
                'currentUserRole' => $roleName
            ]);

        return $entity;
    }

    /**
     * Get a user info for an email notification context
     *
     * @param string $userId uuid
     * @throws \InvalidArgumentException if the user id is not a valid uuid
     * @return object User
     */
    public function getForEmail(string $userId)
    {
        if (!Validation::uuid($userId)) {
            throw new \InvalidArgumentException(__('The user id should be a valid uuid.'));
        }

        $user = $this->find()
            ->where(['Users.id' => $userId])
            ->contain([
                'Profiles' => AvatarsTable::addContainAvatar(),
                'Roles',
            ])
            ->first();

        return $user;
    }

    /**
     * Get a user info for an email notification context
     *
     * @param array $userIds of user uuid
     * @throws \InvalidArgumentException if the user id is not a valid uuid
     * @return object User
     */
    public function findForEmail(array $userIds)
    {
        foreach ($userIds as $userId) {
            if (!Validation::uuid($userId)) {
                throw new \InvalidArgumentException(__('The user id should be a valid uuid.'));
            }
        }
        $users = $this->find()
            ->where(['Users.id IN' => $userIds])
            ->contain([
                'Profiles' => AvatarsTable::addContainAvatar(),
                'Roles',
            ])
            ->all();

        return $users;
    }

    /**
     * Soft delete a user and their associated items
     * Mark user as deleted = true
     * Mark all the user resources only associated with this user as deleted = true
     * Mark all groups where user is sole member as deleted = true
     * Delete all UserGroups association entries
     * Delete all Permissions
     *
     * @param \App\Model\Entity\User $user entity
     * @param array $options additional delete options such as ['checkRules' => true]
     * @return bool status
     */
    public function softDelete(\App\Model\Entity\User $user, array $options = null)
    {
        // Check the delete rules like a normal operation
        if (!isset($options['checkRules'])) {
            $options['checkRules'] = true;
        }
        if ($options['checkRules']) {
            if (!$this->checkRules($user, RulesChecker::DELETE)) {
                return false;
            }
        }

        // find all the resources that only belongs to the user and mark them as deleted
        // Note: all resources that cannot be deleted should have been
        // transferred to other people already (ref. checkRules)
        $resourceIds = $this->Permissions->findResourcesOnlyUserCanAccess($user->id, true);
        if (!empty($resourceIds)) {
            $Resources = TableRegistry::get('Resources');
            $Resources->softDeleteAll($resourceIds);
        }

        // We do not want empty groups
        // Soft delete all the groups where the user is alone
        // Note that all associated resources are already deleted in previous step
        // ref. findResourcesOnlyUserCanAccess checkGroupsUsers = true
        $groupsId = $this->GroupsUsers->findGroupsWhereUserOnlyMember($user->id);
        if (!empty($groupsId)) {
            $this->Groups->updateAll(['deleted' => true], ['id IN' => $groupsId]);
            $this->Permissions->deleteAll(['aro_foreign_key IN' => $groupsId]);
        }

        // Delete all group memberships
        // Delete all permissions
        $this->GroupsUsers->deleteAll(['user_id' => $user->id]);
        $this->Permissions->deleteAll(['aro_foreign_key' => $user->id]);

        // Delete all secrets
        $Secrets = TableRegistry::get('Secrets');
        $Secrets->deleteAll(['user_id' => $user->id]);

        // Delete all favorites
        $Favorites = TableRegistry::get('Favorites');
        $Favorites->deleteAll(['user_id' => $user->id]);

        // Delete all tags
        if (Configure::read('passbolt.plugins.tags')) {
            $ResourcesTags = TableRegistry::get('Passbolt/Tags.ResourcesTags');
            $ResourcesTags->deleteAll(['user_id' => $user->id]);
            $Tags = TableRegistry::get('Passbolt/Tags.Tags');
            $Tags->deleteAllUnusedTags();
        }

        // Mark user as deleted
        $user->deleted = true;
        if (!$this->save($user, ['checkRules' => false])) {
            throw new InternalErrorException(__('Could not delete the user {0}, please try again later.', $user->username));
        }

        return true;
    }
}
