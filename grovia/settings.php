<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$user = require_login();
if ($user['role'] === 'Admin') {
    header('Location: admin.php');
    exit;
}
require_once __DIR__ . '/partials/current-user.php';

$db = get_db();
$stmt = $db->prepare('SELECT full_name, email, phone, role, created_at, profile_image FROM users WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$profile = $stmt->fetch();

$stmt = $db->prepare('SELECT 1 FROM face_id WHERE user_id = ? AND photo_data IS NOT NULL');
$stmt->execute([$user['user_id']]);
$profile['has_face_id'] = (bool) $stmt->fetchColumn();

$token = csrf_token();

$gh_page_title = 'Settings | Grovia';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[820px] px-5 py-6 sm:px-8 lg:px-8 lg:py-7">
    <div class="flex flex-col gap-6">

      <div>
        <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
          <i data-lucide="user-cog" class="h-3.5 w-3.5"></i>
          Account Settings
        </div>
        <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Settings</h1>
        <p class="mt-1 max-w-2xl text-[15px] text-text-secondary">Manage your profile, contact details, and password.</p>
      </div>

      <!-- Profile card -->
      <div class="overflow-hidden rounded-2xl border border-border/60 bg-white shadow-soft">
        <div class="flex flex-wrap items-center gap-5 bg-mesh-green p-6 sm:p-8">
          <div class="group relative h-24 w-24 shrink-0">
            <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-green-gradient text-2xl font-semibold text-white shadow-glow ring-4 ring-white" id="settingsAvatarWrap">
              <?php if (!empty($profile['profile_image'])): ?>
                <img src="<?= htmlspecialchars($profile['profile_image'], ENT_QUOTES) ?>" alt="" class="h-full w-full object-cover" id="settingsAvatarImg">
              <?php else: ?>
                <span id="settingsAvatarInitials"><?= htmlspecialchars($initials) ?></span>
              <?php endif; ?>
            </div>
            <button type="button" id="settingsPhotoBtn" aria-label="Change photo" class="absolute inset-0 flex h-24 w-24 items-center justify-center rounded-full bg-black/0 text-white opacity-0 transition-all duration-200 group-hover:bg-black/40 group-hover:opacity-100">
              <i data-lucide="camera" class="h-5 w-5"></i>
            </button>
            <input type="file" id="settingsPhotoInput" accept="image/jpeg,image/png,image/webp" class="hidden">
          </div>
          <div class="min-w-0">
            <h2 class="text-lg font-semibold text-text-primary"><?= htmlspecialchars($profile['full_name'] ?: $display_name) ?></h2>
            <p class="text-sm text-text-secondary"><?= htmlspecialchars($profile['email']) ?></p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-secondary shadow-sm"><?= htmlspecialchars($profile['role']) ?></span>
              <?php if (!empty($profile['created_at'])): ?>
              <span class="text-[11px] text-text-secondary">Member since <?= htmlspecialchars(date('M Y', strtotime($profile['created_at']))) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <p class="text-xs text-text-secondary" id="settingsPhotoHint">Click your photo to change it — JPEG, PNG, or WebP, up to 5MB.</p>
        </div>
      </div>

      <!-- Personal info -->
      <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
        <h3 class="text-sm font-semibold text-text-primary">Personal Information</h3>
        <p class="mt-1 text-xs text-text-secondary">Your name and contact details.</p>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="text-xs font-medium text-text-secondary">Full Name</label>
            <input type="text" id="settingsFullName" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          </div>
          <div>
            <label class="text-xs font-medium text-text-secondary">Email</label>
            <input type="email" id="settingsEmail" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          </div>
          <div>
            <label class="text-xs font-medium text-text-secondary">Phone</label>
            <input type="tel" id="settingsPhone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" placeholder="Optional" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          </div>
        </div>

        <div class="mt-5 flex items-center gap-3">
          <button type="button" id="settingsSaveInfoBtn" class="flex items-center gap-2 rounded-xl bg-green-gradient px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-all active:scale-95">
            <i data-lucide="check" class="h-4 w-4"></i>
            Save Changes
          </button>
          <p class="text-xs font-medium" id="settingsInfoNote"></p>
        </div>
      </div>

      <!-- Password -->
      <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
        <h3 class="text-sm font-semibold text-text-primary">Change Password</h3>
        <p class="mt-1 text-xs text-text-secondary">Use a password that's at least 8 characters long.</p>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div>
            <label class="text-xs font-medium text-text-secondary">Current Password</label>
            <input type="password" id="settingsCurrentPassword" autocomplete="current-password" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          </div>
          <div>
            <label class="text-xs font-medium text-text-secondary">New Password</label>
            <input type="password" id="settingsNewPassword" autocomplete="new-password" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          </div>
          <div>
            <label class="text-xs font-medium text-text-secondary">Confirm New Password</label>
            <input type="password" id="settingsConfirmPassword" autocomplete="new-password" class="mt-1.5 h-11 w-full rounded-xl border border-border/70 bg-neutral-50/70 px-3.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 focus:bg-white">
          </div>
        </div>

        <div class="mt-5 flex items-center gap-3">
          <button type="button" id="settingsSavePasswordBtn" class="flex items-center gap-2 rounded-xl border border-border/70 bg-white px-5 py-2.5 text-sm font-medium text-text-secondary transition-all hover:bg-neutral-50 active:scale-95">
            <i data-lucide="lock" class="h-4 w-4"></i>
            Update Password
          </button>
          <p class="text-xs font-medium" id="settingsPasswordNote"></p>
        </div>
      </div>

      <!-- Face ID -->
      <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
        <h3 class="text-sm font-semibold text-text-primary">Face ID</h3>
        <p class="mt-1 text-xs text-text-secondary">Sign in with your face instead of typing a password. Optional — your password still works either way.</p>

        <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div>
            <div class="relative aspect-square w-full max-w-[220px] overflow-hidden rounded-2xl bg-neutral-900">
              <video id="faceRegVideo" autoplay playsinline muted hidden class="h-full w-full object-cover"></video>
              <canvas id="faceRegCanvas" hidden class="h-full w-full object-cover"></canvas>
              <img id="faceRegSavedImg" <?= $profile['has_face_id'] ? '' : 'hidden' ?> class="h-full w-full object-cover" alt="Your registered Face ID photo" src="<?= $profile['has_face_id'] ? 'api/face-photo.php?v=' . time() : '' ?>">
              <div id="faceRegPlaceholder" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-neutral-400" <?= $profile['has_face_id'] ? 'hidden' : '' ?>>
                <i data-lucide="scan-face" class="h-8 w-8"></i>
                <p class="text-xs px-4 text-center">Camera not started</p>
              </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
              <button type="button" id="faceRegStartBtn" class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-white px-4 py-2.5 text-sm font-medium text-text-secondary transition-all hover:bg-neutral-50">
                <i data-lucide="video" class="h-4 w-4"></i> Start Camera
              </button>
              <button type="button" id="faceRegCaptureBtn" hidden class="inline-flex items-center gap-2 rounded-xl bg-green-gradient px-4 py-2.5 text-sm font-medium text-white shadow-sm">
                <i data-lucide="aperture" class="h-4 w-4"></i> Take Photo
              </button>
              <button type="button" id="faceRegRetakeBtn" hidden class="inline-flex items-center gap-2 rounded-xl border border-border/70 bg-white px-4 py-2.5 text-sm font-medium text-text-secondary transition-all hover:bg-neutral-50">
                <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Retake
              </button>
            </div>
          </div>

          <div class="flex flex-col justify-center gap-3">
            <p id="faceRegStatus" class="text-xs text-text-secondary"><?= $profile['has_face_id'] ? 'Face ID is set up on this account.' : 'No Face ID photo saved yet.' ?></p>
            <div class="flex items-center gap-3">
              <button type="button" id="faceRegSaveBtn" disabled class="flex items-center gap-2 rounded-xl bg-green-gradient px-5 py-2.5 text-sm font-medium text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-40">
                <i data-lucide="save" class="h-4 w-4"></i>
                Save Face ID
              </button>
              <p class="text-xs font-medium" id="faceRegNote"></p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="js/face-camera.js?v=1"></script>
<script>
(function () {
  function showNote(el, text, isError) {
    el.textContent = text;
    el.className = 'text-xs font-medium ' + (isError ? 'text-red-500' : 'text-primary');
    if (text) setTimeout(() => { el.textContent = ''; }, 4000);
  }


  const infoNote = document.getElementById('settingsInfoNote');
  document.getElementById('settingsSaveInfoBtn').addEventListener('click', () => {
    const full_name = document.getElementById('settingsFullName').value.trim();
    const email = document.getElementById('settingsEmail').value.trim();
    const phone = document.getElementById('settingsPhone').value.trim();

    fetch('api/profile-update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ full_name, email, phone }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) {
          showNote(infoNote, data.email_changed ? 'Saved — please re-verify your new email.' : 'Saved.', false);
        } else {
          showNote(infoNote, data.error || 'Something went wrong.', true);
        }
      })
      .catch(() => showNote(infoNote, 'Network error, try again.', true));
  });


  const passwordNote = document.getElementById('settingsPasswordNote');
  document.getElementById('settingsSavePasswordBtn').addEventListener('click', () => {
    const current_password = document.getElementById('settingsCurrentPassword').value;
    const new_password = document.getElementById('settingsNewPassword').value;
    const confirm_password = document.getElementById('settingsConfirmPassword').value;

    fetch('api/profile-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ current_password, new_password, confirm_password }),
    })
      .then((r) => r.json())
      .then((data) => {
        showNote(passwordNote, data.ok ? 'Password updated.' : (data.error || 'Something went wrong.'), !data.ok);
        if (data.ok) {
          document.getElementById('settingsCurrentPassword').value = '';
          document.getElementById('settingsNewPassword').value = '';
          document.getElementById('settingsConfirmPassword').value = '';
        }
      })
      .catch(() => showNote(passwordNote, 'Network error, try again.', true));
  });

  const photoBtn = document.getElementById('settingsPhotoBtn');
  const photoInput = document.getElementById('settingsPhotoInput');
  photoBtn.addEventListener('click', () => photoInput.click());

  photoInput.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) return;

    const form = new FormData();
    form.append('photo', file);

    const hint = document.getElementById('settingsPhotoHint');
    hint.textContent = 'Uploading…';

    fetch('api/profile-photo.php', { method: 'POST', body: form })
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) {
          const wrap = document.getElementById('settingsAvatarWrap');
          wrap.innerHTML = '<img src="' + data.profile_image + '?v=' + Date.now() + '" alt="" class="h-full w-full object-cover" id="settingsAvatarImg">';
          hint.textContent = 'Click your photo to change it — JPEG, PNG, or WebP, up to 5MB.';
        } else {
          hint.textContent = data.error || 'Upload failed.';
        }
      })
      .catch(() => { hint.textContent = 'Network error, try again.'; });
  });


  const CSRF_TOKEN = <?= json_encode($token) ?>;
  const faceRegSavedImg = document.getElementById('faceRegSavedImg');
  const faceRegStatus = document.getElementById('faceRegStatus');
  const faceRegSaveBtn = document.getElementById('faceRegSaveBtn');
  const faceRegNote = document.getElementById('faceRegNote');

  const faceCam = ghInitFaceCamera({
    videoId: 'faceRegVideo', canvasId: 'faceRegCanvas', placeholderId: 'faceRegPlaceholder',
    startBtnId: 'faceRegStartBtn', captureBtnId: 'faceRegCaptureBtn', retakeBtnId: 'faceRegRetakeBtn',
    onStateChange(state) {
      if (state === 'camera-on') faceRegSavedImg.hidden = true;
      faceRegSaveBtn.disabled = state !== 'captured';
    },
  });

  faceRegSaveBtn.addEventListener('click', () => {
    const blob = faceCam.getBlob();
    if (!blob) return;
    faceRegSaveBtn.disabled = true;
    showNote(faceRegNote, 'Checking photo…', false);

    const form = new FormData();
    form.append('photo', blob, 'face.jpg');

    fetch('api/face-register.php', { method: 'POST', credentials: 'same-origin', body: form })
      .then((r) => r.json())
      .then((data) => {
        if (data.ok) {
          showNote(faceRegNote, 'Face ID saved.', false);
          faceRegStatus.textContent = 'Face ID is set up on this account.';
          faceRegSavedImg.src = 'api/face-photo.php?v=' + Date.now();
          faceRegSavedImg.hidden = false;
          faceCam.reset();
        } else {
          faceRegSaveBtn.disabled = false;
          showNote(faceRegNote, data.error || 'Could not save.', true);
        }
      })
      .catch(() => { faceRegSaveBtn.disabled = false; showNote(faceRegNote, 'Network error, try again.', true); });
  });
})();
</script>
<script src="js/dashboard-app.js?v=1"></script>
</body>
</html>
