<?php use App\Support\Csrf; $t = Csrf::token($session); $jid = (int) $job['id']; ?>
<div x-data="jobProgress(<?= $jid ?>, '<?= $e($job['state']) ?>')" x-init="init()">
  <div class="job-head">
    <h1><?= $e($job['name']) ?></h1>
    <span class="badge" :class="'badge--' + stateClass(state)" x-text="state"></span>
    <div class="actions actions--inline">
      <?php if ($job['state'] === 'draft'): ?>
      <form method="post" action="/jobs/<?= $jid ?>/queue"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Queue for migration</button></form>
      <?php endif; ?>
      <?php if ($job['state'] === 'paused'): ?>
      <form method="post" action="/jobs/<?= $jid ?>/resume"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Resume</button></form>
      <?php endif; ?>
      <?php if ($job['state'] === 'running'): ?>
      <form method="post" action="/jobs/<?= $jid ?>/pause"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Pause</button></form>
      <?php endif; ?>
      <?php if (in_array($job['state'], ['draft','queued','running','paused'], true)): ?>
      <form method="post" action="/jobs/<?= $jid ?>/cancel"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Cancel</button></form>
      <?php endif; ?>
      <a class="btn" href="/jobs/<?= $jid ?>/edit">Edit</a>
      <form method="post" action="/jobs/<?= $jid ?>/delete" onsubmit="return confirm('Delete this job?')"><input type="hidden" name="_csrf" value="<?= $e($t) ?>"><button>Delete</button></form>
    </div>
  </div>

  <div class="error" x-show="limit" style="display:none">
    This job has a <strong>limit of <span x-text="limit"></span></strong> message(s), so each run copies at most that many.
    Clear the limit on the <a href="/jobs/<?= $jid ?>/edit">Edit</a> page to migrate the whole mailbox.
  </div>

  <div class="progress-wrap">
    <progress max="100" :value="percent"></progress>
    <span class="progress-label"><span x-text="percent"></span>% &middot;
      <span x-text="(counts.copied + counts.skipped).toLocaleString()"></span> /
      <span x-text="counts.total.toLocaleString()"></span> processed</span>
  </div>
  <p class="muted" x-show="currentFolder">Current folder: <strong x-text="currentFolder"></strong></p>

  <div class="tiles">
    <div class="tile tile--success"><span class="tile__n" x-text="counts.copied.toLocaleString()"></span><span class="tile__l">Copied</span></div>
    <div class="tile"><span class="tile__n" x-text="counts.skipped.toLocaleString()"></span><span class="tile__l">Skipped</span></div>
    <div class="tile tile--danger"><span class="tile__n" x-text="counts.failed.toLocaleString()"></span><span class="tile__l">Failed</span></div>
    <div class="tile tile--pending"><span class="tile__n" x-text="counts.pending.toLocaleString()"></span><span class="tile__l">Pending</span></div>
    <div class="tile"><span class="tile__n" x-text="counts.total.toLocaleString()"></span><span class="tile__l">Total</span></div>
  </div>

  <?php if (!empty($job['last_error'])): ?><div class="error">Last job error: <?= $e($job['last_error']) ?></div><?php endif; ?>

  <div class="list-bar">
    <div class="tabs">
      <template x-for="f in ['all','copied','failed','skipped','pending']" :key="f">
        <button type="button" class="tab" :class="{'tab--active': filter === f}" @click="setFilter(f)" x-text="f.charAt(0).toUpperCase()+f.slice(1)"></button>
      </template>
    </div>
    <label class="auto"><input type="checkbox" x-model="auto"> Auto-refresh</label>
    <button type="button" class="btn" @click="load()" :disabled="loading">
      <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
    </button>
  </div>

  <table class="msg-table">
    <thead><tr><th>Folder</th><th>Subject</th><th>Sent date</th><th>Size</th><th>Status</th><th>Info</th></tr></thead>
    <tbody>
      <template x-for="m in messages" :key="m.folder + ':' + m.uid">
        <tr>
          <td x-text="m.folder"></td>
          <td :title="m.subject || m.message_id" x-text="m.subject ? shorten(m.subject) : (m.message_id ? shorten(m.message_id) : ('uid ' + m.uid))"></td>
          <td class="muted nowrap" x-text="m.sent_date || '—'"></td>
          <td class="nowrap" x-text="humanSize(m.size)"></td>
          <td><span class="badge" :class="'badge--' + stateClass(m.status)" x-text="m.status"></span></td>
          <td class="info" :title="m.error" x-text="infoText(m)"></td>
        </tr>
      </template>
      <tr x-show="messages.length === 0"><td colspan="6" class="muted empty">No messages<span x-show="filter !== 'all'" x-text="' with status: ' + filter"></span> yet.</td></tr>
    </tbody>
  </table>

  <div class="pager">
    <button type="button" class="btn" @click="prev()" :disabled="page <= 1 || loading">&larr; Prev</button>
    <span class="muted">Page <span x-text="page"></span></span>
    <button type="button" class="btn" @click="next()" :disabled="!hasMore || loading">Next &rarr;</button>
  </div>

  <p><a href="/dashboard">&larr; Back to dashboard</a></p>
</div>

<script>
function jobProgress(id, initialState) {
  return {
    id, state: initialState, percent: 0, currentFolder: '',
    counts: { copied: 0, skipped: 0, failed: 0, pending: 0, total: 0 },
    messages: [], filter: 'all', page: 1, hasMore: false,
    limit: null, since: null, auto: true, loading: false, timer: null,
    init() {
      this.load();
      this.$watch('auto', v => v ? this.start() : this.stop());
      this.start();
    },
    start() { this.stop(); if (this.auto) this.timer = setInterval(() => this.load(true), 4000); },
    stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
    async load(silent = false) {
      if (this.loading) return;
      if (!silent) this.loading = true;
      try {
        const url = `/jobs/${this.id}/progress?status=${this.filter}&page=${this.page}`;
        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (r.ok) {
          const d = await r.json();
          this.state = d.state; this.percent = d.percent; this.currentFolder = d.current_folder || '';
          this.counts = d.counts; this.messages = d.messages; this.hasMore = d.has_more;
          this.limit = d.limit; this.since = d.since;
          if (['completed','canceled','failed','draft'].includes(d.state)) this.stop();
        }
      } finally { this.loading = false; }
    },
    setFilter(f) { this.filter = f; this.page = 1; this.load(); },
    next() { if (this.hasMore) { this.page++; this.load(); } },
    prev() { if (this.page > 1) { this.page--; this.load(); } },
    infoText(m) {
      if (m.status === 'failed') return m.error || 'failed';
      let s = m.unread ? 'Unread' : 'Read';
      if (m.attempts > 1) s += ' · attempts: ' + m.attempts;
      return s;
    },
    stateClass(s) {
      return ({ copied: 'success', completed: 'success', failed: 'danger', canceled: 'danger',
                running: 'primary', queued: 'primary', pending: 'pending', paused: 'pending',
                skipped: 'muted', draft: 'muted' })[s] || 'muted';
    },
    shorten(s) { return s.length > 48 ? s.slice(0, 45) + '…' : s; },
    humanSize(b) {
      if (!b) return '—';
      const u = ['B','KB','MB','GB']; let i = 0, n = b;
      while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
      return (i ? n.toFixed(1) : n) + ' ' + u[i];
    },
  };
}
</script>
