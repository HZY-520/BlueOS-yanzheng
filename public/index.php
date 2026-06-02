<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('blueos_public');
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function public_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

$message = null;
$isOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(public_csrf_token(), $token)) {
        $message = '表单已过期，请刷新页面后重试。';
    } else {
        $km = app_input('km');
        $sn = app_input('sn');
        if ($km === null || $sn === null) {
            $message = '请输入卡密和手表序列号。';
        } else {
            $result = bind_card($km, $sn);
            $isOk = $result['status'] === 0;
            $message = $isOk ? '绑定成功，设备已验证可用。' : '绑定失败：卡密无效、已绑定其他设备或账号不可用。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BlueOS 自助激活</title>
    <style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#dbeafe,#f8fafc);min-height:100vh;margin:0;display:grid;place-items:center;color:#111827}.box{width:min(440px,calc(100% - 32px));background:#fff;border-radius:18px;padding:28px;box-shadow:0 20px 60px #1e3a8a26}label{display:block;margin:16px 0 6px;font-weight:700}input,button{width:100%;box-sizing:border-box;font:inherit;padding:13px;border-radius:10px;border:1px solid #cbd5e1}button{margin-top:20px;background:#2563eb;color:#fff;border:0;font-weight:700;cursor:pointer}.msg{padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b}.ok{background:#ecfdf5;color:#065f46}.hint{color:#64748b;font-size:14px;line-height:1.6}</style>
</head>
<body>
    <main class="box">
        <h1>BlueOS 手表自助激活</h1>
        <p class="hint">请输入 9 位数字卡密和手表序列号，提交后系统会将两者绑定并写入 SQLite 数据库。</p>
        <?php if ($message !== null): ?>
            <p class="msg <?= $isOk ? 'ok' : '' ?>"><?= e($message) ?></p>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(public_csrf_token()) ?>">
            <label for="km">卡密（km）</label>
            <input id="km" name="km" inputmode="numeric" pattern="\d{9}" maxlength="9" placeholder="例如：123456789" required>
            <label for="sn">手表序列号（sn）</label>
            <input id="sn" name="sn" maxlength="120" placeholder="例如：WATCH_SN" required>
            <button type="submit">立即绑定激活</button>
        </form>
    </main>
</body>
</html>
