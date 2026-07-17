<?php
/**
 * Vault Module — Company Information CRUD.
 * GET    : returns all rows ordered by sort_order, id
 * POST action=upsert : create/update single row
 *   fields: id (optional), label, value, notes, sort_order
 * POST action=delete : delete by id
 * POST action=reorder: bulk update sort_order — body[ids][] = ordered ids
 */
require_once __DIR__ . '/../../connection.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../includes/security_helpers.php';

require_auth();
header('Content-Type: application/json');

$isAdmin = intval($_SESSION['usuario']['level'] ?? 99) <= 1;
if (!$isAdmin && !can('vault', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = [];
    $res = mysqli_query($dbnew, "SELECT id, label, value, notes, sort_order, updated_at FROM vault_info ORDER BY sort_order ASC, id ASC");
    while ($r = mysqli_fetch_assoc($res)) {
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

require_csrf_token();

$action = $_POST['action'] ?? '';
$writeAct = ['upsert' => 'edit', 'delete' => 'delete', 'reorder' => 'edit'];
if (isset($writeAct[$action])) {
    $needed = $action === 'upsert' ? ['create', 'edit'] : [$writeAct[$action]];
    $hasWritePerm = $isAdmin;
    foreach ($needed as $p) if (!$hasWritePerm && can('vault', $p)) { $hasWritePerm = true; break; }
    if (!$hasWritePerm) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
}

if ($action === 'upsert') {
    $id     = (int)($_POST['id'] ?? 0);
    $label  = trim((string)($_POST['label']  ?? ''));
    $value  = trim((string)($_POST['value']  ?? ''));
    $notes  = trim((string)($_POST['notes']  ?? ''));
    $sort   = (int)($_POST['sort_order'] ?? 0);

    if ($label === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Label required']);
        exit;
    }
    if (mb_strlen($label) > 255) {
        http_response_code(400);
        echo json_encode(['error' => 'Label too long (max 255)']);
        exit;
    }

    if ($id > 0) {
        $stmt = mysqli_prepare($dbnew,
            "UPDATE vault_info SET label = ?, value = ?, notes = ?, sort_order = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sssii', $label, $value, $notes, $sort, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        $stmt = mysqli_prepare($dbnew,
            "INSERT INTO vault_info (label, value, notes, sort_order) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'sssi', $label, $value, $notes, $sort);
        $ok = mysqli_stmt_execute($stmt);
        $id = $ok ? (int)mysqli_insert_id($dbnew) : 0;
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['success' => (bool)$ok, 'id' => $id]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'id required']);
        exit;
    }
    $stmt = mysqli_prepare($dbnew, "DELETE FROM vault_info WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

if ($action === 'reorder') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'ids array required']);
        exit;
    }
    $stmt = mysqli_prepare($dbnew, "UPDATE vault_info SET sort_order = ? WHERE id = ?");
    foreach ($ids as $i => $rid) {
        $rid = (int)$rid;
        if ($rid <= 0) continue;
        $sort = (int)$i;
        mysqli_stmt_bind_param($stmt, 'ii', $sort, $rid);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
