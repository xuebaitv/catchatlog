<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/functions.php';
$settings = getSettings();

if (isset($_POST['action']) && $_POST['action'] === 'ai_suggest') {
    header('Content-Type: application/json');
    
    if (!$settings['ai_enabled'] || empty($settings['ai_api_key'])) {
        echo json_encode(['success' => false, 'message' => 'AI功能未启用或API未配置']);
        exit;
    }
    
    $userQuestion = $_POST['question'];
    if (empty($userQuestion)) {
        echo json_encode(['success' => false, 'message' => '请输入问题']);
        exit;
    }
    
    $allChats = getChats();
    $chatHistory = '';
    foreach ($allChats as $chat) {
        if (!empty($chat['messages']) && is_array($chat['messages'])) {
            foreach ($chat['messages'] as $msg) {
                $role = $msg['sender'] === 'me' ? '客服' : '客户';
                $chatHistory .= "[{$role}]: {$msg['content']}\n";
            }
        }
    }
    
    $systemPrompt = $settings['ai_system_prompt'] . "\n\n参考对话记录：\n" . $chatHistory;
    
    $requestData = [
        'model' => $settings['ai_model'],
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => '用户问题：' . $userQuestion . "\n请给我3个不同风格的回复建议。"]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $settings['ai_api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $settings['ai_api_key']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if (!empty($_SERVER['HTTP_PROXY'])) {
        curl_setopt($ch, CURLOPT_PROXY, $_SERVER['HTTP_PROXY']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo json_encode(['success' => false, 'message' => 'CURL错误：' . $error]);
        exit;
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode !== 200) {
        echo json_encode([
            'success' => false, 
            'message' => 'HTTP错误 ' . $httpCode . ': ' . ($result['error']['message'] ?? print_r($result, true))
        ]);
        exit;
    }
    
    if (!isset($result['choices'][0]['message']['content'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'API返回格式错误: ' . print_r($result, true)
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'content' => $result['choices'][0]['message']['content']
    ]);
    exit;
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
                <title>访问验证 - <?php echo htmlspecialchars($settings['page_title']); ?></title>
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
    <title><?php echo htmlspecialchars($settings['page_title']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            position: relative;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.8s ease-out;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 10;
        }
        .header h1 {
            color: #fff;
            margin-bottom: 10px;
            font-size: 28px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .logout-access-btn {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        .logout-access-btn:hover {
            background: rgba(255, 68, 68, 0.4);
        }
        .group-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
            justify-content: center;
        }
        .group-nav-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #fff;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .group-nav-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .search-container {
            margin-bottom: 30px;
            position: relative;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .search-input {
            width: 100%;
            padding: 14px 120px 14px 50px;
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 30px;
            font-size: 15px;
            color: #333;
            outline: none;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .search-input::placeholder {
            color: #999;
        }
        .search-input:focus {
            background: #fff;
            border-color: #667eea;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
            z-index: 2;
        }
        .search-actions {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 5px;
            z-index: 2;
        }
        .ai-search-btn {
            padding: 8px 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .ai-search-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.4);
        }
        .ai-search-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .ai-search-btn .loading {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-top: 8px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .search-results.active {
            display: block;
        }
        .search-result-item {
            padding: 15px 20px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item:hover {
            background: #f8f9fa;
        }
        .search-result-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .search-result-preview {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .search-result-highlight {
            background: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }
        .message-highlight {
            animation: messagePulse 0.5s ease-in-out 3;
            box-shadow: 0 0 0 3px rgba(255, 76, 76, 0.4) !important;
        }
        .keyword-highlight {
            background-color: #ff4757;
            color: white;
            padding: 0 4px;
            border-radius: 3px;
            font-weight: bold;
        }
        @keyframes messagePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .no-results {
            padding: 30px;
            text-align: center;
            color: #999;
        }
        .result-count {
            padding: 10px 20px;
            background: #f8f9fa;
            font-size: 12px;
            color: #666;
            border-bottom: 1px solid #eee;
        }
        .ai-result-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-bottom: 2px solid #e9ecef;
        }
        .ai-result-header {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ai-result-loading {
            padding: 25px 20px;
            text-align: center;
            color: #667eea;
        }
        .ai-result-loading .spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        .ai-suggestion-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background 0.2s;
        }
        .ai-suggestion-item:last-child {
            border-bottom: none;
        }
        .ai-suggestion-item:hover {
            background: rgba(102, 126, 234, 0.08);
        }
        .ai-suggestion-label {
            font-size: 11px;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .ai-suggestion-text {
            color: #333;
            line-height: 1.6;
            font-size: 14px;
        }
        .ai-error {
            padding: 20px;
            text-align: center;
            color: #ff4757;
            font-size: 14px;
        }
        .groups-container {
            columns: 3;
            column-gap: 30px;
        }
        
        .group-section {
            width: 100%;
            break-inside: avoid;
            margin-bottom: 30px;
        }
        
        .group-section {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            animation: fadeInUp 0.8s ease-out;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .group-section:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.28);
            border-color: rgba(255, 255, 255, 0.35);
        }
        .group-title {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.25);
            position: relative;
        }
        
        .fullscreen-btn {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .group-section:hover .fullscreen-btn {
            opacity: 1;
            visibility: visible;
        }
        
        .fullscreen-btn:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: translateY(-50%) scale(1.1);
        }
        .group-title h2 {
            color: #fff;
            font-size: 20px;
            flex: 1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .chat-count-inline {
            font-size: 13px;
            font-weight: normal;
            background: rgba(255, 255, 255, 0.3);
            padding: 4px 12px;
            border-radius: 12px;
            margin-left: 10px;
        }
        .chat-count {
            background: rgba(255, 255, 255, 0.3);
            color: #fff;
            padding: 6px 14px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            margin-left: auto;
            margin-right: 50px;
        }
        .chat-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .chat-item {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.8s ease-out;
            animation-fill-mode: both;
        }
        .chat-item:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        .chat-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .chat-header .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: bold;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }
        .chat-item:hover .avatar {
            transform: scale(1.1);
        }
        .chat-header .avatar.me {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .chat-info {
            margin-left: 15px;
            flex: 1;
            min-width: 0;
        }
        .chat-name {
            font-weight: bold;
            color: #fff;
            font-size: 16px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .chat-time {
            color: rgba(255, 255, 255, 0.75);
            font-size: 13px;
            margin-top: 3px;
        }
        .messages {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .message {
            display: flex;
            max-width: 85%;
            width: fit-content;
            position: relative;
        }
        .message.customer {
            align-self: flex-start;
        }
        .message.me {
            align-self: flex-end;
        }
        .message-wrapper {
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .message-content {
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }
        .message.customer .message-content {
            border-bottom-left-radius: 4px;
            background: rgba(255, 255, 255, 0.9);
        }
        .message.me .message-content {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95) 0%, rgba(5, 150, 105, 0.95) 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .copy-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 28px;
            height: 28px;
            background: rgba(0, 0, 0, 0.7);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 14px;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .copy-btn:hover {
            background: rgba(0, 0, 0, 0.85);
            transform: scale(1.1);
        }
        .copy-btn.copied {
            background: #4caf50;
        }
        .message-wrapper:hover .copy-btn {
            opacity: 1;
            visibility: visible;
        }
        .message-text {
            line-height: 1.6;
            font-size: 15px;
            word-break: break-all;
            color: #333;
        }
        .message.me .message-text {
            color: #fff;
        }
        .message-images {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .message-image {
            max-width: 110px;
            max-height: 110px;
            border-radius: 12px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .message-image:hover {
            transform: scale(1.08);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }
        .message-time {
            font-size: 11px;
            color: #888;
            margin-top: 6px;
            text-align: right;
        }
        .message.me .message-time {
            color: rgba(255, 255, 255, 0.7);
        }
        .admin-link {
            text-align: center;
            margin-top: 30px;
            padding: 25px;
        }
        .admin-link a {
            color: #fff;
            text-decoration: none;
            padding: 14px 32px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            display: inline-block;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        .admin-link a:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }
        .no-chats {
            text-align: center;
            color: #fff;
            padding: 50px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.8s ease-out;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease-out;
        }
        .modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 16px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            animation: zoomIn 0.3s ease-out;
        }
        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            font-weight: 300;
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0.8;
        }
        .close-modal:hover {
            transform: rotate(90deg);
            opacity: 1;
        }
        
        .group-fullscreen-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1500;
            padding: 30px;
            box-sizing: border-box;
            overflow-y: auto;
        }
        
        .group-fullscreen-modal.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .group-fullscreen-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
        }
        
        .group-fullscreen-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px 25px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px 16px 0 0;
            position: relative;
        }
        
        .group-fullscreen-title {
            color: white;
            font-size: 28px;
            font-weight: 600;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin: 0;
            letter-spacing: 0.5px;
        }
        
        .group-fullscreen-close {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .group-fullscreen-close:hover {
            background: rgba(255, 100, 100, 0.4);
            transform: translateY(-50%) scale(1.1);
        }
        
        .group-fullscreen-body {
            padding: 20px;
        }
        
        .group-fullscreen-item {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }
        
        .group-fullscreen-item .chat-item {
            margin-bottom: 0 !important;
        }
        
        .group-fullscreen-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .group-fullscreen-body::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .group-fullscreen-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        
        .group-fullscreen-body::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        @media (max-width: 768px) {
            .group-nav {
                gap: 8px;
                margin-bottom: 20px;
            }
            .group-nav-btn {
                padding: 8px 16px;
                font-size: 13px;
            }
            .groups-container {
                columns: 1;
                column-gap: 20px;
            }
            .container {
                padding: 15px;
            }
            .header {
                padding: 25px 15px;
            }
            .header h1 {
                font-size: 24px;
            }
            .copy-btn {
                opacity: 1;
                visibility: visible;
            }
            .group-section {
                padding: 15px;
                margin-bottom: 15px;
            }
            .fullscreen-btn {
                opacity: 1;
                visibility: visible;
            }
            .group-fullscreen-modal {
                padding: 15px;
            }
            .group-fullscreen-content {
                padding: 20px;
            }
            .group-fullscreen-title {
                font-size: 20px;
            }
            .group-fullscreen-close {
                right: 15px;
                width: 36px;
                height: 36px;
                font-size: 20px;
            }
            .chat-count-inline {
                font-size: 11px;
                padding: 3px 8px;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .groups-container {
                columns: 2;
                column-gap: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-wrapper" id="bgWrapper"></div>
    <div class="bg-overlay"></div>
    
    <div class="container">
        <div class="header">
            <h1>💬 <?php echo htmlspecialchars($settings['page_title']); ?></h1>
            <p><?php echo htmlspecialchars($settings['page_subtitle']); ?></p>
            <?php if ($settings['access_password_enabled'] && isset($_SESSION['access_granted']) && $_SESSION['access_granted']): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout_access" class="logout-access-btn">🚪 退出访问</button>
                </form>
            <?php endif; ?>
        </div>

        <?php

        function getBingWallpaper() {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1&mkt=zh-CN');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    if (isset($data['images']) && !empty($data['images'])) {
                        $imageUrl = $data['images'][0]['url'];
                        if (strpos($imageUrl, '//') === 0) {
                            $imageUrl = 'http:' . $imageUrl;
                        } elseif (strpos($imageUrl, 'http') !== 0) {
                            $imageUrl = 'http://www.bing.com' . $imageUrl;
                        }
                        return $imageUrl;
                    }
                }
            } catch (Exception $e) {
                error_log('获取必应壁纸失败: ' . $e->getMessage());
            }
            return null;
        }

        $bgImage = getBingWallpaper();
        if (!$bgImage) {
            $bgImage = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        }

        $groupedChats = getChatsGrouped();
        $hasChats = false;
        foreach ($groupedChats as $groupData) {
            if (!empty($groupData['chats'])) {
                $hasChats = true;
                break;
            }
        }
        ?>

        <script>
            const bgWrapper = document.getElementById('bgWrapper');
            <?php if (strpos($bgImage, 'http') === 0): ?>
                bgWrapper.style.backgroundImage = `url('<?php echo htmlspecialchars($bgImage); ?>')`;
            <?php else: ?>
                bgWrapper.style.backgroundImage = '<?php echo $bgImage; ?>';
            <?php endif; ?>
        </script>

        <?php
        if (!$hasChats) {
            echo '<div class="no-chats">暂无对话内容，请登录后台添加</div>';
        } else {
            // 输出分组导航
            echo '<div class="group-nav">';
            foreach ($groupedChats as $groupId => $groupData) {
                if (empty($groupData['chats'])) {
                    continue;
                }
                echo '<button class="group-nav-btn" onclick="openGroupFullscreen(\'' . htmlspecialchars($groupData['group']['id'], ENT_QUOTES) . '\', \'' . htmlspecialchars($groupData['group']['name'], ENT_QUOTES) . '\')">';
                echo htmlspecialchars($groupData['group']['name']);
                echo '</button>';
            }
            echo '</div>';

            echo '<div class="search-container">';
            echo '<span class="search-icon">🔍</span>';
            echo '<input type="text" class="search-input" id="searchInput" placeholder="输入关键词搜索对话." oninput="searchMessages(this.value); aiSearchActive=false;" onfocus="toggleSearchResults(true)" onblur="setTimeout(() => aiSearchActive || toggleSearchResults(false), 500)" onkeypress="if(event.key===\'Enter\'){aiSearchActive=true; triggerAiSearch();}">';
            echo '<div class="search-actions">';
            if ($settings['ai_enabled'] && !empty($settings['ai_api_key'])) {
                echo '<button class="ai-search-btn" id="aiSearchBtn" onclick="aiSearchActive=true; setTimeout(() => triggerAiSearch(), 50);"><span>🤖</span><span>AI推荐</span></button>';
            }
            echo '</div>';
            echo '<div class="search-results" id="searchResults"></div>';
            echo '</div>';
            
            echo '<div class="groups-container">';
            $groupIndex = 0;
            foreach ($groupedChats as $groupId => $groupData) {
                if (empty($groupData['chats'])) {
                    continue;
                }
                $groupIndex++;
                echo '<div class="group-section" id="group-' . htmlspecialchars($groupData['group']['id']) . '" style="animation-delay: ' . ($groupIndex * 0.1) . 's">';
                echo '<div class="group-title">';
                echo '<h2>' . htmlspecialchars($groupData['group']['name']) . ' <span class="chat-count-inline">' . count($groupData['chats']) . ' 条</span></h2>';
                echo '<button class="fullscreen-btn" onclick="openGroupFullscreen(\'' . htmlspecialchars($groupData['group']['id'], ENT_QUOTES) . '\', \'' . htmlspecialchars($groupData['group']['name'], ENT_QUOTES) . '\')" title="全屏查看">⛶</button>';
                echo '</div>';
                echo '<div class="chat-list">';

                $chatIndex = 0;
                foreach ($groupData['chats'] as $chat) {
                    $chatIndex++;
                    $hasMessages = !empty($chat['messages']) && is_array($chat['messages']);

                    echo '<div class="chat-item" id="chat-' . htmlspecialchars($chat['id']) . '" style="animation-delay: ' . ($groupIndex * 0.1 + $chatIndex * 0.05) . 's">';
                    echo '<div class="chat-header">';
                    echo '<div class="avatar' . (!empty($chat['sender']) && $chat['sender'] === 'me' ? ' me' : '') . '">';
                    echo mb_substr($chat['name'], 0, 1);
                    echo '</div>';
                    echo '<div class="chat-info">';
                    echo '<div class="chat-name">' . htmlspecialchars($chat['name']) . '</div>';
                    echo '<div class="chat-time">' . date('Y-m-d H:i', $chat['timestamp']) . '</div>';
                    echo '</div>';
                    echo '</div>';

                    echo '<div class="messages">';

                    if ($hasMessages) {
                        foreach ($chat['messages'] as $msgIndex => $msg) {
                            $isMe = !empty($msg['sender']) && $msg['sender'] === 'me';
                            echo '<div class="message ' . ($isMe ? 'me' : 'customer') . '">';
                            echo '<div class="message-wrapper">';
                            echo '<button class="copy-btn" onclick="copyMessage(this, \'' . addslashes(htmlspecialchars($msg['content'])) . '\')" title="复制内容">📋</button>';
                            echo '<div class="message-content">';
                            echo '<div class="message-text">' . nl2br(htmlspecialchars($msg['content'])) . '</div>';
                            if (!empty($msg['images']) && is_array($msg['images'])) {
                                echo '<div class="message-images">';
                                foreach ($msg['images'] as $image) {
                                    echo '<img src="' . PHOTOS_URL . htmlspecialchars($image) . '" class="message-image" onclick="openModal(this.src)">';
                                }
                                echo '</div>';
                            }
                            echo '<div class="message-time">' . date('H:i', $msg['timestamp']) . '</div>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        $isMe = !empty($chat['sender']) && $chat['sender'] === 'me';
                        echo '<div class="message ' . ($isMe ? 'me' : 'customer') . '">';
                        echo '<div class="message-wrapper">';
                        echo '<button class="copy-btn" onclick="copyMessage(this, \'' . addslashes(htmlspecialchars($chat['content'])) . '\')" title="复制内容">📋</button>';
                        echo '<div class="message-content">';
                        echo '<div class="message-text">' . nl2br(htmlspecialchars($chat['content'])) . '</div>';
                        if (!empty($chat['images']) && is_array($chat['images'])) {
                            echo '<div class="message-images">';
                            foreach ($chat['images'] as $image) {
                                echo '<img src="' . PHOTOS_URL . htmlspecialchars($image) . '" class="message-image" onclick="openModal(this.src)">';
                            }
                            echo '</div>';
                        }
                        echo '<div class="message-time">' . date('H:i', $chat['timestamp']) . '</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }

                    echo '</div>';
                    echo '</div>';
                }

                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        ?>

        <div class="admin-link">
            <a href="admin/login.php">🔐 后台管理</a>
        </div>
    </div>

    <div class="modal" id="imageModal" onclick="closeModal()">
        <span class="close-modal">&times;</span>
        <img src="" id="modalImage">
    </div>

    <div class="group-fullscreen-modal" id="groupFullscreenModal">
        <div class="group-fullscreen-content">
            <div class="group-fullscreen-header">
                <h2 class="group-fullscreen-title" id="groupFullscreenTitle"></h2>
            <button class="group-fullscreen-close" onclick="closeGroupFullscreen()" title="关闭全屏">✕</button>
            </div>
            <div class="group-fullscreen-body" id="groupFullscreenBody">
            </div>
        </div>
    </div>

    <script>
        function copyMessage(btn, text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    btn.textContent = '✓';
                    btn.classList.add('copied');
                    setTimeout(() => {
                        btn.textContent = '📋';
                        btn.classList.remove('copied');
                    }, 1500);
                }).catch(err => {
                    fallbackCopyText(text, btn);
                });
            } else {
                fallbackCopyText(text, btn);
            }
        }

        function fallbackCopyText(text, btn) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                btn.textContent = '✓';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.textContent = '📋';
                    btn.classList.remove('copied');
                }, 1500);
            } catch (err) {
                btn.textContent = '✗';
                setTimeout(() => {
                    btn.textContent = '📋';
                }, 1500);
            }
            document.body.removeChild(textarea);
        }

        function openModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('imageModal').classList.remove('active');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeGroupFullscreen();
            }
        });
        
        function openGroupFullscreen(groupId, groupName) {
            const groupElement = document.getElementById('group-' + groupId);
            if (!groupElement) return;
            
            const chatList = groupElement.querySelector('.chat-list');
            const chatItems = chatList.querySelectorAll('.chat-item');
            
            document.getElementById('groupFullscreenTitle').textContent = groupName;
            
            const bodyContainer = document.getElementById('groupFullscreenBody');
            bodyContainer.innerHTML = '';
            
            const gridContainer = document.createElement('div');
            gridContainer.className = 'group-fullscreen-grid';
            
            chatItems.forEach(function(item, index) {
                const itemWrapper = document.createElement('div');
                itemWrapper.className = 'group-fullscreen-item';
                itemWrapper.style.animationDelay = (index * 0.05) + 's';
                itemWrapper.innerHTML = item.outerHTML;
                gridContainer.appendChild(itemWrapper);
            });
            
            bodyContainer.appendChild(gridContainer);
            document.getElementById('groupFullscreenModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeGroupFullscreen() {
            document.getElementById('groupFullscreenModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        document.getElementById('groupFullscreenModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeGroupFullscreen();
            }
        });
        
        const originalOpenGroupFullscreen = openGroupFullscreen;
        openGroupFullscreen = function(groupId, groupName) {
            const groupElement = document.getElementById('group-' + groupId);
            if (!groupElement) return;
            
            const chatList = groupElement.querySelector('.chat-list');
            const chatItems = chatList.querySelectorAll('.chat-item');
            
            document.getElementById('groupFullscreenTitle').textContent = groupName;
            
            const bodyContainer = document.getElementById('groupFullscreenBody');
            bodyContainer.innerHTML = '';
            
            const colCount = window.innerWidth <= 768 ? 1 : (window.innerWidth <= 1024 ? 2 : 3);
            const gridContainer = document.createElement('div');
            gridContainer.style.cssText = 'columns: ' + colCount + '; column-gap: 25px;';
            
            chatItems.forEach((item, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'group-fullscreen-item';
                wrapper.style.cssText = 'break-inside: avoid; margin-bottom: 25px;';
                wrapper.innerHTML = item.outerHTML;
                gridContainer.appendChild(wrapper);
            });
            
            bodyContainer.appendChild(gridContainer);
            
            document.getElementById('groupFullscreenModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        const allChats = <?php
            $chatsData = [];
            foreach ($groupedChats as $groupData) {
                foreach ($groupData['chats'] as $chat) {
                    $chatData = [
                        'id' => $chat['id'],
                        'name' => $chat['name'],
                        'messages' => []
                    ];
                    if (!empty($chat['messages']) && is_array($chat['messages'])) {
                        foreach ($chat['messages'] as $msg) {
                            $chatData['messages'][] = $msg['content'];
                        }
                    }
                    $chatsData[] = $chatData;
                }
            }
            echo json_encode($chatsData, JSON_UNESCAPED_UNICODE);
        ?>;

        let currentSearchKeyword = '';

        function highlightText(text, keyword) {
            if (!keyword) return text;
            const regex = new RegExp('(' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            return text.replace(regex, '<span class="search-result-highlight">$1</span>');
        }

        function searchMessages(keyword) {
            const resultsContainer = document.getElementById('searchResults');
            const trimmedKeyword = keyword.trim().toLowerCase();
            currentSearchKeyword = trimmedKeyword;
            
            if (!trimmedKeyword) {
                resultsContainer.classList.remove('active');
                resultsContainer.innerHTML = '';
                return;
            }

            const results = [];
            allChats.forEach(chat => {
                const matchedMessages = chat.messages.filter(msg => 
                    msg.toLowerCase().includes(trimmedKeyword)
                );
                if (matchedMessages.length > 0) {
                    results.push({
                        id: chat.id,
                        name: chat.name,
                        preview: matchedMessages[0],
                        matchCount: matchedMessages.length
                    });
                }
            });

            if (results.length === 0) {
                resultsContainer.innerHTML = '<div class="no-results">未找到包含 "' + keyword + '" 的对话</div>';
            } else {
                let html = '<div class="result-count">找到 ' + results.length + ' 个相关对话</div>';
                results.forEach(chat => {
                    html += '<div class="search-result-item" onclick="jumpToChat(\'' + chat.id + '\', \'' + trimmedKeyword.replace(/'/g, "\\'") + '\')">';
                    html += '<div class="search-result-title">' + highlightText(chat.name, keyword) + ' <span style="font-size: 12px; color: #999; font-weight: normal;">(' + chat.matchCount + ' 条匹配)</span></div>';
                    html += '<div class="search-result-preview">' + highlightText(chat.preview, keyword) + '</div>';
                    html += '</div>';
                });
                resultsContainer.innerHTML = html;
            }
            resultsContainer.classList.add('active');
        }

        function toggleSearchResults(show) {
            const resultsContainer = document.getElementById('searchResults');
            const input = document.getElementById('searchInput');
            if (show && input.value.trim()) {
                resultsContainer.classList.add('active');
            } else if (!show && !aiSearchActive) {
                resultsContainer.classList.remove('active');
            }
        }

        function jumpToChat(chatId, keyword) {
            const element = document.getElementById('chat-' + chatId);
            if (element) {
                aiSearchActive = false;
                toggleSearchResults(false);
                document.getElementById('searchInput').value = '';
                
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                element.style.transition = 'box-shadow 0.3s, transform 0.3s';
                element.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.5)';
                element.style.transform = 'scale(1.02)';
                
                setTimeout(() => {
                    element.style.boxShadow = '';
                    element.style.transform = '';
                }, 2000);
                
                setTimeout(() => {
                    highlightMessages(element, keyword);
                }, 500);
            }
        }

        function highlightMessages(chatElement, keyword) {
            if (!keyword) return;
            
            const messages = chatElement.querySelectorAll('.message-text');
            const regex = new RegExp('(' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            
            messages.forEach((msgEl, index) => {
                const text = msgEl.innerHTML;
                if (regex.test(text)) {
                    const messageWrapper = msgEl.closest('.message');
                    if (messageWrapper) {
                        messageWrapper.classList.add('message-highlight');
                        setTimeout(() => {
                            messageWrapper.classList.remove('message-highlight');
                        }, 3000);
                    }
                    
                    msgEl.innerHTML = text.replace(regex, '<span class="keyword-highlight">$1</span>');
                    
                    setTimeout(() => {
                        msgEl.innerHTML = text;
                    }, 5000);
                }
            });
        }

        let aiSearchActive = false;

        function triggerAiSearch() {
            const input = document.getElementById('searchInput');
            const aiBtn = document.getElementById('aiSearchBtn');
            const question = input.value.trim();
            
            if (!question) {
                input.focus();
                return;
            }
            
            aiSearchActive = true;
            
            if (aiBtn) {
                aiBtn.disabled = true;
                aiBtn.innerHTML = '<span class="loading"></span><span>思考中</span>';
            }
            
            toggleSearchResults(true);
            renderAiLoading(question);
            
            const formData = new FormData();
            formData.append('action', 'ai_suggest');
            formData.append('question', question);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (aiBtn) {
                    aiBtn.disabled = false;
                    aiBtn.innerHTML = '<span>🤖</span><span>AI推荐</span>';
                }
                
                if (!data.success) {
                    renderAiError(data.message);
                    return;
                }
                
                renderAiResults(question, data.content);
            })
            .catch(err => {
                if (aiBtn) {
                    aiBtn.disabled = false;
                    aiBtn.innerHTML = '<span>🤖</span><span>AI推荐</span>';
                }
                renderAiError(err.message);
            });
        }

        function renderAiLoading(question) {
            const resultsContainer = document.getElementById('searchResults');
            aiSearchActive = true;
            
            let aiSection = resultsContainer.querySelector('.ai-result-section');
            
            let aiHtml = `
                <div class="ai-result-section">
                    <div class="ai-result-header">
                        <span>🤖</span>
                        <span>AI智能分析中："${question}"</span>
                    </div>
                    <div class="ai-result-loading">
                        <div class="spinner"></div>
                        <div>正在分析所有历史对话记录，思考最佳回复...</div>
                    </div>
                </div>
            `;
            
            if (aiSection) {
                aiSection.outerHTML = aiHtml;
            } else {
                resultsContainer.innerHTML = aiHtml + resultsContainer.innerHTML;
            }
            
            resultsContainer.classList.add('active');
        }

        function renderAiError(message) {
            const resultsContainer = document.getElementById('searchResults');
            const aiSection = resultsContainer.querySelector('.ai-result-section');
            
            const troubleshoot = `
                <div style="font-size: 12px; margin-top: 10px; text-align: left; color: #666;">
                    <strong>🔧 常见问题排查：</strong>
                    <ul style="margin: 5px 0 0 20px; padding: 0;">
                        <li>检查API地址是否正确（需带/v1/chat/completions）</li>
                        <li>检查API Key是否正确</li>
                        <li>检查模型名称是否匹配（如:gpt-3.5-turbo）</li>
                        <li>确认服务器能否访问外网API</li>
                        <li>查看PHP curl扩展是否启用</li>
                    </ul>
                </div>
            `;
            
            if (aiSection) {
                aiSection.innerHTML = `
                    <div class="ai-result-header" style="background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%);">
                        <span>❌</span>
                        <span>AI推荐失败</span>
                    </div>
                    <div class="ai-error" style="text-align: left;">
                        <div style="margin-bottom: 10px;">错误信息：${message}</div>
                        ${troubleshoot}
                    </div>
                `;
            }
        }

        function renderAiResults(question, content) {
            const resultsContainer = document.getElementById('searchResults');
            
            const suggestions = content.split(/\d+\./).filter(s => s.trim()).slice(0, 3);
            const labels = ['亲切友好', '专业正式', '简洁高效'];
            
            let aiHtml = `
                <div class="ai-result-section">
                    <div class="ai-result-header">
                        <span>✨</span>
                        <span>AI推荐话术："${question}"（点击复制）</span>
                    </div>
            `;
            
            suggestions.forEach((suggestion, index) => {
                const label = labels[index] || `风格 ${index + 1}`;
                aiHtml += `
                    <div class="ai-suggestion-item" onclick="copyAiSuggestion(this)">
                        <div class="ai-suggestion-label">${label}</div>
                        <div class="ai-suggestion-text">${suggestion.trim()}</div>
                    </div>
                `;
            });
            
            aiHtml += '</div>';
            
            const existingAiSection = resultsContainer.querySelector('.ai-result-section');
            if (existingAiSection) {
                existingAiSection.outerHTML = aiHtml;
            } else {
                resultsContainer.innerHTML = aiHtml + resultsContainer.innerHTML;
            }
        }

        function copyAiSuggestion(element) {
            const text = element.querySelector('.ai-suggestion-text').textContent;
            const success = copyToClipboard(text);
            if (success) {
                const originalBg = element.style.background;
                element.style.background = '#d4edda';
                setTimeout(() => {
                    element.style.background = originalBg;
                }, 500);
            }
            aiSearchActive = true;
        }

        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                return true;
            }
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            return true;
        }

        document.addEventListener('click', function(e) {
            const searchContainer = document.querySelector('.search-container');
            if (searchContainer && !searchContainer.contains(e.target)) {
                aiSearchActive = false;
                toggleSearchResults(false);
            }
        });
    </script>
</body>
</html>