<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$isAjax) {
            verify_csrf_token($_POST['csrf_token'] ?? '');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($name === '' || $username === '' || $email === '' || $password === '') {
            throw new RuntimeException('Please fill in all required fields.');
        }

        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            throw new RuntimeException('Username may only contain letters, numbers, dots, underscores, or hyphens.');
        }

        if (!is_valid_email($email)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters long.');
        }

        $turnstileToken = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
        if (!verify_turnstile_token($turnstileToken)) {
            throw new RuntimeException('Please complete the CAPTCHA challenge.');
        }

        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id FROM students WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute([':email' => $email, ':username' => $username]);

        if ($stmt->fetch()) {
            throw new RuntimeException('An account already exists for that username or email address.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO students (name, username, email, password_hash, phone, status) VALUES (:name, :username, :email, :password_hash, :phone, :status)');
        $stmt->execute([
            ':name' => $name,
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':phone' => $phone ?: null,
            ':status' => 'active',
        ]);

        if ($isAjax) {
            json_response(['success' => true, 'message' => 'Student registration successful. You can now log in.']);
        }

        $success = 'Student registration successful. You can now log in.';
    } catch (RuntimeException $exception) {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            json_response(['success' => false, 'message' => $exception->getMessage()], 400);
        }
        $errors[] = $exception->getMessage();
    }
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    if ($success) {
        json_response(['success' => true, 'message' => $success]);
    }
    json_response(['success' => false, 'message' => $errors[0] ?? 'Registration failed.'], 400);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register as Student — Lawable</title>
    <link rel="stylesheet" href="../../assets/css/lawable.css" />
    <style>
        body { font-family: Inter, sans-serif; background: #07111f; color: #f7f7f2; margin: 0; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        .card { width: min(100%, 520px); background: rgba(255,255,255,0.08); padding: 2rem; border-radius: 24px; backdrop-filter: blur(16px); }
        .field { display:flex; flex-direction:column; gap:0.35rem; margin-bottom: 1rem; }
        label { font-weight:600; }
        input, select { padding:0.8rem 0.95rem; border-radius:12px; border:1px solid rgba(255,255,255,0.16); background:#10233f; color:#fff; }
        button { background:#f2c94c; color:#07111f; border:0; padding:0.9rem 1rem; border-radius:999px; font-weight:700; cursor:pointer; }
        .alert { padding:0.8rem 1rem; margin-bottom:1rem; border-radius:12px; }
        .alert-error { background:#7f1d1d; }
        .alert-success { background:#14532d; }
        a { color:#f2c94c; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>Create a Student Account</h1>
        <p>Register to access courses, exams, and mentorship.</p>

        <?php if ($errors): foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" action="register_user.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

            <div class="field">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" required placeholder="Enter your full name" />
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" required placeholder="Choose a username, e.g. lawstudent01" />
            </div>

            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required placeholder="Enter your email, e.g. you@example.com" />
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required placeholder="At least 8 characters" />
            </div>

            <div class="field">
                <label for="phone">Phone (optional)</label>
                <input id="phone" name="phone" type="tel" placeholder="Optional phone number" />
            </div>

            <button type="submit">Create Account</button>
        </form>

        <p style="margin-top:1rem;">
            Already have an account? <a href="login.php">Login</a><br />
            Register as an <a href="register_organization.php">Organization</a>
        </p>
    </div>
</main>
</body>
</html>
