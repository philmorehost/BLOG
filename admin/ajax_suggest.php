<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) {
    die(json_encode(['error' => 'Unauthorized']));
}

$topics = get_suggested_topics();
echo json_encode($topics);