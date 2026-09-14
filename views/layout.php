<?php
use App\Support\Csrf;
$navUser = $currentUser ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($title) ? $e($title) : 'Email Migration' ?></title>
  <link rel="stylesheet" href="/assets/app.css">
  <script defer src="/assets/alpine.min.js"></script>
</head>
<body>
  <nav class="site-nav">
    <div class="site-nav__inner">
      <a class="site-nav__brand" href="<?= $navUser ? '/dashboard' : '/login' ?>">Email Migration</a>
      <div class="site-nav__links">
        <?php if ($navUser): ?>
          <a href="/dashboard">Dashboard</a>
          <form method="post" action="/logout" class="site-nav__logout">
            <input type="hidden" name="_csrf" value="<?= $e(Csrf::token($session)) ?>">
            <button type="submit" class="link-button">Logout</button>
          </form>
        <?php else: ?>
          <a href="/login">Login</a>
          <a href="/register">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
  <div class="container">
    <main><?= $content ?></main>
  </div>
</body>
</html>
