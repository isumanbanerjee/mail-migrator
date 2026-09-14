<?php use App\Support\Csrf; ?>
<h1>Login</h1>
<?php if (!empty($error)): ?><div class="error"><?= $e($error) ?></div><?php endif; ?>
<form method="post" action="/login">
  <input type="hidden" name="_csrf" value="<?= $e(Csrf::token($session)) ?>">
  <label>Email <input name="email" type="email" value="<?= $e($old['email'] ?? '') ?>"></label>
  <label>Password <input name="password" type="password"></label>
  <button type="submit">Log in</button>
</form>
<p><a href="/register">Need an account? Register</a></p>
