<?php
declare(strict_types=1);

namespace App\Controller\Api;

use Cake\Event\EventInterface;
use Cake\Http\Response;

class UsersController extends \App\Controller\AppController
{
    public function initialize(): void
    {
        parent::initialize();

        // コンポーネントの設定で requireIdentity を false にする（API制御のため）
        $this->loadComponent('Authentication.Authentication', [
            'requireIdentity' => false
        ]);
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // API は JSON 戻しにしたいので、認証チェックはこちらで制御する。
        // login/check/logout はログイン不要、listStudents は中で権限判定する。
        $this->Authentication->allowUnauthenticated(['login', 'check', 'logout', 'listStudents']);
    }

    // 1. ログイン実行 API
    public function login(): Response
    {
        $data = (array)$this->request->getData();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // 入力バリデーション（CI の validateData 相当）
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '') {
            return $this->json(['error' => '入力内容に誤りがあります'], 400);
        }

        // AuthenticationMiddleware が POST を認証し、結果を Controller に注入する前提。
        $result = $this->Authentication->getResult();
        if ($result === null || !$result->isValid()) {
            // ここで 401
            return $this->json(['error' => 'メールアドレスまたはパスワードが違います'], 401);
        }

        $this->Authentication->setIdentity($result->getData());
        $identity = $this->Authentication->getIdentity();
        // \Cake\Log\Log::debug('Identity Data: ' . print_r($identity, true));
        
        // $identity は配列のようにアクセスでき、リレーション先のデータは 
        // 紐付けたモデル名（またはその小文字/複数形）のキーに入っています。
        return $this->json([
            'message' => 'ログイン成功',
            'user' => [
                'username' => $identity['user']['username'] ?? $identity['username'] ?? '',
                'student_id' => $identity['user']['student_id'] ?? $identity['student_id'] ?? 0,
            ],
        ]);
    }

    // 2. 認証状態チェック API（React のページ更新用）
    public function check(): Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity !== null) {
            return $this->json([
                'isLoggedIn' => true,
                'user' => [
                    'username' => $identity->user->username ?? '',
                    'student_id' => $identity->user->student_id ?? 0,
                ],
            ]);
        }

        return $this->json(['isLoggedIn' => false]);
    }

    // ログアウト API
    public function logout(): Response
    {
        $this->Authentication->logout();
        return $this->json(['message' => 'ログアウトしました']);
    }

    // 生徒一覧（管理者のみ）
    public function listStudents(): Response
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity === null) {
            return $this->json(['error' => '未認証'], 401);
        }

        $studentId = (int)($this->identityValue($identity, 'student_id') ?? 0);
        if ($studentId !== 0) {
            return $this->json([], 403);
        }

        $this->fetchTable('Users');
        $students = $this->Users->find()
            ->where(['student_id >' => 0])
            ->all();

        // パスワード等の機密を返さないよう、必要な項目のみ抽出する。
        $payload = array_map(
            static fn ($user) => $user->extract([
                'id',
                'username',
                'student_id',
                'status',
                'status_message',
                'active',
                'last_active',
            ]),
            $students->toArray()
        );

        return $this->json($payload);
    }

    /**
     * Authentication の identity から値を取り出す（IdentityInterface / ArrayAccess / Entity 対応）。
     *
     * @param mixed $identity
     * @return mixed|null
     */
    private function identityValue(mixed $identity, string $key): mixed
    {
        if ($identity === null) {
            return null;
        }

        if ($identity instanceof \ArrayAccess && isset($identity[$key])) {
            return $identity[$key];
        }

        if (is_object($identity) && method_exists($identity, 'get')) {
            return $identity->get($key);
        }

        if (is_object($identity) && property_exists($identity, $key)) {
            return $identity->$key;
        }

        return null;
    }
}

