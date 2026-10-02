<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin-lib.php';

$user = require_admin();
require_once __DIR__ . '/partials/current-user.php';
$db = get_db();


function gh_canonical_greenhouse(PDO $db): ?array {
    return $db->query('SELECT * FROM greenhouses ORDER BY created_at ASC LIMIT 1')->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } else {
        $name = trim($_POST['greenhouse_name'] ?? '');
        $cropType = trim($_POST['crop_type'] ?? '');
        $landSize = $_POST['land_size'] !== '' ? (float) $_POST['land_size'] : null;
        $country = trim($_POST['country'] ?? '');
        $city = trim($_POST['city'] ?? '');

        if ($name === '') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'Give the greenhouse a name.'];
        } else {
            $existing = gh_canonical_greenhouse($db);
            if ($existing) {
                $stmt = $db->prepare(
                    'UPDATE greenhouses SET greenhouse_name = ?, crop_type = ?, land_size = ?, country = ?, city = ? WHERE greenhouse_id = ?'
                );
                $stmt->execute([$name, $cropType ?: null, $landSize, $country ?: null, $city ?: null, $existing['greenhouse_id']]);
                gh_log_admin_action($db, (int) $user['user_id'], 'Update Greenhouse', 'greenhouses', (int) $existing['greenhouse_id'], "Updated greenhouse profile ({$name})");
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO greenhouses (user_id, greenhouse_name, crop_type, land_size, country, city) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$user['user_id'], $name, $cropType ?: null, $landSize, $country ?: null, $city ?: null]);
                gh_log_admin_action($db, (int) $user['user_id'], 'Create Greenhouse', 'greenhouses', (int) $db->lastInsertId(), "Created greenhouse profile ({$name})");
            }
            $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'Greenhouse profile saved.'];
        }
    }
    header('Location: admin-greenhouse.php');
    exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$greenhouse = gh_canonical_greenhouse($db);

$token = csrf_token();
$gh_page_title = 'Greenhouse | Admin';
require __DIR__ . '/partials/head.php';
?>
<body class="dash-body">

<?php require __DIR__ . '/partials/admin-sidebar.php'; ?>

<div class="lg:pl-[272px]">
  <?php require __DIR__ . '/partials/topbar.php'; ?>

  <main class="mx-auto max-w-[1600px] px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
    <div class="flex flex-col gap-6">

      <div>
        <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-light px-3 py-1.5 text-xs font-semibold text-secondary">
          <i data-lucide="sprout" class="h-3.5 w-3.5"></i>
          Greenhouse
        </div>
        <h1 class="text-[26px] font-semibold tracking-tight text-text-primary sm:text-[30px]">Greenhouse Profile</h1>
        <p class="mt-1 text-[15px] text-text-secondary">There's one greenhouse in the system — this is its profile. Workers you grant access to will see this same information in the Greenhouse Planner.</p>
      </div>

      <?php if ($flash): ?>
        <div class="rounded-xl border px-4 py-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'border-primary/20 bg-light text-secondary' : 'border-red-200 bg-red-50 text-red-600' ?>">
          <?= htmlspecialchars($flash['text'], ENT_QUOTES) ?>
        </div>
      <?php endif; ?>

      <div class="rounded-2xl border border-border/60 bg-white p-6 shadow-soft sm:p-8">
        <form method="post" action="admin-greenhouse.php" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

          <div class="sm:col-span-2">
            <label class="text-xs font-semibold text-text-secondary">Greenhouse Name</label>
            <input type="text" name="greenhouse_name" value="<?= htmlspecialchars($greenhouse['greenhouse_name'] ?? '', ENT_QUOTES) ?>" placeholder="e.g. Main Greenhouse" class="mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none" required>
          </div>

          <div>
            <label class="text-xs font-semibold text-text-secondary">Crop Type</label>
            <input type="text" name="crop_type" value="<?= htmlspecialchars($greenhouse['crop_type'] ?? '', ENT_QUOTES) ?>" placeholder="e.g. Tomato" class="mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          </div>

          <div>
            <label class="text-xs font-semibold text-text-secondary">Land Size (m²)</label>
            <input type="number" step="0.1" min="0" name="land_size" value="<?= htmlspecialchars($greenhouse['land_size'] !== null ? (string) $greenhouse['land_size'] : '', ENT_QUOTES) ?>" placeholder="e.g. 50" class="mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          </div>

          <div>
            <label class="text-xs font-semibold text-text-secondary">Country</label>
            <input type="text" name="country" value="<?= htmlspecialchars($greenhouse['country'] ?? '', ENT_QUOTES) ?>" class="mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          </div>

          <div>
            <label class="text-xs font-semibold text-text-secondary">City</label>
            <input type="text" name="city" value="<?= htmlspecialchars($greenhouse['city'] ?? '', ENT_QUOTES) ?>" class="mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm focus:border-primary focus:outline-none">
          </div>

          <div class="sm:col-span-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-green-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-glow transition-all hover:brightness-105 active:scale-[0.98]">
              <?= $greenhouse ? 'Save Changes' : 'Create Greenhouse' ?>
            </button>
          </div>
        </form>
      </div>

    </div>
  </main>
</div>

</body>
</html>
