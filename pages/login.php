<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

// If already logged in, redirect to home
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$flash = get_flash();
$turnstileSiteKey = get_turnstile_site_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — Lawable</title>
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
      --sage:     #93A580;
      --sage-dk:  #6F8260;
      --sage-lt:  #DCE4D2;
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
    }

    .tabs {
      display: flex;
      gap: 0.35rem;
      background: var(--cream);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 0.3rem;
      width: fit-content;
    }
    .tab-btn {
      border: 0;
      background: transparent;
      color: var(--ink-mid);
      padding: 0.55rem 1.1rem;
      border-radius: 999px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.85rem;
      font-family: var(--body);
      transition: background .2s, color .2s;
    }
    .tab-btn.active { background: var(--ink); color: var(--paper); }

    .form-panel { display: none; flex-direction: column; gap: 1rem; }
    .form-panel.active { display: flex; }

    .field { display: flex; flex-direction: column; gap: 0.35rem; }
    .field label { font-size: 0.82rem; font-weight: 600; color: var(--ink-mid); }

    .input-wrap { position: relative; }
    .field input, .field select {
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
    .field input:focus, .field select:focus {
      border-color: var(--gold);
      background: var(--paper);
      box-shadow: 0 0 0 3px rgba(201,147,58,0.14);
    }
    .field.valid input, .field.valid select {
      border-color: var(--sage-dk);
      box-shadow: 0 0 0 3px rgba(111,130,96,0.12);
    }
    .field.invalid input, .field.invalid select {
      border-color: var(--red);
      box-shadow: 0 0 0 3px rgba(192,96,74,0.10);
    }

    .toggle-visibility {
      position: absolute;
      right: 0.9rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: 0;
      cursor: pointer;
      color: var(--ink-soft);
      font-size: 0.8rem;
      font-family: var(--body);
      font-weight: 600;
    }

    .field-status {
      font-size: 0.78rem;
      color: var(--ink-soft);
      min-height: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }
    .field.valid .field-status { color: var(--sage-dk); }
    .field.invalid .field-status { color: var(--red); }

    .strength-meter { display: flex; gap: 0.3rem; margin-top: 0.2rem; }
    .strength-meter .bar { flex: 1; height: 0.3rem; border-radius: 999px; background: var(--border); }
    .strength-meter .bar.active { background: var(--gold); }
    .strength-meter .bar.strong { background: var(--sage-dk); }

    .turnstile-wrap {
      display: flex;
      align-items: center;
      min-height: 70px;
    }

    .row-between {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.85rem;
    }
    .remember { display: flex; align-items: center; gap: 0.5rem; color: var(--ink-mid); }
    .remember input { width: 16px; height: 16px; accent-color: var(--gold); }
    .forgot-link { color: var(--ink-mid); text-decoration: underline; }
    .forgot-link:hover { color: var(--gold-dk); }

    .radio-row {
      display: flex;
      gap: 0.75rem;
    }
    .radio-option {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 0.55rem;
      border: 1.5px solid var(--border);
      background: var(--cream);
      border-radius: 12px;
      padding: 0.7rem 0.9rem;
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--ink-mid);
      cursor: pointer;
      transition: border-color .2s, background .2s, color .2s;
    }
    .radio-option input {
      width: 16px; height: 16px; accent-color: var(--gold); margin: 0;
    }
    .radio-option:has(input:checked) {
      border-color: var(--gold);
      background: var(--paper);
      color: var(--ink);
    }

    .section-title {
      font-size: 0.82rem; font-weight: 700; color: var(--gold-dk);
      text-transform: uppercase; letter-spacing: 0.06em;
      margin: 0.25rem 0 0.1rem;
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

    .btn-ghost {
      display: block;
      text-align: center;
      padding: 0.8rem;
      border-radius: 999px;
      border: 1.5px solid var(--border);
      color: var(--ink-mid);
      font-weight: 600;
      font-size: 0.88rem;
      font-family: var(--body);
      text-decoration: none;
      transition: border-color .2s;
    }
    .btn-ghost:hover { border-color: var(--gold); }

    .alert {
      padding: 0.8rem 1rem;
      border-radius: 12px;
      font-size: 0.86rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .alert-success { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
    .alert-info { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }

    .switch-text {
      text-align: center;
      font-size: 0.86rem;
      color: var(--ink-soft);
      margin-top: 0.1rem;
    }
    .switch-text a { color: var(--gold-dk); font-weight: 600; text-decoration: none; }
    .switch-text a:hover { text-decoration: underline; }

    .back-home {
      display: inline-block;
      font-size: 0.84rem;
      color: var(--ink-soft);
      text-decoration: none;
      text-align: center;
    }
    .back-home:hover { color: var(--gold-dk); }

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

      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>

      <div class="form-head">
        <h1 id="formTitle">Sign in to Lawable</h1>
        <p id="formSub">Pick up your courses right where you left off.</p>
      </div>

      <div class="tabs" role="tablist" aria-label="Authentication tabs">
        <button class="tab-btn active" data-target="signin" type="button">Sign in</button>
        <button class="tab-btn" data-target="signup" type="button">Create account</button>
      </div>

      <!-- SIGN IN -->
      <form class="form-panel active" id="signin" novalidate>
        <div class="field">
          <label for="si-role">Account type</label>
          <select id="si-role" required>
            <option value="user">Student</option>
            <option value="teacher">Teacher</option>
            <option value="organization">Organization</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div class="field">
          <label for="si-email">Username or email</label>
          <input id="si-email" type="text" placeholder="you@example.com" required />
        </div>

        <div class="field">
          <label for="si-password">Password</label>
          <div class="input-wrap">
            <input id="si-password" type="password" placeholder="Enter your password" required />
            <button type="button" class="toggle-visibility" data-for="si-password">Show</button>
          </div>
          <div class="row-between" style="justify-content: flex-end; margin-top: 0.15rem;">
            <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
          </div>
        </div>

        <div class="field">
          <label>Verify you're human</label>
          <div class="turnstile-wrap" id="si-turnstile-widget" data-sitekey="<?= e($turnstileSiteKey) ?>">
            <?php if ($turnstileSiteKey): ?>
              <div class="cf-turnstile" data-sitekey="<?= e($turnstileSiteKey) ?>" data-theme="light" data-callback="onTurnstileSuccess" data-expired-callback="onTurnstileExpired" data-error-callback="onTurnstileError"></div>
            <?php else: ?>
              <p class="field-status">Turnstile is not configured yet.</p>
            <?php endif; ?>
          </div>
        </div>
        <input type="hidden" id="si-turnstile-token" name="cf-turnstile-response" />

        <button class="btn-primary" type="submit" id="signin-btn">Sign in</button>

        <p class="switch-text">New here? <a href="#" data-switch="signup">Create an account</a></p>
        <a class="back-home" href="courses.php">← Back to home</a>
      </form>

      <!-- CREATE ACCOUNT -->
      <form class="form-panel" id="signup" novalidate>
        <div class="field role-picker">
          <label>I am a</label>
          <div class="radio-row">
            <label class="radio-option">
              <input type="radio" name="su-role" value="user" checked /> Student
            </label>
            <label class="radio-option">
              <input type="radio" name="su-role" value="teacher" /> Teacher
            </label>
            <label class="radio-option">
              <input type="radio" name="su-role" value="organization" /> Organization
            </label>
          </div>
        </div>

        <div id="su-user-section">
          <div class="field">
            <label for="su-name">Full name</label>
            <input id="su-name" type="text" placeholder="Your name" />
            <div class="field-status"></div>
          </div>
        </div>

        <div id="su-org-section" style="display:none;">
          <div class="section-title">Organization details</div>
          <div class="field">
            <label for="su-org-name">Organization name</label>
            <input id="su-org-name" type="text" placeholder="Your organization name" />
            <div class="field-status"></div>
          </div>
          <div class="field">
            <label for="su-contact">Contact person</label>
            <input id="su-contact" type="text" placeholder="Full name of contact person" />
            <div class="field-status"></div>
          </div>
        </div>

        <div class="field">
          <label for="su-username">Username</label>
          <input id="su-username" type="text" placeholder="Choose a username" />
          <div class="field-status"></div>
        </div>

        <div class="field">
          <label for="su-email">Email address</label>
          <input id="su-email" type="email" placeholder="you@example.com" />
          <div class="field-status"></div>
        </div>

        <div class="field">
          <label for="su-password">Password</label>
          <div class="input-wrap">
            <input id="su-password" type="password" placeholder="Create a password" />
            <button type="button" class="toggle-visibility" data-for="su-password">Show</button>
          </div>
          <div class="strength-meter" id="su-password-meter">
            <span class="bar"></span><span class="bar"></span><span class="bar"></span><span class="bar"></span>
          </div>
          <div class="field-status"></div>
        </div>

        <div class="field">
          <label>Verify you're human</label>
      <div class="turnstile-wrap" id="su-turnstile-widget" data-sitekey="<?= e($turnstileSiteKey) ?>">
            <?php if ($turnstileSiteKey): ?>
              <div id="su-turnstile-container"></div>
            <?php else: ?>
              <p class="field-status">Turnstile is not configured yet. Add your site key in C:\\xampp\\lawable-secrets.php.</p>
            <?php endif; ?>
          </div>
        </div>
        <input type="hidden" id="su-turnstile-token" name="cf-turnstile-response" />

        <button class="btn-primary" type="submit" id="signup-btn">Create account</button>
        <p class="switch-text">Already have an account? <a href="#" data-switch="signin">Sign in</a></p>
      </form>

    </div>
  </div>

  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <script type="module">
    /* ─── Firebase Auth SDK ────────────────────────────────────────────── */
    import { initializeApp }                              from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-app.js';
    import { getAuth, signInWithEmailAndPassword,
             createUserWithEmailAndPassword }             from 'https://www.gstatic.com/firebasejs/12.16.0/firebase-auth.js';

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

    /* ═══════════════════════════════════════════════
       UI Helpers: tabs, toggles, validation
       ═══════════════════════════════════════════════ */
    const $ = (id) => document.getElementById(id);
    const qs = (sel, ctx) => (ctx || document).querySelector(sel);
    const qsa = (sel, ctx) => (ctx || document).querySelectorAll(sel);

    const toast = $('toast');

    function syncTurnstileToken(token) {
      ['si-turnstile-token', 'su-turnstile-token'].forEach((id) => {
        const input = $(id);
        if (input) input.value = token;
      });
    }

    window.onTurnstileSuccess = (token) => syncTurnstileToken(token);
    window.onTurnstileExpired = () => syncTurnstileToken('');
    window.onTurnstileError = () => syncTurnstileToken('');

    function showToast(message, type = 'error') {
      toast.textContent = message;
      toast.className = type + ' shown';
      toast.style.display = 'block';
      clearTimeout(toast._timer);
      toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 4000);
    }

    // Tabs
    const tabButtons = qsa('.tab-btn');
    const panels = qsa('.form-panel');
    const formTitle = $('formTitle');
    const formSub = $('formSub');
    let suTurnstileWidgetId = null;

    function renderSuTurnstile() {
      const container = $('su-turnstile-container');
      const siteKey = $('su-turnstile-widget')?.dataset.sitekey;
      if (!container || !siteKey || suTurnstileWidgetId) return;
      try {
        suTurnstileWidgetId = turnstile.render('#su-turnstile-container', {
          sitekey: siteKey,
          callback: (token) => {
            const input = $('su-turnstile-token');
            if (input) input.value = token;
          },
          'expired-callback': () => {
            const input = $('su-turnstile-token');
            if (input) input.value = '';
          },
          'error-callback': () => {
            const input = $('su-turnstile-token');
            if (input) input.value = '';
          },
        });
      } catch (e) {
        // Turnstile script might not be loaded yet — retry once
        setTimeout(() => { try { renderSuTurnstile(); } catch(e) {} }, 500);
      }
    }

    function resetSuTurnstile() {
      if (suTurnstileWidgetId) {
        try { turnstile.reset(suTurnstileWidgetId); } catch(e) {}
        suTurnstileWidgetId = null;
      }
      const container = $('su-turnstile-container');
      if (container) container.innerHTML = '';
    }

    function switchTab(target) {
      tabButtons.forEach(b => b.classList.toggle('active', b.dataset.target === target));
      panels.forEach(p => p.classList.toggle('active', p.id === target));
      if (target === 'signin') {
        formTitle.textContent = 'Sign in to Lawable';
        formSub.textContent = 'Pick up your courses right where you left off.';
        resetSuTurnstile();
      } else {
        formTitle.textContent = 'Create your account';
        formSub.textContent = 'Join Lawable as a student or register your organization.';
        renderSuTurnstile();
      }
    }

    tabButtons.forEach(b => b.addEventListener('click', () => switchTab(b.dataset.target)));
    qsa('[data-switch]').forEach(l => l.addEventListener('click', e => { e.preventDefault(); switchTab(l.dataset.switch); }));

    // Visibility toggles
    qsa('.toggle-visibility').forEach(btn => {
      btn.addEventListener('click', () => {
        const inp = $(btn.dataset.for);
        const hidden = inp.type === 'password';
        inp.type = hidden ? 'text' : 'password';
        btn.textContent = hidden ? 'Hide' : 'Show';
      });
    });

    // Signup role toggle
    const roleRadios = qsa('input[name="su-role"]');
    const suUserSec = $('su-user-section');
    const suOrgSec = $('su-org-section');

    function toggleSignupSections() {
      const role = qs('input[name="su-role"]:checked')?.value || 'user';
      suUserSec.style.display = role === 'organization' ? 'none' : 'block';
      suOrgSec.style.display = role === 'organization' ? 'block' : 'none';
    }
    roleRadios.forEach(r => r.addEventListener('change', toggleSignupSections));
    toggleSignupSections();

    // Field validation helpers
    const emailRe = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,6}$/;
    const freeDomains = ['gmail.com','yahoo.com','hotmail.com','outlook.com','rediffmail.com'];

    function setFieldState(input, ok, msg) {
      const field = input.closest('.field');
      if (!field) return;
      field.classList.toggle('valid', ok);
      field.classList.toggle('invalid', !ok);
      const st = field.querySelector('.field-status');
      if (!st) return;
      if (!input.value.trim() && !input.required) { st.innerHTML = ''; return; }
      st.innerHTML = ok ? '<span>✓ Looks good</span>' : `<span>✕ ${msg}</span>`;
    }

    function validateReq(input, msg) {
      if (!input.value.trim()) { setFieldState(input, false, msg); return false; }
      setFieldState(input, true); return true;
    }

    function validateEmail(input, official) {
      const v = input.value.trim();
      if (!v || /\s/.test(v) || (v.match(/@/g)||[]).length !== 1 || !emailRe.test(v)) {
        setFieldState(input, false, 'Enter a valid email address'); return false;
      }
      const domain = v.split('@')[1].toLowerCase();
      if (official && freeDomains.includes(domain)) {
        setFieldState(input, false, 'Use your official organization email'); return false;
      }
      setFieldState(input, true); return true;
    }

    function validatePassword(input) {
      const v = input.value;
      const email = ($('su-email')?.value || '').trim().toLowerCase();
      const meter = $('su-password-meter');
      const bars = meter?.querySelectorAll('.bar') || [];
      let score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[a-z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      if (/\s/.test(v)) score = Math.min(score, 1);
      if (email && v.toLowerCase().includes(email)) score = Math.min(score, 2);

      bars.forEach((b, i) => {
        b.className = 'bar' + (i < Math.min(score, 4) ? ' active' : '') + (score >= 5 ? ' strong' : '');
      });

      if (!v || v.length < 8) { setFieldState(input, false, 'Use at least 8 characters'); return false; }
      if (/\s/.test(v)) { setFieldState(input, false, 'No spaces allowed'); return false; }
      if (!/[A-Z]/.test(v)) { setFieldState(input, false, 'Add an uppercase letter'); return false; }
      if (!/\d/.test(v)) { setFieldState(input, false, 'Add a number'); return false; }
      if (!/[^A-Za-z0-9]/.test(v)) { setFieldState(input, false, 'Add a special character'); return false; }
      if (email && v.toLowerCase().includes(email)) { setFieldState(input, false, "Don't reuse your email"); return false; }
      setFieldState(input, true); return true;
    }

    // Live validation on signup fields
    $('su-name')?.addEventListener('input', () => validateReq($('su-name'), 'Full name is required'));
    $('su-username')?.addEventListener('input', () => validateReq($('su-username'), 'Username is required'));
    $('su-email')?.addEventListener('input', () => validateEmail($('su-email'), qs('input[name="su-role"]:checked')?.value === 'organization'));
    $('su-password')?.addEventListener('input', () => validatePassword($('su-password')));
    $('su-org-name')?.addEventListener('input', () => validateReq($('su-org-name'), 'Organization name is required'));
    $('su-contact')?.addEventListener('input', () => validateReq($('su-contact'), 'Contact person is required'));

    /* ═══════════════════════════════════════════════
       SIGN IN — Firebase Auth → PHP token verify
       ═══════════════════════════════════════════════ */
    $('signin').addEventListener('submit', async (e) => {
      e.preventDefault();

      const btn   = $('signin-btn');
      const email = $('si-email').value.trim();
      const pass  = $('si-password').value;
      const role  = $('si-role').value;

      if (!email || !pass) {
        showToast('Please enter your email and password.', 'error');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Signing in…';

      const friendlyErrors = {
        'auth/invalid-credential':      'Invalid email or password.',
        'auth/user-not-found':          'No account found with that email.',
        'auth/wrong-password':          'Incorrect password.',
        'auth/user-disabled':           'This account has been disabled.',
        'auth/too-many-requests':       'Too many failed attempts. Please try again later.',
        'auth/network-request-failed':  'Network error. Please check your connection.',
        'auth/invalid-email':           'Please enter a valid email address.',
      };

      try {
        // Step 1 — Authenticate with Firebase
        const credential = await signInWithEmailAndPassword(auth, email, pass);
        const idToken    = await credential.user.getIdToken();

        // Step 2 — Verify token + create PHP session
        const res  = await fetch('../api/login.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ idToken, role }),
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => { window.location.href = data.redirect || '/lawable/pages/dashboard.php'; }, 600);
        } else {
          showToast(data.message || 'Login failed.', 'error');
          btn.disabled = false;
          btn.textContent = 'Sign in';
        }
      } catch (err) {
        showToast(friendlyErrors[err.code] || err.message || 'Login failed.', 'error');
        btn.disabled = false;
        btn.textContent = 'Sign in';
      }
    });

    /* ═══════════════════════════════════════════════
       SIGN UP — Firebase Auth → PHP profile store
       ═══════════════════════════════════════════════ */
    $('signup').addEventListener('submit', async (e) => {
      e.preventDefault();

      const role = qs('input[name="su-role"]:checked')?.value || 'user';

      // Validate fields
      const checks = [
        validateReq($('su-username'), 'Username is required'),
        validateEmail($('su-email'), role === 'organization'),
        validatePassword($('su-password')),
      ];
      if (role === 'user' || role === 'teacher') {
        checks.push(validateReq($('su-name'), 'Full name is required'));
      } else {
        checks.push(validateReq($('su-org-name'), 'Organization name is required'));
        checks.push(validateReq($('su-contact'), 'Contact person is required'));
      }
      if (!checks.every(Boolean)) return;

      const btn = $('signup-btn');
      btn.disabled = true;
      btn.textContent = 'Creating account…';

      const email    = $('su-email').value.trim();
      const password = $('su-password').value;
      
      let endpoint = '../api/register_user.php';
      if (role === 'organization') {
        endpoint = '../api/register_organization.php';
      } else if (role === 'teacher') {
        endpoint = '../api/register_teacher.php';
      }

      const friendlyErrors = {
        'auth/email-already-in-use':   'This email address is already registered.',
        'auth/weak-password':          'Password must be at least 6 characters.',
        'auth/invalid-email':          'Please enter a valid email address.',
        'auth/network-request-failed': 'Network error. Please check your connection.',
      };

      try {
        // Step 1 — Create user in Firebase Auth
        const credential = await createUserWithEmailAndPassword(auth, email, password);
        const idToken    = await credential.user.getIdToken();

        // Step 2 — Send token + profile to PHP
        const bodyObj = { idToken, username: $('su-username').value.trim() };
        if (role === 'user' || role === 'teacher') {
          bodyObj.name  = $('su-name').value.trim();
        } else {
          bodyObj.organization_name = $('su-org-name').value.trim();
          bodyObj.contact_person    = $('su-contact').value.trim();
        }

        const res  = await fetch(endpoint, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify(bodyObj),
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message, 'success');
          setTimeout(() => switchTab('signin'), 1200);
        } else {
          // PHP rejected — rollback Firebase account
          await credential.user.delete();
          showToast(data.message || 'Registration failed.', 'error');
        }
      } catch (err) {
        showToast(friendlyErrors[err.code] || err.message || 'Registration failed.', 'error');
      }

      btn.disabled = false;
      btn.textContent = 'Create account';
    });
  </script>
</body>
</html>
</write_to_file>
