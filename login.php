<?php
require_once __DIR__ . '/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . get_dashboard_url());
    exit;
}

$error = '';
$usernameOrEmail = '';

// Check for logout query param
if (isset($_GET['logout'])) {
    set_flash('success', 'You have been logged out successfully.');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!verify_csrf_token($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($usernameOrEmail) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute([
            'username' => $usernameOrEmail,
            'email'    => $usernameOrEmail
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Prevent Session Fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            set_flash('success', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '! (' . htmlspecialchars(get_role_label($user['role'])) . ')');
            
            // Redirect to role-specific dashboard
            header('Location: ' . get_dashboard_url($user['role']));
            exit;
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}

$flash = get_flash();
$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login_btn') ?> - PPAP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
     <style>
       
        body {
            font-family: 'Kantumruy Pro', 'Khmer OS', 'Khmer OS Battambang', sans-serif;
            
        }
        </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
            <?php render_language_switcher('compact'); ?>
        </div>

        <div class="brand-header">
            <div class="brand-icon" style="width: 72px; height: 72px; min-width: 72px; min-height: 72px; max-width: 72px; max-height: 72px; border-radius: 50%; background: #ffffff; padding: 3px; border: 2.5px solid #d97706; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(14, 42, 134, 0.25); overflow: hidden; box-sizing: border-box; margin-bottom: 14px;">
                <img src="assets/images/logo.png" alt="MPWT Logo" class="brand-logo-img" style="width: 66px; height: 66px; max-width: 66px; max-height: 66px; object-fit: contain; border-radius: 50%; display: block;">
            </div>
            <h1><?= t('login_welcome') ?></h1>
            <p><?= t('login_subtitle') ?></p>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="username_or_email"><?= t('login_username_email') ?></label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <input 
                        type="text" 
                        id="username_or_email" 
                        name="username_or_email" 
                        placeholder="<?= t('login_username_ph') ?>" 
                        value="<?= htmlspecialchars($usernameOrEmail) ?>" 
                        required 
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password"><?= t('login_password') ?></label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="<?= t('login_password_ph') ?>" 
                        required
                    >
                    <button type="button" class="toggle-password" data-target="password" title="Toggle password visibility">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-actions-row">
                <label class="remember-label">
                    <input type="checkbox" name="remember" id="remember">
                    <span><?= t('login_remember') ?></span>
                </label>
                <a href="#" class="forgot-link" onclick="alert('Password reset link can be sent to your registered email.')"><?= is_khmer() ? 'ភ្លេចពាក្យសម្ងាត់?' : 'Forgot password?' ?></a>
            </div>

            <button type="submit" class="btn-submit">
                <span><?= t('login_btn') ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </form>

        <div class="auth-switch">
            <?= t('login_no_account') ?> <a href="register.php"><?= t('login_register_here') ?></a>
        </div>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
