<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

// If already logged in, redirect to home
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$turnstileSiteKey = get_turnstile_site_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink:      #1C1B17;
      --ink-mid:  #5B5A52;
      --ink-soft: #8C8A7E;
      --gold:     #C9933A;
      --gold-dk:  #A8732A;
      --cream:    #FBF7EF;
      --paper:    #FFFFFF;
      --border:   #E6E0D2;
      --sky:      #F1ECDD;
      --earth:    #B98B5E;
      --shadow:   0 18px 50px rgba(28,27,23,0.10);
      --red:      #C0604A;
      --green:    #4A7C59;

      --display: 'Playfair Display', serif;
      --body:    'Inter', sans-serif;
    }

    * , *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }

    body {
      font-family: var(--body);
      background: var(--cream);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1.5rem;
      -webkit-font-smoothing: antialiased;
    }

    .auth-shell {
      width: min(100%, 1080px);
      min-height: 640px;
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      background: var(--paper);
      border-radius: 28px;
      overflow: hidden;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
    }

    .auth-scene {
      position: relative;
      background: linear-gradient(180deg, var(--sky) 0%, #EFE7D2 60%, #E9DFC6 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 2.5rem;
      overflow: hidden;
    }

    .scene-brand {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      position: relative;
      z-index: 3;
    }
    .scene-brand-mark {
      width: 34px; height: 34px; border-radius: 9px;
      background: var(--ink);
      display: flex; align-items: center; justify-content: center;
      color: var(--gold); font-family: var(--display); font-weight: 700; font-size: 1.05rem;
    }
    .scene-brand-name {
      font-family: var(--display); font-weight: 700; font-size: 1.25rem; color: var(--ink);
      letter-spacing: -0.01em;
    }
    .scene-brand-name span { color: var(--gold); }

    .scene-illustration {
      position: relative;
      flex: 1;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      margin: 1rem 0;
    }
    .scene-illustration svg {
      width: 100%;
      max-width: 460px;
      height: auto;
      display: block;
    }

    .scene-caption {
      position: relative;
      z-index: 3;
      max-width: 380px;
    }
    .scene-caption h2 {
      font-family: var(--display);
      font-weight: 700;
      font-size: 1.5rem;
      line-height: 1.3;
      margin: 0 0 0.6rem;
      color: var(--ink);
    }
    .scene-caption p {
      font-size: 0.92rem;
      line-height: 1.65;
      color: var(--ink-mid);
      margin: 0;
    }

    .auth-form-area {
      padding: 3.25rem 3rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1.5rem;
    }

    .form-head h1 {
      font-family: var(--display);
      font-weight: 700;
      font-size: 1.9rem;
      margin: 0 0 0.4rem;
      letter-spacing: -0.01em;
      color: var(--ink);
    }
    .form-head p {
      margin: 0;
      font-size: 0.92rem;
      color: var(--ink-soft);
      line-height: 1.45;
    }

    .field { display: flex; flex-direction: column; gap: 0.35rem; }
    .field label { font-size: 0.82rem; font-weight: 600; color: var(--ink-mid); }

    .field input {
      width: 100%;
      border: 1.5px solid var(--border);
      background: var(--cream);
      color: var(--ink);
      border-radius: 12px;
      padding: 0.85rem 1rem;
      font-size: 0.92rem;
      font-family: var(--body);
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .field input::placeholder { color: var(--ink-soft); }
    .field input:focus {
      border-color: var(--gold);
      background: var(--paper);
      box-shadow: 0 0 0 3px rgba(201,147,58,0.14);
    }

    .turnstile-wrap {
      display: flex;
      align-items: center;
      min-height: 70px;
    }
    .field-status {
      font-size: 0.78rem;
      color: var(--ink-soft);
      min-height: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }

    .btn-primary {
      background: var(--ink);
      color: var(--paper);
      border: 0;
      padding: 0.95rem 1rem;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.95rem;
      font-family: var(--body);
      cursor: pointer;
      text-align: center;
      transition: background .2s, transform .15s;
    }
    .btn-primary:hover { background: var(--gold-dk); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

    .back-link {
      display: block;
      text-align: center;
      font-size: 0.88rem;
      color: var(--gold-dk);
      font-weight: 600;
      text-decoration: none;
      margin-top: 0.5rem;
    }
    .back-link:hover { text-decoration: underline; }

    #toast {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      max-width: 380px;
      padding: 1rem 1.25rem;
      border-radius: 14px;
      background: var(--paper);
      box-shadow: 0 12px 40px rgba(0,0,0,0.15);
      border-left: 4px solid var(--gold);
      font-size: 0.9rem;
      z-index: 9999;
      display: none;
      animation: slideIn .3s ease;
    }
    #toast.error { border-left-color: var(--red); }
    #toast.success { border-left-color: var(--green); }
    @keyframes slideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }

    @media (max-width: 880px) {
      .auth-shell { grid-template-columns: 1fr; min-height: auto; }
      .auth-scene { min-height: 280px; padding: 1.75rem; }
      .scene-caption { display: none; }
      .auth-form-area { padding: 2.25rem 1.5rem; }
    }
  </style>
</head>
<body>

  <div id="toast"></div>

  <div class="auth-shell">

    <!-- ═══ LEFT: ILLUSTRATION ═══ -->
    <div class="auth-scene">
      <div class="scene-brand">
        <div class="scene-brand-mark">L</div>
        <div class="scene-brand-name">Law<span>able</span></div>
      </div>

      <div class="scene-illustration">
        <svg viewBox="0 0 460 420" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="A student sitting and reading under a tree">
          <ellipse cx="230" cy="378" rx="190" ry="26" fill="#E3D9BD" opacity="0.6"/>
          <path d="M0 360 Q 120 330 230 350 T 460 355 L 460 420 L 0 420 Z" fill="#E7DFC9"/>
          <ellipse cx="170" cy="372" rx="120" ry="14" fill="#CDBE96" opacity="0.45"/>
          <path d="M168 372 C 164 320 160 270 172 222 C 176 206 168 196 174 182" stroke="#8B6F47" stroke-width="14" fill="none" stroke-linecap="round"/>
          <path d="M172 250 C 150 240 140 222 146 206" stroke="#8B6F47" stroke-width="9" fill="none" stroke-linecap="round"/>
          <path d="M172 230 C 196 218 206 198 200 180" stroke="#8B6F47" stroke-width="9" fill="none" stroke-linecap="round"/>
          <g>
            <circle cx="110" cy="150" r="62" fill="#9FAE8B" opacity="0.9"/>
            <circle cx="190" cy="110" r="76" fill="#93A580"/>
            <circle cx="255" cy="155" r="64" fill="#9FAE8B" opacity="0.9"/>
            <circle cx="150" cy="190" r="58" fill="#A9B998" opacity="0.85"/>
            <circle cx="220" cy="195" r="50" fill="#A9B998" opacity="0.8"/>
          </g>
          <g fill="#F1ECDD" opacity="0.55">
            <circle cx="130" cy="130" r="5"/>
            <circle cx="205" cy="95" r="4"/>
            <circle cx="245" cy="140" r="6"/>
            <circle cx="170" cy="170" r="4"/>
            <circle cx="95" cy="165" r="3.5"/>
          </g>
          <g fill="#C9933A" opacity="0.7">
            <ellipse cx="320" cy="365" rx="7" ry="4" transform="rotate(20 320 365)"/>
            <ellipse cx="60" cy="358" rx="6" ry="3.5" transform="rotate(-15 60 358)"/>
            <ellipse cx="350" cy="340" rx="5" ry="3" transform="rotate(40 350 340)"/>
          </g>
          <g transform="translate(150 290)">
            <ellipse cx="40" cy="78" rx="58" ry="10" fill="#CDBE96" opacity="0.4"/>
            <path d="M-6 60 Q 18 78 46 68 Q 70 60 84 64 Q 70 78 40 80 Q 6 80 -10 64 Z" fill="#D8C9A6"/>
            <path d="M2 8 C -8 8 -16 22 -14 38 L -10 64 C 6 72 56 72 70 62 L 64 34 C 62 16 50 6 36 6 Z" fill="#C9933A"/>
            <path d="M0 26 C -10 34 -12 44 -6 50 L 18 52 L 16 38 Z" fill="#C9933A"/>
            <path d="M62 24 C 72 32 74 42 68 48 L 44 50 L 46 36 Z" fill="#C9933A"/>
            <g transform="translate(14 38)">
              <path d="M0 10 C 10 2 22 2 28 8 L 28 22 C 22 16 10 16 0 24 Z" fill="#FBF7EF" stroke="#E6E0D2" stroke-width="1"/>
              <path d="M28 8 C 34 2 46 2 56 10 L 56 24 C 46 16 34 16 28 22 Z" fill="#FBF7EF" stroke="#E6E0D2" stroke-width="1"/>
              <line x1="6" y1="13" x2="22" y2="9"  stroke="#CFC6AE" stroke-width="1"/>
              <line x1="6" y1="17" x2="22" y2="13" stroke="#CFC6AE" stroke-width="1"/>
              <line x1="34" y1="9" x2="50" y2="13"  stroke="#CFC6AE" stroke-width="1"/>
              <line x1="34" y1="13" x2="50" y2="17" stroke="#CFC6AE" stroke-width="1"/>
            </g>
            <rect x="22" y="-4" width="12" height="12" rx="4" fill="#D8A877"/>
            <circle cx="28" cy="-14" r="16" fill="#E3B98C"/>
            <path d="M12 -18 C 10 -30 22 -34 28 -34 C 38 -34 46 -28 44 -16 C 42 -22 36 -26 28 -26 C 20 -26 14 -22 12 -18 Z" fill="#3A2E22"/>
            <circle cx="14" cy="-12" r="5" fill="#3A2E22"/>
            <path d="M22 -10 Q 26 -6 30 -10" stroke="#7A5230" stroke-width="1.4" fill="none" stroke-linecap="round"/>
          </g>
        </svg>
      </div>

      <div class="scene-caption">
        <h2>Learn law at your own pace, anywhere you find quiet.</h2>
        <p>Courses, mentorship, and exams built around how you actually study — not the other way around.</p>
      </div>
    </div>

    <!-- ═══ RIGHT: FORM ═══ -->
    <div class="auth-form-area">

      <div class="form-head">
        <h1>Reset your password</h1>
        <p>Enter your email address below, and we'll send you a secure link to reset your account password.</p>
      </div>

      <form id="forgotForm" novalidate style="display: flex; flex-direction: column; gap: 1.25rem;">
        <div class="field">
          <label for="reset-email">Email address</label>
          <input id="reset-email" type="email" placeholder="you@example.com" required />
        </div>

        <div class="field">
          <label>Verify you're human</label>
          <div class="turnstile-wrap" id="turnstile-widget" data-sitekey="<?= e($turnstileSiteKey) ?>">
            <?php if ($turnstileSiteKey): ?>
              <div class="cf-turnstile" data-sitekey="<?= e($turnstileSiteKey) ?>" data-theme="light" data-callback="onTurnstileSuccess" data-expired-callback="onTurnstileExpired" data-error-callback="onTurnstileError"></div>
            <?php else: ?>
              <p class="field-status">Turnstile is not configured yet.</p>
            <?php endif; ?>
          </div>
        </div>
        <input type="hidden" id="turnstile-token" name="cf-turnstile-response" />

        <button class="btn-primary" type="submit" id="submit-btn">Send Reset Link</button>

        <a class="back-link" href="login.php">← Back to sign in</a>
      </form>

    </div>
  </div>

  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-app.js';
    import { getAuth, sendPasswordResetEmail } from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-auth.js';

    const firebaseConfig = {
      apiKey:            'AIzaSyDgZMSypU6KKt8t_NbcpRtgQDttjV4JXtw',
      authDomain:        'lawable-9c1e0.firebaseapp.com',
      projectId:         'lawable-9c1e0',
      storageBucket:     'lawable-9c1e0.firebasestorage.app',
      messagingSenderId: '222866591543',
      appId:             '1:222866591543:web:7b1e55f74c9e15bb7294b2',
    };
    const fbApp  = initializeApp(firebaseConfig);
    const auth   = getAuth(fbApp);

    const toast = document.getElementById('toast');

    window.onTurnstileSuccess = (token) => {
      const input = document.getElementById('turnstile-token');
      if (input) input.value = token;
    };
    window.onTurnstileExpired = () => {
      const input = document.getElementById('turnstile-token');
      if (input) input.value = '';
    };
    window.onTurnstileError = () => {
      const input = document.getElementById('turnstile-token');
      if (input) input.value = '';
    };

    function showToast(message, type = 'error') {
      toast.textContent = message;
      toast.className = type;
      toast.style.display = 'block';
      clearTimeout(toast._timer);
      toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 4500);
    }

    const form = document.getElementById('forgotForm');
    const btn = document.getElementById('submit-btn');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const email = document.getElementById('reset-email').value.trim();
      const tokenInput = document.getElementById('turnstile-token');
      const token = tokenInput ? tokenInput.value : '';

      if (!email) {
        showToast('Please enter your email address.', 'error');
        return;
      }

      // Check turnstile token if site key configured
      <?php if ($turnstileSiteKey): ?>
      if (!token) {
        showToast('Please complete the Turnstile verification.', 'error');
        return;
      }
      <?php endif; ?>

      btn.disabled = true;
      btn.textContent = 'Sending link…';

      try {
        // Firebase Send Password Reset Email
        await sendPasswordResetEmail(auth, email);

        // Security check: Generic message (no user enumeration)
        showToast('If the email is registered, a password recovery link has been sent to it.', 'success');
        
        // Disable button for a few seconds to prevent spamming
        setTimeout(() => {
          btn.disabled = false;
          btn.textContent = 'Send Reset Link';
          if (typeof turnstile !== 'undefined') turnstile.reset();
        }, 5000);

      } catch (err) {
        // For security, show the same success message even on errors like user-not-found
        if (err.code === 'auth/user-not-found' || err.code === 'auth/invalid-email') {
          showToast('If the email is registered, a password recovery link has been sent to it.', 'success');
        } else {
          showToast(err.message || 'An error occurred. Please try again.', 'error');
        }
        
        setTimeout(() => {
          btn.disabled = false;
          btn.textContent = 'Send Reset Link';
          if (typeof turnstile !== 'undefined') turnstile.reset();
        }, 5000);
      }
    });
  </script>
</body>
</html>
