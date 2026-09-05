<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

if (is_admin()) {
    redirect('/admin/');
}

$error = '';
$success = '';
$view = $_GET['view'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF TOKEN INVALID.";
    } else {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];

        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                log_activity("Admin login successful: " . $username);

                $settings = get_settings();
                if (!empty($settings['pin_enabled'])) {
                    redirect('/admin/pin_verify');
                } else {
                    $_SESSION['pin_verified'] = true;
                    $_SESSION['pin_verified_at'] = time();
                    redirect('/admin/');
                }
            } else {
                $error = "INVALID CREDENTIALS. SYSTEM SECURE.";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request'])) {
    $email = sanitize($_POST['id_val']);
    $pin = $_POST['pin'] ?? '';
    $conn = get_db_connection();
    $settings = get_settings();

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && !empty($settings['admin_pin']) && password_verify($pin, $settings['admin_pin'])) {
        $_SESSION['reset_user_id'] = $user['id'];
        $view = 'reset_form';
    } else {
        $error = "IDENTITY NOT RECOGNIZED OR PIN INVALID.";
        log_activity("Failed password reset attempt for: " . $email);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_reset'])) {
    $user_id = $_SESSION['reset_user_id'] ?? null;
    $new_pass = $_POST['new_password'];

    if ($user_id && !empty($new_pass)) {
        $conn = get_db_connection();
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed, $user_id])) {
            $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $settings = get_settings();
            $subject = "Password Changed for " . $settings['name'];
            $msg = "Password has been changed for the admin user: <strong>[" . $user['username'] . "]</strong>. If you are not the one that initiated this change, please login to cPanel immediately to stop the abuse of your website.";
            $html = render_email_template("<p>$msg</p>", "Security Alert: Password Changed");
            send_mail($user['email'], $subject, $html);

            $success = "CIPHER UPDATED. ACCESS GRANTED.";
            $view = 'login';
            unset($_SESSION['reset_user_id']);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['name'] ?? 'Football Intelligence'; ?> | CORE ACCESS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #05070a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; padding: 20px; }
        .login-card { background: #0a0e17; border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 30px; width: 100%; max-width: 450px; }
        @media (max-width: 576px) {
            .login-card { padding: 30px 20px; }
        }
        .font-condensed { font-family: 'Barlow Condensed'; text-transform: uppercase; }
        .btn-primary { background: #ff3e3e; border: none; font-weight: 900; letter-spacing: 2px; }
    </style>
</head>
<body>
    <div class="login-card shadow-2xl">
        <div class="text-center mb-4">
            <?php if (!empty($settings['logo'])): ?>
                <img src="<?php echo $settings['logo']; ?>" alt="Logo" style="max-height: 120px;" class="d-inline-block">
            <?php else: ?>
                <h1 class="font-condensed italic text-white mb-0"><?php echo format_site_title($settings['name'] ?? 'FootballIntelligence', 'text-danger'); ?></h1>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-20 text-danger small font-bold italic mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success bg-green-900 bg-opacity-10 border-green-500 border-opacity-20 text-green-500 small font-bold italic mb-4"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if ($view == 'login'): ?>
            <p class="text-center text-white-50 small uppercase tracking-widest mb-5">Identify yourself to the network</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="mb-4">
                    <label class="form-label text-white-50 small uppercase font-black">Operator ID</label>
                    <input type="text" name="username" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl py-3" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-white-50 small uppercase font-black">Security Cipher</label>
                    <input type="password" name="password" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl py-3" required>
                </div>
                <div class="mb-5 text-end">
                    <a href="?view=forgot" class="text-danger small font-condensed italic uppercase fw-bold text-decoration-none">Forgot Cipher?</a>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100 py-3 rounded-xl font-condensed italic">AUTHORIZE ACCESS</button>
            </form>

        <?php elseif ($view == 'forgot'): ?>
            <p class="text-center text-white-50 small uppercase tracking-widest mb-5">Verify your identity</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="mb-4">
                    <label class="form-label text-white-50 small uppercase font-black">Registered Email</label>
                    <input type="email" name="id_val" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl py-3" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-white-50 small uppercase font-black">Security PIN</label>
                    <input type="password" name="pin" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl py-3" required inputmode="numeric" pattern="[0-9]*">
                </div>
                <button type="submit" name="reset_request" class="btn btn-primary w-100 py-3 rounded-xl font-condensed italic mb-3">REQUEST RESET</button>
                <a href="?view=login" class="btn btn-link w-100 text-white-50 font-condensed italic text-decoration-none">CANCEL</a>
            </form>

        <?php elseif ($view == 'reset_form'): ?>
            <p class="text-center text-white-50 small uppercase tracking-widest mb-5">Set New Security Cipher</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="mb-5">
                    <label class="form-label text-white-50 small uppercase font-black">New Password</label>
                    <input type="password" name="new_password" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl py-3" required>
                </div>
                <button type="submit" name="complete_reset" class="btn btn-primary w-100 py-3 rounded-xl font-condensed italic">UPDATE CIPHER</button>
            </form>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="/" class="text-white-50 small text-decoration-none hover:text-white transition-all uppercase font-black italic tracking-widest">Back to Broadcast</a>
        </div>
    </div>
</body>
</html>