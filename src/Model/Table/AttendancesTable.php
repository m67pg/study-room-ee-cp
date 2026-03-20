<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class AttendancesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // CodeIgniter 4 と同じテーブル名を指定
        $this->setTable('attendance_logs'); 
        
        // 主キーの設定（通常は id）
        $this->setPrimaryKey('id');

        // カラム型の明示的設定
        $this->getSchema()->setColumnType('status', 'integer');
        $this->getSchema()->setColumnType('timestamp', 'datetime');

        // Users テーブルとの紐付け設定
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);

        // タイムスタンプの自動更新が必要な場合は以下を追加（任意）
        // $this->addBehavior('Timestamp');
    }
}