<?php use App\Support\Csrf; $t = Csrf::token($session); ?>
<h1>Billing</h1>
<?php if (!empty($error)): ?><div class="error"><?= $e($error) ?></div><?php endif; ?>

<h2>Usage</h2>
<p>Jobs used: <?= (int) $usage['jobs'] ?> / <?= (int) $usage['jobLimit'] ?></p>
<p>Emails copied: <?= (int) $usage['emails'] ?> / <?= (int) $usage['emailLimit'] ?></p>

<h2>Your entitlement</h2>
<?php if ((int) ($entitlement['unlimited'] ?? 0) === 1): ?>
  <p>Unlimited access</p>
<?php elseif (!empty($entitlement['subscription_until'])): ?>
  <p>Subscription active until <?= $e($entitlement['subscription_until']) ?></p>
<?php else: ?>
  <p>Credits remaining: <?= (int) ($entitlement['credits'] ?? 0) ?></p>
<?php endif; ?>

<h2>Buy more</h2>
<?php if (empty($products)): ?>
  <p>No products are currently available for purchase.</p>
<?php else: ?>
  <?php foreach ($products as $product => $price): ?>
    <?php foreach ($gatewayNames as $gateway): ?>
      <form method="post" action="/billing/checkout" class="billing-form">
        <input type="hidden" name="_csrf" value="<?= $e($t) ?>">
        <input type="hidden" name="product" value="<?= $e($product) ?>">
        <input type="hidden" name="gateway" value="<?= $e($gateway) ?>">
        <button type="submit"><?= $e(ucwords(str_replace('_', ' ', $product))) ?> &mdash; <?= $e($price) ?> <?= $e($currency) ?> via <?= $e(ucfirst($gateway)) ?></button>
      </form>
    <?php endforeach; ?>
  <?php endforeach; ?>
<?php endif; ?>
