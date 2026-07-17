<?php
error_reporting(E_ALL);
require_once '../connection.php';
require_once 'class/dbClass.php';
require_once 'includes/security_helpers.php';

$dbClass = new dbClass();
require_once 'security.php';
$pagename = 'Biz Info';

$userId  = intval($_SESSION['usuario']['id'] ?? 0);
$isAdmin = intval($_SESSION['usuario']['level'] ?? 99) <= 1;
if (!$isAdmin && !can('vault', 'view')) {
    header('Location: dashboard.php');
    exit;
}

// Tab toggle: ?tab=info (default) | accounts | software | vault
$tab = $_GET['tab'] ?? 'info';
if (!in_array($tab, ['info', 'accounts', 'software', 'vault'], true)) $tab = 'info';

// Vault visibility: super admin OR explicit grant
$canSeeVault = $isAdmin;
if (!$canSeeVault && $userId > 0) {
    $stmt = mysqli_prepare($dbnew, "SELECT 1 FROM vault_secrets_access WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $canSeeVault = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) !== null;
        mysqli_stmt_close($stmt);
    }
}
if ($tab === 'vault' && !$canSeeVault) $tab = 'info';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pagename); ?></title>
    <?php include('header.php'); ?>
    <style>
      .mk-card { background:#fff; border:1px solid #E5E7EB; border-radius:8px; padding:16px; margin-bottom:16px; }
      .mk-tabs { display:flex; gap:0; border-bottom:1px solid #E5E7EB; margin-bottom:20px; overflow-x:auto; }
      .mk-tab { padding:10px 16px; cursor:pointer; color:#6B7280; border-bottom:2px solid transparent; white-space:nowrap; font-size:14px; font-weight:500; text-decoration:none; }
      .mk-tab.active { color:#3B82F6; border-bottom-color:#3B82F6; }
      .mk-view { display:none; }
      .mk-view.active { display:block; }

      table.grid { width:100%; border-collapse:collapse; font-size:14px; }
      table.grid thead th { background:#F9FAFB; border:1px solid #E5E7EB; padding:8px 10px; text-align:left; font-weight:600; color:#374151; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }
      table.grid tbody td { border:1px solid #E5E7EB; padding:0; vertical-align:top; }
      table.grid tbody td.actions { padding:6px; white-space:nowrap; width:140px; text-align:right; }
      table.grid tbody td input.cell, table.grid tbody td textarea.cell {
        width:100%; border:0; padding:8px 10px; font-size:14px; font-family:inherit;
        background:transparent; outline:none; resize:vertical;
      }
      table.grid tbody td input.cell:focus, table.grid tbody td textarea.cell:focus { background:#FEF3C7; }
      table.grid tbody tr.dirty td input.cell, table.grid tbody tr.dirty td textarea.cell { background:#FEF9C3; }
      table.grid tbody tr.dirty td.actions { background:#FEF9C3; }
      .drag-handle { cursor:grab; color:#9CA3AF; padding:0 6px; user-select:none; text-align:center; width:30px; }
      .drag-handle:hover { color:#6B7280; }
      tr.dragging { opacity:0.4; }
      tr.drag-over { border-top:2px solid #3B82F6 !important; }

      .pw-cell { display:flex; align-items:center; }
      .pw-cell input { font-family:monospace; }
      .pw-toggle { background:none; border:0; color:#6B7280; padding:4px 8px; cursor:pointer; }
      .pw-toggle:hover { color:#3B82F6; }
      .link-cell a { color:#3B82F6; text-decoration:none; padding:0 8px; }
      .link-cell a:hover { text-decoration:underline; }

      /* Block printing — hide everything when printing */
      @media print {
        body * { visibility: hidden !important; }
        body::after {
          content: "Printing disabled. This page contains confidential information.";
          visibility: visible !important;
          position: fixed; top: 40%; left: 0; right: 0;
          text-align: center; font-size: 24px; color: #000;
        }
      }
    </style>
</head>
<body>
    <?php include('menu.php'); ?>
    <div class="container-fluid p-0">

        <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap">
          <h1 class="h3 mb-0">Marketing</h1>
        </div>

        <div class="mk-tabs">
          <a class="mk-tab <?php echo $tab==='info'?'active':''; ?>" href="?tab=info" data-tab="info">Biz Info</a>
          <a class="mk-tab <?php echo $tab==='accounts'?'active':''; ?>" href="?tab=accounts" data-tab="accounts">Company Accounts</a>
          <a class="mk-tab <?php echo $tab==='software'?'active':''; ?>" href="?tab=software" data-tab="software">Software Accounts</a>
          <?php if ($canSeeVault): ?>
            <a class="mk-tab <?php echo $tab==='vault'?'active':''; ?>" href="?tab=vault" data-tab="vault"><i class="fa fa-shield"></i> Vault</a>
          <?php endif; ?>
        </div>

        <!-- INFO TAB -->
        <div class="mk-view <?php echo $tab==='info'?'active':''; ?>" id="view-info">
          <div class="mk-card">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="mb-0">Biz Info</h5>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="info-add"><i class="fa fa-plus"></i> Add Row</button>
                <button class="btn btn-sm btn-primary" id="info-save-all"><i class="fa fa-save"></i> Save Changes</button>
              </div>
            </div>
            <p class="text-muted small mb-3">Excel-style grid for company data. Click any cell to edit. Tab/Enter to save row. Drag handle to reorder.</p>
            <div class="table-responsive">
              <table class="grid" id="info-grid">
                <thead><tr>
                  <th style="width:30px"></th>
                  <th style="width:30%">Label</th>
                  <th style="width:40%">Value</th>
                  <th style="width:30%">Notes (optional)</th>
                  <th style="width:120px;text-align:right">Actions</th>
                </tr></thead>
                <tbody id="info-body"><tr><td colspan="5" class="text-muted small p-3">Loading…</td></tr></tbody>
              </table>
            </div>
            <div class="small text-muted mt-2" id="info-status"></div>
          </div>
        </div>

        <!-- ACCOUNTS TAB -->
        <div class="mk-view <?php echo $tab==='accounts'?'active':''; ?>" id="view-accounts">
          <div class="mk-card">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="mb-0">Company Accounts</h5>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="acct-add"><i class="fa fa-plus"></i> Add Account</button>
                <button class="btn btn-sm btn-primary" id="acct-save-all"><i class="fa fa-save"></i> Save Changes</button>
              </div>
            </div>
            <p class="text-muted small mb-3">Passwords encrypted at rest (AES-256). Click 👁 to reveal stored password. Click <i class="fa fa-lock text-warning"></i> to mark a row Private — only the creator and super admin (level ≤ 1) can see/edit it. Leave password blank to keep current value when editing.</p>
            <div class="table-responsive">
              <table class="grid" id="acct-grid">
                <thead><tr>
                  <th style="width:30px"></th>
                  <th style="width:18%">Name</th>
                  <th style="width:18%">Username</th>
                  <th style="width:18%">Password</th>
                  <th style="width:50px;text-align:center" title="Private — only creator + super admin can see">Private</th>
                  <th style="width:18%">Link</th>
                  <th style="width:18%">Notes</th>
                  <th style="width:140px;text-align:right">Actions</th>
                </tr></thead>
                <tbody id="acct-body"><tr><td colspan="8" class="text-muted small p-3">Loading…</td></tr></tbody>
              </table>
            </div>
            <div class="small text-muted mt-2" id="acct-status"></div>
          </div>
        </div>

        <!-- SOFTWARE ACCOUNTS TAB -->
        <div class="mk-view <?php echo $tab==='software'?'active':''; ?>" id="view-software">
          <div class="mk-card">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="mb-0">Software Accounts</h5>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="sw-add"><i class="fa fa-plus"></i> Add Software</button>
                <button class="btn btn-sm btn-primary" id="sw-save-all"><i class="fa fa-save"></i> Save Changes</button>
              </div>
            </div>
            <p class="text-muted small mb-3">SaaS / software credentials. Same shape as Company Accounts. Passwords encrypted at rest. Mark a row Private (<i class="fa fa-lock text-warning"></i>) to limit visibility to creator + super admin.</p>
            <div class="table-responsive">
              <table class="grid" id="sw-grid">
                <thead><tr>
                  <th style="width:30px"></th>
                  <th style="width:18%">Name</th>
                  <th style="width:18%">Username</th>
                  <th style="width:18%">Password</th>
                  <th style="width:50px;text-align:center">Private</th>
                  <th style="width:18%">Link</th>
                  <th style="width:18%">Notes</th>
                  <th style="width:140px;text-align:right">Actions</th>
                </tr></thead>
                <tbody id="sw-body"><tr><td colspan="8" class="text-muted small p-3">Loading…</td></tr></tbody>
              </table>
            </div>
            <div class="small text-muted mt-2" id="sw-status"></div>
          </div>
        </div>

        <?php if ($canSeeVault): ?>
        <!-- VAULT TAB -->
        <div class="mk-view <?php echo $tab==='vault'?'active':''; ?>" id="view-vault">
          <div class="mk-card">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <h5 class="mb-0"><i class="fa fa-shield text-warning"></i> Vault</h5>
              <div class="d-flex gap-2">
                <?php if ($isAdmin): ?>
                  <button class="btn btn-sm btn-outline-warning" id="vault-access-btn" data-bs-toggle="modal" data-bs-target="#vaultAccessModal"><i class="fa fa-users"></i> Manage Access</button>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-secondary" id="vault-add"><i class="fa fa-plus"></i> Add Entry</button>
                <button class="btn btn-sm btn-primary" id="vault-save-all"><i class="fa fa-save"></i> Save Changes</button>
              </div>
            </div>
            <p class="text-muted small mb-3"><strong>Restricted.</strong> Visible only to super admin and explicitly granted users. Passwords encrypted at rest (AES-256).</p>
            <div class="table-responsive">
              <table class="grid" id="vault-grid">
                <thead><tr>
                  <th style="width:30px"></th>
                  <th style="width:18%">Name</th>
                  <th style="width:18%">Username</th>
                  <th style="width:18%">Password</th>
                  <th style="width:18%">Link</th>
                  <th style="width:18%">Notes</th>
                  <th style="width:140px;text-align:right">Actions</th>
                </tr></thead>
                <tbody id="vault-body"><tr><td colspan="7" class="text-muted small p-3">Loading…</td></tr></tbody>
              </table>
            </div>
            <div class="small text-muted mt-2" id="vault-status"></div>
          </div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- VAULT ACCESS MODAL (admin only) -->
        <div class="modal fade" id="vaultAccessModal" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-users"></i> Manage Vault Access</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <p class="text-muted small">Toggle to grant or revoke Vault access. Super admins always have access regardless of this list.</p>
                <div id="vault-access-list" class="small">Loading…</div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    <?php include('footer.php'); ?>

<script>
const CSRF = '<?php echo e(csrf_token()); ?>';
const $ = sel => document.querySelector(sel);

// Disable right-click context menu
document.addEventListener('contextmenu', e => e.preventDefault());

// Block keyboard print (Ctrl+P / Cmd+P) and Save (Ctrl+S)
document.addEventListener('keydown', e => {
  const key = (e.key || '').toLowerCase();
  if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 's')) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  }
});

// Block print via window.print
window.print = () => false;
window.addEventListener('beforeprint', e => { e.preventDefault(); return false; });
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function setStatus(elId, msg, kind='') {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent = msg;
  el.className = 'small mt-2 ' + (kind === 'ok' ? 'text-success' : (kind === 'error' ? 'text-danger' : 'text-muted'));
  if (kind === 'ok') setTimeout(() => { if (el.textContent === msg) el.textContent = ''; }, 2000);
}

// ============================================================
// COMPANY INFORMATION
// ============================================================
const INFO_API = 'api/vault_info.php';
let INFO_ROWS = [];

async function infoLoad() {
  const r = await fetch(INFO_API, { credentials:'same-origin' });
  const j = await r.json();
  INFO_ROWS = j.rows || [];
  infoRender();
}
function infoRender() {
  const tbody = $('#info-body');
  if (!INFO_ROWS.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted small p-3">No data — click <strong>Add Row</strong> to start.</td></tr>';
    return;
  }
  tbody.innerHTML = INFO_ROWS.map((r, i) => `<tr data-id="${r.id}" data-idx="${i}" draggable="true">
    <td class="drag-handle"><i class="fa fa-bars"></i></td>
    <td><input class="cell" data-field="label" value="${esc(r.label)}" placeholder="Field name"></td>
    <td><input class="cell" data-field="value" value="${esc(r.value)}" placeholder="Value"></td>
    <td><input class="cell" data-field="notes" value="${esc(r.notes)}" placeholder=""></td>
    <td class="actions">
      <button class="btn btn-sm btn-outline-success" data-act="save" title="Save"><i class="fa fa-check"></i></button>
      <button class="btn btn-sm btn-outline-danger" data-act="delete" title="Delete"><i class="fa fa-trash"></i></button>
    </td>
  </tr>`).join('');
  bindGridEvents('#info-body', { rowsRef: () => INFO_ROWS, save: infoSaveRow, del: infoDeleteRow, reorder: infoSaveOrder, render: infoRender });
}
async function infoSaveRow(tr) {
  const id = tr.dataset.id;
  const label = tr.querySelector('input[data-field="label"]').value.trim();
  const value = tr.querySelector('input[data-field="value"]').value;
  const notes = tr.querySelector('input[data-field="notes"]').value;
  if (!label) { setStatus('info-status', 'Label required', 'error'); return; }
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'upsert');
  if (id && id !== 'new') fd.append('id', id);
  fd.append('label', label); fd.append('value', value); fd.append('notes', notes);
  fd.append('sort_order', tr.dataset.idx);
  setStatus('info-status', 'Saving…');
  const r = await fetch(INFO_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    if (j.id) tr.dataset.id = j.id;
    tr.classList.remove('dirty');
    INFO_ROWS[parseInt(tr.dataset.idx)] = { id: parseInt(j.id||id), label, value, notes, sort_order: parseInt(tr.dataset.idx) };
    setStatus('info-status', '✓ Saved', 'ok');
  } else setStatus('info-status', '✗ ' + (j.error || 'Save failed'), 'error');
}
async function infoDeleteRow(tr) {
  const id = tr.dataset.id;
  const label = tr.querySelector('input[data-field="label"]').value.trim() || '(empty)';
  if (!confirm('Delete row "' + label + '"?')) return;
  if (!id || id === 'new') {
    INFO_ROWS.splice(parseInt(tr.dataset.idx), 1);
    infoRender(); return;
  }
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'delete'); fd.append('id', id);
  const r = await fetch(INFO_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    INFO_ROWS = INFO_ROWS.filter(x => String(x.id) !== String(id));
    infoRender(); setStatus('info-status', '✓ Deleted', 'ok');
  } else setStatus('info-status', '✗ Delete failed', 'error');
}
async function infoSaveOrder() {
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'reorder');
  INFO_ROWS.forEach((r, i) => { fd.append('ids[]', r.id); r.sort_order = i; });
  await fetch(INFO_API, { method:'POST', body:fd, credentials:'same-origin' });
  setStatus('info-status', '✓ Reordered', 'ok');
}
$('#info-add').addEventListener('click', () => {
  INFO_ROWS.push({ id: 'new', label: '', value: '', notes: '', sort_order: INFO_ROWS.length });
  infoRender();
  const trs = document.querySelectorAll('#info-body tr');
  trs[trs.length-1]?.querySelector('input[data-field="label"]')?.focus();
});
$('#info-save-all').addEventListener('click', async () => {
  const dirty = document.querySelectorAll('#info-body tr.dirty');
  if (!dirty.length) { setStatus('info-status', 'Nothing to save'); return; }
  for (const tr of dirty) await infoSaveRow(tr);
  setStatus('info-status', '✓ All saved', 'ok');
});

// ============================================================
// COMPANY ACCOUNTS
// ============================================================
const ACCT_API = 'api/vault_company_accounts.php';
let ACCT_ROWS = [];

async function acctLoad() {
  const r = await fetch(ACCT_API, { credentials:'same-origin' });
  const j = await r.json();
  ACCT_ROWS = j.rows || [];
  acctRender();
}
function acctRender() {
  const tbody = $('#acct-body');
  if (!ACCT_ROWS.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-muted small p-3">No accounts — click <strong>Add Account</strong> to start.</td></tr>';
    return;
  }
  tbody.innerHTML = ACCT_ROWS.map((r, i) => {
    const linkPreview = r.link ? `<a href="${esc(r.link)}" target="_blank" rel="noopener" title="${esc(r.link)}"><i class="fa fa-external-link"></i></a>` : '';
    const pwPlaceholder = r.has_password ? '••••••••' : '';
    const privChecked = r.is_private ? 'checked' : '';
    const privIcon = r.is_private ? 'fa-lock text-warning' : 'fa-unlock text-muted';
    return `<tr data-id="${r.id}" data-idx="${i}" data-haspw="${r.has_password ? 1 : 0}" draggable="true">
      <td class="drag-handle"><i class="fa fa-bars"></i></td>
      <td><input class="cell" data-field="name" value="${esc(r.name)}" placeholder="Account name"></td>
      <td><input class="cell" data-field="username" value="${esc(r.username)}" placeholder="username / email"></td>
      <td><div class="pw-cell">
        <input class="cell" data-field="password" type="password" value="" placeholder="${pwPlaceholder}" autocomplete="new-password">
        <button class="pw-toggle" data-act="reveal" title="Show stored password" type="button"><i class="fa fa-eye"></i></button>
      </div></td>
      <td style="text-align:center;padding:6px">
        <label class="priv-toggle" title="Private: only creator + super admin can see this row" style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;margin:0">
          <input type="checkbox" data-field="is_private" ${privChecked} style="display:none">
          <i class="fa ${privIcon}" style="font-size:16px"></i>
        </label>
      </td>
      <td><div class="d-flex align-items-center">
        <input class="cell" data-field="link" value="${esc(r.link)}" placeholder="https://...">
        ${linkPreview ? '<span class="link-cell">' + linkPreview + '</span>' : ''}
      </div></td>
      <td><input class="cell" data-field="notes" value="${esc(r.notes)}" placeholder=""></td>
      <td class="actions">
        <button class="btn btn-sm btn-outline-success" data-act="save" title="Save"><i class="fa fa-check"></i></button>
        <button class="btn btn-sm btn-outline-danger" data-act="delete" title="Delete"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
  bindGridEvents('#acct-body', { rowsRef: () => ACCT_ROWS, save: acctSaveRow, del: acctDeleteRow, reorder: acctSaveOrder, render: acctRender, extra: acctExtraEvents });
}
function acctExtraEvents(tr) {
  // Private toggle: change icon + mark dirty + auto-save
  tr.querySelector('input[data-field="is_private"]')?.addEventListener('change', e => {
    const ic = tr.querySelector('.priv-toggle i');
    if (e.target.checked) { ic.className = 'fa fa-lock text-warning'; }
    else { ic.className = 'fa fa-unlock text-muted'; }
    tr.classList.add('dirty');
  });
  tr.querySelector('[data-act="reveal"]')?.addEventListener('click', async () => {
    const id = tr.dataset.id;
    if (!id || id === 'new') return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF); fd.append('action', 'reveal'); fd.append('id', id);
    const r = await fetch(ACCT_API, { method:'POST', body:fd, credentials:'same-origin' });
    const j = await r.json();
    if (j.success) {
      const inp = tr.querySelector('input[data-field="password"]');
      inp.type = 'text';
      inp.value = j.password || '';
      // Auto-hide after 30s
      setTimeout(() => { inp.type = 'password'; if (!tr.classList.contains('dirty')) inp.value = ''; }, 30000);
    } else setStatus('acct-status', '✗ Reveal failed', 'error');
  });
}
async function acctSaveRow(tr) {
  const id = tr.dataset.id;
  const name     = tr.querySelector('input[data-field="name"]').value.trim();
  const username = tr.querySelector('input[data-field="username"]').value;
  const password = tr.querySelector('input[data-field="password"]').value;
  const link     = tr.querySelector('input[data-field="link"]').value.trim();
  const notes    = tr.querySelector('input[data-field="notes"]').value;
  if (!name) { setStatus('acct-status', 'Name required', 'error'); return; }
  const isPrivate = tr.querySelector('input[data-field="is_private"]')?.checked ? 1 : 0;
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'upsert');
  if (id && id !== 'new') fd.append('id', id);
  fd.append('name', name); fd.append('username', username); fd.append('password', password);
  fd.append('link', link); fd.append('notes', notes);
  fd.append('is_private', isPrivate);
  fd.append('sort_order', tr.dataset.idx);
  setStatus('acct-status', 'Saving…');
  const r = await fetch(ACCT_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    if (j.id) tr.dataset.id = j.id;
    tr.classList.remove('dirty');
    // Clear password field after save (re-fetch state)
    tr.querySelector('input[data-field="password"]').value = '';
    if (password !== '') tr.dataset.haspw = '1';
    if (password !== '') tr.querySelector('input[data-field="password"]').placeholder = '••••••••';
    const idx = parseInt(tr.dataset.idx);
    ACCT_ROWS[idx] = { id: parseInt(j.id||id), name, username, link, notes, is_private: isPrivate, sort_order: idx, has_password: tr.dataset.haspw === '1', password: '' };
    setStatus('acct-status', '✓ Saved', 'ok');
  } else setStatus('acct-status', '✗ ' + (j.error || 'Save failed'), 'error');
}
async function acctDeleteRow(tr) {
  const id = tr.dataset.id;
  const name = tr.querySelector('input[data-field="name"]').value.trim() || '(empty)';
  if (!confirm('Delete account "' + name + '"?')) return;
  if (!id || id === 'new') {
    ACCT_ROWS.splice(parseInt(tr.dataset.idx), 1);
    acctRender(); return;
  }
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'delete'); fd.append('id', id);
  const r = await fetch(ACCT_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    ACCT_ROWS = ACCT_ROWS.filter(x => String(x.id) !== String(id));
    acctRender(); setStatus('acct-status', '✓ Deleted', 'ok');
  } else setStatus('acct-status', '✗ Delete failed', 'error');
}
async function acctSaveOrder() {
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'reorder');
  ACCT_ROWS.forEach((r, i) => { fd.append('ids[]', r.id); r.sort_order = i; });
  await fetch(ACCT_API, { method:'POST', body:fd, credentials:'same-origin' });
  setStatus('acct-status', '✓ Reordered', 'ok');
}
$('#acct-add').addEventListener('click', () => {
  ACCT_ROWS.push({ id: 'new', name: '', username: '', password: '', link: '', notes: '', is_private: 0, sort_order: ACCT_ROWS.length, has_password: false });
  acctRender();
  const trs = document.querySelectorAll('#acct-body tr');
  trs[trs.length-1]?.querySelector('input[data-field="name"]')?.focus();
});
$('#acct-save-all').addEventListener('click', async () => {
  const dirty = document.querySelectorAll('#acct-body tr.dirty');
  if (!dirty.length) { setStatus('acct-status', 'Nothing to save'); return; }
  for (const tr of dirty) await acctSaveRow(tr);
  setStatus('acct-status', '✓ All saved', 'ok');
});

// ============================================================
// SOFTWARE ACCOUNTS
// ============================================================
const SW_API = 'api/vault_software.php';
let SW_ROWS = [];

async function swLoad() {
  const r = await fetch(SW_API, { credentials:'same-origin' });
  if (r.status === 403) return;
  const j = await r.json();
  SW_ROWS = j.rows || [];
  swRender();
}
function swRender() {
  const tbody = $('#sw-body');
  if (!tbody) return;
  if (!SW_ROWS.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-muted small p-3">No software accounts — click <strong>Add Software</strong> to start.</td></tr>';
    return;
  }
  tbody.innerHTML = SW_ROWS.map((r, i) => {
    const linkPreview = r.link ? `<a href="${esc(r.link)}" target="_blank" rel="noopener" title="${esc(r.link)}"><i class="fa fa-external-link"></i></a>` : '';
    const pwPlaceholder = r.has_password ? '••••••••' : '';
    const privChecked = r.is_private ? 'checked' : '';
    const privIcon = r.is_private ? 'fa-lock text-warning' : 'fa-unlock text-muted';
    return `<tr data-id="${r.id}" data-idx="${i}" data-haspw="${r.has_password ? 1 : 0}" draggable="true">
      <td class="drag-handle"><i class="fa fa-bars"></i></td>
      <td><input class="cell" data-field="name" value="${esc(r.name)}" placeholder="Software name"></td>
      <td><input class="cell" data-field="username" value="${esc(r.username)}" placeholder="username / email"></td>
      <td><div class="pw-cell">
        <input class="cell" data-field="password" type="password" value="" placeholder="${pwPlaceholder}" autocomplete="new-password">
        <button class="pw-toggle" data-act="reveal" title="Show stored password" type="button"><i class="fa fa-eye"></i></button>
      </div></td>
      <td style="text-align:center;padding:6px">
        <label class="priv-toggle" title="Private: only creator + super admin can see this row" style="cursor:pointer;display:inline-flex;align-items:center;gap:4px;margin:0">
          <input type="checkbox" data-field="is_private" ${privChecked} style="display:none">
          <i class="fa ${privIcon}" style="font-size:16px"></i>
        </label>
      </td>
      <td><div class="d-flex align-items-center">
        <input class="cell" data-field="link" value="${esc(r.link)}" placeholder="https://...">
        ${linkPreview ? '<span class="link-cell">' + linkPreview + '</span>' : ''}
      </div></td>
      <td><input class="cell" data-field="notes" value="${esc(r.notes)}" placeholder=""></td>
      <td class="actions">
        <button class="btn btn-sm btn-outline-success" data-act="save" title="Save"><i class="fa fa-check"></i></button>
        <button class="btn btn-sm btn-outline-danger" data-act="delete" title="Delete"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
  bindGridEvents('#sw-body', { rowsRef: () => SW_ROWS, save: swSaveRow, del: swDeleteRow, reorder: swSaveOrder, render: swRender, extra: swExtraEvents });
}
function swExtraEvents(tr) {
  tr.querySelector('input[data-field="is_private"]')?.addEventListener('change', e => {
    const ic = tr.querySelector('.priv-toggle i');
    ic.className = e.target.checked ? 'fa fa-lock text-warning' : 'fa fa-unlock text-muted';
    tr.classList.add('dirty');
  });
  tr.querySelector('[data-act="reveal"]')?.addEventListener('click', async () => {
    const id = tr.dataset.id;
    if (!id || id === 'new') return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF); fd.append('action', 'reveal'); fd.append('id', id);
    const r = await fetch(SW_API, { method:'POST', body:fd, credentials:'same-origin' });
    const j = await r.json();
    if (j.success) {
      const inp = tr.querySelector('input[data-field="password"]');
      inp.type = 'text'; inp.value = j.password || '';
      setTimeout(() => { inp.type = 'password'; if (!tr.classList.contains('dirty')) inp.value = ''; }, 30000);
    } else setStatus('sw-status', '✗ Reveal failed', 'error');
  });
}
async function swSaveRow(tr) {
  const id = tr.dataset.id;
  const name = tr.querySelector('input[data-field="name"]').value.trim();
  const username = tr.querySelector('input[data-field="username"]').value;
  const password = tr.querySelector('input[data-field="password"]').value;
  const link = tr.querySelector('input[data-field="link"]').value.trim();
  const notes = tr.querySelector('input[data-field="notes"]').value;
  if (!name) { setStatus('sw-status', 'Name required', 'error'); return; }
  const isPrivate = tr.querySelector('input[data-field="is_private"]')?.checked ? 1 : 0;
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'upsert');
  if (id && id !== 'new') fd.append('id', id);
  fd.append('name', name); fd.append('username', username); fd.append('password', password);
  fd.append('link', link); fd.append('notes', notes);
  fd.append('is_private', isPrivate);
  fd.append('sort_order', tr.dataset.idx);
  setStatus('sw-status', 'Saving…');
  const r = await fetch(SW_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    if (j.id) tr.dataset.id = j.id;
    tr.classList.remove('dirty');
    tr.querySelector('input[data-field="password"]').value = '';
    if (password !== '') tr.dataset.haspw = '1';
    if (password !== '') tr.querySelector('input[data-field="password"]').placeholder = '••••••••';
    const idx = parseInt(tr.dataset.idx);
    SW_ROWS[idx] = { id: parseInt(j.id||id), name, username, link, notes, is_private: isPrivate, sort_order: idx, has_password: tr.dataset.haspw === '1', password: '' };
    setStatus('sw-status', '✓ Saved', 'ok');
  } else setStatus('sw-status', '✗ ' + (j.error || 'Save failed'), 'error');
}
async function swDeleteRow(tr) {
  const id = tr.dataset.id;
  const name = tr.querySelector('input[data-field="name"]').value.trim() || '(empty)';
  if (!confirm('Delete software "' + name + '"?')) return;
  if (!id || id === 'new') {
    SW_ROWS.splice(parseInt(tr.dataset.idx), 1);
    swRender(); return;
  }
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'delete'); fd.append('id', id);
  const r = await fetch(SW_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    SW_ROWS = SW_ROWS.filter(x => String(x.id) !== String(id));
    swRender(); setStatus('sw-status', '✓ Deleted', 'ok');
  } else setStatus('sw-status', '✗ Delete failed', 'error');
}
async function swSaveOrder() {
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'reorder');
  SW_ROWS.forEach((r, i) => { fd.append('ids[]', r.id); r.sort_order = i; });
  await fetch(SW_API, { method:'POST', body:fd, credentials:'same-origin' });
  setStatus('sw-status', '✓ Reordered', 'ok');
}
$('#sw-add')?.addEventListener('click', () => {
  SW_ROWS.push({ id: 'new', name: '', username: '', password: '', link: '', notes: '', is_private: 0, sort_order: SW_ROWS.length, has_password: false });
  swRender();
  const trs = document.querySelectorAll('#sw-body tr');
  trs[trs.length-1]?.querySelector('input[data-field="name"]')?.focus();
});
$('#sw-save-all')?.addEventListener('click', async () => {
  const dirty = document.querySelectorAll('#sw-body tr.dirty');
  if (!dirty.length) { setStatus('sw-status', 'Nothing to save'); return; }
  for (const tr of dirty) await swSaveRow(tr);
  setStatus('sw-status', '✓ All saved', 'ok');
});

// ============================================================
// VAULT (admin + granted users only)
// ============================================================
const VAULT_API = 'api/vault_secrets.php';
let VAULT_ROWS = [];
const CAN_SEE_VAULT = <?php echo $canSeeVault ? 'true' : 'false'; ?>;
const IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;

async function vaultLoad() {
  if (!CAN_SEE_VAULT) return;
  const r = await fetch(VAULT_API, { credentials:'same-origin' });
  if (r.status === 403) return;
  const j = await r.json();
  VAULT_ROWS = j.rows || [];
  vaultRender();
}
function vaultRender() {
  const tbody = $('#vault-body');
  if (!tbody) return;
  if (!VAULT_ROWS.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-muted small p-3">No vault entries — click <strong>Add Entry</strong> to start.</td></tr>';
    return;
  }
  tbody.innerHTML = VAULT_ROWS.map((r, i) => {
    const linkPreview = r.link ? `<a href="${esc(r.link)}" target="_blank" rel="noopener" title="${esc(r.link)}"><i class="fa fa-external-link"></i></a>` : '';
    const pwPlaceholder = r.has_password ? '••••••••' : '';
    return `<tr data-id="${r.id}" data-idx="${i}" data-haspw="${r.has_password ? 1 : 0}" draggable="true">
      <td class="drag-handle"><i class="fa fa-bars"></i></td>
      <td><input class="cell" data-field="name" value="${esc(r.name)}" placeholder="Entry name"></td>
      <td><input class="cell" data-field="username" value="${esc(r.username)}" placeholder="username / email"></td>
      <td><div class="pw-cell">
        <input class="cell" data-field="password" type="password" value="" placeholder="${pwPlaceholder}" autocomplete="new-password">
        <button class="pw-toggle" data-act="reveal" title="Show stored password" type="button"><i class="fa fa-eye"></i></button>
      </div></td>
      <td><div class="d-flex align-items-center">
        <input class="cell" data-field="link" value="${esc(r.link)}" placeholder="https://...">
        ${linkPreview ? '<span class="link-cell">' + linkPreview + '</span>' : ''}
      </div></td>
      <td><input class="cell" data-field="notes" value="${esc(r.notes)}" placeholder=""></td>
      <td class="actions">
        <button class="btn btn-sm btn-outline-success" data-act="save" title="Save"><i class="fa fa-check"></i></button>
        <button class="btn btn-sm btn-outline-danger" data-act="delete" title="Delete"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
  bindGridEvents('#vault-body', { rowsRef: () => VAULT_ROWS, save: vaultSaveRow, del: vaultDeleteRow, reorder: vaultSaveOrder, render: vaultRender, extra: vaultExtraEvents });
}
function vaultExtraEvents(tr) {
  tr.querySelector('[data-act="reveal"]')?.addEventListener('click', async () => {
    const id = tr.dataset.id;
    if (!id || id === 'new') return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF); fd.append('action', 'reveal'); fd.append('id', id);
    const r = await fetch(VAULT_API, { method:'POST', body:fd, credentials:'same-origin' });
    const j = await r.json();
    if (j.success) {
      const inp = tr.querySelector('input[data-field="password"]');
      inp.type = 'text'; inp.value = j.password || '';
      setTimeout(() => { inp.type = 'password'; if (!tr.classList.contains('dirty')) inp.value = ''; }, 30000);
    } else setStatus('vault-status', '✗ Reveal failed', 'error');
  });
}
async function vaultSaveRow(tr) {
  const id = tr.dataset.id;
  const name = tr.querySelector('input[data-field="name"]').value.trim();
  const username = tr.querySelector('input[data-field="username"]').value;
  const password = tr.querySelector('input[data-field="password"]').value;
  const link = tr.querySelector('input[data-field="link"]').value.trim();
  const notes = tr.querySelector('input[data-field="notes"]').value;
  if (!name) { setStatus('vault-status', 'Name required', 'error'); return; }
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'upsert');
  if (id && id !== 'new') fd.append('id', id);
  fd.append('name', name); fd.append('username', username); fd.append('password', password);
  fd.append('link', link); fd.append('notes', notes);
  fd.append('sort_order', tr.dataset.idx);
  setStatus('vault-status', 'Saving…');
  const r = await fetch(VAULT_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    if (j.id) tr.dataset.id = j.id;
    tr.classList.remove('dirty');
    tr.querySelector('input[data-field="password"]').value = '';
    if (password !== '') tr.dataset.haspw = '1';
    if (password !== '') tr.querySelector('input[data-field="password"]').placeholder = '••••••••';
    const idx = parseInt(tr.dataset.idx);
    VAULT_ROWS[idx] = { id: parseInt(j.id||id), name, username, link, notes, sort_order: idx, has_password: tr.dataset.haspw === '1', password: '' };
    setStatus('vault-status', '✓ Saved', 'ok');
  } else setStatus('vault-status', '✗ ' + (j.error || 'Save failed'), 'error');
}
async function vaultDeleteRow(tr) {
  const id = tr.dataset.id;
  const name = tr.querySelector('input[data-field="name"]').value.trim() || '(empty)';
  if (!confirm('Delete vault entry "' + name + '"?')) return;
  if (!id || id === 'new') {
    VAULT_ROWS.splice(parseInt(tr.dataset.idx), 1);
    vaultRender(); return;
  }
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'delete'); fd.append('id', id);
  const r = await fetch(VAULT_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  if (j.success) {
    VAULT_ROWS = VAULT_ROWS.filter(x => String(x.id) !== String(id));
    vaultRender(); setStatus('vault-status', '✓ Deleted', 'ok');
  } else setStatus('vault-status', '✗ Delete failed', 'error');
}
async function vaultSaveOrder() {
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'reorder');
  VAULT_ROWS.forEach((r, i) => { fd.append('ids[]', r.id); r.sort_order = i; });
  await fetch(VAULT_API, { method:'POST', body:fd, credentials:'same-origin' });
  setStatus('vault-status', '✓ Reordered', 'ok');
}
$('#vault-add')?.addEventListener('click', () => {
  VAULT_ROWS.push({ id: 'new', name: '', username: '', password: '', link: '', notes: '', sort_order: VAULT_ROWS.length, has_password: false });
  vaultRender();
  const trs = document.querySelectorAll('#vault-body tr');
  trs[trs.length-1]?.querySelector('input[data-field="name"]')?.focus();
});
$('#vault-save-all')?.addEventListener('click', async () => {
  const dirty = document.querySelectorAll('#vault-body tr.dirty');
  if (!dirty.length) { setStatus('vault-status', 'Nothing to save'); return; }
  for (const tr of dirty) await vaultSaveRow(tr);
  setStatus('vault-status', '✓ All saved', 'ok');
});

// Vault Access modal (admin only)
$('#vault-access-btn')?.addEventListener('click', loadVaultAccessUsers);
async function loadVaultAccessUsers() {
  const fd = new FormData();
  fd.append('csrf_token', CSRF); fd.append('action', 'list_users');
  const r = await fetch(VAULT_API, { method:'POST', body:fd, credentials:'same-origin' });
  const j = await r.json();
  const list = $('#vault-access-list');
  if (!j.success) { list.innerHTML = '<div class="text-danger">Failed to load users.</div>'; return; }
  if (!j.users.length) { list.innerHTML = '<div class="text-muted">No active users.</div>'; return; }
  list.innerHTML = `<table class="table table-sm">
    <thead><tr><th>User</th><th>Email</th><th class="text-center" style="width:100px">Vault Access</th></tr></thead>
    <tbody>${j.users.map(u => `<tr>
      <td>${esc(u.name || '(no name)')}</td>
      <td class="small text-muted">${esc(u.email || '')}</td>
      <td class="text-center">
        <input type="checkbox" class="vault-access-toggle" data-uid="${u.id}" ${u.has_access ? 'checked' : ''}>
      </td>
    </tr>`).join('')}</tbody></table>`;
  list.querySelectorAll('.vault-access-toggle').forEach(cb => {
    cb.addEventListener('change', async e => {
      const uid = e.target.dataset.uid;
      const action = e.target.checked ? 'grant' : 'revoke';
      const fd = new FormData();
      fd.append('csrf_token', CSRF); fd.append('action', action); fd.append('user_id', uid);
      const r = await fetch(VAULT_API, { method:'POST', body:fd, credentials:'same-origin' });
      const j = await r.json();
      if (!j.success) { e.target.checked = !e.target.checked; alert('Failed: ' + (j.error || 'unknown')); }
    });
  });
}

// ============================================================
// SHARED — grid event binding (drag, edit, save, delete)
// ============================================================
function bindGridEvents(bodySel, opts) {
  document.querySelectorAll(bodySel + ' tr').forEach(tr => {
    tr.querySelectorAll('input.cell').forEach(inp => {
      inp.addEventListener('input', () => tr.classList.add('dirty'));
      inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); opts.save(tr); } });
    });
    tr.querySelector('[data-act="save"]')?.addEventListener('click', () => opts.save(tr));
    tr.querySelector('[data-act="delete"]')?.addEventListener('click', () => opts.del(tr));
    if (opts.extra) opts.extra(tr);

    tr.addEventListener('dragstart', e => { tr.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', tr.dataset.idx); });
    tr.addEventListener('dragend',   () => tr.classList.remove('dragging'));
    tr.addEventListener('dragover',  e => { e.preventDefault(); tr.classList.add('drag-over'); });
    tr.addEventListener('dragleave', () => tr.classList.remove('drag-over'));
    tr.addEventListener('drop', async e => {
      e.preventDefault(); tr.classList.remove('drag-over');
      const fromIdx = parseInt(e.dataTransfer.getData('text/plain'));
      const toIdx = parseInt(tr.dataset.idx);
      if (isNaN(fromIdx) || fromIdx === toIdx) return;
      const ROWS = opts.rowsRef();
      const [moved] = ROWS.splice(fromIdx, 1);
      ROWS.splice(toIdx, 0, moved);
      opts.render();
      await opts.reorder();
    });
  });
}

// Lazy-load each tab on first activation
const ACTIVE_TAB = '<?php echo e($tab); ?>';
if (ACTIVE_TAB === 'info') infoLoad();
else if (ACTIVE_TAB === 'accounts') acctLoad();
else if (ACTIVE_TAB === 'software') swLoad();
else if (ACTIVE_TAB === 'vault') vaultLoad();
// Pre-load other tabs after initial render
setTimeout(() => {
  if (ACTIVE_TAB !== 'info') infoLoad();
  if (ACTIVE_TAB !== 'accounts') acctLoad();
  if (ACTIVE_TAB !== 'software') swLoad();
  if (ACTIVE_TAB !== 'vault' && CAN_SEE_VAULT) vaultLoad();
}, 200);
</script>
</body>
</html>
