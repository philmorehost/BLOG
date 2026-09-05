<?php
session_start();

$stage = isset($_GET['stage']) ? (int)$_GET['stage'] : 1;
$error = '';
$success = '';

if (file_exists(__DIR__ . '/../includes/config.php')) {
    include __DIR__ . '/../includes/config.php';
    if (defined('INSTALLED') && INSTALLED && $stage != 4) {
        // Check if database actually has site_settings table
        require_once __DIR__ . '/../includes/db.php';
        $conn = get_db_connection();
        $table_exists = false;
        if ($conn) {
            try {
                $count = $conn->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
                if ($count > 0) {
                    $table_exists = true;
                }
            } catch (Exception $e) {
                $table_exists = false;
            }
        }
        if ($table_exists) {
            header('Location: /');
            exit;
        }
    }
}

// Stage 2: Database installation
if ($stage == 2 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_type = $_POST['db_type'] ?? 'mysql';
    $host = $_POST['host'] ?? 'localhost';
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    $name = $_POST['name'] ?? 'gfw_db';

    try {
        if ($db_type == 'sqlite') {
            $pdo = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $schema = file_get_contents(__DIR__ . '/schema_sqlite.sql');
            $pdo->exec($schema);

            $_SESSION['db_config'] = [
                'type' => 'sqlite',
                'host' => 'localhost',
                'user' => '',
                'pass' => '',
                'name' => 'database.sqlite'
            ];
        } else {
            $pdo = new PDO("mysql:host=$host", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name` ");

            // Execute schema
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            $pdo->exec($schema);

            // Execute initial data
            $data = file_get_contents(__DIR__ . '/data.sql');
            $pdo->exec($data);

            $_SESSION['db_config'] = [
                'type' => 'mysql',
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'name' => $name
            ];
        }

        header('Location: ?stage=3');
        exit;
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Stage 3: Admin details
if ($stage == 3 && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_user = $_POST['admin_user'];
    $admin_email = $_POST['admin_email'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_BCRYPT);

    $db = $_SESSION['db_config'];

    try {
        if ($db['type'] == 'sqlite') {
            $pdo = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
        } else {
            $pdo = new PDO("mysql:host=".$db['host'].";dbname=".$db['name'], $db['user'], $db['pass']);
        }
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$admin_user, $admin_email, $admin_pass]);

        // Update admin email in settings
        $stmt = $pdo->prepare("UPDATE site_settings SET admin_email = ? WHERE id = 1");
        $stmt->execute([$admin_email]);

        // Generate config.php
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

        $db_type_val = $db['type'];
        $config_content = "<?php
define('DB_TYPE', '$db_type_val');
define('DB_HOST', '".$db['host']."');
define('DB_USER', '".$db['user']."');
define('DB_PASS', '".$db['pass']."');
define('DB_NAME', '".$db['name']."');
define('DB_FILE', __DIR__ . '/../database.sqlite');
define('SITE_URL', '".$base_url."');
define('INSTALLED', true);";
        file_put_contents(__DIR__ . '/../includes/config.php', trim($config_content));

        header('Location: ?stage=4');
        exit;
    } catch (PDOException $e) {
        $error = "Admin Creation Error: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Installer - Stage <?php echo $stage; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #05070a; color: #fff; font-family: 'Inter', sans-serif; padding-bottom: 50px; }
        .installer-card { background: #0a0e17; border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 40px; margin-top: 50px; }
        @media (max-width: 576px) {
            .installer-card { padding: 30px 20px; margin-top: 20px; }
        }
        .btn-primary { background-color: #ff3e3e; border: none; font-family: 'Barlow Condensed'; text-transform: uppercase; padding: 12px 30px; }
        .font-condensed { font-family: 'Barlow Condensed', sans-serif; text-transform: uppercase; }
        .text-electric-red { color: #ff3e3e; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="installer-card shadow-lg">
                <h1 class="font-condensed italic text-center mb-4">SYSTEM <span class="text-electric-red">INSTALLER</span></h1>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($stage == 1): ?>
                    <h3 class="font-condensed h5 mb-3 text-white-50">Stage 1: Welcome & Requirements</h3>
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between">
                            PHP Version (>= 7.4)
                            <span><?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '✅ ' . PHP_VERSION : '❌ ' . PHP_VERSION; ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between">
                            PDO Extension
                            <span><?php echo extension_loaded('pdo_mysql') ? '✅' : '❌'; ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between">
                            OpenSSL Extension
                            <span><?php echo extension_loaded('openssl') ? '✅' : '❌'; ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-white border-white border-opacity-10 d-flex justify-content-between">
                            Config Writable
                            <span><?php echo is_writable(__DIR__ . '/../includes/') ? '✅' : '❌'; ?></span>
                        </li>
                    </ul>
                    <a href="?stage=2" class="btn btn-primary w-100">Proceed to Database</a>

                <?php elseif ($stage == 2): ?>
                    <h3 class="font-condensed h5 mb-3 text-white-50">Stage 2: Database Configuration</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small uppercase font-black">Database Engine</label>
                            <select name="db_type" id="db_type" class="form-select bg-dark text-white border-secondary" onchange="toggleDBFields()">
                                <option value="mysql">MySQL (Recommended for cPanel)</option>
                                <option value="sqlite">SQLite (No configuration required)</option>
                            </select>
                        </div>
                        <div id="mysql_fields">
                            <div class="mb-3">
                                <label class="form-label text-white-50 small uppercase font-black">Database Host</label>
                                <input type="text" name="host" class="form-control bg-dark text-white border-secondary" value="localhost">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-white-50 small uppercase font-black">Database User</label>
                                <input type="text" name="user" class="form-control bg-dark text-white border-secondary">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-white-50 small uppercase font-black">Database Password</label>
                                <input type="password" name="pass" class="form-control bg-dark text-white border-secondary">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-white-50 small uppercase font-black">Database Name</label>
                                <input type="text" name="name" class="form-control bg-dark text-white border-secondary" value="gfw_db">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">Install Schema</button>
                    </form>
                    <script>
                    function toggleDBFields() {
                        const type = document.getElementById('db_type').value;
                        document.getElementById('mysql_fields').style.display = type === 'sqlite' ? 'none' : 'block';
                    }
                    </script>

                <?php elseif ($stage == 3): ?>
                    <h3 class="font-condensed h5 mb-3 text-white-50">Stage 3: Admin Account</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small uppercase font-black">Admin Username</label>
                            <input type="text" name="admin_user" class="form-control bg-dark text-white border-secondary" value="admin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small uppercase font-black">Admin Email</label>
                            <input type="email" name="admin_email" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small uppercase font-black">Admin Password</label>
                            <input type="password" name="admin_pass" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Complete Installation</button>
                    </form>

                <?php elseif ($stage == 4): ?>
                    <h3 class="font-condensed h5 mb-3 text-white-50 text-center">Stage 4: Congratulations!</h3>
                    <div class="text-center py-4">
                        <div class="display-1 text-success mb-4">✅</div>
                        <p class="lead">The system has been successfully installed on your server.</p>
                        <hr class="border-white border-opacity-10">
                        <div class="text-start small text-white-50">
                            <p><strong>Next steps:</strong></p>
                            <ul>
                                <li>Delete the <code>install</code> directory for security.</li>
                                <li>Login to the admin panel at <code>/admin/login</code>.</li>
                                <li>Configure your AI API keys in System Settings.</li>
                                <li>Set up your SMTP credentials for notifications.</li>
                            </ul>
                        </div>
                        <a href="/" class="btn btn-primary w-100 mt-4">Go to Website</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>