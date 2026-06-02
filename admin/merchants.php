<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/admin.php';
require_super_admin();
init_schema();

$message = null;
$error = null;
$editingMerchant = null;

function validate_merchant_username(string $username): void
{
    if (!preg_match('/^[A-Za-z0-9_@.-]{3,50}$/', $username)) {
        throw new InvalidArgumentException('商户用户名需为 3-50 位，可包含字母、数字、下划线、点、@ 或短横线。');
    }
}

function get_merchant(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM admins WHERE id = :id AND role = 'merchant' LIMIT 1");
    $stmt->execute([':id' => $id]);
    $merchant = $stmt->fetch();

    return $merchant ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            validate_merchant_username($username);
            if (strlen($password) < 6) {
                throw new InvalidArgumentException('商户密码至少需要 6 位。');
            }

            $now = gmdate('c');
            $stmt = db()->prepare("INSERT INTO admins (username, password_hash, role, created_by, created_at, updated_at) VALUES (:username, :password_hash, 'merchant', :created_by, :created_at, :updated_at)");
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':created_by' => current_admin_id(),
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $message = '商户已创建。';
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            validate_merchant_username($username);
            if (!get_merchant($id)) {
                throw new InvalidArgumentException('商户不存在或不可编辑。');
            }

            if ($password !== '') {
                if (strlen($password) < 6) {
                    throw new InvalidArgumentException('新密码至少需要 6 位。');
                }
                $stmt = db()->prepare("UPDATE admins SET username = :username, password_hash = :password_hash, updated_at = :updated_at WHERE id = :id AND role = 'merchant'");
                $stmt->execute([
                    ':username' => $username,
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':updated_at' => gmdate('c'),
                    ':id' => $id,
                ]);
            } else {
                $stmt = db()->prepare("UPDATE admins SET username = :username, updated_at = :updated_at WHERE id = :id AND role = 'merchant'");
                $stmt->execute([
                    ':username' => $username,
                    ':updated_at' => gmdate('c'),
                    ':id' => $id,
                ]);
            }
            $message = '商户已更新。';
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if (!get_merchant($id)) {
                throw new InvalidArgumentException('商户不存在或不可删除。');
            }

            $stmt = db()->prepare("DELETE FROM admins WHERE id = :id AND role = 'merchant'");
            $stmt->execute([':id' => $id]);
            $message = '商户已删除。';
        } else {
            throw new InvalidArgumentException('未知操作。');
        }
    } catch (PDOException $e) {
        $error = $e->getCode() === '23000' ? '用户名已存在，请换一个商户用户名。' : $e->getMessage();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editingMerchant = get_merchant($editId);
    if (!$editingMerchant) {
        $error = $error ?: '要编辑的商户不存在。';
    }
}

$stmt = db()->prepare("SELECT m.*, creator.username AS created_by_username FROM admins m LEFT JOIN admins creator ON creator.id = m.created_by WHERE m.role = 'merchant' ORDER BY m.id DESC");
$stmt->execute();
$merchants = $stmt->fetchAll();

render_admin_header('商户管理');
?>
<h1>商户管理</h1>
<p class="muted">仅超级管理员可访问本页。这里创建的商户账号可登录后台进行卡密、用户与 API 文档管理，但不能管理其他商户。</p>
<?php if ($message): ?><p class="msg"><?= h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="msg err"><?= h($error) ?></p><?php endif; ?>
<div class="card">
    <h2><?= $editingMerchant ? '编辑商户' : '新增商户' ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editingMerchant ? 'update' : 'create' ?>">
        <?php if ($editingMerchant): ?><input type="hidden" name="id" value="<?= (int) $editingMerchant['id'] ?>"><?php endif; ?>
        <label>商户用户名<br><input name="username" value="<?= h($editingMerchant['username'] ?? '') ?>" maxlength="50" required></label>
        <label><?= $editingMerchant ? '新密码（留空不修改）' : '登录密码' ?><br><input name="password" type="password" minlength="6" <?= $editingMerchant ? '' : 'required' ?>></label>
        <div class="actions">
            <button type="submit"><?= $editingMerchant ? '保存修改' : '创建商户' ?></button>
            <?php if ($editingMerchant): ?><a class="btn btn-muted" href="merchants.php">取消编辑</a><?php endif; ?>
        </div>
    </form>
</div>
<div class="card">
    <h2>商户列表</h2>
    <table>
        <thead><tr><th>ID</th><th>用户名</th><th>创建者</th><th>创建时间</th><th>更新时间</th><th>操作</th></tr></thead>
        <tbody>
            <?php foreach ($merchants as $merchant): ?>
                <tr>
                    <td><?= (int) $merchant['id'] ?></td>
                    <td><?= h($merchant['username']) ?></td>
                    <td><?= h($merchant['created_by_username'] ?? '-') ?></td>
                    <td><?= h($merchant['created_at']) ?></td>
                    <td><?= h($merchant['updated_at']) ?></td>
                    <td class="actions">
                        <a class="btn" href="merchants.php?edit=<?= (int) $merchant['id'] ?>">编辑</a>
                        <form method="post" onsubmit="return confirm('确定删除该商户？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $merchant['id'] ?>">
                            <button class="btn-danger" type="submit">删除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$merchants): ?><tr><td colspan="6" class="muted">暂无商户，请先创建。</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php render_admin_footer(); ?>
