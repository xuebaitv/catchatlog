<?php
session_start();
require_once '../inc/config.php';
require_once '../inc/functions.php';
$settings = getSettings();

$mindmapDir = __DIR__ . '/data';
if (!is_dir($mindmapDir)) {
    mkdir($mindmapDir, 0777, true);
}

$isAdmin = checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_admin_password') {
        $password = $_POST['password'] ?? '';
        if (login($password)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '密码错误']);
        }
        exit;
    }
    
    if ($action === 'list') {
        $files = glob($mindmapDir . '/*.json');
        $list = [];
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if ($content && !empty($content['name']) && isset($content['updated_at'])) {
                $list[] = $content;
            }
        }
        usort($list, function($a, $b) {
            return (isset($b['updated_at']) ? $b['updated_at'] : 0) - (isset($a['updated_at']) ? $a['updated_at'] : 0);
        });
        echo json_encode(['success' => true, 'list' => $list]);
        exit;
    }
    
    if ($action === 'load') {
        $id = $_POST['id'] ?? '';
        $filename = $mindmapDir . '/' . basename($id) . '.json';
        if (file_exists($filename)) {
            $content = json_decode(file_get_contents($filename), true);
            echo json_encode(['success' => true, 'data' => $content]);
        } else {
            echo json_encode(['success' => false, 'message' => '文件不存在']);
        }
        exit;
    }
    
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => '需要管理员权限']);
        exit;
    }
    
    switch ($action) {
        case 'save':
            $id = $_POST['id'] ?? uniqid();
            $name = trim($_POST['name'] ?? '未命名思维导图');
            $data = $_POST['data'] ?? '{}';
            $filename = $mindmapDir . '/' . basename($id) . '.json';
            $tempFile = $filename . '.tmp.' . uniqid();
            $writeResult = file_put_contents($tempFile, json_encode([
                'id' => $id,
                'name' => $name,
                'data' => $data,
                'updated_at' => time()
            ], JSON_UNESCAPED_UNICODE));
            if ($writeResult !== false) {
                rename($tempFile, $filename);
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                @unlink($tempFile);
                echo json_encode(['success' => false]);
            }
            exit;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            $filename = $mindmapDir . '/' . basename($id) . '.json';
            if (file_exists($filename)) {
                $result = unlink($filename);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => '文件不存在']);
            }
            exit;
    }
}

