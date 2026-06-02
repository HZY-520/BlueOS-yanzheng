<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/admin.php';
require_admin();

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'your-domain.example';
$base = $scheme . '://' . $host;

render_admin_header('API 对接说明');
?>
<h1>后台 API 对接说明</h1>
<div class="card">
    <h2>通用说明</h2>
    <ul>
        <li><code>km</code> 表示 9 位纯数字卡密。</li>
        <li><code>sn</code> 表示手表序列号。</li>
        <li>所有接口同时支持 GET 和 POST 提交。</li>
        <li>返回 JSON 中 <code>status=0</code> 表示已验证、可跳转或可用；<code>status=1</code> 表示未验证、未绑定、已封禁、卡密无效或其他不可用状态。</li>
    </ul>
</div>
<div class="card">
    <h2>绑定接口</h2>
    <p>地址：<code><?= h($base) ?>/api/bind.php</code></p>
    <p>GET 示例：<code>/api/bind.php?km=123456789&amp;sn=WATCH_SN</code></p>
    <p>POST 参数：<code>km=123456789</code>，<code>sn=WATCH_SN</code></p>
</div>
<div class="card">
    <h2>验证接口</h2>
    <p>地址：<code><?= h($base) ?>/api/check.php</code></p>
    <p>GET 示例：<code>/api/check.php?km=123456789&amp;sn=WATCH_SN</code></p>
    <p>POST 参数：可提交 <code>km</code>、<code>sn</code>，或两者同时提交。</p>
</div>
<div class="card">
    <h2>返回 JSON 示例</h2>
    <pre><code>{"status":0,"msg":"verified"}</code></pre>
    <pre><code>{"status":1,"msg":"invalid_or_banned"}</code></pre>
</div>
<?php render_admin_footer(); ?>
