<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => !empty($_SERVER['HTTPS']),
    'cookie_samesite' => 'Lax',
]);

if (($_GET['logout'] ?? '') !== '') {
    session_destroy();
    header('Location: index.php');
    exit;
}

$err = '';
if (($_POST['action'] ?? '') === 'login') {
    // Constant-time via password_verify; brief sleep blunts online guessing.
    $hash = admin_hash();
    if ($hash !== null && password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
    } else {
        usleep(400000);
        $err = 'Incorrect password.';
    }
}

if (empty($_SESSION['ok'])) {
    http_response_code(401);
    $note = admin_hash() === null
        ? '<p class="warn">No admin password set. Run <code>ssh -t \u2026 php tools/set-admin-password.php</code></p>'
        : '';
    ?><!doctype html><html><head><meta charset="utf-8"><title>WYSIWYG admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
      body{background:#303030;color:#ddd;font:14px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
           display:grid;place-items:center;height:100vh;margin:0}
      form{background:#3a3a3a;padding:28px;border-radius:8px;min-width:280px}
      h1{font-size:15px;font-weight:600;margin:0 0 16px;letter-spacing:.06em;text-transform:uppercase;color:#999}
      input{width:100%;padding:9px;border:1px solid #555;border-radius:4px;background:#2a2a2a;color:#eee;
            font-size:14px;box-sizing:border-box}
      button{width:100%;margin-top:12px;padding:9px;border:0;border-radius:4px;background:#3232fa;color:#fff;
             font-size:14px;cursor:pointer}
      .e{color:#ff8080;margin-top:10px}.warn{color:#ffcc66}code{background:#2a2a2a;padding:1px 4px;border-radius:3px}
    </style></head><body>
    <form method="post"><h1>WYSIWYG</h1><?= $note ?>
      <input type="password" name="password" placeholder="Password" autofocus autocomplete="current-password">
      <input type="hidden" name="action" value="login">
      <button>Enter</button>
      <?php if ($err): ?><div class="e"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    </form></body></html><?php
    exit;
}

/** CSRF token for state-changing admin actions. */
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function check_csrf(): bool {
    return hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''));
}
