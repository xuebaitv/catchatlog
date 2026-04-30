<?php
session_start();
require_once '../inc/config.php';
require_once '../inc/functions.php';

if (checkAuth()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    if (login($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = '密码错误，请重试';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        .login-box h2 { text-align: center; color: #333; margin-bottom: 30px; font-size: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #666; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #1a73e8; }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: opacity 0.3s; }
        .btn:hover { opacity: 0.9; }
        .error { color: #ff4444; text-align: center; margin-bottom: 15px; padding: 10px; background: #ffebee; border-radius: 4px; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #666; text-decoration: none; font-size: 14px; }
        .back-link a:hover { color: #1a73e8; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 后台登录</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="password">管理员密码</label>
                <input type="password" id="password" name="password" placeholder="请输入密码" required>
            </div>
            <button type="submit" class="btn">登录</button>
        </form>
        <div class="back-link">
            <a href="../index.php">← 返回首页</a>
        </div>
    </div>
</body>
</html>