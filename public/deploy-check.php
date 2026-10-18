<?php
/**
 * 前台管理系统 —— 部署环境自检页
 *
 * 用途：部署完成后访问 http://服务器地址/deploy-check.php ，逐项确认环境是否就绪。
 * 全部通过后请【立即删除本文件】（位于项目 public/deploy-check.php）。
 */
header('Content-Type: text/html; charset=utf-8');

$checks = [];

// —— 1. PHP 版本（要求 ≥ 8.2）——
$checks[] = [
    'name' => 'PHP 版本 ≥ 8.2',
    'pass' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'info' => '当前 '.PHP_VERSION,
];

// —— 2. 必需扩展 ——
$requiredExts = [
    'bcmath' => 'BCMath（Laravel 必需）',
    'ctype' => 'Ctype（Laravel 必需）',
    'curl' => 'cURL',
    'dom' => 'DOM/XML（Excel 导入导出）',
    'fileinfo' => 'Fileinfo（文件上传）',
    'gd' => 'GD（Excel / 图片处理）',
    'intl' => 'Intl（国际化）',
    'mbstring' => 'Mbstring（多字节字符串）',
    'openssl' => 'OpenSSL（加密）',
    'pdo_mysql' => 'PDO MySQL（数据库）',
    'tokenizer' => 'Tokenizer（Laravel 必需）',
    'xml' => 'XML（Excel 导入导出）',
    'zip' => 'Zip（Excel 文件读写）',
];
foreach ($requiredExts as $ext => $label) {
    $checks[] = [
        'name' => '扩展：'.$label,
        'pass' => extension_loaded($ext),
        'info' => extension_loaded($ext) ? '已启用' : '未启用——请在 phpStudy「PHP 扩展」中勾选后重启 Nginx',
    ];
}

// —— 3. 读取项目 .env ——
$envPath = dirname(__DIR__).'/.env';
$env = [];
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
$checks[] = [
    'name' => '.env 配置文件存在',
    'pass' => is_file($envPath),
    'info' => is_file($envPath) ? '已找到' : '未找到——请复制 .env.example 为 .env 并填写',
];

// —— 4. 生产模式检查 ——
$appDebug = strtolower($env['APP_DEBUG'] ?? 'true');
$checks[] = [
    'name' => 'APP_DEBUG 已关闭（生产必须 false）',
    'pass' => $appDebug === 'false',
    'info' => '当前 APP_DEBUG='.($env['APP_DEBUG'] ?? '未设置'),
];
$checks[] = [
    'name' => 'APP_KEY 已生成',
    'pass' => ! empty($env['APP_KEY']),
    'info' => ! empty($env['APP_KEY']) ? '已生成' : '未生成——请执行 php artisan key:generate',
];

// —— 5. 数据库连接 ——
$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';
$dbOk = false;
$dbInfo = '';
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_TIMEOUT => 3]
    );
    $tableCnt = (int) $pdo->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$pdo->quote($dbName)
    )->fetchColumn();
    $dbOk = $tableCnt >= 30;
    $dbInfo = "连接成功，库 {$dbName} 共 {$tableCnt} 张表".($tableCnt >= 30 ? '' : '（少于 30 张——初始化 SQL 可能未导入）');
} catch (Throwable $e) {
    $dbInfo = '连接失败：'.$e->getMessage();
}
$checks[] = ['name' => '数据库连接 + 初始化完成', 'pass' => $dbOk, 'info' => $dbInfo];

// —— 6. 目录可写 ——
foreach (['storage', 'bootstrap/cache'] as $dir) {
    $path = dirname(__DIR__).'/'.$dir;
    $writable = is_dir($path) && is_writable($path);
    $checks[] = [
        'name' => "目录可写：{$dir}",
        'pass' => $writable,
        'info' => $writable ? '正常' : '不可写——请给 IIS/服务账号或 Everyone 加写权限',
    ];
}

$allPass = ! in_array(false, array_column($checks, 'pass'), true);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>部署环境自检 - 前台管理系统</title>
<style>
  body { font-family: "Microsoft YaHei", sans-serif; background:#f5f7fa; margin:0; padding:40px; }
  .box { max-width:820px; margin:0 auto; background:#fff; border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:32px; }
  h1 { font-size:20px; margin:0 0 8px; }
  .summary { padding:12px 16px; border-radius:6px; font-weight:bold; margin:16px 0; }
  .ok  { background:#f0f9eb; color:#67c23a; border:1px solid #e1f3d8; }
  .bad { background:#fef0f0; color:#f56c6c; border:1px solid #fde2e2; }
  table { width:100%; border-collapse:collapse; font-size:14px; }
  td, th { border-bottom:1px solid #ebeef5; padding:10px 12px; text-align:left; }
  .pass { color:#67c23a; font-weight:bold; }
  .fail { color:#f56c6c; font-weight:bold; }
  .warn { margin-top:20px; padding:12px 16px; background:#fdf6ec; color:#e6a23c; border:1px solid #faecd8; border-radius:6px; font-size:13px; }
</style>
</head>
<body>
<div class="box">
  <h1>前台管理系统 · 部署环境自检</h1>
  <div class="summary <?= $allPass ? 'ok' : 'bad' ?>">
    <?= $allPass ? '✔ 全部检查通过，可以投入使用。' : '✘ 存在未通过项，请按下表提示处理后刷新本页。' ?>
  </div>
  <table>
    <tr><th style="width:42%">检查项</th><th style="width:10%">结果</th><th>说明</th></tr>
    <?php foreach ($checks as $c) { ?>
    <tr>
      <td><?= htmlspecialchars($c['name']) ?></td>
      <td class="<?= $c['pass'] ? 'pass' : 'fail' ?>"><?= $c['pass'] ? '通过' : '未过' ?></td>
      <td><?= htmlspecialchars($c['info']) ?></td>
    </tr>
    <?php } ?>
  </table>
  <div class="warn">
    ⚠ 安全检查完成后，请立即删除本文件（项目目录 public/deploy-check.php），避免对外暴露环境信息。
  </div>
</div>
</body>
</html>
