<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
    
        // CSRFチェックを無効化（API用）
        if ($this->components()->has('Csrf')) {
            $this->loadComponent('Csrf', ['enabled' => false]);
        }

        // requireIdentityをfalseに設定（allowUnauthenticatedで個別制御）
        $this->Authentication->setConfig('requireIdentity', false);
    }
    
    /**
     * 入退室ログを記録する
     * POST /api/attendance/log
     */
    public function saveLog()
    {
        $data = $this->request->getData();
        $status = $data['status'] ?? null;

        if (!in_array($status, [0, 1, 2, 3], true)) {
            return $this->json(['error' => '無効なステータスです'], 400);
        }

        $identity = $this->Authentication->getIdentity();
        $userId = $identity->user_id ?? $identity->user->id ?? null;

        if (!$userId) {
            return $this->json(['error' => 'ログインが必要です'], 401);
        }

        $attendanceTable = $this->fetchTable('Attendances');
        $newLog = $attendanceTable->newEntity([
            'user_id'   => $userId,
            'status'    => (int)$status,
            'timestamp' => date('Y-m-d H:i:s'),
            'latitude'  => $data['lat'] ?? null,
            'longitude' => $data['lng'] ?? null,
        ]);

        try {
            if ($attendanceTable->save($newLog)) {
                return $this->json([
                    'message' => '記録しました',
                    'status'  => $newLog->status,
                    'time'    => $newLog->timestamp
                ]);
            }
            return $this->json(['error' => '保存に失敗しました'], 500);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 最新のステータスを取得する（React起動時にボタンの状態を決めるため）
     * GET /api/attendance/current
     */
    public function current()
    {
        // 1. ログインユーザーの取得
        $identity = $this->Authentication->getIdentity();
        $userId = $identity->user->id ?? $identity->id ?? null;

        if (!$userId) {
            return $this->json(['error' => 'ユーザーIDが取得できません'], 401);
        }

        $attendancesTable = $this->fetchTable('Attendances');

        // 2. 今日の全ログを取得（古い順）
        $todayStart = new \DateTime('today midnight', new \DateTimeZone('Asia/Tokyo'));
        $todayEnd = new \DateTime('tomorrow midnight', new \DateTimeZone('Asia/Tokyo'));
        $todayEnd->modify('-1 second');
        
        $query = $attendancesTable->find()
            ->where([
                'user_id' => $userId,
                'timestamp >=' => $todayStart->format('Y-m-d H:i:s'),
                'timestamp <=' => $todayEnd->format('Y-m-d H:i:s')
            ])
            ->orderBy(['timestamp' => 'ASC']);

        $logs = $query->toArray();

        // 3. 純自習時間の計算（秒単位で集計）
        $totalSeconds = 0;
        $lastEnterTime = null;

        foreach ($logs as $log) {
            $time = strtotime((string)$log->timestamp);
            $status = (int)$log->status;

            if ($status === 1 || $status === 3) {
                // 入室 または 戻り：開始時間を記録
                $lastEnterTime = $time;
            } 
            elseif ($status === 2 || $status === 0) {
                // 一時退出 または 最終退室：入室から今までの時間を加算
                if ($lastEnterTime) {
                    $totalSeconds += ($time - $lastEnterTime);
                    $lastEnterTime = null;
                }
            }
        }

        // 最新のステータス取得
        $lastLog = end($logs);

        return $this->json([
            'status' => $lastLog ? (int)$lastLog->status : 0,
            'totalSeconds' => $totalSeconds
        ]);
    }

    /**
     * 全履歴をExcelで出力する
     * GET /api/attendance/export
     */
    public function export()
    {
        // 1. 権限チェック (以前設定した isAdmin() メソッドを利用)
        $identity = $this->Authentication->getIdentity();
        $user = $identity->user ?? null;
        $studentId = $user->student_id ?? $identity->student_id ?? null;

        if ($studentId !== 0) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        // クエリパラメータ取得
        $targetUserId = $this->request->getQuery('student_user_id');

        $attendancesTable = $this->fetchTable('Attendances');
        $query = $attendancesTable->find()
            ->contain(['Users'])
            ->orderBy(['Attendances.timestamp' => 'ASC']);

        if ($targetUserId && $targetUserId !== 'all') {
            $query->where(['Attendances.user_id' => $targetUserId]);
        }

        $logs = $query->all();

        // 2. 日別・ユーザー別にデータを集計 (ロジックは移植)
        $dailyData = [];
        foreach ($logs as $log) {
            $date = $log->timestamp->format('Y-m-d');
            $userId = $log->user_id;
            $time = $log->timestamp->getTimestamp();
            $status = (int)$log->status;
            $key = $date . '_' . $userId;

            if (!isset($dailyData[$key])) {
                $dailyData[$key] = [
                    'date' => $date,
                    'sid'  => $log->user->student_id,
                    'name' => $log->user->username,
                    'total_study_sec' => 0,
                    'total_break_sec' => 0,
                    'last_time' => null,
                    'last_status' => null,
                    'latitude' => '',
                    'longitude' => '',
                ];
            }

            $d = &$dailyData[$key];

            if ($d['last_time'] !== null) {
                $diff = $time - $d['last_time'];
                if ($d['last_status'] === 1 || $d['last_status'] === 3) {
                    $d['total_study_sec'] += $diff;
                } elseif ($d['last_status'] === 2) {
                    $d['total_break_sec'] += $diff;
                }
            }

            $d['last_time'] = $time;
            $d['last_status'] = $status;

            if ($log->latitude !== null) {
                $d['latitude'] .= ($d['latitude'] ? ',' : '') . $log->status . ':' . $log->latitude;
            }
            if ($log->longitude !== null) {
                $d['longitude'] .= ($d['longitude'] ? ',' : '') . $log->status . ':' . $log->longitude;
            }
        }

        // 3. Excel作成
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('日別自習状況');

        $headers = ['日付', '学籍番号', '名前', '一時退出合計', '純自習時間', '緯度', '経度'];
        $sheet->fromArray($headers, NULL, 'A1');

        $row = 2;
        foreach ($dailyData as $data) {
            $sheet->setCellValue('A' . $row, $data['date']);
            $sheet->setCellValue('B' . $row, $data['sid']);
            $sheet->setCellValue('C' . $row, $data['name']);
            $sheet->setCellValue('D' . $row, $this->formatSec($data['total_break_sec']));
            $sheet->setCellValue('E' . $row, $this->formatSec($data['total_study_sec']));
            $sheet->setCellValue('F' . $row, $data['latitude']);
            $sheet->setCellValue('G' . $row, $data['longitude']);
            $row++;
        }

        foreach (range('A', 'G') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        // 4. Responseの生成
        $fileName = 'daily_report_' . date('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // 一時ファイルを使わずに出力
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $this->response
            ->withType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->withStringBody($content);
    }

    /**
     * 秒を 00:00:00 形式にする補助関数
     * 
     * @param int $seconds 秒数
     * @return string フォーマットされた文字列
     */
    private function formatSec(int $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds / 60) % 60);
        $s = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /**
     * JSONレスポンスを返す補助メソッド
     */
    protected function json(array $data, int $status = 200): \Cake\Http\Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode($data));
    }

    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['current']);
    }
}
