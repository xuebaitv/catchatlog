<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>对话展示</title>
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
        .header h1 {
            color: #fff;
            margin-bottom: 10px;
            font-size: 28px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
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
            <h1>💬 对话展示</h1>
            <p>优雅的查看并编辑你的话术</p>
        </div>

        <?php
        require_once 'inc/config.php';
        require_once 'inc/functions.php';

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

                    echo '<div class="chat-item" style="animation-delay: ' . ($groupIndex * 0.1 + $chatIndex * 0.05) . 's">';
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
    </script>
</body>
</html>