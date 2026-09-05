<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    redirect('/admin/login');
}

$settings = get_settings();
if (!$settings['pin_enabled']) {
    $_SESSION['pin_verified'] = true;
    $_SESSION['pin_verified_at'] = time();
    redirect('/admin/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "CSRF TOKEN INVALID.";
    } else {
        $pin = $_POST['pin'];
        if (password_verify($pin, $settings['admin_pin'])) {
            $_SESSION['pin_verified'] = true;
            $_SESSION['pin_verified_at'] = time();
            redirect('/admin/');
        } else {
            $error = "INVALID SECURITY PIN. ACCESS DENIED.";
            log_activity("Failed admin PIN attempt.");
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['name'] ?? 'Football Intelligence'; ?> | SECURITY VERIFICATION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #05070a; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; padding: 20px; }
        .pin-card { background: #0a0e17; border: 1px solid rgba(255,255,255,0.05); padding: 40px; border-radius: 30px; width: 100%; max-width: 400px; }
        @media (max-width: 576px) {
            .pin-card { padding: 30px 20px; }
        }
        .font-condensed { font-family: 'Barlow Condensed'; text-transform: uppercase; }
        .btn-primary { background: #ff3e3e; border: none; font-weight: 900; letter-spacing: 2px; }
    </style>
</head>
<body>
    <div class="pin-card shadow-2xl text-center">
        <div class="text-center mb-4">
            <?php if (!empty($settings['logo'])): ?>
                <img src="<?php echo $settings['logo']; ?>" alt="Logo" style="max-height: 120px;" class="d-inline-block">
            <?php else: ?>
                <h1 class="font-condensed italic text-white mb-0"><?php echo format_site_title($settings['name'] ?? 'FootballIntelligence', 'text-danger'); ?></h1>
            <?php endif; ?>
        </div>
        <h1 class="font-condensed italic text-white mb-2">SECURITY <span class="text-danger">PIN</span></h1>
        <p class="text-white-50 small uppercase tracking-widest mb-5">Enter your secondary authorization cipher</p>

        <?php if ($error): ?>
            <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-20 text-danger small font-bold italic"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="pinForm">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="mb-4">
                <input type="password" name="pin" id="pinInput" class="form-control bg-black border-white border-opacity-10 text-white rounded-xl py-3 text-center tracking-[1em] fs-4" placeholder="••••" required autofocus maxlength="10" inputmode="numeric" pattern="[0-9]*">
            </div>

            <!-- Digital Keypad -->
            <div class="pin-keypad mb-5">
                <div class="row g-2">
                    <?php for($i=1; $i<=9; $i++): ?>
                        <div class="col-4"><button type="button" class="btn btn-dark w-100 py-3 fw-bold keypad-btn" data-val="<?php echo $i; ?>"><?php echo $i; ?></button></div>
                    <?php endfor; ?>
                    <div class="col-4"><button type="button" class="btn btn-outline-danger w-100 py-3 fw-bold keypad-clear">CLR</button></div>
                    <div class="col-4"><button type="button" class="btn btn-dark w-100 py-3 fw-bold keypad-btn" data-val="0">0</button></div>
                    <div class="col-4"><button type="button" class="btn btn-outline-secondary w-100 py-3 fw-bold keypad-del"><i class="bi bi-backspace"></i></button></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-3 rounded-xl font-condensed italic">VERIFY IDENTITY</button>
        </form>

        <script>
            document.querySelectorAll('.keypad-btn').forEach(btn => {
                btn.onclick = function() {
                    const input = document.getElementById('pinInput');
                    if (input.value.length < 10) {
                        input.value += this.dataset.val;
                    }
                };
            });
            document.querySelector('.keypad-clear').onclick = function() {
                document.getElementById('pinInput').value = '';
            };
            document.querySelector('.keypad-del').onclick = function() {
                const input = document.getElementById('pinInput');
                input.value = input.value.slice(0, -1);
            };
            // Prevent non-numeric input
            document.getElementById('pinInput').onkeydown = function(e) {
                if (e.key.length === 1 && !/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            };
        </script>
    </div>
</body>
</html>