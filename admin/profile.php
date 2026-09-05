<?php
admin_header("Operator");

$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed");
    }
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);

    $bio = sanitize($_POST['bio']);
    $tw = sanitize($_POST['twitter_url']);
    $li = sanitize($_POST['linkedin_url']);

    // Handle Avatar Upload
    $avatar_path = upload_image($_FILES['avatar'], 'uploads/avatars/');
    $avatar_sql = "";
    $avatar_params = [];
    if ($avatar_path) {
        $avatar_sql = ", avatar = ?";
        $avatar_params[] = $avatar_path;
    }

    if (!empty($_POST['new_password'])) {
        $pass = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        $sql = "UPDATE users SET username = ?, email = ?, password = ?, bio = ?, twitter_url = ?, linkedin_url = ? $avatar_sql WHERE id = ?";
        $params = array_merge([$username, $email, $pass, $bio, $tw, $li], $avatar_params, [$user_id]);
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "UPDATE users SET username = ?, email = ?, bio = ?, twitter_url = ?, linkedin_url = ? $avatar_sql WHERE id = ?";
        $params = array_merge([$username, $email, $bio, $tw, $li], $avatar_params, [$user_id]);
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
    }
    $success = "Operator credentials updated.";
    $_SESSION['username'] = $username;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

?>
<h1 class="font-condensed fw-black italic text-white display-5 mb-5">OPERATOR <span class="text-danger">PROFILE</span></h1>

<?php if (isset($success)): ?>
    <div class="alert alert-success bg-green-900 bg-opacity-10 border-green-500 border-opacity-20 text-green-500 font-condensed italic uppercase mb-5"><?php echo $success; ?></div>
<?php endif; ?>

<div class="bg-[#0a0e17] rounded-3xl border border-white/5 p-5 p-md-5 shadow-2xl">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="row g-4">
            <div class="col-md-6">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Operator ID</label>
                <input type="text" name="username" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $me['username']; ?>" required>
            </div>
            <div class="col-md-6">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Secure Email</label>
                <input type="email" name="email" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $me['email']; ?>" required>
            </div>
            <div class="col-md-12">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Professional Bio (E-E-A-T Signal)</label>
                <textarea name="bio" rows="4" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold"><?php echo $me['bio']; ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">X (Twitter) URL</label>
                <input type="url" name="twitter_url" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $me['twitter_url']; ?>">
            </div>
            <div class="col-md-6">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">LinkedIn URL</label>
                <input type="url" name="linkedin_url" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" value="<?php echo $me['linkedin_url']; ?>">
            </div>
            <div class="col-md-6">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">Operator Avatar</label>
                <input type="file" name="avatar" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold">
                <?php if (!empty($me['avatar'])): ?>
                    <img src="<?php echo $me['avatar']; ?>" class="mt-3 rounded-full w-20 h-20 object-cover border-2 border-electric-red">
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="block text-[10px] font-black uppercase text-gray-500 mb-2">New Security Cipher</label>
                <input type="password" name="new_password" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white font-bold" placeholder="Leave blank to retain current">
            </div>
        </div>
        <button type="submit" class="mt-8 bg-danger text-white px-10 py-3 rounded-2xl font-black uppercase italic tracking-widest hover:bg-white hover:text-danger transition-all">Synchronize Profile</button>
    </form>
</div>

<?php admin_footer();