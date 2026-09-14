<?php use App\Support\Csrf; ?>
<h1>Register</h1>
<?php if (!empty($errors)): ?><div class="error">Please fix the errors below.</div><?php endif; ?>
<form method="post" action="/register">
  <input type="hidden" name="_csrf" value="<?= $e(Csrf::token($session)) ?>">
  <label>Name <input name="name" value="<?= $e($old['name'] ?? '') ?>"></label>
  <?php if (isset($errors['name'])): ?><span class="field-error">Required</span><?php endif; ?>
  <label>Email <input name="email" type="email" value="<?= $e($old['email'] ?? '') ?>"></label>
  <?php if (isset($errors['email'])): ?><span class="field-error">Invalid or taken</span><?php endif; ?>
  <label>Password <input name="password" type="password"></label>
  <?php if (isset($errors['password'])): ?><span class="field-error">Min 8 chars</span><?php endif; ?>
  <button type="submit">Create account</button>
</form>
<p><a href="/login">Already have an account? Log in</a></p>
