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
                <meta name="theme-color" content="#667eea">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
                <link rel="manifest" href="/manifest.json">
                <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white'>💬</text></svg>">
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
                <script>
                    if ('serviceWorker' in navigator) {
                        window.addEventListener('load', () => {
                            navigator.serviceWorker.register('/service-worker.js');
                        });
                    }
                </script>
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
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="快捷回复">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white'>💬</text></svg>">
    <title><?php echo htmlspecialchars($settings['page_title']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        @media (min-width: 1900px) {
            .container {
                max-width: 1800px;
            }
        }

        @media (min-width: 2200px) {
            .container {
                max-width: 2000px;
            }
        }

        @media (min-width: 2600px) {
            .container {
                max-width: 2400px;
            }
        }
        .top-section {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 22px 28px 20px 28px;
            margin-bottom: 30px;
            animation: fadeInDown 0.8s ease-out;
            position: relative;
            z-index: 1000;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 30px;
            margin-bottom: 15px;
            position: relative;
            z-index: 10;
            flex-wrap: wrap;
        }
        .header-content {
            text-align: left;
            flex: 1;
            min-width: 280px;
            padding-top: 5px;
        }
        .header h1 {
            color: #fff;
            margin-bottom: 3px;
            font-size: 26px;
            font-weight: 600;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.25);
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .header p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 14px;
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        .header-search {
            flex: 0 1 580px;
            min-width: 300px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .search-wrapper {
            flex: 1;
            position: relative;
        }
        .search-toolbar {
            display: flex;
            gap: 8px;
        }
        .toolbar-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
        }
        .toolbar-btn:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px) scale(1.08);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .logout-access-btn {
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
        }
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .back-to-top:hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
        }
        .group-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            justify-content: flex-start;
        }
        .group-nav-btn {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.9);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .group-nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        .search-container {
            position: relative;
            width: 100%;
        }
        .search-input {
            width: 100%;
            padding: 12px 115px 12px 48px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 22px;
            font-size: 14px;
            color: #fff;
            outline: none;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.65);
        }
        .search-input:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.35);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.65);
            font-size: 17px;
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
            padding: 7px 12px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 4px;
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
            background: #f8f9fc;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin-top: 10px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 99999;
            display: none;
            padding: 8px 0;
        }
        .search-results.active {
            display: block;
        }
        .search-results::-webkit-scrollbar {
            width: 6px;
        }
        .search-results::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.03);
            border-radius: 0 16px 16px 0;
        }
        .search-results::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 3px;
        }
        .search-results::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.25);
        }
        .search-result-item {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255, 255, 255, 0.8);
            margin: 8px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            position: relative;
        }
        .search-result-item:first-child {
            margin-top: 12px;
        }
        .search-result-item:last-child {
            margin-bottom: 16px;
        }
        .search-result-item:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .search-result-title {
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 5px;
        }
        .search-result-preview {
            font-size: 13px;
            color: #4a4a6a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .search-result-highlight {
            background: rgba(255, 193, 7, 0.6);
            color: #1a1a2e;
            padding: 0 4px;
            border-radius: 3px;
            font-weight: bold;
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
            padding: 40px;
            text-align: center;
            color: #6a6a8a;
            background: rgba(255, 255, 255, 0.8);
            margin: 15px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.7);
        }
        .result-count {
            padding: 12px 20px 0 20px;
            background: transparent;
            font-size: 12px;
            color: #4a4a6a;
            border-bottom: none;
        }
        .ai-result-section {
            background: transparent;
            border-bottom: none;
            margin: 10px 12px;
        }
        .ai-result-header {
            padding: 12px 18px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.25) 0%, rgba(118, 75, 162, 0.25) 100%);
            color: #1a1a2e;
            font-weight: bold;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 12px 12px 0 0;
            border: 1px solid rgba(102, 126, 234, 0.4);
            border-bottom: none;
        }
        .ai-result-loading {
            padding: 30px 20px;
            text-align: center;
            color: #667eea;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 0 0 12px 12px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-top: none;
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
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            margin: 0;
            border-radius: 0;
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-top: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ai-suggestion-item:first-child {
            border-radius: 0;
            border-top: 1px solid rgba(102, 126, 234, 0.4);
        }
        .ai-suggestion-item:last-child {
            border-radius: 0 0 12px 12px;
            margin-bottom: 0;
        }
        .ai-suggestion-item:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            transform: translateX(3px);
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
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        @media (min-width: 1400px) {
            .groups-container {
                max-width: 1600px;
            }
        }

        @media (min-width: 1800px) {
            .groups-container {
                max-width: 2000px;
            }
        }

        @media (min-width: 2200px) {
            .groups-container {
                max-width: 2400px;
            }
        }
        
        .group-section {
            width: 100%;
            margin-bottom: 0;
        }
        
        .group-section {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .group-section.animate-in {
            opacity: 1;
            transform: translateY(0);
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
        @media (max-width: 480px) {
            .top-section {
                padding: 10px 12px 8px 12px;
                margin-bottom: 12px;
            }
            .header {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
                margin-bottom: 0;
                padding: 0;
            }
            .header-content {
                text-align: center;
                padding-top: 0;
            }
            .header h1 {
                font-size: 17px;
                margin-bottom: 0;
            }
            .header p {
                display: none;
            }
            .header-search {
                width: 100%;
                flex-direction: column;
                gap: 8px;
            }
            .search-wrapper {
                width: 100%;
            }
            .search-toolbar {
                justify-content: center;
                gap: 6px;
            }
            .toolbar-btn {
                width: 34px;
                height: 34px;
                font-size: 15px;
            }
            .search-input {
                padding: 8px 70px 8px 34px;
                font-size: 12px;
            }
            .search-icon {
                left: 10px;
                font-size: 14px;
            }
            .ai-search-btn {
                padding: 4px 7px;
                font-size: 10px;
            }
            .ai-search-btn span:last-child {
                display: none;
            }
            .ai-search-btn span:first-child {
                margin-right: 0 !important;
            }
            .group-nav {
                gap: 4px;
                padding-top: 8px;
                justify-content: center;
            }
            .group-nav-btn {
                padding: 4px 8px;
                font-size: 10px;
            }
            .container {
                padding: 8px;
            }
            .groups-container {
                max-width: 100%;
            }
        }

        @media (min-width: 481px) and (max-width: 768px) {
            .top-section {
                padding: 14px 18px 12px 18px;
                margin-bottom: 18px;
            }
            .header {
                flex-direction: row;
                gap: 12px;
                align-items: center;
                margin-bottom: 10px;
                padding: 0;
                flex-wrap: wrap;
            }
            .header-content {
                text-align: left;
                padding-top: 0;
                flex: 0 0 auto;
            }
            .header h1 {
                font-size: 19px;
                margin-bottom: 0;
            }
            .header p {
                display: none;
            }
            .header-search {
                flex: 1;
                min-width: 240px;
                gap: 8px;
            }
            .search-wrapper {
                flex: 1;
            }
            .search-toolbar {
                justify-content: center;
                gap: 6px;
                flex-shrink: 0;
            }
            .toolbar-btn {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
            .search-input {
                padding: 9px 80px 9px 36px;
                font-size: 13px;
            }
            .search-icon {
                left: 12px;
                font-size: 15px;
            }
            .ai-search-btn {
                padding: 5px 10px;
                font-size: 11px;
            }
            .ai-search-btn span:last-child {
                display: none;
            }
            .group-nav {
                gap: 6px;
                padding-top: 10px;
                justify-content: flex-start;
            }
            .group-nav-btn {
                padding: 5px 10px;
                font-size: 11px;
            }
            .container {
                padding: 12px;
            }
            .groups-container {
                max-width: 500px;
                margin: 0 auto;
            }
        }

        @media (max-width: 768px) {
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
            .top-section {
                padding: 18px 22px 16px 22px;
            }
            .header {
                gap: 20px;
                align-items: center;
            }
            .header-content {
                min-width: 220px;
                padding-top: 0;
            }
            .header h1 {
                font-size: 22px;
            }
            .header p {
                font-size: 13px;
            }
            .header-search {
                flex: 0 1 450px;
                min-width: 250px;
                gap: 10px;
            }
            .search-input {
                padding: 10px 95px 10px 42px;
                font-size: 13px;
            }
            .search-icon {
                left: 14px;
                font-size: 16px;
            }
            .ai-search-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
            .toolbar-btn {
                width: 38px;
                height: 38px;
                font-size: 17px;
            }
            .group-nav {
                padding-top: 14px;
                gap: 8px;
            }
            .group-nav-btn {
                padding: 7px 14px;
                font-size: 12px;
            }
            .groups-container {
                max-width: 800px;
                margin: 0 auto;
            }
        }

        @media (min-width: 1025px) and (max-width: 1399px) {
            .top-section {
                padding: 20px 25px 18px 25px;
            }
            .header {
                gap: 25px;
                align-items: flex-start;
            }
            .header-content {
                min-width: 250px;
            }
            .header h1 {
                font-size: 24px;
            }
            .header-search {
                flex: 0 1 500px;
                min-width: 280px;
            }
            .search-input {
                padding: 11px 105px 11px 45px;
            }
            .groups-container {
                max-width: 1200px;
                margin: 0 auto;
            }
        }

        @media (min-width: 1400px) and (max-width: 1799px) {
            .header-search {
                flex: 0 1 650px;
            }
        }

        @media (min-width: 1800px) {
            .header-search {
                flex: 0 1 750px;
            }
            .search-input {
                padding: 13px 120px 13px 50px;
                font-size: 15px;
            }
            .toolbar-btn {
                width: 46px;
                height: 46px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-wrapper" id="bgWrapper"></div>
    <div class="bg-overlay"></div>
    
    <div class="container">
        <div class="top-section">
            <div class="header">
                <div class="header-content">
                    <h1>💬 <?php echo htmlspecialchars($settings['page_title']); ?></h1>
                    <p><?php echo htmlspecialchars($settings['page_subtitle']); ?></p>
                </div>
                <div class="header-search">
                    <div class="search-wrapper">
                        <div class="search-container">
                            <span class="search-icon">🔍</span>
                            <input type="text" class="search-input" id="searchInput" placeholder="输入关键词搜索对话." oninput="searchMessages(this.value); aiSearchActive=false;" onfocus="toggleSearchResults(true)" onblur="setTimeout(() => aiSearchActive || toggleSearchResults(false), 500)" onkeypress="if(event.key==='Enter'){aiSearchActive=true; triggerAiSearch();}">
                            <div class="search-actions">
                                <?php if ($settings['ai_enabled'] && !empty($settings['ai_api_key'])): ?>
                                    <button class="ai-search-btn" id="aiSearchBtn" onclick="aiSearchActive=true; setTimeout(() => triggerAiSearch(), 50);"><span>🤖</span><span>AI推荐</span></button>
                                <?php endif; ?>
                            </div>
                            <div class="search-results" id="searchResults"></div>
                        </div>
                    </div>
                    <div class="search-toolbar">
                        <a href="compact.php" class="toolbar-btn" title="紧凑视图">📋</a>
                        <a href="admin/login.php" class="toolbar-btn" title="后台管理">🔐</a>
                        <?php if ($settings['access_password_enabled'] && isset($_SESSION['access_granted']) && $_SESSION['access_granted']): ?>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="logout_access" class="toolbar-btn logout-access-btn" title="退出访问">🚪</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php

        function getBingWallpaper() {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1&mkt=zh-CN');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    if (isset($data['images']) && !empty($data['images'])) {
                        $imageUrl = $data['images'][0]['url'];
                        if (strpos($imageUrl, '//') === 0) {
                            $imageUrl = 'https:' . $imageUrl;
                        } elseif (strpos($imageUrl, 'http') !== 0) {
                            $imageUrl = 'https://www.bing.com' . $imageUrl;
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
            echo '</div>';
            echo '</div>';
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
            echo '</div>';
            
            echo '<div class="groups-container" id="groupsContainer">';
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

    </div>

    <button class="back-to-top" id="backToTop" onclick="scrollToTop()" title="回到顶部">⬆️</button>

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
        function initSmartMasonry() {
            const container = document.getElementById('groupsContainer');
            if (!container) return;
            
            const items = Array.from(container.querySelectorAll('.group-section'));
            if (items.length === 0) return;
            
            const width = window.innerWidth;
            let colCount = 3;
            let gap = 30;
            
            if (width <= 768) {
                colCount = 1;
                gap = 20;
            } else if (width <= 1024) {
                colCount = 2;
                gap = 25;
            } else if (width >= 2200) {
                colCount = 6;
            } else if (width >= 1800) {
                colCount = 5;
            } else if (width >= 1400) {
                colCount = 4;
            }
            
            container.style.display = 'flex';
            container.style.alignItems = 'flex-start';
            container.style.justifyContent = 'center';
            container.style.gap = gap + 'px';
            container.style.columns = 'auto';
            
            const existingCols = container.querySelectorAll('.masonry-column');
            existingCols.forEach(col => col.remove());
            
            const columns = [];
            const columnHeights = [];
            
            for (let i = 0; i < colCount; i++) {
                const col = document.createElement('div');
                col.className = 'masonry-column';
                col.style.flex = '1';
                col.style.display = 'flex';
                col.style.flexDirection = 'column';
                col.style.gap = gap + 'px';
                col.style.minWidth = '0';
                container.appendChild(col);
                columns.push(col);
                columnHeights.push(0);
            }
            
            items.forEach((item, index) => {
                item.style.marginBottom = '0';
                const shortestColIndex = columnHeights.indexOf(Math.min(...columnHeights));
                columns[shortestColIndex].appendChild(item);
                columnHeights[shortestColIndex] += item.offsetHeight + gap;
            });
        }

        let masonryTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(masonryTimeout);
            masonryTimeout = setTimeout(initSmartMasonry, 100);
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(initSmartMasonry, 300);
                initScrollEffects();
            });
        } else {
            setTimeout(initSmartMasonry, 300);
            initScrollEffects();
        }

        function initScrollEffects() {
            const backToTop = document.getElementById('backToTop');
            
            window.addEventListener('scroll', () => {
                const scrollY = window.scrollY;
                
                if (scrollY > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });

            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('animate-in');
                        }, index * 80);
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.group-section').forEach(section => {
                observer.observe(section);
            });
        }

        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

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
        
        function closeGroupFullscreen() {
            document.getElementById('groupFullscreenModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        document.getElementById('groupFullscreenModal').addEventListener('click', function(e) {
            if (e.target === this) {
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
            gridContainer.id = 'fullscreenMasonry';
            gridContainer.style.cssText = 'display: flex; align-items: flex-start; gap: 25px;';
            
            chatItems.forEach((item, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'group-fullscreen-item';
                wrapper.innerHTML = item.outerHTML;
                wrapper.style.marginBottom = '0';
                gridContainer.appendChild(wrapper);
            });
            
            bodyContainer.appendChild(gridContainer);
            
            initFullscreenMasonry();
            
            document.getElementById('groupFullscreenModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        function initFullscreenMasonry() {
            const container = document.getElementById('fullscreenMasonry');
            if (!container) return;
            
            const items = Array.from(container.querySelectorAll('.group-fullscreen-item'));
            if (items.length === 0) return;
            
            const width = window.innerWidth;
            let colCount = 3;
            let gap = 25;
            
            if (width <= 768) {
                colCount = 1;
                gap = 20;
            } else if (width <= 1024) {
                colCount = 2;
                gap = 22;
            } else if (width >= 2200) {
                colCount = 6;
            } else if (width >= 1800) {
                colCount = 5;
            } else if (width >= 1400) {
                colCount = 4;
            }
            
            container.style.gap = gap + 'px';
            
            const existingCols = container.querySelectorAll('.masonry-column');
            existingCols.forEach(col => col.remove());
            
            const columns = [];
            const columnHeights = [];
            
            for (let i = 0; i < colCount; i++) {
                const col = document.createElement('div');
                col.className = 'masonry-column';
                col.style.flex = '1';
                col.style.display = 'flex';
                col.style.flexDirection = 'column';
                col.style.gap = gap + 'px';
                col.style.minWidth = '0';
                container.appendChild(col);
                columns.push(col);
                columnHeights.push(0);
            }
            
            items.forEach((item, index) => {
                item.style.marginBottom = '0';
                const shortestColIndex = columnHeights.indexOf(Math.min(...columnHeights));
                columns[shortestColIndex].appendChild(item);
                columnHeights[shortestColIndex] += item.offsetHeight + gap;
            });
        }

        let fullscreenResizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(fullscreenResizeTimeout);
            fullscreenResizeTimeout = setTimeout(() => {
                if (document.getElementById('groupFullscreenModal').classList.contains('active')) {
                    initFullscreenMasonry();
                }
            }, 150);
        });

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
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(registration => {
                        console.log('PWA ServiceWorker registered successfully');
                    })
                    .catch(err => {
                        console.log('PWA ServiceWorker registration failed:', err);
                    });
            });
        }
    </script>
</body>
</html>