<?php
$theme = 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <script>
    document.documentElement.dataset.theme = localStorage.getItem('theme') || document.documentElement.dataset.theme;
  </script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap">
