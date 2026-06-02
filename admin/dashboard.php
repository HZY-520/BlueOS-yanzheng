<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/admin.php';
require_admin();
init_schema();

$stats = [];
foreach (['unused', 'bound', 'banned'] as $status) {
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM cards WHERE status = :status');
    $stmt->execute([':status' => $status]);
    $stats[$status] = (int) $stmt->fetch()['total'];
}

$merchantCount = 0;
if (is_super_admin()) {
    $merchantCount = (int) db()->query("SELECT COUNT(*) FROM admins WHERE role = 'merchant'")->fetchColumn();
}

render_admin_header('控制台');
?>
<h1>控制台</h1>
<div class="grid">
    <div class="card"><h3>未绑定卡密</h3><p style="font-size:32px"><?= $stats['unused'] ?></p></div>
    <div class="card"><h3>已绑定用户</h3><p style="font-size:32px"><?= $stats['bound'] ?></p></div>
    <div class="card"><h3>已封禁记录</h3><p style="font-size:32px"><?= $stats['banned'] ?></p></div>
    <?php if (is_super_admin()): ?>
        <div class="card"><h3>商户数量</h3><p style="font-size:32px"><?= $merchantCount ?></p></div>
    <?php endif; ?>
</div>
<div class="card">
    <h2>快速入口</h2>
    <p><a class="btn" href="cards.php">批量生成卡密</a> <a class="btn btn-ok" href="users.php">查看绑定用户</a> <?php if (is_super_admin()): ?><a class="btn" href="merchants.php">管理商户</a><?php endif; ?> <a class="btn" href="docs.php">查看 API 对接说明</a></p>
</div>
<?php render_admin_footer(); ?>
