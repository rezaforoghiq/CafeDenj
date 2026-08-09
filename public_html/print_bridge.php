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
        $orderNumber = null;
        $orderStmt = Database::getConnection()->prepare('SELECT order_number FROM orders WHERE id = :id LIMIT 1');
        $orderStmt->execute(['id' => (int) $job['order_id']]);
        $currentOrderNumber = $orderStmt->fetchColumn();
        if ($currentOrderNumber !== false && $currentOrderNumber !== null) {
            $orderNumber = (string) $currentOrderNumber;
        } else {
            $orderNumber = (string) ($job['order_number'] ?? '');
        }
        $printText = is_string($payload['print_text'] ?? null) ? $payload['print_text'] : null;
        if ($printText !== null && $orderNumber !== null) {
            $lines = preg_split('/\r\n|\r|\n/', $printText);
            $updated = false;
            for ($i = 0; $i < count($lines); $i++) {
                if (trim((string) $lines[$i]) === 'سفارش:') {
                    if (isset($lines[$i + 1])) {
                        $lines[$i + 1] = (string) $orderNumber;
                        $updated = true;
                    }
                    break;
                }
            }
            if ($updated) {
                $printText = implode("\n", $lines);
            }
        }
        $payload['order_number'] = $orderNumber;
        $payload['print_text'] = $printText;
        $response = [
            'success' => true,
            'job' => [
                'id' => (int) $job['id'],
                'order_id' => (int) $job['order_id'],
                'order_number' => $orderNumber,
                'print_text' => $printText,
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
