<?php
declare(strict_types=1);
require __DIR__ . '/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_json(['ok' => false, 'message' => 'POST requests only.'], 405);

$input = request_body();
$name = clean_text($input['name'] ?? '', 120);
$phone = clean_text($input['phone'] ?? '', 50);
$email = filter_var(clean_text($input['email'] ?? '', 160), FILTER_VALIDATE_EMAIL);
$category = clean_text($input['category'] ?? $input['product'] ?? '', 100);
$requirement = clean_text($input['requirement'] ?? '', 2000);

if ($name === '' || $phone === '' || !$email || $category === '' || $requirement === '') {
    send_json(['ok' => false, 'message' => 'Please complete all quote-request fields.'], 422);
}

try {
    $entry = [
        'id' => bin2hex(random_bytes(10)),
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'category' => $category,
        'requirement' => $requirement,
        'status' => 'New',
        'createdAt' => gmdate(DATE_ATOM),
    ];
    enquiry_store(function (&$enquiries) use ($entry) {
        array_unshift($enquiries, $entry);
        return $entry;
    });
    send_json(['ok' => true, 'entry' => $entry], 201);
} catch (Throwable $error) {
    send_json(['ok' => false, 'message' => 'Your enquiry could not be saved at this time.'], 500);
}
