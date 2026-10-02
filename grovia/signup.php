<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (current_user()) {
    header('Location: ' . (current_user()['role'] === 'Admin' ? 'admin.php' : 'dashboard.php'));
    exit;
}


function gh_handle_profile_upload(): ?string {
    if (empty($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['profile_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The profile image failed to upload. Please try again.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Profile image must be 2MB or smaller.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Profile image must be a JPG, PNG, or WEBP file.');
    }
    $dir = __DIR__ . '/assets/uploads/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Could not save the profile image. Please try again.');
    }
    return 'assets/uploads/avatars/' . $filename;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($fullName === '') {
            $error = 'Enter your full name.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $db = get_db();
            $stmt = $db->prepare('SELECT user_id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                try {
                    $profileImage = gh_handle_profile_upload();
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare('INSERT INTO users (full_name, email, password, role, profile_image) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$fullName, $email, $hash, 'User', $profileImage]);

                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'user_id' => (int)$db->lastInsertId(),
                        'full_name' => $fullName,
                        'email' => $email,
                        'role' => 'User',
                        'onboarding_completed' => false,
                        'profile_image' => $profileImage,
                    ];
                    header('Location: verify.php');
                    exit;
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
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
<title>Sign Up | Grovia</title>
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
  .auth-field .hint { font-size: 0.76rem; color: var(--ink-dim); margin-top: 6px; }
  .auth-submit { width: 100%; margin-top: 6px; }
  .auth-foot { text-align: center; margin-top: 22px; font-size: 0.88rem; color: var(--ink-dim); }
  .auth-foot a { color: var(--green-600); font-weight: 600; }
  .auth-back { display: block; text-align: center; margin-top: 26px; font-size: 0.85rem; color: var(--ink-dim); }
  .auth-back:hover { color: var(--green-600); }
  .auth-error {
    background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
    font-size: 0.85rem; font-weight: 600; padding: 12px 14px; border-radius: 10px; margin-bottom: 18px; text-align: center;
  }
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
      <li><span class="nav-auth-hint">Already a member?</span></li>
      <li><a href="login.php" class="btn btn-primary">Log In</a></li>
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
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-sub">Start monitoring your greenhouse today.</p>

      <?php if ($error): ?>
        <div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>

      <form method="post" action="signup.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="auth-field">
          <label for="signupFullName">Full Name</label>
          <input type="text" id="signupFullName" name="full_name" placeholder="Jane Doe" autocomplete="name" value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES) ?>" required>
        </div>
        <div class="auth-field">
          <label for="signupEmail">Email</label>
          <input type="email" id="signupEmail" name="email" placeholder="you@example.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>" required>
        </div>
        <div class="auth-field">
          <label for="signupPassword">Password</label>
          <input type="password" id="signupPassword" name="password" placeholder="••••••••" autocomplete="new-password" minlength="8" required>
          <div class="hint">At least 8 characters.</div>
        </div>
        <div class="auth-field">
          <label for="signupProfileImage">Profile Photo <span class="hint" style="display:inline;">(optional)</span></label>
          <input type="file" id="signupProfileImage" name="profile_image" accept="image/jpeg,image/png,image/webp">
          <div class="hint">JPG, PNG, or WEBP — up to 2MB.</div>
        </div>
        <button type="submit" class="btn btn-primary auth-submit">
          <span>Sign Up</span>
          <span class="arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </button>
      </form>

      <p class="auth-foot">Already have an account? <a href="login.php">Log in</a></p>
      <a href="index.php" class="auth-back">← Back to home</a>
    </div>
  </section>
</main>

<script src="js/vendor/gsap.min.js"></script>
<script src="js/vendor/ScrollTrigger.min.js"></script>
<script src="js/vendor/lenis.min.js"></script>
<script src="js/main.js?v=1"></script>

</body>
</html>
