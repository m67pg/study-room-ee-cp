<?php
declare(strict_types=1);
    
namespace App\Controller;
    
use Cake\Controller\Controller;
use Cake\Event\EventInterface;
    
class AppController extends Controller
{
        public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');

        $this->loadComponent('Authentication.Authentication', [
            'requireIdentity' => false,
        ]);

        if (str_contains($this->request->getPath(), '/api')) {
            if ($this->components()->has('Csrf')) {
                $this->loadComponent('Csrf', ['enabled' => false]);
            }
        }
    }
    
        public function beforeFilter(EventInterface $event)
        {
            parent::beforeFilter($event);
    
            // 3. CORS ヘッダーの設定（環境変数からフロントエンドURLを取得）
            if (str_contains($this->request->getPath(), '/api')) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:4173');
                // 開発環境ではワイルドカードを許可
                $allowOrigin = (env('DEBUG', false)) ? '*' : $frontendUrl;
                
                $this->response = $this->response
                    ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
                    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                    ->withHeader('Access-Control-Allow-Credentials', $allowOrigin !== '*' ? 'true' : 'false');
    
                // OPTIONSリクエストには即座に200を返す
                if ($this->request->is('options')) {
                    return $this->response->withStatus(200);
                }
            }
        }
    
        /**
         * JSONレスポンスを返す共通メソッド
         */
        protected function json(array $data, int $status = 200): \Cake\Http\Response
        {
            return $this->response
                ->withType('application/json')
                ->withStatus($status)
                ->withStringBody(json_encode($data));
        }
    }
