<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class User extends Entity
{
    protected array $_accessible = [
        '*' => false,
        'username' => true,
        'status' => true,
        'status_message' => true,
        'active' => true,
        'last_active' => true,
        'student_id' => true,
    ];

    protected array $_type = [
        'username' => 'string',
        'status' => 'string',
        'status_message' => 'string',
        'active' => 'boolean',
        'last_active' => 'datetime',
        'student_id' => 'integer',
    ];

    public function isAdmin(): bool
    {
        return $this->student_id === 0;
    }
}