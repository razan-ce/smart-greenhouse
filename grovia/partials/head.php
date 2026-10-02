<?php
// Shared <head> for every app-shell page (dashboard.php, ...).
// Set $gh_page_title before including this file.
$gh_page_title = $gh_page_title ?? 'Grovia';
$gh_extra_head = $gh_extra_head ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($gh_page_title) ?></title>
<meta name="theme-color" content="#22C55E">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Inter', 'sans-serif'] },
        colors: {
          background: '#FFFFFF',
          border: '#E5E7EB',
          primary: { DEFAULT: '#22C55E', foreground: '#FFFFFF' },
          secondary: { DEFAULT: '#16A34A', foreground: '#FFFFFF' },
          light: { DEFAULT: '#DCFCE7' },
          text: { primary: '#111827', secondary: '#6B7280' },
        },
        borderRadius: { '2xl': '24px', xl: '16px' },
        boxShadow: {
          soft: '0 2px 8px rgba(17,24,39,0.04), 0 8px 24px rgba(17,24,39,0.06)',
          'soft-lg': '0 4px 16px rgba(17,24,39,0.06), 0 16px 40px rgba(17,24,39,0.08)',
          glow: '0 0 0 1px rgba(34,197,94,0.08), 0 8px 24px rgba(34,197,94,0.18)',
        },
        backgroundImage: {
          'green-gradient': 'linear-gradient(135deg, #22C55E 0%, #16A34A 100%)',
          'mesh-green': 'radial-gradient(at 20% 20%, rgba(34,197,94,0.10) 0px, transparent 50%), radial-gradient(at 80% 0%, rgba(22,163,74,0.08) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(220,252,231,0.6) 0px, transparent 50%)',
        },
      },
    },
  };
</script>

<link rel="stylesheet" href="css/dashboard-app.css?v=2">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<?= $gh_extra_head ?>
</head>
