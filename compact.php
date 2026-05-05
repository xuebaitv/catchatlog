<?php
session_start();
require_once 'inc/config.php';
require_once 'inc/functions.php';
$settings = getSettings();

if (isset($_POST['logout_access'])) {
    unset($_SESSION['access_granted']);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

if ($settings['access_password_enabled']) {
    if (!isset($_SESSION['access_granted']) || !$_SESSION['access_granted']) {
        if (isset($_POST['access_password'])) {
            if ($_POST['access_password'] === $settings['access_password']) {
                $_SESSION['access_granted'] = true;
            } else {
                $error = '密码错误';
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
    <title><?php echo htmlspecialchars($settings['page_title']); ?> - 紧凑型</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 90%;
            max-width: 400px;
        }
        .password-box h2 {
            color: #333;
            margin-bottom: 30px;
        }
        .password-box input[type="password"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 15px;
            transition: border-color 0.3s;
        }
        .password-box input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .password-box button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .password-box button:hover {
            transform: translateY(-2px);
        }
        .error {
            color: #ff4444;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="password-box">
        <h2>🔐 访问验证</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="password" name="access_password" placeholder="请输入访问密码" required>
            <button type="submit">进入</button>
        </form>
    </div>
</body>
</html>
            <?php
            exit;
        }
    }
}

$groupedChats = getChatsGrouped();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f8fafc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23667eea'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='white'>💬</text></svg>">
    <title><?php echo htmlspecialchars($settings['page_title']); ?> - 紧凑型</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Microsoft YaHei', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.4;
        }
        .page-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .page-header h1 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            display: inline-block;
        }
        .page-header .subtitle {
            font-size: 12px;
            color: #64748b;
            margin-left: 10px;
        }
        .page-nav {
            float: right;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .nav-link {
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: #f1f5f9;
            color: #667eea;
        }
        .container {
            max-width: 100%;
            padding: 0;
        }
        .group-section {
            margin: 0;
        }
        .group-header {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 20px;
            position: sticky;
            top: 55px;
            z-index: 50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .group-name {
            font-weight: 600;
            font-size: 14px;
            color: #475569;
        }
        .group-count {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }
        .chat-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 1px;
            background: #e2e8f0;
        }
        .chat-item {
            background: white;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .chat-item:hover {
            background: #f8fafc;
        }
        .chat-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .chat-name {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
        }
        .chat-badge {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        .badge-customer {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .badge-me {
            background: #dcfce7;
            color: #15803d;
        }
        .chat-preview {
            font-size: 13px;
            color: #64748b;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        .chat-time {
            font-size: 11px;
            color: #94a3b8;
            float: right;
        }
        .message-inline {
            display: inline;
            margin-right: 8px;
        }
        .message-inline.me {
            color: #15803d;
        }
        .message-inline.customer {
            color: #1d4ed8;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 95%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
        }
        .modal-title {
            font-weight: 600;
            font-size: 16px;
        }
        .modal-close {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: #f1f5f9;
            cursor: pointer;
            font-size: 16px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .modal-close:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .modal-body {
            padding: 20px;
        }
        .message-item {
            margin-bottom: 12px;
            display: flex;
        }
        .message-item.me {
            justify-content: flex-end;
        }
        .message-wrapper {
            position: relative;
            max-width: 80%;
        }
        .message-bubble {
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
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
        .message-item.customer .message-bubble {
            background: #f1f5f9;
            color: #1e293b;
            border-bottom-left-radius: 4px;
        }
        .message-item.me .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message-images {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .message-images img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .message-images img:hover {
            transform: scale(1.05);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
            font-size: 14px;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @media (max-width: 768px) {
            .chat-list {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 16px;
            }
            .page-header .subtitle {
                display: none;
            }
            .copy-btn {
                opacity: 1;
                visibility: visible;
            }
        }
        @media (min-width: 1400px) {
            .chat-list {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (min-width: 1900px) {
            .chat-list {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        @media (min-width: 2400px) {
            .chat-list {
                grid-template-columns: repeat(5, 1fr);
            }
        }
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .image-modal.active {
            display: flex;
        }
        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }
        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 32px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>💬 <?php echo htmlspecialchars($settings['page_title']); ?></h1>
        <span class="subtitle"><?php echo htmlspecialchars($settings['page_subtitle']); ?></span>
        <div class="page-nav">
            <a href="index.php" class="nav-link">卡片视图</a>
            <a href="admin/login.php" class="nav-link">后台管理</a>
            <?php if ($settings['access_password_enabled'] && isset($_SESSION['access_granted']) && $_SESSION['access_granted']): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout_access" class="nav-link" style="border: none; background: none; cursor: pointer;">退出访问</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (empty($groupedChats)): ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
                <div>暂无对话数据</div>
            </div>
        <?php else: ?>
            <?php foreach ($groupedChats as $groupKey => $groupData): ?>
                <div class="group-section">
                    <div class="group-header">
                        <span class="group-name"><?php echo htmlspecialchars($groupData['group']['name']); ?></span>
                        <span class="group-count"><?php echo count($groupData['chats']); ?> 条</span>
                    </div>
                    <div class="chat-list">
                        <?php foreach ($groupData['chats'] as $chat): ?>
                            <div class="chat-item" onclick="openChatDetail(<?php echo htmlspecialchars(json_encode($chat), ENT_QUOTES); ?>)">
                                <div class="chat-header">
                                    <span class="chat-name"><?php echo htmlspecialchars($chat['name']); ?></span>
                                    <span class="chat-time"><?php echo date('m-d H:i', $chat['timestamp']); ?></span>
                                </div>
                                <div class="chat-preview">
                                    <?php
                                    $previewText = '';
                                    if (!empty($chat['messages']) && is_array($chat['messages'])) {
                                        foreach ($chat['messages'] as $msg) {
                                            $sender = $msg['sender'] === 'me' ? '【我】' : '【客户】';
                                            $previewText .= $sender . $msg['content'] . ' ';
                                        }
                                    } elseif (!empty($chat['content'])) {
                                        $sender = isset($chat['sender']) && $chat['sender'] === 'me' ? '【我】' : '【客户】';
                                        $previewText = $sender . $chat['content'];
                                    }
                                    echo htmlspecialchars(mb_substr($previewText, 0, 120));
                                    if (mb_strlen($previewText) > 120) echo '...';
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="modal" id="chatModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title" id="modalTitle">对话详情</span>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>

    <div class="image-modal" id="imageModal" onclick="closeImageModal()">
        <span class="image-modal-close">×</span>
        <img src="" id="modalImage">
    </div>

    <script>
        function openChatDetail(chat) {
            document.getElementById('modalTitle').textContent = chat.name;
            const body = document.getElementById('modalBody');
            body.innerHTML = '';
            
            let messages = [];
            if (chat.messages && Array.isArray(chat.messages)) {
                messages = chat.messages;
            } else {
                messages = [{
                    sender: chat.sender || 'customer',
                    content: chat.content,
                    images: chat.images || []
                }];
            }
            
            messages.forEach(msg => {
                const item = document.createElement('div');
                item.className = 'message-item ' + msg.sender;
                
                let imagesHtml = '';
                if (msg.images && msg.images.length > 0) {
                    imagesHtml = '<div class="message-images">';
                    msg.images.forEach(img => {
                        imagesHtml += '<img src="photos/' + img + '" onclick="openImage(\'photos/' + img + '\')">';
                    });
                    imagesHtml += '</div>';
                }
                
                item.innerHTML = '<div class="message-wrapper"><button class="copy-btn" onclick="copyMessage(this, \'' + escapeHtml(msg.content) + '\')" title="复制内容">📋</button><div class="message-bubble">' + msg.content + imagesHtml + '</div></div>';
                body.appendChild(item);
            });
            
            document.getElementById('chatModal').classList.add('active');
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/'/g, "\\'");
        }
        
        function copyMessage(button, text) {
            const success = copyToClipboard(text);
            if (success) {
                button.classList.add('copied');
                button.innerHTML = '✓';
                setTimeout(() => {
                    button.classList.remove('copied');
                    button.innerHTML = '📋';
                }, 1500);
            }
        }
        
        function copyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                document.body.removeChild(textarea);
                return true;
            } catch (err) {
                document.body.removeChild(textarea);
                return false;
            }
        }
        
        function closeModal() {
            document.getElementById('chatModal').classList.remove('active');
        }
        
        function openImage(src) {
            event.stopPropagation();
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').classList.add('active');
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }
        
        document.getElementById('chatModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeImageModal();
            }
        });
    </script>
</body>
</html>
