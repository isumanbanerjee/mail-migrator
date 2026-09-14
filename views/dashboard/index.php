<h1>Your migration jobs</h1>
<p><a href="/jobs/create">+ New job</a></p>
<?php if (empty($jobs)): ?>
  <p>No jobs yet. Create your first one.</p>
<?php else: ?>
<table>
  <thead><tr><th>Name</th><th>State</th><th>Progress</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($jobs as $job): ?>
    <tr x-data="{p: <?= (int) $job['percent'] ?>, state: '<?= $e($job['state']) ?>', copied: <?= (int) $job['copied'] ?>, total: <?= (int) $job['total_messages'] ?>}"
        x-init="setInterval(async () => { const r = await fetch('/jobs/<?= (int) $job['id'] ?>/progress'); if (r.ok) { const d = await r.json(); p=d.percent; state=d.state; copied=d.copied; total=d.total; } }, 4000)">
      <td><a href="/jobs/<?= (int) $job['id'] ?>"><?= $e($job['name']) ?></a></td>
      <td x-text="state"></td>
      <td><progress max="100" :value="p"></progress> <span x-text="copied + '/' + total"></span></td>
      <td><a href="/jobs/<?= (int) $job['id'] ?>">View</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
