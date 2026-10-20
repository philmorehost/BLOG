<?php
$path = strtok($_SERVER["REQUEST_URI"], '?');

// If file exists in root, serve it
if (file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

// Otherwise, use the root index.php
require 'index.php';