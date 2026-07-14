<?php

declare(strict_types=1);

/**
 * api/logout.php — Firebase-aware logout for Lawable.
 *
 * Two-pass strategy (invisible to the user):
 *   Pass 1: Serve a blank page whose JS signs out of Firebase, then
 *            immediately redirects back here with ?confirmed=1.
 *   Pass 2: Destroy the PHP session and redirect to login with a
 *            "Logged out successfully" flash message.
 *
 * The user sees: brief blank flash → login page with success message.
 * All 11 existing href="../api/logout.php" links work with no changes.
 */

require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

// ── Pass 2: PHP session destruction ───────────────────────────────────────
if (isset($_GET['confirmed'])) {
    logout_user();
    header('Location: /lawable/pages/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Logging out…</title>
    <!-- Blank page — Firebase signOut runs and redirects instantly -->
    <style>html,body{margin:0;background:#07111f;}</style>
</head>
<body>
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-app.js';
import { getAuth, signOut } from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-auth.js';

const app  = initializeApp({
    apiKey:            'AIzaSyDgZMSypU6KKt8t_NbcpRtgQDttjV4JXtw',
    authDomain:        'lawable-9c1e0.firebaseapp.com',
    projectId:         'lawable-9c1e0',
    storageBucket:     'lawable-9c1e0.firebasestorage.app',
    messagingSenderId: '222866591543',
    appId:             '1:222866591543:web:7b1e55f74c9e15bb7294b2',
});

try { await signOut(getAuth(app)); } catch (_) {}

window.location.replace('/lawable/api/logout.php?confirmed=1');
</script>
</body>
</html>
