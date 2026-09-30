<?php
declare(strict_types=1);
require __DIR__ . '/connect.php';

$input = request_body();
$action = clean_text($input['action'] ?? $_GET['action'] ?? 'session', 30);
admin_session();

if (!in_array($action, ['session', 'inquiries'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'message' => 'POST requests are required for this action.'], 405);
}

if ($action === 'login') {
    $username = clean_text($input['username'] ?? '', 80);
    $password = (string) ($input['password'] ?? '');
    $expectedUser = getenv('SUPERFINE_ADMIN_USERNAME') ?: 'admin';
    $expectedPassword = getenv('SUPERFINE_ADMIN_PASSWORD') ?: 'admin';
    if (!hash_equals($expectedUser, $username) || !hash_equals($expectedPassword, $password)) {
        send_json(['ok' => false, 'message' => 'Invalid administrator credentials.'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['superfine_admin'] = $username;
    send_json(['ok' => true, 'username' => $username]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    send_json(['ok' => true]);
}

require_admin();

try {
    if ($action === 'session') send_json(['ok' => true, 'username' => $_SESSION['superfine_admin']]);

    if ($action === 'inquiries') {
        $items = enquiry_store(fn (&$enquiries) => $enquiries);
        send_json(['ok' => true, 'enquiries' => $items]);
    }

    if ($action === 'update') {
        $id = clean_text($input['id'] ?? '', 50);
        $status = clean_text($input['status'] ?? '', 40);
        $allowed = ['New', 'In review', 'Quoted', 'Closed'];
        if ($id === '' || !in_array($status, $allowed, true)) send_json(['ok' => false, 'message' => 'Invalid enquiry update.'], 422);
        $updated = enquiry_store(function (&$enquiries) use ($id, $status) {
            foreach ($enquiries as &$entry) {
                if (($entry['id'] ?? '') === $id) {
                    $entry['status'] = $status;
                    return $entry;
                }
            }
            return null;
        });
        if (!$updated) send_json(['ok' => false, 'message' => 'Enquiry not found.'], 404);
        send_json(['ok' => true, 'entry' => $updated]);
    }

    if ($action === 'delete') {
        $id = clean_text($input['id'] ?? '', 50);
        if ($id === '') send_json(['ok' => false, 'message' => 'Missing enquiry identifier.'], 422);
        $deleted = enquiry_store(function (&$enquiries) use ($id) {
            $before = count($enquiries);
            $enquiries = array_values(array_filter($enquiries, fn ($entry) => ($entry['id'] ?? '') !== $id));
            return count($enquiries) !== $before;
        });
        if (!$deleted) send_json(['ok' => false, 'message' => 'Enquiry not found.'], 404);
        send_json(['ok' => true]);
    }

    send_json(['ok' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $error) {
    send_json(['ok' => false, 'message' => 'The admin service is temporarily unavailable.'], 500);
}
