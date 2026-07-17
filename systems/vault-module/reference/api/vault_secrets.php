<?php
/**
 * Vault Module — Vault CRUD with strict access control.
 * Access: super admin (level <= 1) OR explicit grant in vault_secrets_access.
 */
require_once __DIR__ . '/../../connection.php';
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../includes/security_helpers.php';
require_once __DIR__ . '/../includes/env_loader.php';

require_auth();
header('Content-Type: application/json');

$encKey = getenv('VAULT_ENC_KEY') ?: ($_ENV['VAULT_ENC_KEY'] ?? '');
if (strlen($encKey) < 64) {
    http_response_code(500);
    echo json_encode(['error' => 'Encryption key not configured']);
    exit;
}

function vaultEnc(string $plain, string $hexKey): string {
    if ($plain === '') return '';
    $key = hex2bin($hexKey);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}
function vaultDec(?string $b64, string $hexKey): string {
    if (!$b64) return '';
    $key = hex2bin($hexKey);
    $bin = base64_decode($b64, true);
    if ($bin === false || strlen($bin) < 17) return '';
    $iv = substr($bin, 0, 16);
    $cipher = substr($bin, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

$currentUserId = (int)($_SESSION['usuario']['id'] ?? 0);
$isAdmin = intval($_SESSION['usuario']['level'] ?? 99) <= 1;

// Access check: super admin OR row in vault_secrets_access
function userHasVaultAccess(mysqli $db, int $userId): bool {
    if ($userId <= 0) return false;
    $stmt = mysqli_prepare($db, "SELECT 1 FROM vault_secrets_access WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $hit = mysqli_fetch_assoc($res) !== null;
    mysqli_stmt_close($stmt);
    return $hit;
}

$hasAccess = $isAdmin || userHasVaultAccess($dbnew, $currentUserId);
if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = [];
    $res = mysqli_query($dbnew,
        "SELECT id, name, username, password_enc, link, notes, sort_order, created_by, updated_at
         FROM vault_secrets ORDER BY sort_order ASC, id ASC"
    );
    while ($r = mysqli_fetch_assoc($res)) {
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        $r['created_by'] = $r['created_by'] !== null ? (int)$r['created_by'] : null;
        $r['has_password'] = !empty($r['password_enc']);
        $r['password'] = '';
        unset($r['password_enc']);
        $rows[] = $r;
    }
    // List of users with access (admin only)
    $access = [];
    if ($isAdmin) {
        $res = mysqli_query($dbnew,
            "SELECT a.user_id, a.granted_at, u.name, u.email
             FROM vault_secrets_access a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY u.name ASC"
        );
        if ($res) while ($r = mysqli_fetch_assoc($res)) {
            $access[] = ['user_id' => (int)$r['user_id'], 'name' => $r['name'], 'email' => $r['email'], 'granted_at' => $r['granted_at']];
        }
    }
    echo json_encode(['success' => true, 'rows' => $rows, 'is_admin' => $isAdmin, 'access' => $access]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

require_csrf_token();
$action = $_POST['action'] ?? '';

if ($action === 'reveal') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
    $stmt = mysqli_prepare($dbnew, "SELECT password_enc FROM vault_secrets WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }
    echo json_encode(['success' => true, 'password' => vaultDec($row['password_enc'] ?? '', $encKey)]);
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

    if ($name === '') { http_response_code(400); echo json_encode(['error' => 'Name required']); exit; }

    if ($id > 0) {
        if ($password !== '') {
            $passEnc = vaultEnc($password, $encKey);
            $stmt = mysqli_prepare($dbnew,
                "UPDATE vault_secrets SET name=?, username=?, password_enc=?, link=?, notes=?, sort_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'sssssii', $name, $username, $passEnc, $link, $notes, $sort, $id);
        } else {
            $stmt = mysqli_prepare($dbnew,
                "UPDATE vault_secrets SET name=?, username=?, link=?, notes=?, sort_order=? WHERE id=?"
            );
            mysqli_stmt_bind_param($stmt, 'ssssii', $name, $username, $link, $notes, $sort, $id);
        }
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        $passEnc = $password !== '' ? vaultEnc($password, $encKey) : '';
        $stmt = mysqli_prepare($dbnew,
            "INSERT INTO vault_secrets (name, username, password_enc, link, notes, created_by, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'sssssii', $name, $username, $passEnc, $link, $notes, $currentUserId, $sort);
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
    $stmt = mysqli_prepare($dbnew, "DELETE FROM vault_secrets WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

if ($action === 'reorder') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) { http_response_code(400); echo json_encode(['error' => 'ids required']); exit; }
    $stmt = mysqli_prepare($dbnew, "UPDATE vault_secrets SET sort_order = ? WHERE id = ?");
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

// ───────── Access management (admin only) ─────────
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'admin only']);
    exit;
}

if ($action === 'list_users') {
    $rows = [];
    $res = mysqli_query($dbnew,
        "SELECT u.id, u.name, u.email, (a.user_id IS NOT NULL) AS has_access
         FROM users u
         LEFT JOIN vault_secrets_access a ON a.user_id = u.id
         WHERE u.status = 'Active'
         ORDER BY u.name ASC"
    );
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'email' => $r['email'], 'has_access' => (bool)$r['has_access']];
    }
    echo json_encode(['success' => true, 'users' => $rows]);
    exit;
}

if ($action === 'grant') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid <= 0) { http_response_code(400); echo json_encode(['error' => 'user_id required']); exit; }
    $stmt = mysqli_prepare($dbnew,
        "INSERT INTO vault_secrets_access (user_id, granted_by) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), granted_at = CURRENT_TIMESTAMP"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $currentUserId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

if ($action === 'revoke') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid <= 0) { http_response_code(400); echo json_encode(['error' => 'user_id required']); exit; }
    $stmt = mysqli_prepare($dbnew, "DELETE FROM vault_secrets_access WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$ok]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
