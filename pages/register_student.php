<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
start_secure_session();
// Already logged in? Go to dashboard
if (is_logged_in()) { redirect('pages/dashboard.php'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Student Account — Lawable</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #07111f;
            color: #f7f7f2;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
            background-image: radial-gradient(ellipse at 20% 50%, rgba(99,102,241,0.12) 0%, transparent 60%),
                              radial-gradient(ellipse at 80% 20%, rgba(242,201,76,0.08) 0%, transparent 50%);
        }
        .card {
            width: min(100%, 520px);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 2.5rem;
            border-radius: 24px;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .logo { font-size: 1.5rem; font-weight: 700; color: #f2c94c; margin-bottom: 1.5rem; }
        h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.4rem; }
        .subtitle { color: rgba(247,247,242,0.55); font-size: 0.9rem; margin-bottom: 1.8rem; }
        .field { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1.1rem; }
        label { font-size: 0.875rem; font-weight: 600; color: rgba(247,247,242,0.85); }
        input {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05);
            color: #fff;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        input:focus { outline: none; border-color: #f2c94c; box-shadow: 0 0 0 3px rgba(242,201,76,0.15); }
        input::placeholder { color: rgba(255,255,255,0.25); }
        button[type="submit"] {
            width: 100%;
            background: linear-gradient(135deg, #f2c94c, #e6b800);
            color: #07111f;
            border: 0;
            padding: 0.95rem 1rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 1rem;
            font-family: inherit;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: opacity 0.2s, transform 0.15s;
        }
        button[type="submit"]:hover { opacity: 0.9; transform: translateY(-1px); }
        button[type="submit"]:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .alert {
            padding: 0.85rem 1rem;
            margin-bottom: 1.2rem;
            border-radius: 12px;
            font-size: 0.9rem;
            display: none;
        }
        .alert.show { display: block; }
        .alert-error { background: rgba(127,29,29,0.7); border: 1px solid rgba(220,38,38,0.4); }
        .alert-success { background: rgba(20,83,45,0.7); border: 1px solid rgba(34,197,94,0.4); }
        .links { margin-top: 1.2rem; font-size: 0.875rem; color: rgba(247,247,242,0.5); }
        .links a { color: #f2c94c; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }
        .spinner {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid rgba(7,17,31,0.3);
            border-top-color: #07111f;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
            display: none;
        }
        .spinner.show { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">⚖️ Lawable</div>
    <h1>Create a Student Account</h1>
    <p class="subtitle">Register to access courses, exams, and mentorship.</p>

    <div class="alert alert-error" id="alertError"></div>
    <div class="alert alert-success" id="alertSuccess"></div>

    <form id="registerForm">
        <div class="field">
            <label for="name">Full Name</label>
            <input id="name" name="name" type="text" required placeholder="Your full name" autocomplete="name" />
        </div>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required placeholder="e.g. lawstudent01" autocomplete="username" />
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required placeholder="you@example.com" autocomplete="email" />
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required placeholder="At least 8 characters" autocomplete="new-password" minlength="8" />
        </div>
        <div class="field">
            <label for="phone">Phone <span style="opacity:0.45;">(optional)</span></label>
            <input id="phone" name="phone" type="tel" placeholder="Optional phone number" autocomplete="tel" />
        </div>
        <button type="submit" id="submitBtn">
            <span class="spinner" id="spinner"></span>
            Create Account
        </button>
    </form>

    <div class="links">
        Already have an account? <a href="/lawable/api/login.php">Log in</a><br>
        Register as an <a href="/lawable/api/register_organization.php">Organization</a>
    </div>
</div>

<!-- Firebase JS SDK (v9 modular via CDN) -->
<script type="module">
import { initializeApp }                          from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-app.js';
import { getAuth, createUserWithEmailAndPassword } from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-auth.js';

const firebaseConfig = {
    apiKey:            'AIzaSyDgZMSypU6KKt8t_NbcpRtgQDttjV4JXtw',
    authDomain:        'lawable-9c1e0.firebaseapp.com',
    projectId:         'lawable-9c1e0',
    storageBucket:     'lawable-9c1e0.firebasestorage.app',
    messagingSenderId: '222866591543',
    appId:             '1:222866591543:web:7b1e55f74c9e15bb7294b2',
};

const app  = initializeApp(firebaseConfig);
const auth = getAuth(app);

const form      = document.getElementById('registerForm');
const errBox    = document.getElementById('alertError');
const okBox     = document.getElementById('alertSuccess');
const submitBtn = document.getElementById('submitBtn');
const spinner   = document.getElementById('spinner');

function showError(msg) {
    errBox.textContent = msg;
    errBox.classList.add('show');
    okBox.classList.remove('show');
}
function showSuccess(msg) {
    okBox.textContent = msg;
    okBox.classList.add('show');
    errBox.classList.remove('show');
}
function setLoading(on) {
    submitBtn.disabled = on;
    spinner.classList.toggle('show', on);
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errBox.classList.remove('show');
    okBox.classList.remove('show');

    const name     = document.getElementById('name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const phone    = document.getElementById('phone').value.trim();

    if (!name || !username || !email || !password) {
        showError('Please fill in all required fields.');
        return;
    }

    setLoading(true);

    try {
        // Step 1 — Create user in Firebase Auth
        const credential = await createUserWithEmailAndPassword(auth, email, password);
        const idToken    = await credential.user.getIdToken();

        // Step 2 — Send token + profile fields to PHP
        const res = await fetch('/lawable/api/register_user.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ idToken, name, username, phone }),
        });

        const data = await res.json();

        if (data.success) {
            showSuccess(data.message + ' Redirecting to login…');
            setTimeout(() => { window.location.href = '/lawable/api/login.php'; }, 2000);
        } else {
            // PHP rejected — delete the Firebase account we just created
            // so the user can try again cleanly
            await credential.user.delete();
            showError(data.message || 'Registration failed. Please try again.');
        }
    } catch (err) {
        // Firebase Auth errors (weak password, email in use, etc.)
        const friendlyErrors = {
            'auth/email-already-in-use':    'This email address is already registered.',
            'auth/weak-password':           'Password must be at least 6 characters.',
            'auth/invalid-email':           'Please enter a valid email address.',
            'auth/network-request-failed':  'Network error. Please check your connection.',
        };
        showError(friendlyErrors[err.code] || err.message || 'Registration failed.');
    } finally {
        setLoading(false);
    }
});
</script>
</body>
</html>
