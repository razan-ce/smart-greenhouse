<?php
// Requires the user to be logged in — signup.php creates the session and
// this page sends the first code, then the user types it back in. No token
// in the URL: we already know who's asking, they just have to prove they
// got the email.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail-lib.php';

$user = require_login();
$db = get_db();

function gh_post_verify_redirect(array $row): string {
    return $row['role'] === 'Admin' ? 'admin.php' : 'dashboard.php';
}

$stmt = $db->prepare('SELECT user_id, full_name, email, role, onboarding_completed, email_verified, verification_token, verification_token_expiry FROM users WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$row = $stmt->fetch();

$error = '';
$notice = '';
$success = (bool) $row['email_verified'];

if (!$success) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            $error = 'Your session expired. Please try again.';
        } elseif (isset($_POST['resend'])) {
            $lastSent = $_SESSION['gh_verify_last_sent'] ?? 0;
            $wait = 60 - (time() - $lastSent);
            if ($wait > 0) {
                $error = "Please wait {$wait}s before requesting another code.";
            } else {
                $result = gh_send_verification_email($db, $row);
                $_SESSION['gh_verify_last_sent'] = time();
                $notice = $result['success']
                    ? "We sent a new code to {$row['email']}."
                    : 'We could not send the email right now (' . ($result['error'] ?? 'unknown error') . '). Please try again shortly.';
            }
        } else {
            $code = trim($_POST['code'] ?? '');
            if ($code === '') {
                $error = 'Enter the 6-digit code from your email.';
            } elseif (!$row['verification_token']) {
                $error = 'No code has been sent yet. Click "Resend code" below.';
            } elseif (strtotime($row['verification_token_expiry']) < time()) {
                $error = 'That code has expired. Click "Resend code" below for a new one.';
            } elseif (!hash_equals($row['verification_token'], $code)) {
                $error = 'That code is incorrect. Double-check your email and try again.';
            } else {
                $stmt = $db->prepare('UPDATE users SET email_verified = 1, verified_at = NOW(), verification_token = NULL, verification_token_expiry = NULL WHERE user_id = ?');
                $stmt->execute([$row['user_id']]);
                $success = true;
            }
        }
    } elseif (!$row['verification_token']) {
        // First time landing here (straight after signup) — send the first
        // code automatically instead of making them click "Resend" first.
        $result = gh_send_verification_email($db, $row);
        $_SESSION['gh_verify_last_sent'] = time();
        $notice = $result['success']
            ? "We sent a 6-digit code to {$row['email']}."
            : 'We could not send the email right now (' . ($result['error'] ?? 'unknown error') . '). Click "Resend code" below to try again.';
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email | Grovia</title>
<meta name="theme-color" content="#16a34a">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=4">
<style>
  .verify-section { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 24px; background: var(--bg-soft); }
  .verify-card { width: 100%; max-width: 440px; background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 44px 36px; text-align: center; }
  .verify-icon { width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
  .verify-icon.success { background: var(--green-50); color: var(--green-600); }
  .verify-icon.pending { background: var(--green-50); color: var(--green-600); }
  .verify-title { font-size: 1.4rem; margin-bottom: 10px; }
  .verify-message { color: var(--ink-dim); font-size: 0.95rem; line-height: 1.6; }
  .verify-code-input {
    width: 100%; margin-top: 24px; padding: 16px; border-radius: 14px; border: 1.5px solid var(--border);
    background: var(--bg-soft); font-family: 'Courier New', monospace; font-size: 1.6rem; font-weight: 700;
    letter-spacing: 0.5rem; text-align: center; color: var(--ink);
  }
  .verify-code-input:focus { outline: none; border-color: var(--green-500); background: #fff; }
  .verify-submit { width: 100%; margin-top: 18px; }
  .verify-resend { margin-top: 18px; font-size: 0.85rem; color: var(--ink-dim); }
  .verify-resend button { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: var(--green-600); cursor: pointer; }
  .verify-resend button:hover { text-decoration: underline; }
  .verify-notice {
    background: var(--green-50); border: 1px solid #bbf0cc; color: var(--green-600);
    font-size: 0.85rem; font-weight: 600; padding: 12px 14px; border-radius: 10px; margin-top: 18px; text-align: center;
  }
  .verify-error {
    background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
    font-size: 0.85rem; font-weight: 600; padding: 12px 14px; border-radius: 10px; margin-top: 18px; text-align: center;
  }
</style>
</head>
<body>
<main>
  <section class="verify-section">
    <div class="verify-card">
      <?php if ($success): ?>
        <div id="verifySuccessPanel">
          <div class="verify-icon success">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <h1 class="verify-title">Email Verified</h1>
          <p class="verify-message">Your email address is confirmed. You're all set.</p>
          <a href="<?= htmlspecialchars(gh_post_verify_redirect($row), ENT_QUOTES) ?>" class="btn btn-primary" style="margin-top: 24px; display: inline-flex;">Continue</a>
        </div>

        <!-- Optional — entirely skippable, never blocks continuing to the dashboard -->
        <div id="faceIdSetupPanel" hidden>
          <div class="verify-icon pending">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M9 10v1M15 10v1M9.5 15.5c.7.7 1.6 1 2.5 1s1.8-.3 2.5-1"/></svg>
          </div>
          <h1 class="verify-title">Set up Face ID?</h1>
          <p class="verify-message">Sign in next time without typing your password. Totally optional — you can always add this later from Settings.</p>

          <div class="faceid-cam-frame" style="margin-top:22px;">
            <video id="faceSetupVideo" autoplay playsinline muted hidden></video>
            <canvas id="faceSetupCanvas" hidden></canvas>
            <div id="faceSetupPlaceholder" class="faceid-cam-placeholder">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M9 10v1M15 10v1M9.5 15.5c.7.7 1.6 1 2.5 1s1.8-.3 2.5-1"/></svg>
              <p>Camera not started</p>
            </div>
          </div>
          <div class="faceid-modal-actions">
            <button type="button" class="btn" id="faceSetupStartBtn">Start Camera</button>
            <button type="button" class="btn btn-primary" id="faceSetupCaptureBtn" hidden>Take Photo</button>
            <button type="button" class="btn" id="faceSetupRetakeBtn" hidden>Retake</button>
          </div>
          <p class="auth-faceid-error" id="faceSetupError" hidden></p>

          <div style="margin-top:18px; display:flex; gap:10px; justify-content:center;">
            <a href="<?= htmlspecialchars(gh_post_verify_redirect($row), ENT_QUOTES) ?>" class="btn" style="border:1.5px solid var(--border); color:var(--ink);">Skip for now</a>
            <button type="button" class="btn btn-primary" id="faceSetupSaveBtn" disabled>Save &amp; Continue</button>
          </div>
        </div>

        <script src="js/face-camera.js?v=1"></script>
        <script>
          document.addEventListener('DOMContentLoaded', () => {
            const successPanel = document.getElementById('verifySuccessPanel');
            const setupPanel = document.getElementById('faceIdSetupPanel');
            const saveBtn = document.getElementById('faceSetupSaveBtn');
            const errorEl = document.getElementById('faceSetupError');
            const continueUrl = <?= json_encode(gh_post_verify_redirect($row)) ?>;

            // Only offer this if a camera is even possible — otherwise
            // just send them straight on, no dead-end prompt.
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
              successPanel.hidden = true;
              setupPanel.hidden = false;
            }

            const cam = ghInitFaceCamera({
              videoId: 'faceSetupVideo', canvasId: 'faceSetupCanvas', placeholderId: 'faceSetupPlaceholder',
              startBtnId: 'faceSetupStartBtn', captureBtnId: 'faceSetupCaptureBtn', retakeBtnId: 'faceSetupRetakeBtn',
              onStateChange(state) { saveBtn.disabled = state !== 'captured'; },
            });

            saveBtn.addEventListener('click', () => {
              const blob = cam.getBlob();
              if (!blob) return;
              saveBtn.disabled = true;
              saveBtn.textContent = 'Saving…';
              errorEl.hidden = true;

              const form = new FormData();
              form.append('photo', blob, 'face.jpg');

              fetch('api/face-register.php', { method: 'POST', credentials: 'same-origin', body: form })
                .then((r) => r.json())
                .then((data) => {
                  if (data.ok) {
                    window.location.href = continueUrl;
                  } else {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save & Continue';
                    errorEl.textContent = data.error || 'Could not save. You can try again from Settings later.';
                    errorEl.hidden = false;
                  }
                })
                .catch(() => {
                  saveBtn.disabled = false;
                  saveBtn.textContent = 'Save & Continue';
                  errorEl.textContent = 'Network error, try again.';
                  errorEl.hidden = false;
                });
            });
          });
        </script>
      <?php else: ?>
        <div class="verify-icon pending">
          <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
        </div>
        <h1 class="verify-title">Check your email</h1>
        <p class="verify-message">We sent a 6-digit code to <strong><?= htmlspecialchars($row['email'], ENT_QUOTES) ?></strong>. Enter it below to verify your account.</p>

        <?php if ($notice): ?><div class="verify-notice"><?= htmlspecialchars($notice, ENT_QUOTES) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="verify-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

        <form method="post" action="verify.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
          <input type="text" name="code" class="verify-code-input" placeholder="000000" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required>
          <button type="submit" class="btn btn-primary verify-submit">
            <span>Verify Email</span>
          </button>
        </form>

        <form method="post" action="verify.php" class="verify-resend">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
          Didn't get it? <button type="submit" name="resend" value="1">Resend code</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>
