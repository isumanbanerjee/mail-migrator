<?php use App\Support\Csrf; $t = Csrf::token($session); ?>
<h1><?= $e($job['name']) ?></h1>
<p>State: <strong><?= $e($job['state']) ?></strong> | Progress: <?= $e($job['copied']) ?>/<?= $e($job['total_messages']) ?></p>
<?php if (!empty($job['last_error'])): ?><div class="error"><?= $e($job['last_error']) ?></div><?php endif; ?>
<div class="actions">
  <?php if ($job['state'] === 'draft'): ?>
  <form method="post" action="/jobs/<?= $e($job['id']) ?>/queue"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Queue for migration</button></form>
  <?php endif; ?>
  <?php if (in_array($job['state'], ['draft','queued'], true)): ?>
  <form method="post" action="/jobs/<?= $e($job['id']) ?>/cancel"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Cancel</button></form>
  <?php endif; ?>
  <a href="/jobs/<?= $e($job['id']) ?>/edit">Edit</a>
  <form method="post" action="/jobs/<?= $e($job['id']) ?>/delete" onsubmit="return confirm('Delete this job?')"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Delete</button></form>
</div>
<p><a href="/dashboard">&larr; Back to dashboard</a></p>
