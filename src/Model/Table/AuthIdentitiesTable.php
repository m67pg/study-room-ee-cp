<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\Table;

class AuthIdentitiesTable extends Table
{
    public function initialize(array $config = []): void
    {
        parent::initialize($config);

        $this->setTable('auth_identities');
        $this->setPrimaryKey('id');
        $this->setEntityClass(\App\Model\Entity\AuthIdentity::class);

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Authentication.login 用に、auth_identities + users を join した形で取得します。
     *
     * 返すカラム（別名）:
     * - identity: auth_identities.identity
     * - secret: auth_identities.secret
     * - username: users.username
     * - student_id: users.student_id
     */
    public function findWithUser(Query $query, array $options = []): Query
    {
        return $query
        ->contain('Users')
        ->select([
                'AuthIdentities.id',
                'AuthIdentities.user_id',
                'AuthIdentities.secret',
                'AuthIdentities.secret2',
                'Users.username',
                'Users.student_id',
        ]);
    }
}

