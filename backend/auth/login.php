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

        $identifier = trim((string) ($_POST['username_or_email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'user');

        if ($identifier === '' || $password === '') {
            throw new RuntimeException('Please enter both your username/email and password.');
        }

        if (strlen($identifier) < 3) {
            throw new RuntimeException('Please enter a valid username or email address.');
        }

        if (str_contains($identifier, '@') && !is_valid_email($identifier)) {
            throw new RuntimeException('Please enter a valid username or email address.');
        }

        $turnstileToken = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
        if (!verify_turnstile_token($turnstileToken)) {
            throw new RuntimeException('Please complete the CAPTCHA challenge.');
        }

        $pdo = get_pdo();

        $table = match ($role) {
            'user' => 'students',
            'organization' => 'organizations',
            'admin' => 'admins',
            default => throw new RuntimeException('Invalid account type selected.'),
        };

        $stmt = match ($role) {
            'organization' => $pdo->prepare("SELECT id, organization_name, contact_person AS name, username, email, password_hash, phone, status FROM {$table} WHERE (email = :email OR username = :username) LIMIT 1"),
            'admin' => $pdo->prepare("SELECT id, name AS name, username, email, password_hash, '' AS phone, status FROM {$table} WHERE (email = :email OR username = :username) LIMIT 1"),
            default => $pdo->prepare("SELECT id, name, username, email, password_hash, phone, status FROM {$table} WHERE (email = :email OR username = :username) LIMIT 1"),
        };

        $stmt->execute([':email' => $identifier, ':username' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid username/email, password, or account type.');
        }

        if (($user['status'] ?? '') !== 'active') {
            throw new RuntimeException('This account is inactive.');
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $role,
            'phone' => $user['phone'] ?? '',
        ];

        if ($role === 'organization') {
            $_SESSION['user']['organization_name'] = $user['organization_name'] ?? '';
        }

        // Determine redirect based on role
$redirectUrl = match ($role) {
            'user' => 'pages/user-dashboard.php',
            'admin' => 'pages/teacher-dashboard.php',
            default => 'home.php',
        };

        if ($isAjax) {
            json_response([
                'success' => true,
                'message' => 'Login successful.',
'redirect' => '/lawable/' . $redirectUrl,
            ]);
        }

        $success = 'Login successful.';
        redirect($redirectUrl);
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
            json_response(['success' => true, 'message' => $success, 'redirect' => '/lawable/home.php']);
        }
    json_response(['success' => false, 'message' => $errors[0] ?? 'Login failed.'], 400);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — Lawable</title>
    <link rel="stylesheet" href="../../assets/css/lawable.css" />
    <style>
        body { font-family: Inter, sans-serif; background: #07111f; color: #f7f7f2; margin: 0; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        .card { width: min(100%, 500px); background: rgba(255,255,255,0.08); padding: 2rem; border-radius: 24px; backdrop-filter: blur(16px); }
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
        <h1>Welcome back</h1>
        <p>Log in to access your Lawable dashboard.</p>

        <?php if ($errors): foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

            <div class="field">
                <label for="role">Account type</label>
                <select id="role" name="role" required>
                    <option value="user">Student</option>
                    <option value="organization">Organization</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="field">
                <label for="username_or_email">Username or email</label>
                <input id="username_or_email" name="username_or_email" type="text" required placeholder="Enter your username or email, e.g. lawuser01 or you@example.com" />
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required placeholder="Enter your password" />
            </div>

            <button type="submit">Login</button>
        </form>

        <p style="margin-top:1rem;">
            New student? <a href="register_user.php">Create student account</a><br />
            New organization? <a href="register_organization.php">Create organization account</a>
        </p>
    </div>
</main>
</body>
</html>
