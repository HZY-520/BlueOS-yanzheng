<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

init_schema();
$created = seed_admin('admin', 'admin123');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>BlueOS 安装</title>
    <style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:760px;margin:40px auto;padding:0 20px;line-height:1.7}.ok{color:#047857}.warn{color:#b45309}code{background:#f3f4f6;padding:2px 6px;border-radius:6px}</style>
</head>
<body>
    <h1>BlueOS 激活系统安装完成</h1>
    <p class="ok">SQLite 数据表已初始化，数据库位置：<code>data/app.db</code></p>
    <?php if ($created): ?>
        <p class="warn">默认超级管理员已创建：用户名 <code>admin</code>，密码 <code>admin123</code>。首次登录后请尽快修改或替换管理员密码。</p>
    <?php else: ?>
        <p>管理员账号已存在，本次未重复写入默认账号。</p>
    <?php endif; ?>
    <p><a href="admin/login.php">进入后台登录</a></p>
</body>
</html>
