<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/admin.php';
require_admin();
init_schema();

$message = null;
$error = null;
$generated = [];

function generate_unique_card(string $groupName): string
{
    $pdo = db();
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $km = (string) random_int(100000000, 999999999);
        try {
            $stmt = $pdo->prepare('INSERT INTO cards (km, group_name, status, created_at) VALUES (:km, :group_name, :status, :created_at)');
            $stmt->execute([
                ':km' => $km,
                ':group_name' => $groupName,
                ':status' => 'unused',
                ':created_at' => gmdate('c'),
            ]);
            return $km;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    throw new RuntimeException('卡密生成重试次数过多，请稍后再试。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $count = max(1, min(1000, (int) ($_POST['count'] ?? 10)));
    $groupName = trim((string) ($_POST['group_name'] ?? 'default')) ?: 'default';

    try {
        for ($i = 0; $i < $count; $i++) {
            $generated[] = generate_unique_card($groupName);
        }
        $message = '已生成 ' . count($generated) . ' 个 9 位纯数字卡密。';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = db()->prepare('SELECT * FROM cards ORDER BY id DESC LIMIT :limit');
$stmt->bindValue(':limit', 200, PDO::PARAM_INT);
$stmt->execute();
$cards = $stmt->fetchAll();

render_admin_header('卡密管理');
?>
<h1>卡密组管理</h1>
<?php if ($message): ?><p class="msg"><?= h($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="msg err"><?= h($error) ?></p><?php endif; ?>
<div class="card">
    <h2>批量生成 9 位纯数字卡密</h2>
    <form method="post">
        <?= csrf_field() ?>
        <label>分组名称 <input name="group_name" value="default" maxlength="80" required></label>
        <label>生成数量 <input name="count" type="number" min="1" max="1000" value="10" required></label>
        <button type="submit">生成卡密</button>
    </form>
</div>
<?php if ($generated): ?>
<div class="card">
    <h2>本次生成卡密</h2>
    <textarea id="generatedCards" class="copybox" readonly><?= h(implode("\n", $generated)) ?></textarea>
    <p><button type="button" onclick="navigator.clipboard.writeText(document.getElementById('generatedCards').value).then(()=>alert('已复制'))">批量复制已生成卡密</button></p>
</div>
<?php endif; ?>
<div class="card">
    <h2>最近卡密（最多 200 条）</h2>
    <table>
        <thead><tr><th>ID</th><th>卡密</th><th>分组</th><th>状态</th><th>绑定 SN</th><th>绑定时间</th><th>创建时间</th></tr></thead>
        <tbody>
            <?php foreach ($cards as $card): ?>
                <tr><td><?= (int) $card['id'] ?></td><td><code><?= h($card['km']) ?></code></td><td><?= h($card['group_name']) ?></td><td><span class="pill"><?= h($card['status']) ?></span></td><td><?= h($card['bound_sn']) ?></td><td><?= h($card['bound_at']) ?></td><td><?= h($card['created_at']) ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_admin_footer(); ?>