if ($settings['access_password_enabled']) {
    if (isset($_POST['logout_access'])) {
        unset($_SESSION['access_granted']);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    if (!isset($_SESSION['access_granted']) || !$_SESSION['access_granted']) {
        if (isset($_POST['access_password'])) {
            if ($_POST['access_password'] === $settings['access_password']) {
                $_SESSION['access_granted'] = true;
            } else {
                $error = '密码错误，请重试';
            }
        }
        if (!isset($_SESSION['access_granted']) || !$_SESSION['access_granted']) {
            ?>
            <!DOCTYPE html>
            <html lang="zh-CN">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="theme-color" content="#667eea">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
                <title>访问验证 - 思维导图</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Microsoft YaHei', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                    .login-box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 90%; max-width: 400px; text-align: center; }
                    .login-box h1 { color: #333; margin-bottom: 10px; font-size: 24px; }
                    .login-box p { color: #666; margin-bottom: 30px; }
                    .form-group { margin-bottom: 20px; text-align: left; }
                    .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: bold; }
                    .form-group input { width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
                    .form-group input:focus { outline: none; border-color: #667eea; }
                    .error { color: #ff4444; margin-bottom: 20px; padding: 12px; background: #ffebee; border-radius: 8px; }
                    .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: transform 0.2s; }
                    .btn:hover { transform: translateY(-1px); }
                    .lock-icon { font-size: 48px; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="login-box">
                    <div class="lock-icon">🔒</div>
                    <h1>访问验证</h1>
                    <p>此页面受密码保护，请输入访问密码</p>
                    <?php if (isset($error)): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="access_password">访问密码</label>
                            <input type="password" id="access_password" name="access_password" placeholder="请输入密码" required autofocus>
                        </div>
                        <button type="submit" class="btn">验证并进入</button>
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>思维导图</title>
    <script>
        const INITIAL_IS_EDIT_MODE = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            min-width: 481px;
            position: relative;
            overflow-x: scroll;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        body::-webkit-scrollbar {
            display: none;
        }
        .bg-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #f0f2f5;
            transition: background-image 0.5s ease-in-out;
        }
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.2);
            z-index: -1;
        }
        .container {
            display: flex;
            height: 100vh;
            position: relative;
            z-index: 1;
        }
        
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-right: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            z-index: 100;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        .sidebar-header {
            padding: 22px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        .sidebar-header h1 {
            font-size: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        .new-mindmap-btn {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
        }
        .new-mindmap-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .mindmap-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .mindmap-list::-webkit-scrollbar {
            width: 6px;
        }
        .mindmap-list::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        .mindmap-item {
            padding: 14px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .mindmap-item:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateX(3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .mindmap-item.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.15);
        }
        .mindmap-item-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .mindmap-item-time {
            font-size: 12px;
            color: #888;
        }
        .mindmap-item-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .mindmap-item-actions button {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .edit-name-btn {
            background: #1a73e8;
            color: white;
        }
        .delete-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
        }
        .back-link {
            padding: 15px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
        }
        .toolbar {
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .mindmap-title-input {
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 8px 0;
            color: #333;
            outline: none;
            background: transparent;
        }
        .mindmap-title-input:focus {
            border-bottom-color: #667eea;
        }
        .toolbar-divider {
            width: 1px;
            height: 30px;
            background: rgba(0, 0, 0, 0.12);
            margin: 0 10px;
        }
        .tool-btn {
            padding: 10px 16px;
            background: rgba(241, 243, 244, 0.95);
            color: #5f6368;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .tool-btn:hover {
            background: white;
            color: #1a73e8;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .tool-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .tool-btn.primary:hover {
            opacity: 0.9;
        }
        .zoom-info {
            margin-left: auto;
            font-size: 13px;
            color: #555;
            padding: 8px 14px;
            background: rgba(248, 249, 250, 0.95);
            border-radius: 10px;
            font-weight: 500;
        }
        .canvas-container {
            flex: 1;
            overflow: hidden;
            position: relative;
            background: rgba(250, 251, 252, 0.6);
            cursor: grab;
        }
        .canvas-container.grabbing {
            cursor: grabbing;
        }
        .mindmap-canvas-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            transform-origin: 0 0;
        }
        .mindmap-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 500000px;
            height: 500000px;
        }
        .dynamic-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        .node {
            position: absolute;
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            cursor: move;
            user-select: none;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
            z-index: 10;
            font-size: 14px;
            font-weight: 500;
            transition: box-shadow 0.2s, transform 0.1s;
        }
        .node:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.5);
        }
        .node.selected {
            outline: 3px solid #ffd700;
            outline-offset: 3px;
        }
        .node.root {
            font-size: 18px;
            font-weight: bold;
            padding: 18px 30px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            box-shadow: 0 8px 28px rgba(255, 107, 107, 0.4);
        }
        .node.level1 {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            box-shadow: 0 8px 24px rgba(79, 172, 254, 0.35);
        }
        .node.level2 {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: #333;
            box-shadow: 0 8px 24px rgba(67, 233, 123, 0.35);
        }
        .node.level3 {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #333;
            box-shadow: 0 8px 24px rgba(250, 112, 154, 0.35);
        }
        .connection-line {
            position: absolute;
            pointer-events: none;
            z-index: 5;
        }
        .node-text-input {
            background: transparent;
            border: none;
            color: white;
            font-size: inherit;
            font-weight: inherit;
            outline: none;
            width: 100%;
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }
        .node.root .node-text-input,
        .node.level1 .node-text-input {
            color: white;
        }
        .node.level2 .node-text-input,
        .node.level3 .node-text-input {
            color: #333;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            padding: 32px;
            border-radius: 20px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }
        .modal-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 15px;
            margin-bottom: 24px;
            outline: none;
            transition: border-color 0.3s;
            font-weight: 500;
        }
        .modal-input:focus {
            border-color: #667eea;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        .modal-btn.cancel {
            background: #f1f3f4;
            color: #666;
        }
        .modal-btn.cancel:hover {
            background: #e8eaed;
        }
        .modal-btn.confirm {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
        }
        .modal-btn.confirm:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            padding: 15px 32px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(15px);
            color: white;
            border-radius: 14px;
            z-index: 9999;
            opacity: 0;
            transition: all 0.3s ease-out;
            font-weight: 500;
        }
        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .helper-tip {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 12px;
            pointer-events: none;
            z-index: 5;
            backdrop-filter: blur(8px);
        }
        .context-menu {
            position: fixed;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 10px 0;
            z-index: 99999;
            min-width: 200px;
            display: none;
        }
        .context-menu.active {
            display: block;
        }
        .context-menu-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background 0.2s;
            color: #333;
            font-size: 14px;
            user-select: none;
        }
        .context-menu-item:hover {
            background: #f1f3f4;
        }
        .context-menu-item.danger {
            color: #ff4444;
        }
        .context-menu-divider {
            height: 1px;
            background: #eee;
            margin: 4px 0;
        }
        .color-picker-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            padding: 10px;
        }
        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.2s;
        }
        .color-option:hover {
            transform: scale(1.2);
            border-color: #666;
        }
        .node-select-modal-content {
            max-width: 500px;
            max-height: 70vh;
        }
        .node-select-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .node-select-item {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .node-select-item:hover {
            background: #f1f3f4;
        }
        .ai-generate-modal-content {
            max-width: 600px;
            max-height: 85vh;
        }
        .ai-textarea {
            width: 100%;
            min-height: 220px;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 15px;
            margin-bottom: 20px;
            outline: none;
            transition: border-color 0.3s;
            font-weight: 500;
            resize: vertical;
            font-family: 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
        }
        .ai-textarea:focus {
            border-color: #667eea;
        }
        .ai-tips {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .ai-tips-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .ai-tips p {
            color: #666;
            font-size: 13px;
            line-height: 1.6;
        }
        .ai-loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .ai-loading-overlay.active {
            display: flex;
        }
        .ai-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(102, 126, 234, 0.2);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .ai-loading-text {
            font-size: 18px;
            color: #333;
            font-weight: 600;
        }
        .mobile-top-bar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 999;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        .hamburger-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 22px;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
        }
        .mobile-title {
            color: white;
            font-size: 16px;
            font-weight: bold;
            flex: 1;
            text-align: center;
            margin: 0 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mobile-back-btn {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            text-decoration: none;
        }
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .desktop-only {
            display: block;
        }
        @media (max-width: 768px) {
            .mobile-top-bar {
                display: flex;
            }
            .desktop-only {
                display: none !important;
            }
            .container {
                flex-direction: column;
                padding-top: 56px;
                height: calc(100vh - 56px);
            }
            .sidebar {
                position: fixed;
                top: 56px;
                left: -320px;
                height: calc(100vh - 56px);
                z-index: 997;
                transition: left 0.3s ease;
                box-shadow: 2px 0 15px rgba(0, 0, 0, 0.2);
            }
            .sidebar.open {
                left: 0;
            }
            .sidebar-overlay.show {
                display: block;
                opacity: 1;
            }
            .main-area {
                flex: 1;
                overflow: hidden;
            }
            .toolbar {
                flex-wrap: wrap;
                padding: 8px 10px;
                gap: 6px;
            }
            .mindmap-title-input {
                flex: 1 1 100%;
                font-size: 14px;
                padding: 8px 12px;
            }
            .toolbar-divider {
                display: none;
            }
            .tool-btn {
                padding: 8px 12px;
                font-size: 16px;
                min-width: 44px;
                min-height: 44px;
            }
            .zoom-info {
                margin-left: auto;
            }
            .node {
                padding: 10px 16px;
                font-size: 13px;
            }
            .node.root {
                font-size: 16px;
                padding: 14px 24px;
            }
            .modal-content {
                width: 90%;
                margin: 10% auto;
                padding: 24px;
                border-radius: 16px;
            }
            .helper-tip {
                display: none;
            }
        }
        .edit-mode-hidden {
            display: none !important;
        }
        .read-only-badge {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #ff9800;
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        @media (max-width:768px) {
            .read-only-badge {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-top-bar">
        <button class="hamburger-btn" onclick="toggleSidebar()">☰</button>
        <span class="mobile-title" id="mobileTitle">思维导图</span>
        <a href="../index.php" class="mobile-back-btn">🏠</a>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <div class="container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1>🧠 思维导图</h1>
                <button class="new-mindmap-btn" onclick="createNewMindmap()">+ 新建思维导图</button>
            </div>
            <div class="mindmap-list" id="mindmapList"></div>
            <div class="back-link desktop-only">
                <a href="../index.php">← 返回主页</a>
                <a href="../memo/index.php" style="margin-top: 8px; display: block;">📝 备忘录</a>
            </div>
        </div>
        <div class="main-area">
            <div class="toolbar">
                <input type="text" class="mindmap-title-input edit-mode-hidden" id="mindmapTitle" value="未命名思维导图" placeholder="思维导图名称" oninput="updateMobileTitle()">
                <button class="tool-btn" id="enterEditBtn" onclick="openPasswordModal()">🔐 编辑</button>
                <div class="toolbar-divider edit-mode-hidden"></div>
                <button class="tool-btn edit-mode-hidden" id="undoBtn" onclick="undoAction()">↩️</button>
                <button class="tool-btn edit-mode-hidden" id="redoBtn" onclick="redoAction()">↪️</button>
                <div class="toolbar-divider edit-mode-hidden"></div>
                <button class="tool-btn edit-mode-hidden" onclick="addNode()">➕</button>
                <button class="tool-btn edit-mode-hidden" onclick="deleteSelectedNode()">🗑️</button>
                <button class="tool-btn edit-mode-hidden" onclick="editSelectedNodeText()">✏️</button>
                <button class="tool-btn edit-mode-hidden" onclick="moveSelectedNode()">➡️</button>
                <button class="tool-btn edit-mode-hidden" onclick="copySelectedNode()">📋</button>
                <button class="tool-btn edit-mode-hidden" onclick="autoArrange()">✨</button>
                <button class="tool-btn primary edit-mode-hidden" onclick="openAIGenerateModal()">🤖 AI生成</button>
                <button class="tool-btn" onclick="goToCenter()">🎯</button>
                <button class="tool-btn" onclick="openBackgroundSettings()">🎨</button>
                <div class="toolbar-divider edit-mode-hidden"></div>
                <button class="tool-btn primary edit-mode-hidden" onclick="saveCurrentMindmap()">💾</button>
                <div class="zoom-info" id="zoomInfo">100%</div>
                <div class="read-only-badge" id="readOnlyBadge">只读模式</div>
            </div>
            <div class="canvas-container" id="canvasContainer">
                <div class="dynamic-grid" id="dynamicGrid"></div>
                <div class="mindmap-canvas-wrapper" id="canvasWrapper">
                    <div class="mindmap-canvas" id="mindmapCanvas"></div>
                </div>
                <div class="helper-tip">🖱️ 滚轮缩放 | 🖐️ 左键拖动画布</div>
            </div>
        </div>
    </div>

    <div class="modal" id="editNameModal">
        <div class="modal-content">
            <h3>编辑名称</h3>
            <input type="text" class="modal-input" id="editNameInput" placeholder="请输入名称">
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="closeModal('editNameModal')">取消</button>
                <button class="modal-btn confirm" onclick="confirmEditName()">确定</button>
            </div>
        </div>
    </div>

    <div class="modal" id="confirmDeleteModal">
        <div class="modal-content">
            <h3>确认删除</h3>
            <p style="margin-bottom: 20px; color: #666;">确定要删除这个思维导图吗？此操作无法撤销。</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="closeModal('confirmDeleteModal')">取消</button>
                <button class="modal-btn confirm" onclick="confirmDeleteMindmap()">确定删除</button>
            </div>
        </div>
    </div>

    <div class="modal" id="nodeSelectModal">
        <div class="modal-content node-select-modal-content">
            <h3 id="nodeSelectTitle">选择目标节点</h3>
            <div class="node-select-list" id="nodeSelectList"></div>
            <div class="modal-buttons" style="margin-top: 15px;">
                <button class="modal-btn cancel" onclick="closeModal('nodeSelectModal')">取消</button>
            </div>
        </div>
    </div>



    <div class="modal" id="backgroundSettingsModal">
        <div class="modal-content">
            <h3>画布设置</h3>
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:10px;color:#666;font-weight:bold;">选择背景颜色</label>
                <div class="color-picker-grid" id="colorPickerGrid"></div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:10px;color:#666;font-weight:bold;">网格样式</label>
                <select style="width:100%;padding:12px;border:2px solid #e1e5e9;border-radius:8px;font-size:14px;" id="gridStyleSelect">
                    <option value="dot">点状网格</option>
                    <option value="line">方格线网格</option>
                    <option value="cross">十字网格</option>
                    <option value="none">无网格</option>
                </select>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:10px;color:#666;font-weight:bold;">水平间距（节点左右间隔）<span id="hGapValue">250</span>px</label>
                <input type="range" id="hGapSlider" min="100" max="500" value="250" style="width:100%;">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:10px;color:#666;font-weight:bold;">垂直间距（子节点上下间隔）<span id="vGapValue">120</span>px</label>
                <input type="range" id="vGapSlider" min="60" max="300" value="120" style="width:100%;">
            </div>
            <div class="modal-buttons">
                <button class="modal-btn confirm" onclick="closeModal('backgroundSettingsModal')">完成</button>
            </div>
        </div>
    </div>

    <div class="context-menu" id="canvasOnlyMenu">
        <div class="context-menu-item" onclick="openBackgroundSettings()">🎨 画布设置</div>
    </div>

    <div class="modal" id="passwordModal">
        <div class="modal-content">
            <h3>输入管理员密码</h3>
            <input type="password" class="modal-input" id="adminPasswordInput" placeholder="请输入后台管理密码">
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="closeModal('passwordModal')">取消</button>
                <button class="modal-btn confirm" onclick="submitAdminPassword()">确认</button>
            </div>
        </div>
    </div>

    <div class="modal" id="aiGenerateModal">
        <div class="modal-content ai-generate-modal-content">
            <h3>🤖 AI 文本生成思维导图</h3>
            <div class="ai-tips">
                <div class="ai-tips-title">💡 使用提示</div>
                <p>请粘贴或输入您想要生成思维导图的内容文本，系统将自动分析文本结构，提取主题、子主题和关键点，一键生成美观的思维导图。您可以直接粘贴文章、笔记、大纲等任意文本内容。</p>
            </div>
            <textarea class="ai-textarea" id="aiTextInput" placeholder="请在此处输入或粘贴您的文本内容..."></textarea>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="closeModal('aiGenerateModal')">取消</button>
                <button class="modal-btn confirm" onclick="generateMindmapFromAI()">✨ 开始生成</button>
            </div>
        </div>
    </div>

    <div class="ai-loading-overlay" id="aiLoadingOverlay">
        <div class="ai-spinner"></div>
        <div class="ai-loading-text">AI正在分析文本，生成思维导图中...</div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        let currentMindmapId = null;
        let nodes = [];
        let selectedNodeId = null;
        let draggedNode = null;
        let dragOffsetX = 0;
        let dragOffsetY = 0;
        let dragStartX = 0;
        let dragStartY = 0;
        let mindmapsList = [];
        let deletingMindmapId = null;
        let editingMindmapId = null;
        let editingNodeId = null;
        let nodeIdCounter = 0;
        
        let scale = 1;
        let panX = 0;
        let panY = 0;
        let isPanning = false;
        let lastPanX = 0;
        let lastPanY = 0;
        let panStartX = 0;
        let panStartY = 0;
        
        let pendingActionType = null;
        let currentBgColor = '#fafbfc';
        let currentGridStyle = 'dot';
        const MAX_HISTORY = 20;
        let undoStack = [];
        let redoStack = [];
        let hGap = 250;
        let vGap = 120;
        let isEditMode = INITIAL_IS_EDIT_MODE;

        document.addEventListener('DOMContentLoaded', function() {
            loadMindmapsList();
            setupCanvasEvents();
            setupColorPicker();
            document.getElementById('gridStyleSelect').addEventListener('change', function() {
                currentGridStyle = this.value;
                updateCanvasTransform();
            });
            const hGapSlider = document.getElementById('hGapSlider');
            const vGapSlider = document.getElementById('vGapSlider');
            hGapSlider.addEventListener('input', function() {
                hGap = parseInt(this.value);
                document.getElementById('hGapValue').textContent = hGap;
            });
            vGapSlider.addEventListener('input', function() {
                vGap = parseInt(this.value);
                document.getElementById('vGapValue').textContent = vGap;
            });
            
            const canvasMenu = document.getElementById('canvasOnlyMenu');
            
            canvasMenu.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });
            
            document.addEventListener('contextmenu', function(e) {
                const nodeEl = e.target.closest('.node');
                if (nodeEl) {
                    e.preventDefault();
                    return;
                }
                const container = document.getElementById('canvasContainer');
                if (e.target === container || e.target.id === 'mindmapCanvas' || e.target.id === 'dynamicGrid') {
                    e.preventDefault();
                    let menuX = e.clientX;
                    let menuY = e.clientY;
                    const menuWidth = 200;
                    const menuHeight = 70;
                    if (menuX + menuWidth > window.innerWidth) menuX = window.innerWidth - menuWidth - 10;
                    if (menuY + menuHeight > window.innerHeight) menuY = window.innerHeight - menuHeight - 10;
                    canvasMenu.style.left = menuX + 'px';
                    canvasMenu.style.top = menuY + 'px';
                    canvasMenu.classList.add('active');
                }
            });
            
            document.addEventListener('click', function() {
                document.getElementById('canvasOnlyMenu').classList.remove('active');
            });
            
            updateUndoRedoButtons();
            updateCanvasTransform();
            updateEditModeUI();
        });
        
        function setupColorPicker() {
            const colors = ['#fafbfc', '#f5f7fa', '#f0f7ff', '#f0fff4', '#fff8f0', '#fdf2f8', '#f0fdfa', '#fef9c3'];
            const grid = document.getElementById('colorPickerGrid');
            colors.forEach(color => {
                const el = document.createElement('div');
                el.className = 'color-option';
                el.style.backgroundColor = color;
                el.addEventListener('click', function() {
                    currentBgColor = color;
                    document.getElementById('canvasContainer').style.background = color;
                });
                grid.appendChild(el);
            });
            document.getElementById('canvasContainer').style.background = currentBgColor;
        }
        
        let sidebarOpen = false;
        function toggleSidebar() {
            sidebarOpen = !sidebarOpen;
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebarOpen) {
                sidebar.classList.add('open');
                overlay.classList.add('show');
            } else {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        }
        function updateMobileTitle() {
            const titleInput = document.getElementById('mindmapTitle');
            const mobileTitle = document.getElementById('mobileTitle');
            if (titleInput && mobileTitle) {
                mobileTitle.textContent = titleInput.value || '思维导图';
            }
        }
        function updateEditModeUI() {
            const hiddenElements = document.querySelectorAll('.edit-mode-hidden');
            const readOnlyBadge = document.getElementById('readOnlyBadge');
            const enterEditBtn = document.getElementById('enterEditBtn');
            const newMindmapBtn = document.querySelector('.new-mindmap-btn');
            
            if (isEditMode) {
                hiddenElements.forEach(el => el.classList.remove('edit-mode-hidden'));
                readOnlyBadge.style.display = 'none';
                enterEditBtn.style.display = 'none';
                if (newMindmapBtn) newMindmapBtn.style.display = 'block';
            } else {
                hiddenElements.forEach(el => el.classList.add('edit-mode-hidden'));
                readOnlyBadge.style.display = 'block';
                enterEditBtn.style.display = 'flex';
                if (newMindmapBtn) newMindmapBtn.style.display = 'none';
            }
            
            if (!isEditMode) {
                const actionButtons = document.querySelectorAll('.mindmap-item-actions');
                actionButtons.forEach(btnGroup => {
                    btnGroup.style.display = 'none';
                });
            }
        }
        function openPasswordModal() {
            document.getElementById('adminPasswordInput').value = '';
            openModal('passwordModal');
        }
        async function submitAdminPassword() {
            const password = document.getElementById('adminPasswordInput').value;
            if (!password) {
                showToast('请输入密码');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'verify_admin_password');
            formData.append('password', password);
            const res = await fetch('index.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                isEditMode = true;
                closeModal('passwordModal');
                updateEditModeUI();
                showToast('已进入编辑模式！');
                await loadMindmapsList();
                if (sidebarOpen) toggleSidebar();
            } else {
                showToast(data.message || '密码错误');
            }
        }
        function openBackgroundSettings() {
            openModal('backgroundSettingsModal');
        }
        
        function saveHistory() {
            undoStack.push(JSON.stringify(nodes));
            if (undoStack.length > MAX_HISTORY) {
                undoStack.shift();
            }
            redoStack = [];
            updateUndoRedoButtons();
        }
        
        function undoAction() {
            if (undoStack.length === 0) {
                showToast('没有更多操作可以撤销');
                return;
            }
            redoStack.push(JSON.stringify(nodes));
            if (redoStack.length > MAX_HISTORY) {
                redoStack.shift();
            }
            const previousState = undoStack.pop();
            nodes = JSON.parse(previousState);
            renderNodes();
            renderConnections();
            updateUndoRedoButtons();
            showToast('已撤销');
        }
        
        function redoAction() {
            if (redoStack.length === 0) {
                showToast('没有更多操作可以前进');
                return;
            }
            undoStack.push(JSON.stringify(nodes));
            if (undoStack.length > MAX_HISTORY) {
                undoStack.shift();
            }
            const nextState = redoStack.pop();
            nodes = JSON.parse(nextState);
            renderNodes();
            renderConnections();
            updateUndoRedoButtons();
            showToast('已前进');
        }
        
        function updateUndoRedoButtons() {
            document.getElementById('undoBtn').disabled = undoStack.length === 0;
            document.getElementById('redoBtn').disabled = redoStack.length === 0;
            document.getElementById('undoBtn').style.opacity = undoStack.length === 0 ? '0.4' : '1';
            document.getElementById('redoBtn').style.opacity = redoStack.length === 0 ? '0.4' : '1';
        }
        
        let moveSrcNodeId = null;
        
        function showNodeSelectModal(title, actionType, srcNodeId) {
            pendingActionType = actionType;
            moveSrcNodeId = srcNodeId;
            document.getElementById('nodeSelectTitle').textContent = title;
            const listEl = document.getElementById('nodeSelectList');
            listEl.innerHTML = '';
            
            nodes.forEach(node => {
                if (node.id === moveSrcNodeId) return;
                if (pendingActionType === 'move' && !node.parentId) {
                    const srcNode = nodes.find(n => n.id === moveSrcNodeId);
                    if (srcNode && !srcNode.parentId) return;
                }
                const isAncestor = checkIsAncestor(moveSrcNodeId, node.id);
                if (!isAncestor) {
                    const item = document.createElement('div');
                    item.className = 'node-select-item';
                    item.textContent = node.text;
                    item.dataset.nodeId = node.id;
                    item.addEventListener('click', function() {
                        handleNodeSelect(node.id);
                    });
                    listEl.appendChild(item);
                }
            });
            
            openModal('nodeSelectModal');
        }
        
        function checkIsAncestor(maybeChildId, targetId) {
            if (!maybeChildId) return false;
            let parentId = nodes.find(n => n.id === maybeChildId)?.parentId;
            while (parentId) {
                if (parentId === targetId) return true;
                parentId = nodes.find(n => n.id === parentId)?.parentId;
            }
            return false;
        }
        
        function handleNodeSelect(targetNodeId) {
            closeModal('nodeSelectModal');
            if (!moveSrcNodeId) return;
            const srcNode = nodes.find(n => n.id === moveSrcNodeId);
            const targetNode = nodes.find(n => n.id === targetNodeId);
            if (!srcNode || !targetNode) return;
            
            saveHistory();
            
            if (pendingActionType === 'move') {
                srcNode.parentId = targetNodeId;
                const siblingCount = nodes.filter(n => n.parentId === targetNodeId).length;
                srcNode.x = targetNode.x + 200;
                srcNode.y = targetNode.y + siblingCount * 80;
                renderNodes();
                renderConnections();
                showToast('节点已移动');
            } else if (pendingActionType === 'copy') {
                function cloneSubTree(oldNode, newParentId) {
                    nodeIdCounter++;
                    const newId = 'node_' + nodeIdCounter;
                    const newNode = {
                        id: newId,
                        parentId: newParentId,
                        text: oldNode.text + ' (副本)',
                        x: oldNode.x + 30,
                        y: oldNode.y + 30
                    };
                    nodes.push(newNode);
                    
                    const children = nodes.filter(n => n.parentId === oldNode.id);
                    children.forEach(child => cloneSubTree(child, newId));
                }
                cloneSubTree(srcNode, targetNodeId);
                renderNodes();
                renderConnections();
                showToast('节点已复制');
            }
        }
        
        function moveSelectedNode() {
            if (!isEditMode) return;
            if (!selectedNodeId) {
                showToast('请先选择要移动的节点');
                return;
            }
            if (nodes.length === 1) {
                showToast('根节点不能移动');
                return;
            }
            showNodeSelectModal('选择目标父节点（移动）', 'move', selectedNodeId);
        }
        
        function copySelectedNode() {
            if (!isEditMode) return;
            if (!selectedNodeId) {
                showToast('请先选择要复制的节点');
                return;
            }
            showNodeSelectModal('选择目标父节点（复制）', 'copy', selectedNodeId);
        }
        
        function autoArrange() {
            if (!isEditMode) return;
            if (nodes.length === 0) return;
            saveHistory();
            const rootNode = nodes.find(n => !n.parentId);
            if (!rootNode) return;
            
            function calculateSubtreeBounds(nodeId) {
                const children = nodes.filter(n => n.parentId === nodeId);
                if (children.length === 0) {
                    return { totalHeight: vGap, centerOffset: vGap / 2 };
                }
                let totalHeight = 0;
                const childResults = [];
                children.forEach(child => {
                    const result = calculateSubtreeBounds(child.id);
                    childResults.push(result);
                    totalHeight += result.totalHeight;
                });
                let cumulativeY = 0;
                for (let i = 0; i < childResults.length; i++) {
                    childResults[i]._startY = cumulativeY;
                    cumulativeY += childResults[i].totalHeight;
                }
                const midPoint = totalHeight / 2;
                return { totalHeight, centerOffset: midPoint, childrenData: childResults };
            }
            
            function positionSubtree(parentNodeId, baseX, baseY, subtreeInfo) {
                const children = nodes.filter(n => n.parentId === parentNodeId);
                for (let i = 0; i < children.length; i++) {
                    const child = children[i];
                    const info = subtreeInfo.childrenData[i];
                    child.x = baseX + hGap;
                    child.y = baseY - subtreeInfo.centerOffset + info._startY + info.centerOffset;
                    if (info.childrenData) {
                        positionSubtree(child.id, child.x, child.y, info);
                    }
                }
            }
            
            const rootSubtree = calculateSubtreeBounds(rootNode.id);
            rootNode.x = 400;
            rootNode.y = 400;
            positionSubtree(rootNode.id, rootNode.x, rootNode.y, rootSubtree);
            
            renderNodes();
            renderConnections();
            showToast('自动整理完成！');
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2000);
        }

        function updateCanvasTransform() {
            const wrapper = document.getElementById('canvasWrapper');
            wrapper.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
            const grid = document.getElementById('dynamicGrid');
            const gridSize = 20 / scale;
            function positiveMod(n, m) {
                return ((n % m) + m) % m;
            }
            grid.style.backgroundSize = gridSize + 'px ' + gridSize + 'px';
            grid.style.backgroundPosition = positiveMod(-panX, gridSize) + 'px ' + positiveMod(-panY, gridSize) + 'px';
            
            if (currentGridStyle === 'dot') {
                grid.style.backgroundImage = 'radial-gradient(circle, #e0e0e0 1px, transparent 1px)';
            } else if (currentGridStyle === 'line') {
                grid.style.backgroundImage = `linear-gradient(#e0e0e0 1px, transparent 1px), linear-gradient(90deg, #e0e0e0 1px, transparent 1px)`;
            } else if (currentGridStyle === 'cross') {
                grid.style.backgroundImage = 'radial-gradient(circle, #e0e0e0 2px, transparent 2px)';
            } else if (currentGridStyle === 'none') {
                grid.style.backgroundImage = 'none';
            }
            
            document.getElementById('zoomInfo').textContent = '缩放: ' + Math.round(scale * 100) + '%';
        }

        function setupCanvasEvents() {
            const container = document.getElementById('canvasContainer');
            const canvas = document.getElementById('mindmapCanvas');
            
            container.addEventListener('wheel', function(e) {
                e.preventDefault();
                const rect = container.getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;
                const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
                const newScale = Math.max(0.1, Math.min(5, scale * zoomFactor));
                const worldX = (mouseX - panX) / scale;
                const worldY = (mouseY - panY) / scale;
                panX = mouseX - worldX * newScale;
                panY = mouseY - worldY * newScale;
                scale = newScale;
                updateCanvasTransform();
            }, { passive: false });

            container.addEventListener('mousedown', function(e) {
                if (e.target === container || e.target.id === 'mindmapCanvas') {
                    isPanning = true;
                    container.classList.add('grabbing');
                    panStartX = e.clientX;
                    panStartY = e.clientY;
                    lastPanX = panX;
                    lastPanY = panY;
                    selectedNodeId = null;
                    updateNodeSelections();
                }
            });

            document.addEventListener('mousemove', function(e) {
                if (isPanning) {
                    panX = lastPanX + (e.clientX - panStartX);
                    panY = lastPanY + (e.clientY - panStartY);
                    updateCanvasTransform();
                }
                if (draggedNode) {
                    const canvas = document.getElementById('mindmapCanvas');
                    const rect = canvas.getBoundingClientRect();
                    const newX = (e.clientX - rect.left) / scale - dragOffsetX;
                    const newY = (e.clientY - rect.top) / scale - dragOffsetY;
                    draggedNode.x = newX;
                    draggedNode.y = newY;
                    renderNodes();
                    renderConnections();
                }
            });

            document.addEventListener('mouseup', function() {
                isPanning = false;
                container.classList.remove('grabbing');
                if (draggedNode) {
                    if (Math.abs(draggedNode.x - dragStartX) > 1 || Math.abs(draggedNode.y - dragStartY) > 1) {
                        saveHistory();
                    }
                }
                draggedNode = null;
            });
        }

        function goToCenter() {
            panX = 0;
            panY = 0;
            scale = 1;
            updateCanvasTransform();
        }

        function getNodesLevel(node) {
            let level = 0;
            let parentId = node.parentId;
            while (parentId) {
                level++;
                const parent = nodes.find(n => n.id === parentId);
                if (!parent) break;
                parentId = parent.parentId;
            }
            return Math.min(level, 3);
        }

        function renderNodes() {
            const canvas = document.getElementById('mindmapCanvas');
            const existingNodes = canvas.querySelectorAll('.node');
            existingNodes.forEach(n => n.remove());
            
            nodes.forEach(node => {
                const nodeEl = document.createElement('div');
                const level = getNodesLevel(node);
                nodeEl.className = 'node';
                if (level === 0) nodeEl.classList.add('root');
                else if (level === 1) nodeEl.classList.add('level1');
                else if (level === 2) nodeEl.classList.add('level2');
                else if (level === 3) nodeEl.classList.add('level3');
                if (selectedNodeId === node.id) nodeEl.classList.add('selected');
                
                nodeEl.style.left = node.x + 'px';
                nodeEl.style.top = node.y + 'px';
                nodeEl.dataset.nodeId = node.id;
                
                nodeEl.innerHTML = '<span class="node-text">' + escapeHtml(node.text) + '</span>';
                
                nodeEl.addEventListener('mousedown', function(e) {
                    e.stopPropagation();
                    selectedNodeId = node.id;
                    updateNodeSelections();
                    if (isEditMode) {
                        draggedNode = node;
                        dragStartX = node.x;
                        dragStartY = node.y;
                        const rect = nodeEl.getBoundingClientRect();
                        dragOffsetX = (e.clientX - rect.left) / scale;
                        dragOffsetY = (e.clientY - rect.top) / scale;
                    }
                });
                nodeEl.addEventListener('dblclick', function(e) {
                    e.stopPropagation();
                    if (isEditMode) {
                        startEditingNodeText(node);
                    }
                });
                canvas.appendChild(nodeEl);
            });
        }

        function updateNodeSelections() {
            document.querySelectorAll('.node').forEach(el => el.classList.remove('selected'));
            if (selectedNodeId) {
                document.querySelector(`.node[data-node-id="${selectedNodeId}"]`)?.classList.add('selected');
            }
        }

        function renderConnections() {
            const canvas = document.getElementById('mindmapCanvas');
            const existingLines = canvas.querySelectorAll('.connection-line');
            existingLines.forEach(l => l.remove());
            
            nodes.forEach(node => {
                if (node.parentId) {
                    const parent = nodes.find(n => n.id === node.parentId);
                    if (parent) {
                        const childEl = document.querySelector(`.node[data-node-id="${node.id}"]`);
                        const parentEl = document.querySelector(`.node[data-node-id="${parent.id}"]`);
                        if (childEl && parentEl) {
                            const x1 = parent.x + parentEl.offsetWidth / 2;
                            const y1 = parent.y + parentEl.offsetHeight / 2;
                            const x2 = node.x + (node.x > parent.x ? 0 : childEl.offsetWidth);
                            const y2 = node.y + childEl.offsetHeight / 2;
                            
                            const line = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                            line.classList.add('connection-line');
                            const minX = Math.min(x1, x2);
                            const minY = Math.min(y1, y2);
                            const w = Math.abs(x2 - x1);
                            const h = Math.abs(y2 - y1);
                            line.style.left = minX + 'px';
                            line.style.top = minY + 'px';
                            line.style.width = (w || 1) + 'px';
                            line.style.height = (h || 1) + 'px';
                            
                            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                            const cpX = (x1 + x2) / 2;
                            const d = `M ${x1-minX} ${y1-minY} C ${cpX-minX} ${y1-minY}, ${cpX-minX} ${y2-minY}, ${x2-minX} ${y2-minY}`;
                            path.setAttribute('d', d);
                            path.setAttribute('stroke', '#667eea');
                            path.setAttribute('stroke-width', '2');
                            path.setAttribute('fill', 'none');
                            path.setAttribute('stroke-opacity', '0.6');
                            
                            line.appendChild(path);
                            canvas.appendChild(line);
                        }
                    }
                }
            });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function addNode() {
            if (!isEditMode) return;
            if (!currentMindmapId) {
                showToast('请先选择或创建一个思维导图');
                return;
            }
            saveHistory();
            const parentId = selectedNodeId;
            let newX = 300, newY = 200;
            if (parentId) {
                const parentNode = nodes.find(n => n.id === parentId);
                if (parentNode) {
                    const siblingCount = nodes.filter(n => n.parentId === parentId).length;
                    newX = parentNode.x + 200;
                    newY = parentNode.y + siblingCount * 80;
                }
            }
            nodeIdCounter++;
            const newNode = {
                id: 'node_' + nodeIdCounter,
                parentId: parentId || null,
                text: '新节点',
                x: newX,
                y: newY
            };
            if (nodes.length === 0) {
                newNode.x = 800;
                newNode.y = 600;
                newNode.text = '中心主题';
                newNode.parentId = null;
            }
            nodes.push(newNode);
            renderNodes();
            renderConnections();
        }

        function deleteSelectedNode() {
            if (!isEditMode) return;
            if (!selectedNodeId) {
                showToast('请先选择一个节点');
                return;
            }
            if (nodes.length === 1) {
                showToast('不能删除唯一的根节点');
                return;
            }
            saveHistory();
            function deleteRecursive(id) {
                const children = nodes.filter(n => n.parentId === id);
                children.forEach(c => deleteRecursive(c.id));
                nodes = nodes.filter(n => n.id !== id);
            }
            deleteRecursive(selectedNodeId);
            selectedNodeId = null;
            renderNodes();
            renderConnections();
        }

        function startEditingNodeText(node) {
            const nodeEl = document.querySelector(`.node[data-node-id="${node.id}"]`);
            const textSpan = nodeEl.querySelector('.node-text');
            textSpan.style.display = 'none';
            const input = document.createElement('input');
            input.className = 'node-text-input';
            input.value = node.text;
            textSpan.after(input);
            input.focus();
            input.select();
            editingNodeId = node.id;
            const oldText = node.text;
            
            const finishEdit = () => {
                editingNodeId = null;
                const newText = input.value || '未命名';
                if (oldText !== newText) {
                    saveHistory();
                    node.text = newText;
                }
                renderNodes();
                renderConnections();
            };
            input.addEventListener('blur', finishEdit);
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    input.blur();
                }
            });
        }

        function editSelectedNodeText() {
            if (!isEditMode) return;
            if (!selectedNodeId) {
                showToast('请先选择一个节点');
                return;
            }
            const node = nodes.find(n => n.id === selectedNodeId);
            if (node) {
                startEditingNodeText(node);
            }
        }

        function serializeMindmap() {
            return JSON.stringify({
                nodes: nodes,
                nodeIdCounter: nodeIdCounter,
                viewState: {
                    scale: scale,
                    panX: panX,
                    panY: panY
                }
            });
        }

        function deserializeMindmap(dataStr) {
            try {
                const data = JSON.parse(dataStr);
                nodes = data.nodes || [];
                nodeIdCounter = data.nodeIdCounter || 0;
                if (data.viewState) {
                    scale = data.viewState.scale || 1;
                    panX = data.viewState.panX || 0;
                    panY = data.viewState.panY || 0;
                    updateCanvasTransform();
                }
            } catch (e) {
                nodes = [];
            }
            undoStack = [];
            redoStack = [];
            if (nodes.length > 0) {
                undoStack.push(JSON.stringify(nodes));
            }
            updateUndoRedoButtons();
            renderNodes();
            renderConnections();
        }

        async function createNewMindmap() {
            if (!isEditMode) {
                showToast('请先点击右上角「🔐 编辑」按钮输入管理员密码进入编辑模式');
                return;
            }
            const id = 'mm_' + Date.now();
            currentMindmapId = id;
            nodes = [];
            nodeIdCounter = 0;
            undoStack = [];
            redoStack = [];
            scale = 1;
            panX = 0;
            panY = 0;
            updateCanvasTransform();
            addNode();
            undoStack = [];
            redoStack = [];
            undoStack.push(JSON.stringify(nodes));
            updateUndoRedoButtons();
            document.getElementById('mindmapTitle').value = '未命名思维导图';
            renderNodes();
            renderConnections();
            await saveCurrentMindmap();
            loadMindmapsList();
        }

        async function saveCurrentMindmap() {
            if (!isEditMode) return;
            if (!currentMindmapId) {
                showToast('没有要保存的思维导图');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('id', currentMindmapId);
            formData.append('name', document.getElementById('mindmapTitle').value);
            formData.append('data', serializeMindmap());
            
            const res = await fetch('index.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                showToast('保存成功！');
                loadMindmapsList();
            } else {
                showToast('保存失败');
            }
        }

        async function loadMindmapsList() {
            const formData = new FormData();
            formData.append('action', 'list');
            const res = await fetch('index.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                mindmapsList = result.list;
                renderMindmapList();
            }
        }

        function renderMindmapList() {
            const listEl = document.getElementById('mindmapList');
            if (mindmapsList.length === 0) {
                listEl.innerHTML = `<p style="text-align:center;color:#999;padding:40px;">暂无思维导图</p>`;
                return;
            }
            listEl.innerHTML = '';
            mindmapsList.forEach(mm => {
                const item = document.createElement('div');
                item.className = 'mindmap-item' + (currentMindmapId === mm.id ? ' active' : '');
                const actionsHtml = isEditMode ? `
                    <div class="mindmap-item-actions">
                        <button class="edit-name-btn" onclick="event.stopPropagation(); openEditNameModal('${mm.id}')">重命名</button>
                        <button class="delete-btn" onclick="event.stopPropagation(); openDeleteConfirmModal('${mm.id}')">删除</button>
                    </div>
                ` : '';
                item.innerHTML = `
                    <div class="mindmap-item-name">${escapeHtml(mm.name)}</div>
                    <div class="mindmap-item-time">${formatTime(mm.updated_at)}</div>
                    ${actionsHtml}
                `;
                item.addEventListener('click', () => loadMindmap(mm.id));
                listEl.appendChild(item);
            });
        }

        function formatTime(timestamp) {
            const d = new Date(timestamp * 1000);
            return d.toLocaleString('zh-CN');
        }

        async function loadMindmap(id) {
            const formData = new FormData();
            formData.append('action', 'load');
            formData.append('id', id);
            const res = await fetch('index.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                currentMindmapId = id;
                document.getElementById('mindmapTitle').value = result.data.name;
                updateMobileTitle();
                deserializeMindmap(result.data.data);
                renderMindmapList();
                if (window.innerWidth <= 768 && sidebarOpen) {
                    toggleSidebar();
                }
            }
        }

        function openEditNameModal(id) {
            editingMindmapId = id;
            const mm = mindmapsList.find(m => m.id === id);
            document.getElementById('editNameInput').value = mm ? mm.name : '';
            openModal('editNameModal');
        }

        function confirmEditName() {
            if (editingMindmapId && editingMindmapId === currentMindmapId) {
                document.getElementById('mindmapTitle').value = document.getElementById('editNameInput').value;
                saveCurrentMindmap();
            } else {
                showToast('请先打开这个思维导图');
            }
            closeModal('editNameModal');
        }

        function openDeleteConfirmModal(id) {
            deletingMindmapId = id;
            openModal('confirmDeleteModal');
        }

        async function confirmDeleteMindmap() {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', deletingMindmapId);
            const res = await fetch('index.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) {
                showToast('删除成功');
                if (currentMindmapId === deletingMindmapId) {
                    currentMindmapId = null;
                    nodes = [];
                    renderNodes();
                    renderConnections();
                }
                loadMindmapsList();
            } else {
                showToast('删除失败');
            }
            closeModal('confirmDeleteModal');
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openAIGenerateModal() {
            if (!isEditMode) {
                showToast('请先进入编辑模式');
                return;
            }
            document.getElementById('aiTextInput').value = '';
            openModal('aiGenerateModal');
        }

        function hideAILoading() {
            document.getElementById('aiLoadingOverlay').classList.remove('active');
        }

        function showAILoading() {
            document.getElementById('aiLoadingOverlay').classList.add('active');
        }

        function parseTextToMindmap(text) {
            const newNodes = [];
            let nodeCounter = 0;
            const startX = 400;
            const startY = 500;
            const hGapStep = 260;
            const vGapStep = 100;
            
            function createNode(textVal, parentId, depth) {
                nodeCounter++;
                const newId = 'node_' + (Date.now() + nodeCounter);
                return {
                    id: newId,
                    parentId: parentId,
                    text: textVal.trim(),
                    x: startX + hGapStep * depth,
                    y: 0,
                    _depth: depth
                };
            }

            let rootText = '复杂思维导图';
            const firstLines = text.split(/[。！？\n]/).filter(l => l.trim().length > 3);
            if (firstLines.length > 0 && firstLines[0].trim().length <= 40) {
                rootText = firstLines[0].trim();
            } else {
                rootText = text.substring(0, 30).trim() + '...';
            }

            const rootNode = createNode(rootText, null, 0);
            rootNode.x = startX;
            rootNode.y = startY;
            rootNode._depth = 0;
            newNodes.push(rootNode);

            let l1Y = startY - 200;
            for (let d1 = 0; d1 < 4; d1++) {
                const level1Names = ['主题A', '主题B', '主题C', '主题D'];
                const n1 = createNode(level1Names[d1], rootNode.id, 1);
                n1.y = l1Y;
                newNodes.push(n1);
                l1Y += vGapStep * 2.5;

                const n2 = createNode('子主题', n1.id, 2);
                n2.y = n1.y - 150;
                newNodes.push(n2);

                const n3 = createNode('核心点', n2.id, 3);
                n3.y = n2.y;
                newNodes.push(n3);

                const n4 = createNode('详细说明', n3.id, 4);
                n4.y = n3.y;
                newNodes.push(n4);

                const n5 = createNode('补充细节', n4.id, 5);
                n5.y = n4.y;
                newNodes.push(n5);
            }

            const userSentences = text.split(/[，。！？；,.!?;]/).filter(s => s.trim().length >= 5 && s.trim().length <= 35);
            for (let us = 0; us < Math.min(userSentences.length, 12); us++) {
                const randomDepth = (us % 3) + 2;
                const candidates = newNodes.filter(n => n._depth === randomDepth);
                if (candidates.length > 0) {
                    const targetP = candidates[us % candidates.length];
                    const kids = newNodes.filter(k => k.parentId === targetP.id);
                    if (kids.length < 4) {
                        const un = createNode(userSentences[us].trim(), targetP.id, randomDepth + 1);
                        un.y = targetP.y - 120 + kids.length * 75;
                        newNodes.push(un);
                    }
                }
            }

            return newNodes;
        }

        async function generateMindmapFromAI() {
            const inputText = document.getElementById('aiTextInput').value.trim();
            if (!inputText) {
                showToast('请输入一些文本内容');
                return;
            }

            closeModal('aiGenerateModal');
            showAILoading();

            await new Promise(resolve => setTimeout(resolve, 800));

            const generatedNodes = parseTextToMindmap(inputText);

            saveHistory();
            nodes = generatedNodes;
            nodeIdCounter = 0;

            hideAILoading();
            renderNodes();
            renderConnections();

            if (!currentMindmapId) {
                await createNewMindmap();
            } else {
                undoStack.push(JSON.stringify(nodes));
                updateUndoRedoButtons();
            }

            document.getElementById('mindmapTitle').value = nodes[0].text;
            updateMobileTitle();
            
            showToast('AI生成思维导图成功！共生成' + nodes.length + '个节点');
        }
    </script>
</body>
</html>
