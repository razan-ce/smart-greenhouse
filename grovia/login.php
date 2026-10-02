<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function gh_post_login_redirect(array $user): string {
    return $user['role'] === 'Admin' ? 'admin.php' : 'dashboard.php';
}

if (current_user()) {
    header('Location: ' . gh_post_login_redirect(current_user()));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Enter your email and password.';
        } else {
            $stmt = get_db()->prepare('SELECT user_id, full_name, email, password, role, onboarding_completed, account_status, profile_image FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($password, $row['password'])) {//actual password check 
                if ($row['account_status'] === 'Inactive') {//blocks deaciveated accounts
                    $error = 'Your account is inactive. Please contact support.';
                } else {
                    session_regenerate_id(true);
                    $sessionUser = [
                        'user_id' => $row['user_id'],
                        'full_name' => $row['full_name'],
                        'email' => $row['email'],
                        'role' => $row['role'],
                        'onboarding_completed' => (bool)$row['onboarding_completed'],
                        'profile_image' => $row['profile_image'],
                    ];
                    $_SESSION['user'] = $sessionUser;// the exact lint that logs the user in 
                    header('Location: ' . gh_post_login_redirect($sessionUser));//sends admin or user
                    exit;
                }
            } else {
                $error = 'Incorrect email or password.';
            }
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Grovia</title>
<meta name="theme-color" content="#16a34a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<style>
  .auth-section { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 140px 24px 80px; background: var(--bg-soft); }
  .auth-card {
    width: 100%; max-width: 420px; background: #fff; border: 1px solid var(--border);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 44px 36px;
  }
  .auth-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 28px; font-family: var(--font-display); font-weight: 600; font-size: 1.15rem; color: var(--ink); }
  .auth-title { text-align: center; font-size: 1.6rem; margin-bottom: 8px; }
  .auth-sub { text-align: center; color: var(--ink-dim); font-size: 0.92rem; margin-bottom: 30px; }
  .auth-field { margin-bottom: 18px; }
  .auth-field label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--ink-soft); margin-bottom: 7px; }
  .auth-field input {
    width: 100%; padding: 13px 16px; border-radius: 12px; border: 1.5px solid var(--border);
    font-family: var(--font-body); font-size: 0.95rem; color: var(--ink); background: var(--bg-soft);
    transition: border-color 0.25s ease, background 0.25s ease;
  }
  .auth-field input:focus { outline: none; border-color: var(--green-500); background: #fff; }
  .auth-submit { width: 100%; margin-top: 6px; }
  .auth-foot { text-align: center; margin-top: 22px; font-size: 0.88rem; color: var(--ink-dim); }
  .auth-foot a { color: var(--green-600); font-weight: 600; }
  .auth-back { display: block; text-align: center; margin-top: 26px; font-size: 0.85rem; color: var(--ink-dim); }
  .auth-back:hover { color: var(--green-600); }
  .auth-error {
    background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
    font-size: 0.85rem; font-weight: 600; padding: 12px 14px; border-radius: 10px; margin-bottom: 18px; text-align: center;
  }
  .auth-divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; color: var(--ink-dim); font-size: 0.8rem; }
  .auth-divider::before, .auth-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
  .auth-faceid {
    width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 13px 16px; border-radius: 12px; border: 1.5px solid var(--border); background: #fff;
    font-family: var(--font-body); font-weight: 600; font-size: 0.95rem; color: var(--ink);
    transition: border-color 0.25s ease, background 0.25s ease, transform 0.15s ease;
  }
  .auth-faceid svg { width: 20px; height: 20px; color: var(--green-600); }
  .auth-faceid:hover { border-color: var(--green-500); background: var(--bg-soft); }
  .auth-faceid:active { transform: scale(0.98); }
  .auth-faceid:disabled { opacity: 0.6; pointer-events: none; }
  .auth-faceid-error {
    margin-top: 12px; font-size: 0.82rem; color: #b91c1c; text-align: center;
  }
  .faceid-modal {
    position: fixed; inset: 0; z-index: 999; display: flex; align-items: center; justify-content: center;
    background: rgba(15, 25, 18, 0.55); backdrop-filter: blur(6px); padding: 24px;
  }
  .faceid-modal-card {
    position: relative; width: 100%; max-width: 380px; background: #fff; border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg); padding: 32px 28px; text-align: center;
  }
  .faceid-modal-close {
    position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; color: var(--ink-dim);
    transition: background 0.2s ease, color 0.2s ease;
  }
  .faceid-modal-close:hover { background: var(--bg-soft); color: var(--ink); }
  .faceid-modal-close svg { width: 18px; height: 18px; }
  .faceid-modal-title { font-size: 1.25rem; margin-bottom: 4px; }
  .faceid-modal-sub { color: var(--ink-dim); font-size: 0.88rem; margin-bottom: 20px; }
</style>
</head>
<body>

<nav class="nav solid" id="siteNav" data-nav-static="true">
  <div class="nav-inner">
    <a href="index.php" class="logo">
      <div class="logo-icon">
        <img src="assets/logo-mark.png" alt="Grovia logo">
      </div>
      Grov<span class="brand-accent">ia</span>
    </a>
    <ul class="nav-links" id="navLinks">
      <li><span class="nav-auth-hint">New here?</span></li>
      <li><a href="signup.php" class="btn btn-primary">Sign Up</a></li>
    </ul>
  </div>
