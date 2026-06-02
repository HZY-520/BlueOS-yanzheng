<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('blueos_admin');
        session_start();
    }
}

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    admin_session_start();
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('CSRF token invalid.');
    }
}

function require_admin(): void
{
    admin_session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_super_admin(): void
{
    require_admin();
    if (!is_super_admin()) {
        http_response_code(403);
        exit('Only super administrators can access merchant management.');
    }
}

function current_admin_id(): int
{
    admin_session_start();
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function current_admin_name(): string
{
    admin_session_start();
    return (string) ($_SESSION['admin_username'] ?? 'admin');
}

function current_admin_role(): string
{
    admin_session_start();
    return (string) ($_SESSION['admin_role'] ?? 'merchant');
}

function is_super_admin(): bool
{
    return current_admin_role() === 'super';
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_login(string $username, string $password): bool
{
    init_schema();
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
        return false;
    }

    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'] ?? 'merchant';
    csrf_token();

    return true;
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function render_admin_header(string $title): void
{
    require_admin();
    $merchantLink = is_super_admin() ? '<a href="merchants.php">商户管理</a>' : '';
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title><style>
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f6f7fb;color:#1f2937}.wrap{max-width:1100px;margin:0 auto;padding:24px}.nav{background:#111827;color:#fff}.nav .wrap{display:flex;gap:16px;align-items:center;padding:14px 24px;flex-wrap:wrap}.nav a{color:#e5e7eb;text-decoration:none}.nav strong{margin-right:auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin:18px 0;box-shadow:0 1px 2px #0000000d}input,button,select,textarea{font:inherit;padding:10px;border:1px solid #d1d5db;border-radius:8px}button,.btn{background:#2563eb;color:#fff;border:0;cursor:pointer;text-decoration:none;display:inline-block}.btn-danger{background:#dc2626}.btn-ok{background:#059669}.btn-muted{background:#6b7280}table{width:100%;border-collapse:collapse;background:#fff}th,td{border-bottom:1px solid #e5e7eb;text-align:left;padding:10px}th{background:#f9fafb}.muted{color:#6b7280}.pill{padding:3px 8px;border-radius:999px;background:#e5e7eb}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.msg{padding:12px;border-radius:8px;background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}.copybox{width:100%;min-height:160px}.actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;align-items:end}
    </style></head><body><nav class="nav"><div class="wrap"><strong>BlueOS 管理后台</strong><a href="dashboard.php">首页</a><a href="cards.php">卡密管理</a><a href="users.php">用户管理</a>' . $merchantLink . '<a href="docs.php">API 文档</a><a href="login.php?action=logout">退出 ' . h(current_admin_name()) . '（' . h(current_admin_role()) . '）</a></div></nav><main class="wrap">';
}

function render_admin_footer(): void
{
    echo '</main></body></html>';
}
