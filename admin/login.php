<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/admin.php';

init_schema();
admin_session_start();

if (($_GET['action'] ?? '') === 'logout') {
    admin_logout();
    header('Location: login.php');
    exit;
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username !== '' && $password !== '' && admin_login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = '用户名或密码错误。';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>商家后台登录</title>
    <style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#111827;min-height:100vh;margin:0;display:grid;place-items:center}.box{width:min(380px,calc(100% - 32px));background:#fff;border-radius:16px;padding:28px}input,button{width:100%;box-sizing:border-box;font:inherit;padding:12px;border-radius:8px;border:1px solid #d1d5db;margin:8px 0 14px}button{background:#2563eb;color:#fff;border:0}.err{background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px}.muted{color:#6b7280;font-size:14px}</style>
</head>
<body>
    <main class="box">
        <h1>商家后台登录</h1>
        <p class="muted">后台仅支持已初始化的商家管理员登录，不开放注册。</p>
        <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="username">用户名</label>
            <input id="username" name="username" required autofocus>
            <label for="password">密码</label>
            <input id="password" name="password" type="password" required>
            <button type="submit">登录</button>
        </form>
    </main>
</body>
</html>