</nav>

<main>
  <section class="auth-section">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="logo-icon">
          <img src="assets/logo-mark.png" alt="Grovia logo">
        </div>
        Grov<span class="brand-accent">ia</span>
      </div>
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-sub">Log in to reach your live dashboard.</p>

      <?php if ($error): ?>
        <div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="auth-field">
          <label for="loginEmail">Email</label>
          <input type="email" id="loginEmail" name="email" placeholder="you@example.com" autocomplete="email" required>
        </div>
        <div class="auth-field">
          <label for="loginPassword">Password</label>
          <input type="password" id="loginPassword" name="password" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary auth-submit">
          <span>Log In</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </button>
      </form>

      <div class="auth-divider" id="faceIdDivider" hidden><span>or</span></div>
      <button type="button" class="btn auth-faceid" id="faceIdOpenBtn" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M9 10v1M15 10v1M9.5 15.5c.7.7 1.6 1 2.5 1s1.8-.3 2.5-1"/></svg>
        <span>Sign in with Face ID</span>
      </button>

      <p class="auth-foot">Don't have an account? <a href="signup.php">Sign up</a></p>
      <a href="index.php" class="auth-back">← Back to home</a>
    </div>
  </section>

  <!-- Face ID sign-in overlay -->
  <div class="faceid-modal" id="faceIdModal" hidden>
    <div class="faceid-modal-card">
      <button type="button" class="faceid-modal-close" id="faceIdCloseBtn" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
      <h2 class="faceid-modal-title">Sign in with Face ID</h2>
      <p class="faceid-modal-sub">Look at the camera and take a photo.</p>

      <div class="faceid-cam-frame">
        <video id="faceLoginVideo" autoplay playsinline muted hidden></video>
        <canvas id="faceLoginCanvas" hidden></canvas>
        <div id="faceLoginPlaceholder" class="faceid-cam-placeholder">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M9 10v1M15 10v1M9.5 15.5c.7.7 1.6 1 2.5 1s1.8-.3 2.5-1"/></svg>
          <p>Camera not started</p>
        </div>
      </div>

      <div class="faceid-modal-actions">
        <button type="button" class="btn" id="faceLoginStartBtn">Start Camera</button>
        <button type="button" class="btn btn-primary" id="faceLoginCaptureBtn" hidden>Take Photo</button>
        <button type="button" class="btn" id="faceLoginRetakeBtn" hidden>Retake</button>
      </div>
      <button type="button" class="btn btn-primary auth-submit" id="faceLoginSubmitBtn" disabled style="margin-top:14px;">Sign In</button>
      <p class="auth-faceid-error" id="faceIdError" hidden></p>
    </div>
  </div>
</main>

<script src="js/vendor/gsap.min.js"></script>
<script src="js/vendor/ScrollTrigger.min.js"></script>
<script src="js/vendor/lenis.min.js"></script>
<script src="js/main.js?v=1"></script>
<script src="js/face-camera.js?v=1"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const divider = document.getElementById('faceIdDivider');
    const openBtn = document.getElementById('faceIdOpenBtn');
    const modal = document.getElementById('faceIdModal');
    const closeBtn = document.getElementById('faceIdCloseBtn');
    const submitBtn = document.getElementById('faceLoginSubmitBtn');
    const errorEl = document.getElementById('faceIdError');
    if (!openBtn || !modal) return;

    const cameraSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);//checks if Face id can even be offered 
    if (cameraSupported) {
      divider.hidden = false;
      openBtn.hidden = false;
    }

    const faceCam = ghInitFaceCamera({
      videoId: 'faceLoginVideo', canvasId: 'faceLoginCanvas', placeholderId: 'faceLoginPlaceholder',
      startBtnId: 'faceLoginStartBtn', captureBtnId: 'faceLoginCaptureBtn', retakeBtnId: 'faceLoginRetakeBtn',
      onStateChange(state) { submitBtn.disabled = state !== 'captured'; },
    });

    function openModal() {
      errorEl.hidden = true;
      modal.hidden = false;
    }
    function closeModal() {
      modal.hidden = true;
      faceCam.stop();
      faceCam.reset();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    submitBtn.addEventListener('click', async () => {
      const blob = faceCam.getBlob();
      if (!blob) return;
      errorEl.hidden = true;
      submitBtn.disabled = true;
      submitBtn.textContent = 'Checking…';

      const form = new FormData();
      form.append('photo', blob, 'face.jpg');
      form.append('csrf_token', <?= json_encode($token) ?>);

      try {
        const res = await fetch('api/face-login.php', { method: 'POST', credentials: 'same-origin', body: form });//sends the captured phooto 
        const data = await res.json();
        if (data.ok) {
          submitBtn.textContent = 'Success — redirecting…';
          window.location.href = data.redirect || 'dashboard.php';
        } else {
          throw new Error(data.error || 'Face ID sign-in failed.');
        }
      } catch (err) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In';
        errorEl.textContent = err.message;
        errorEl.hidden = false;
      }
    });
  });
</script>

</body>
</html>
