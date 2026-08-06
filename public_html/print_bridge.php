<?php
declare(strict_types=1);

// Windows Print Bridge API handler
// Endpoints (all require X-Device-Token header or ?token=...):
// GET  ?action=next       -> get next pending job
// POST ?action=confirm    -> { job_id: int, result: 'completed' }
// POST ?action=failed     -> { job_id: int, error: '...' }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Setting.php';
require_once __DIR__ . '/../classes/ActivityLog.php';
require_once __DIR__ . '/../classes/PrintJob.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
action:
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

// authenticate
$provided = '';
if (!empty($_SERVER['HTTP_X_DEVICE_TOKEN'])) {
    $provided = trim((string) $_SERVER['HTTP_X_DEVICE_TOKEN']);
} elseif (!empty($_GET['token'])) {
    $provided = trim((string) $_GET['token']);
} elseif (!empty($_POST['token'])) {
    $provided = trim((string) $_POST['token']);
}
$expected = Setting::get('print_bridge_token');
if (!$expected || $provided === '' || !hash_equals((string)$expected, (string)$provided)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if ($method === 'GET' && $action === 'next') {
        $job = PrintJob::fetchNextPending();
        if (!$job) {
            echo json_encode(['success' => false, 'message' => 'No pending jobs']);
            exit;
        }
        // Only expose safe fields to the bridge. Provide print_text and job metadata.
        $payload = $job['payload'] ?? null;
        $response = [
            'success' => true,
            'job' => [
                'id' => (int) $job['id'],
                'order_id' => (int) $job['order_id'],
                'order_number' => $job['order_number'],
                'print_text' => $payload['print_text'] ?? null,
                'payload' => $payload // structured payload for advanced bridges (contains prices if needed)
            ]
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'POST' && $action === 'confirm') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || empty($body['job_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payload']);
            exit;
        }
        $jobId = (int) $body['job_id'];
        $ok = PrintJob::markCompleted($jobId);
        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark job completed']);
        }
        exit;
    }

    if ($method === 'POST' && $action === 'failed') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body) || empty($body['job_id']) || !isset($body['error'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payload']);
            exit;
        }
        $jobId = (int) $body['job_id'];
        $error = (string) $body['error'];
        $ok = PrintJob::markFailed($jobId, $error);
        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to mark job failed']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    http_response_code(500);
    ActivityLog::record('order_approve', 'system', null, 'system', null, 'Print bridge exception: '.mb_substr($e->getMessage(),0,200));
    echo json_encode(['success' => false, 'message' => 'Internal error']);
}
