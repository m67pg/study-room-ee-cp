<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AuthIdentity extends Entity
{
    protected array $_accessible = [
        '*' => false,
        'user_id' => true,
        'identity' => true,
        'secret' => true,
        // Authentication 側で users を join して取得した値
        'username' => true,
        'student_id' => true,
    ];

    protected array $_type = [
        'user_id' => 'integer',
        'identity' => 'string',
        'secret' => 'string',
        'username' => 'string',
        'student_id' => 'integer',
    ];
}

