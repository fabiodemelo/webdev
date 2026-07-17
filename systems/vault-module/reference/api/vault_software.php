<?php
/**
 * Vault Module — Software Accounts CRUD with password encryption.
 * Uses VAULT_ENC_KEY from .env (AES-256-CBC).
 */
require_once __DIR__ . '/../../connection.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../includes/env_loader.php';

require_auth();
header('Content-Type: application/json');

$isAdmin = intval($_SESSION['usuario']['level'] ?? 99) <= 1;
if (!$isAdmin && !can('vault', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$encKey = getenv('VAULT_ENC_KEY') ?: ($_ENV['VAULT_ENC_KEY'] ?? '');
if (strlen($encKey) < 64) {
    http_response_code(500);
    echo json_encode(['error' => 'Encryption key not configured (VAULT_ENC_KEY missing or too short)']);
    exit;
}

function enc(string $plain, string $hexKey): string {
    if ($plain === '') return '';
    $key = hex2bin($hexKey);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}
function dec(?string $b64, string $hexKey): string {
    if (!$b64) return '';
    $key = hex2bin($hexKey);
    $bin = base64_decode($b64, true);
    if ($bin === false || strlen($bin) < 17) return '';
    $iv = substr($bin, 0, 16);
    $cipher = substr($bin, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

$method = $_SERVER['REQUEST_METHOD'];

$currentUserId = (int)($_SESSION['usuario']['id'] ?? 0);

if ($method === 'GET') {
    $rows = [];
    $res = mysqli_query($dbnew,
        "SELECT id, name, username, password_enc, link, notes, is_private, created_by, sort_order, updated_at
         FROM vault_software_accounts ORDER BY sort_order ASC, id ASC"
    );
    while ($r = mysqli_fetch_assoc($res)) {
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['is_private'] = (int)$r['is_private'];
        $r['created_by'] = $r['created_by'] !== null ? (int)$r['created_by'] : null;
        $r['has_password'] = !empty($r['password_enc']);
        // Filter private rows: only creator + super admin
        if ($r['is_private'] && !$isAdmin && $r['created_by'] !== $currentUserId) {
            continue;
        }
        $r['password'] = '';
        unset($r['password_enc']);
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

// Helper: load row + enforce private gate
function loadSoftwareRowOrDeny(mysqli $db, int $id, int $currentUserId, bool $isAdmin): ?array {
    $stmt = mysqli_prepare($db, "SELECT id, password_enc, is_private, created_by FROM vault_software_accounts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return null;
    $isPrivate = (int)($row['is_private'] ?? 0);
    $creator = $row['created_by'] !== null ? (int)$row['created_by'] : null;
    if ($isPrivate && !$isAdmin && $creator !== $currentUserId) return null;
    return $row;
}

if ($action === 'reveal') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
    $row = loadSoftwareRowOrDeny($dbnew, $id, $currentUserId, $isAdmin);
    if (!$row) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
    echo json_encode(['success' => true, 'password' => dec($row['password_enc'] ?? '', $encKey)]);
    exit;
}

if ($action === 'upsert') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim((string)($_POST['name']     ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password']      ?? '');
    $link     = trim((string)($_POST['link']     ?? ''));
    $notes    = trim((string)($_POST['notes']    ?? ''));
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $isPrivate = !empty($_POST['is_private']) ? 1 : 0;

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Name required']);
        exit;
    }

    if ($id > 0) {
        // Enforce private gate for edits
        $existing = loadSoftwareRowOrDeny($dbnew, $id, $currentUserId, $isAdmin);
        if (!$existing) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
        if ($password !== '') {
            $passEnc = enc($password, $encKey);
            $stmt = mysqli_prepare($dbnew,
                "UPDATE vault_software_accounts SET name=?, username=?, password_enc=?, link=?, notes=?, is_private=?, sort_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'sssssiii', $name, $username, $passEnc, $link, $notes, $isPrivate, $sort, $id);
        } else {
            $stmt = mysqli_prepare($dbnew,
                "UPDATE vault_software_accounts SET name=?, username=?, link=?, notes=?, is_private=?, sort_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'ssssiii', $name, $username, $link, $notes, $isPrivate, $sort, $id);
        }
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        $passEnc = $password !== '' ? enc($password, $encKey) : '';
        $stmt = mysqli_prepare($dbnew,
            "INSERT INTO vault_software_accounts (name, username, password_enc, link, notes, is_private, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'sssssiii', $name, $username, $passEnc, $link, $notes, $isPrivate, $currentUserId, $sort);
        $ok = mysqli_stmt_execute($stmt);
        $id = $ok ? (int)mysqli_insert_id($dbnew) : 0;
        mysqli_stmt_close($stmt);
    }
    echo json_encode(['success' => (bool)$ok, 'id' => $id]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
    if (!loadSoftwareRowOrDeny($dbnew, $id, $currentUserId, $isAdmin)) { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
    $stmt = mysqli_prepare($dbnew, "DELETE FROM vault_software_accounts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

if ($action === 'reorder') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) { http_response_code(400); echo json_encode(['error' => 'ids required']); exit; }
    $stmt = mysqli_prepare($dbnew, "UPDATE vault_software_accounts SET sort_order = ? WHERE id = ?");
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
