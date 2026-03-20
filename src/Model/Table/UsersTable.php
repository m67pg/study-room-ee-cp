<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

class UsersTable extends Table
{
    public function initialize(array $config = []): void
    {
        parent::initialize($config);

        $this->setTable('users');
        // CI4 Shield と同じテーブル構造を使うため主キーを明示する
        $this->setPrimaryKey('id');
        $this->setEntityClass(\App\Model\Entity\User::class);

        $this->setDisplayField('username');

        // CI4 Shield の認証情報は auth_identities 側に入る想定
        // （users はプロフィール、auth_identities は identity/secret を保持）
        $this->hasMany('AuthIdentities', [
            'foreignKey' => 'user_id',
            'className' => \App\Model\Table\AuthIdentitiesTable::class,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('username')
            ->maxLength('username', 30)
            ->allowEmptyString('username');
    
        $validator
            ->scalar('status')
            ->maxLength('status', 255)
            ->allowEmptyString('status');
    
        $validator
            ->scalar('status_message')
            ->maxLength('status_message', 255)
            ->allowEmptyString('status_message');
    
        // DB: tinyint(1) NOT NULL DEFAULT 0
        $validator
            ->boolean('active');
    
        // DB: datetime NULL
        $validator
            ->dateTime('last_active')
            ->allowEmptyDateTime('last_active');
    
        // DB: INT(11) NOT NULL DEFAULT 0
        $validator
            ->integer('student_id');
    
        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules;
    }
}