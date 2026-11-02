<?php
require_once __DIR__ . '/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . get_dashboard_url());
    exit;
}

$error = '';
$fullName = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $branch = trim($_POST['branch'] ?? '');

    if (!verify_csrf_token($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 characters (letters, numbers, and underscores only).';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = get_db_connection();

        // Check if username or email is already taken
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $username, 'email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if (strtolower($existing['username']) === strtolower($username)) {
                $error = 'This username is already taken. Please choose another.';
            } else {
                $error = 'An account with this email already exists.';
            }
        } else {
            // Hash password and insert user with default role 'user' and selected branch
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $selectedBranch = !empty($branch) ? $branch : 'កំពង់ផែស្វយ័តភ្នំពេញ';
            $insert = $pdo->prepare('INSERT INTO users (full_name, username, email, password, role, branch) VALUES (:full_name, :username, :email, :password, :role, :branch)');
            
            $success = $insert->execute([
                'full_name' => $fullName,
                'username'  => $username,
                'email'     => $email,
                'password'  => $hashedPassword,
                'role'      => 'user',
                'branch'    => $selectedBranch
            ]);

            if ($success) {
                set_flash('success', 'Registration successful! You can now log in.');
                header('Location: login.php');
                exit;
            } else {
                $error = 'Failed to create your account. Please try again.';
            }
        }
    }
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - PPAP</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="brand-header">
            <div class="brand-icon" style="width: 72px; height: 72px; min-width: 72px; min-height: 72px; max-width: 72px; max-height: 72px; border-radius: 50%; background: #ffffff; padding: 3px; border: 2.5px solid #d97706; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(14, 42, 134, 0.25); overflow: hidden; box-sizing: border-box; margin-bottom: 14px;">
                <img src="assets/images/logo.png" alt="MPWT Logo" class="brand-logo-img" style="width: 66px; height: 66px; max-width: 66px; max-height: 66px; object-fit: contain; border-radius: 50%; display: block;">
            </div>
            <h1>Create Account</h1>
            <p>Join us today — it only takes a minute</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        placeholder="e.g. John Doe" 
                        value="<?= htmlspecialchars($fullName) ?>" 
                        required 
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="e.g. johndoe" 
                        value="<?= htmlspecialchars($username) ?>" 
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="e.g. john@example.com" 
                        value="<?= htmlspecialchars($email) ?>" 
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="branch">Operational Branch (សាខាប្រតិបត្តិការ)</label>
                <div class="input-container">
                    <select id="branch" name="branch" class="input-modern" style="width: 100%; border-radius: 8px;">
                        <?php foreach (get_ppap_branches() as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password (min 6 characters)</label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Create a strong password" 
                        minlength="6"
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

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-container">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Re-type password" 
                        minlength="6"
                        required
                    >
                    <button type="button" class="toggle-password" data-target="confirm_password" title="Toggle password visibility">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" style="margin-top: 10px;">
                <span>Register Account</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </form>

        <div class="auth-switch">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>

