<?php use App\Support\Csrf; $action = $job ? '/jobs/' . $job['id'] : '/jobs'; ?>
<h1><?= $e($title) ?></h1>
<?php if (!empty($errors)): ?><div class="error">Please correct the highlighted fields.</div><?php endif; ?>
<form method="post" action="<?= $e($action) ?>" x-data="{testing:false, result:null}">
  <input type="hidden" name="_csrf" value="<?= $e(Csrf::token($session)) ?>">
  <label>Name <input name="name" value="<?= $e($old['name'] ?? '') ?>"></label>
  <fieldset><legend>Source</legend>
    <input name="source_host" placeholder="Host" value="<?= $e($old['source_host'] ?? '') ?>">
    <input name="source_port" placeholder="Port" value="<?= $e($old['source_port'] ?? '993') ?>">
    <select name="source_encryption"><option>ssl</option><option>tls</option><option>none</option></select>
    <input name="source_username" placeholder="Username" value="<?= $e($old['source_username'] ?? '') ?>">
    <input name="source_password" type="password" placeholder="<?= $job ? 'Leave blank to keep' : 'Password' ?>">
  </fieldset>
  <fieldset><legend>Destination</legend>
    <input name="dest_host" placeholder="Host" value="<?= $e($old['dest_host'] ?? '') ?>">
    <input name="dest_port" placeholder="Port" value="<?= $e($old['dest_port'] ?? '993') ?>">
    <select name="dest_encryption"><option>ssl</option><option>tls</option><option>none</option></select>
    <input name="dest_username" placeholder="Username" value="<?= $e($old['dest_username'] ?? '') ?>">
    <input name="dest_password" type="password" placeholder="<?= $job ? 'Leave blank to keep' : 'Password' ?>">
  </fieldset>
  <button type="button" @click="testing=true; result=null; fetch('/jobs/test-connection',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams([...new FormData($el.closest('form'))]).toString()}).then(r=>r.json()).then(d=>{result=d;testing=false})">Test Connection</button>
  <template x-if="result"><pre x-text="JSON.stringify(result,null,2)"></pre></template>
  <button type="submit"><?= $job ? 'Save changes' : 'Create job' ?></button>
</form>
