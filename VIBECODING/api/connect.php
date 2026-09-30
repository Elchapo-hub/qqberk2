<?php
declare(strict_types=1);

const SUPERFINE_DATA_FILE = __DIR__ . '/data/enquiries.json';

function send_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function request_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return $_POST;
}

function clean_text(mixed $value, int $max = 1000): string {
    $value = is_string($value) ? trim($value) : '';
    return mb_substr(strip_tags($value), 0, $max);
}

function enquiry_store(callable $callback): mixed {
    $directory = dirname(SUPERFINE_DATA_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the enquiry store.');
    }

    $handle = fopen(SUPERFINE_DATA_FILE, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Unable to access the enquiry store.');
    }

    try {
        rewind($handle);
        $raw = stream_get_contents($handle) ?: '[]';
        $enquiries = json_decode($raw, true);
        if (!is_array($enquiries)) $enquiries = [];
        $result = $callback($enquiries);
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode(array_values($enquiries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($handle);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function admin_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('superfine_admin');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }
}

function require_admin(): void {
    admin_session();
    if (empty($_SESSION['superfine_admin'])) send_json(['ok' => false, 'message' => 'Administrator authentication is required.'], 401);
}
