<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/admin.php';
require_admin();
init_schema();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($id <= 0 || !in_array($action, ['ban', 'unban'], true)) {
            throw new InvalidArgumentException('操作参数无效。');
        }

        if ($action === 'ban') {
            $stmt = db()->prepare('UPDATE cards SET status = :status WHERE id = :id AND bound_sn IS NOT NULL');
            $stmt->execute([':status' => 'banned', ':id' => $id]);
            $message = '用户已封禁。';
        } else {
            $stmt = db()->prepare('UPDATE cards SET status = :status WHERE id = :id AND bound_sn IS NOT NULL');
            $stmt->execute([':status' => 'bound', ':id' => $id]);
            $message = '用户已解封。';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = db()->prepare("SELECT * FROM cards WHERE bound_sn IS NOT NULL ORDER BY COALESCE(bound_at, created_at) DESC, id DESC");
$stmt->execute();
$users = $stmt->fetchAll();

render_admin_header('用户管理');
?>
<h1>用户管理</h1>
<p class="muted">这里展示已绑定卡密与手表序列号的用户记录。封禁后，API 验证会返回 <code>{"status":1,"msg":"invalid_or_banned"}</code>。</p>
<?php if ($message): ?><p class="msg"><?= h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="msg err"><?= h($error) ?></p><?php endif; ?>
<div class="card">
    <table>
        <thead><tr><th>ID</th><th>卡密</th><th>手表 SN</th><th>分组</th><th>状态</th><th>绑定时间</th><th>操作</th></tr></thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int) $user['id'] ?></td>
                    <td><code><?= h($user['km']) ?></code></td>
                    <td><?= h($user['bound_sn']) ?></td>
                    <td><?= h($user['group_name']) ?></td>
                    <td><span class="pill"><?= h($user['status']) ?></span></td>
                    <td><?= h($user['bound_at']) ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                            <?php if ($user['status'] === 'banned'): ?>
                                <input type="hidden" name="action" value="unban">
                                <button class="btn-ok" type="submit">解封</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="ban">
                                <button class="btn-danger" type="submit">封禁</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="7" class="muted">暂无绑定用户。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php render_admin_footer(); ?>
