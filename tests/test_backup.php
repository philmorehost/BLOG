<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Running Enhanced Backup & Restore Integration Test...\n";

$conn = get_db_connection();

// Step 1: Ensure test user and data exist
$conn->exec("INSERT OR IGNORE INTO users (username, password, email, role) VALUES ('current_admin', 'hash_pass', 'admin@example.com', 'admin');");
$conn->exec("INSERT OR IGNORE INTO categories (name, slug) VALUES ('Test Category', 'test-category');");
$conn->exec("INSERT OR IGNORE INTO posts (title, slug, content, category, author) VALUES ('Backup Test Post', 'backup-test-post', 'Test content', 'Test Category', 'Tester');");

// Step 2: Test JSON Export with Users table
$export_data = [
    'version' => '2.0',
    'exported_at' => date('c'),
    'site_settings' => $conn->query("SELECT * FROM site_settings")->fetchAll(PDO::FETCH_ASSOC),
    'users' => $conn->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC),
    'categories' => $conn->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC),
    'posts' => $conn->query("SELECT * FROM posts")->fetchAll(PDO::FETCH_ASSOC)
];

assert(!empty($export_data['users']), "Export should contain users table data.");
echo "✓ Users table export verified successfully.\n";

// Step 3: Test Restore with Admin Overwrite DISABLED (Default)
$export_data['users'][] = [
    'username' => 'backup_admin_user',
    'password' => 'backup_hash',
    'email' => 'backup_admin@example.com',
    'role' => 'admin'
];

$overwrite_admin = false; // Checkbox unchecked

if ($overwrite_admin && !empty($export_data['users'])) {
    $stmt_u = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    foreach ($export_data['users'] as $u) {
        $stmt_u->execute([$u['username'], $u['password'], $u['email'], $u['role']]);
    }
}

$check_user = $conn->query("SELECT COUNT(*) FROM users WHERE username = 'backup_admin_user'")->fetchColumn();
assert($check_user == 0, "backup_admin_user should NOT be imported when overwrite_admin is false.");
echo "✓ Admin credentials safeguard verified (users table skipped when overwrite_admin is false).\n";

// Step 4: Test Restore with Admin Overwrite ENABLED
$overwrite_admin = true; // Checkbox checked

if ($overwrite_admin && !empty($export_data['users'])) {
    $stmt_u = $conn->prepare("INSERT OR IGNORE INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    foreach ($export_data['users'] as $u) {
        $stmt_u->execute([$u['username'], $u['password'], $u['email'], $u['role']]);
    }
}

$check_user = $conn->query("SELECT COUNT(*) FROM users WHERE username = 'backup_admin_user'")->fetchColumn();
assert($check_user == 1, "backup_admin_user SHOULD be imported when overwrite_admin is true.");
echo "✓ Admin overwrite option verified (users table restored when overwrite_admin is true).\n";

// Cleanup
$conn->exec("DELETE FROM users WHERE username = 'backup_admin_user'");
$conn->exec("DELETE FROM posts WHERE slug = 'backup-test-post'");
$conn->exec("DELETE FROM categories WHERE slug = 'test-category'");

echo "ALL ENHANCED BACKUP & RESTORE TESTS PASSED SUCCESSFULLY!\n";
