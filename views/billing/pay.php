<h1>Complete payment</h1>
<p>Gateway: <?= $e($gateway) ?></p>
<p>Product: <?= $e($product) ?></p>
<?php if (!empty($order['extra'])): ?>
  <pre><?= $e(json_encode($order['extra'])) ?></pre>
<?php endif; ?>
<p><a href="/billing">&larr; Back to billing</a></p>
