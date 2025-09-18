<?php
$theme = 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3601799131755099"
      crossorigin="anonymous"></script>
  <script>
    document.documentElement.dataset.theme = localStorage.getItem('theme') || document.documentElement.dataset.theme;
  </script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap">
  <script type="module" src="/assets/3d-buttons.js" defer></script>
