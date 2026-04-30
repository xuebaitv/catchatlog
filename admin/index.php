<?php
session_start();
require_once '../inc/config.php';
require_once '../inc/functions.php';

if (!checkAuth()) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                // 检测是否是 AJAX 请求 - 两种方式都检测
                $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (!empty($_POST['X-Requested-With']) && strtolower($_POST['X-Requested-With']) === 'xmlhttprequest');
        
        switch ($_POST['action']) {
            case 'add_chat':
                $name = $_POST['name'];
                $sender = $_POST['sender'];
                $content = $_POST['content'];
                $groupId = $_POST['group_id'];
                $images = [];

                if (!empty($_FILES['photos']['name'][0])) {
                    foreach ($_FILES['photos']['name'] as $key => $fileName) {
                        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['photos']['name'][$key],
                                'type' => $_FILES['photos']['type'][$key],
                                'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                                'error' => $_FILES['photos']['error'][$key],
                                'size' => $_FILES['photos']['size'][$key]
                            ];
                            $uploaded = uploadPhoto($file);
                            if ($uploaded) {
                                $images[] = $uploaded;
                            }
                        }
                    }
                }

                $chatData = [
                    'name' => $name,
                    'sender' => $sender,
                    'content' => $content,
                    'images' => $images,
                    'group_id' => $groupId
                ];

                if (saveChat($chatData)) {
                    $message = '对话添加成功';
                } else {
                    $message = '添加失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'add_multi_chat':
                $name = $_POST['name'];
                $groupId = $_POST['group_id'];
                $messages = [];
                $messageCount = isset($_POST['message_count']) ? intval($_POST['message_count']) : 0;
                
                for ($i = 1; $i <= $messageCount; $i++) {
                    $msgSender = $_POST['sender_' . $i];
                    $msgContent = $_POST['content_' . $i];
                    $msgImages = [];
                    
                    if (!empty($_FILES['photos_' . $i]['name'][0])) {
                        foreach ($_FILES['photos_' . $i]['name'] as $key => $fileName) {
                            if ($_FILES['photos_' . $i]['error'][$key] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['photos_' . $i]['name'][$key],
                                    'type' => $_FILES['photos_' . $i]['type'][$key],
                                    'tmp_name' => $_FILES['photos_' . $i]['tmp_name'][$key],
                                    'error' => $_FILES['photos_' . $i]['error'][$key],
                                    'size' => $_FILES['photos_' . $i]['size'][$key]
                                ];
                                $uploaded = uploadPhoto($file);
                                if ($uploaded) {
                                    $msgImages[] = $uploaded;
                                }
                            }
                        }
                    }
                    
                    $messages[] = [
                        'sender' => $msgSender,
                        'content' => $msgContent,
                        'images' => $msgImages,
                        'timestamp' => time() + $i
                    ];
                }

                $chatData = [
                    'name' => $name,
                    'group_id' => $groupId,
                    'messages' => $messages
                ];

                if (saveChat($chatData)) {
                    $message = '多消息对话添加成功';
                } else {
                    $message = '添加失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'delete_chat':
                $id = $_POST['id'];
                if (deleteChat($id)) {
                    $message = '对话删除成功';
                } else {
                    $message = '删除失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'update_chat':
                $chatId = $_POST['chat_id'];
                $filePath = CHAT_DIR . $chatId . '.json';
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $chat = json_decode($content, true);
                    if ($chat) {
                        $chat['name'] = $_POST['name'];
                        $chat['group_id'] = $_POST['group_id'];
                        
                        if (isset($_POST['sender'])) {
                            $chat['sender'] = $_POST['sender'];
                        }
                        if (isset($_POST['content'])) {
                            $chat['content'] = $_POST['content'];
                        }
                        
                        $existingImages = !empty($_POST['existing_images']) ? json_decode($_POST['existing_images'], true) : [];
                        $newImages = [];
                        
                        if (!empty($_FILES['photos']['name'][0])) {
                            foreach ($_FILES['photos']['name'] as $key => $fileName) {
                                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                                    $file = [
                                        'name' => $_FILES['photos']['name'][$key],
                                        'type' => $_FILES['photos']['type'][$key],
                                        'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                                        'error' => $_FILES['photos']['error'][$key],
                                        'size' => $_FILES['photos']['size'][$key]
                                    ];
                                    $uploaded = uploadPhoto($file);
                                    if ($uploaded) {
                                        $newImages[] = $uploaded;
                                    }
                                }
                            }
                        }
                        
                        $chat['images'] = array_merge($existingImages, $newImages);
                        
                        if (file_put_contents($filePath, json_encode($chat, JSON_UNESCAPED_UNICODE))) {
                            $message = '对话修改成功';
                        } else {
                            $message = '修改失败，请重试';
                            $messageType = 'error';
                        }
                    } else {
                        $message = '无效的会话数据';
                        $messageType = 'error';
                    }
                } else {
                    $message = '会话不存在';
                    $messageType = 'error';
                }
                break;

            case 'update_multi_chat':
                $chatId = $_POST['chat_id'];
                $filePath = CHAT_DIR . $chatId . '.json';
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $chat = json_decode($content, true);
                    if ($chat) {
                        $chat['name'] = $_POST['name'];
                        $chat['group_id'] = $_POST['group_id'];
                        
                        $msgCount = isset($_POST['msg_count']) ? intval($_POST['msg_count']) : 0;
                        $messages = [];
                        
                        for ($i = 0; $i < $msgCount; $i++) {
                            $msgSender = $_POST['msg_sender_' . $i];
                            $msgContent = $_POST['msg_content_' . $i];
                            $msgTimestamp = isset($_POST['msg_timestamp_' . $i]) ? $_POST['msg_timestamp_' . $i] : time() + $i;
                            $existingImages = !empty($_POST['msg_existing_images_' . $i]) ? json_decode($_POST['msg_existing_images_' . $i], true) : [];
                            $msgImages = $existingImages;
                            
                            if (!empty($_FILES['msg_photos_' . $i]['name'][0])) {
                                foreach ($_FILES['msg_photos_' . $i]['name'] as $key => $fileName) {
                                    if ($_FILES['msg_photos_' . $i]['error'][$key] === UPLOAD_ERR_OK) {
                                        $file = [
                                            'name' => $_FILES['msg_photos_' . $i]['name'][$key],
                                            'type' => $_FILES['msg_photos_' . $i]['type'][$key],
                                            'tmp_name' => $_FILES['msg_photos_' . $i]['tmp_name'][$key],
                                            'error' => $_FILES['msg_photos_' . $i]['error'][$key],
                                            'size' => $_FILES['msg_photos_' . $i]['size'][$key]
                                        ];
                                        $uploaded = uploadPhoto($file);
                                        if ($uploaded) {
                                            $msgImages[] = $uploaded;
                                        }
                                    }
                                }
                            }
                            
                            $messages[] = [
                                'sender' => $msgSender,
                                'content' => $msgContent,
                                'images' => $msgImages,
                                'timestamp' => intval($msgTimestamp)
                            ];
                        }
                        
                        $chat['messages'] = $messages;
                        
                        if (file_put_contents($filePath, json_encode($chat, JSON_UNESCAPED_UNICODE))) {
                            $message = '对话修改成功';
                        } else {
                            $message = '修改失败，请重试';
                            $messageType = 'error';
                        }
                    } else {
                        $message = '无效的会话数据';
                        $messageType = 'error';
                    }
                } else {
                    $message = '会话不存在';
                    $messageType = 'error';
                }
                break;

            case 'update_chat_group':
                $chatId = $_POST['chat_id'];
                $newGroupId = $_POST['new_group_id'];
                $filePath = CHAT_DIR . $chatId . '.json';
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $chat = json_decode($content, true);
                    if ($chat) {
                        $chat['group_id'] = $newGroupId;
                        if (file_put_contents($filePath, json_encode($chat, JSON_UNESCAPED_UNICODE))) {
                            $message = '会话分组修改成功';
                        } else {
                            $message = '修改失败，请重试';
                            $messageType = 'error';
                        }
                    } else {
                        $message = '无效的会话数据';
                        $messageType = 'error';
                    }
                } else {
                    $message = '会话不存在';
                    $messageType = 'error';
                }
                break;

            case 'add_group':
                $groupName = $_POST['group_name'];
                $groupDesc = $_POST['group_description'];
                if (addGroup($groupName, $groupDesc)) {
                    $message = '分组添加成功';
                } else {
                    $message = '分组添加失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'update_group':
                $groupId = $_POST['group_id'];
                $groupName = $_POST['group_name'];
                $groupDesc = $_POST['group_description'];
                $groups = getGroups();
                if (isset($groups[$groupId])) {
                    $groups[$groupId]['name'] = $groupName;
                    $groups[$groupId]['description'] = $groupDesc;
                    if (saveGroups($groups)) {
                        $message = '分组信息修改成功';
                    } else {
                        $message = '修改失败，请重试';
                        $messageType = 'error';
                    }
                } else {
                    $message = '分组不存在';
                    $messageType = 'error';
                }
                break;

            case 'delete_group':
                $id = $_POST['id'];
                if (deleteGroup($id)) {
                    $message = '分组删除成功（该分组下的对话已一并删除）';
                } else {
                    $message = '分组删除失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'logout':
                logout();
                if ($isAjax) {
                    echo json_encode(['success' => true, 'redirect' => 'login.php']);
                    exit;
                }
                header('Location: login.php');
                exit;
                break;
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $messageType === 'success',
                'message' => $message
            ]);
            exit;
        }
    }
}

$chats = getChats();
$groups = getGroups();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💬</text></svg>">
    <title>后台管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', sans-serif; background: #f0f2f5; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #333; }
        .logout-btn { padding: 10px 20px; background: #ff4444; color: white; border: none; border-radius: 6px; cursor: pointer; transition: opacity 0.3s; }
        .logout-btn:hover { opacity: 0.9; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .card h2 { color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #1a73e8; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; color: #666; font-weight: bold; }
        .form-group input[type="text"], .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input[type="text"]:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #1a73e8; }
        .form-group textarea { height: 80px; resize: vertical; }
        .form-group input[type="file"] { padding: 8px; border: 1px dashed #ddd; border-radius: 8px; width: 100%; }
        .btn { padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; transition: all 0.3s; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-danger { background: #ff4444; }
        .btn-danger:hover { opacity: 0.9; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .btn-secondary { background: #666; }
        .btn-secondary:hover { opacity: 0.9; }
        .btn-add-message { padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; margin-bottom: 15px; }
        .message { padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px; animation: slideIn 0.3s ease-out; }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        .msg-block { padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px; position: relative; }
        .msg-block.customer { border-left: 4px solid #667eea; }
        .msg-block.me { border-left: 4px solid #10b981; }
        .msg-block-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; }
        .sender-select { padding: 4px 10px; border-radius: 6px; border: 2px solid #ddd; font-size: 13px; cursor: pointer; transition: all 0.2s; background: white; }
        .sender-select:focus { outline: none; border-color: #1a73e8; }
        .sender-tag { font-weight: bold; font-size: 12px; }
        .sender-tag.customer { color: #667eea; }
        .sender-tag.me { color: #10b981; }
        .msg-index { font-size: 13px; color: #666; flex: 1; }
        .remove-msg { color: #999; cursor: pointer; font-size: 12px; background: none; border: none; padding: 4px 8px; border-radius: 4px; transition: all 0.2s; }
        .remove-msg:hover { color: #ff4444; background: #ffebee; }
        .chat-table { width: 100%; border-collapse: collapse; }
        .chat-table th, .chat-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .chat-table th { background: #f8f9fa; color: #666; font-weight: bold; }
        .chat-table tr:hover { background: #f8f9fa; }
        .chat-content-preview { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #666; }
        .chat-images-preview { display: flex; gap: 5px; flex-wrap: wrap; }
        .chat-images-preview img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; cursor: pointer; transition: transform 0.2s; }
        .chat-images-preview img:hover { transform: scale(1.1); }
        .sender-tag { display: inline-block; padding: 2px 8px; background: #e8f5e9; color: #10b981; border-radius: 4px; font-size: 12px; }
        .sender-tag.customer { background: #f3e8ff; color: #667eea; }
        .action-btns { display: flex; gap: 8px; }
        .action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s; }
        .action-btn.delete { background: #ff4444; color: white; }
        .action-btn.delete:hover { background: #d32f2f; transform: translateY(-1px); }
        .action-btn.edit { background: #1a73e8; color: white; }
        .action-btn.edit:hover { background: #1557b0; transform: translateY(-1px); }
        .no-chats { text-align: center; color: #999; padding: 50px; }
        .group-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .group-item { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #1a73e8; transition: all 0.3s; }
        .group-item h3 { color: #333; margin-bottom: 5px; font-size: 16px; }
        .group-item p { color: #666; font-size: 12px; margin-bottom: 10px; }
        .group-item .group-actions { display: flex; gap: 5px; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }
        form { display: inline; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; overflow-y: auto; padding: 20px; }
        .modal.active { display: flex; animation: fadeIn 0.3s ease-out; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 20px; }
        .close-modal { float: right; cursor: pointer; color: #999; font-size: 28px; font-weight: 300; }
        .close-modal:hover { color: #333; }
        .edit-msg-item { padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid #667eea; position: relative; }
        .edit-msg-item.me { border-left-color: #10b981; }
        .edit-msg-item label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 12px; color: #666; }
        .edit-msg-item select, .edit-msg-item textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 8px; font-size: 13px; }
        .edit-msg-actions { display: flex; gap: 5px; margin-top: 8px; }
        .edit-msg-actions button { padding: 4px 10px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; }
        .edit-msg-actions .delete-msg-btn { background: #ff4444; color: white; }
        .edit-msg-actions .delete-msg-btn:hover { background: #d32f2f; }
        .existing-images { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .existing-image-item { position: relative; display: inline-block; }
        .existing-image-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
        .existing-image-item .remove-image-btn { position: absolute; top: -6px; right: -6px; width: 18px; height: 18px; background: #ff4444; color: white; border: none; border-radius: 50%; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .existing-image-item .remove-image-btn:hover { background: #d32f2f; }
        .image-upload-area { border: 2px dashed #ddd; border-radius: 8px; padding: 15px; text-align: center; margin-top: 10px; cursor: pointer; transition: all 0.2s; }
        .image-upload-area:hover { border-color: #1a73e8; background: #f8f9fa; }
        .image-upload-area input[type="file"] { display: none; }
        .new-images-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .new-image-item { position: relative; display: inline-block; }
        .new-image-item img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 2px solid #10b981; }
        .new-image-item .cancel-image-btn { position: absolute; top: -6px; right: -6px; width: 18px; height: 18px; background: #ff4444; color: white; border: none; border-radius: 50%; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideOut { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(20px); } }
        .loading { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 6px; vertical-align: middle; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .toast { position: fixed; bottom: 30px; right: 30px; padding: 15px 25px; border-radius: 10px; color: white; font-size: 14px; z-index: 9999; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: toastIn 0.3s ease-out; }
        .toast.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .toast.error { background: linear-gradient(135deg, #ff4444 0%, #d32f2f 100%); }
        .toast-icon { font-size: 18px; }
        @keyframes toastIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(20px); } }
        .row-fade-out { animation: slideOut 0.3s ease-out forwards; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ 后台管理</h1>
            <form id="logoutForm" onsubmit="return logout(event)">
                <button type="submit" class="logout-btn">退出登录</button>
            </form>
        </div>

        <div id="messageContainer"></div>

        <div class="two-col">
            <div class="card">
                <h2>➕ 添加单条对话</h2>
                <form id="addChatForm" onsubmit="return ajaxFormSubmit(event, 'add_chat')" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">昵称</label>
                        <input type="text" id="name" name="name" placeholder="请输入昵称" required>
                    </div>
                    <div class="form-group">
                        <label for="sender">发送者</label>
                        <select id="sender" name="sender" required>
                            <option value="customer">客户（左边）</option>
                            <option value="me">我（右边）</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="content">内容</label>
                        <textarea id="content" name="content" placeholder="请输入对话内容" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="group_id">分组</label>
                        <select id="group_id" name="group_id">
                            <option value="">未分类</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo htmlspecialchars($group['id']); ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="photos">图片（可选，可多选）</label>
                        <input type="file" id="photos" name="photos[]" multiple accept="image/*">
                    </div>
                    <button type="submit" class="btn">添加对话</button>
                </form>
            </div>

            <div class="card">
                <h2>📁 分组管理</h2>
                <form id="addGroupForm" onsubmit="return ajaxFormSubmit(event, 'add_group')">
                    <div class="form-group">
                        <label for="group_name">分组名称</label>
                        <input type="text" id="group_name" name="group_name" placeholder="例如：家庭群、工作群" required>
                    </div>
                    <div class="form-group">
                        <label for="group_description">分组描述（可选）</label>
                        <input type="text" id="group_description" name="group_description" placeholder="简要描述该分组">
                    </div>
                    <button type="submit" class="btn">添加分组</button>
                </form>

                <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">现有分组</h3>
                <?php if (empty($groups)): ?>
                    <p class="no-chats">暂无分组</p>
                <?php else: ?>
                    <div class="group-list" id="groupList">
                        <?php foreach ($groups as $group): ?>
                            <div class="group-item" data-group-id="<?php echo htmlspecialchars($group['id']); ?>">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <p><?php echo htmlspecialchars($group['description'] ?: '暂无描述'); ?></p>
                                <div class="group-actions">
                                    <button type="button" class="action-btn edit btn-small" onclick="editGroup('<?php echo $group['id']; ?>', '<?php echo htmlspecialchars($group['name']); ?>', '<?php echo htmlspecialchars($group['description']); ?>')">编辑</button>
                                    <button type="button" class="action-btn delete btn-small" onclick="deleteGroupById('<?php echo $group['id']; ?>')">删除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>💬 添加多消息对话</h2>
            <form id="addMultiChatForm" onsubmit="return ajaxFormSubmit(event, 'add_multi_chat', true)" enctype="multipart/form-data">
                <input type="hidden" name="message_count" id="message_count" value="2">
                
                <div class="form-group">
                    <label for="multi_name">对话主题/昵称</label>
                    <input type="text" id="multi_name" name="name" placeholder="例如：与客户A的对话" required>
                </div>
                <div class="form-group">
                    <label for="multi_group_id">分组</label>
                    <select id="multi_group_id" name="group_id">
                        <option value="">未分类</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo htmlspecialchars($group['id']); ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="messages_container">
                    <div class="msg-block customer">
                        <div class="msg-block-header">
                            <select class="sender-select" onchange="changeMessageSender(this, 1)">
                                <option value="customer" selected>客户</option>
                                <option value="me">我</option>
                            </select>
                            <span class="msg-index">消息 1</span>
                            <span class="remove-msg" onclick="removeMessage(this)">删除</span>
                        </div>
                        <input type="hidden" name="sender_1" id="sender_1" value="customer">
                        <textarea name="content_1" placeholder="客户的问题..." required></textarea>
                        <input type="file" name="photos_1[]" multiple accept="image/*" style="margin-top: 10px;">
                    </div>

                    <div class="msg-block me">
                        <div class="msg-block-header">
                            <select class="sender-select" onchange="changeMessageSender(this, 2)">
                                <option value="customer">客户</option>
                                <option value="me" selected>我</option>
                            </select>
                            <span class="msg-index">消息 2</span>
                            <span class="remove-msg" onclick="removeMessage(this)">删除</span>
                        </div>
                        <input type="hidden" name="sender_2" id="sender_2" value="me">
                        <textarea name="content_2" placeholder="我的回复..." required></textarea>
                        <input type="file" name="photos_2[]" multiple accept="image/*" style="margin-top: 10px;">
                    </div>
                </div>

                <button type="button" class="btn-add-message" onclick="addMessage()">+ 添加消息</button>
                <br><br>
                <button type="submit" class="btn">添加多消息对话</button>
            </form>
        </div>

        <div class="card">
            <h2>📋 对话列表</h2>
            <div id="chatListContainer">
                <?php if (empty($chats)): ?>
                    <div class="no-chats">暂无对话内容</div>
                <?php else: ?>
                    <table class="chat-table" id="chatTable">
                        <thead>
                            <tr>
                                <th>昵称/主题</th>
                                <th>内容预览</th>
                                <th>发送者</th>
                                <th>分组</th>
                                <th>图片</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="chatTableBody">
                            <?php foreach ($chats as $chat): ?>
                                <tr data-chat-id="<?php echo htmlspecialchars($chat['id']); ?>" class="chat-row">
                                    <td><?php echo htmlspecialchars($chat['name']); ?></td>
                                    <td class="chat-content-preview" title="<?php echo !empty($chat['messages']) ? '多条消息对话' : htmlspecialchars($chat['content']); ?>">
                                        <?php echo !empty($chat['messages']) ? '📝 多条消息对话 (' . count($chat['messages']) . '条)' : htmlspecialchars($chat['content']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($chat['sender'])) {
                                            echo '<span class="sender-tag' . ($chat['sender'] === 'me' ? '' : ' customer') . '">' . ($chat['sender'] === 'me' ? '我' : '客户') . '</span>';
                                        } elseif (!empty($chat['messages'])) {
                                            echo '<span class="sender-tag" style="background:#f0f0f0;color:#666;">多消息</span>';
                                        } else {
                                            echo '<span class="sender-tag customer">客户</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <select class="change-group" onchange="updateChatGroup('<?php echo $chat['id']; ?>', this.value)">
                                            <option value="">未分类</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?php echo htmlspecialchars($group['id']); ?>" <?php echo (!empty($chat['group_id']) && $chat['group_id'] === $group['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($group['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php
                                        $allImages = [];
                                        if (!empty($chat['images']) && is_array($chat['images'])) {
                                            $allImages = $chat['images'];
                                        } elseif (!empty($chat['messages']) && is_array($chat['messages'])) {
                                            foreach ($chat['messages'] as $msg) {
                                                if (!empty($msg['images']) && is_array($msg['images'])) {
                                                    $allImages = array_merge($allImages, $msg['images']);
                                                }
                                            }
                                        }
                                        if (!empty($allImages)) {
                                            echo '<div class="chat-images-preview">';
                                            foreach (array_slice($allImages, 0, 3) as $image) {
                                                echo '<img src="../' . PHOTOS_URL . htmlspecialchars($image) . '" alt="图片" onclick="openImgPreview(\'' . PHOTOS_URL . htmlspecialchars($image) . '\')">';
                                            }
                                            if (count($allImages) > 3) {
                                                echo '<span style="color:#999;font-size:12px;">+' . (count($allImages) - 3) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '无';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', $chat['timestamp']); ?></td>
                                    <td>
                                        <button type="button" class="action-btn edit" onclick="editChat('<?php echo $chat['id']; ?>')">编辑</button>
                                        <button type="button" class="action-btn delete" onclick="deleteChatById('<?php echo $chat['id']; ?>')">删除</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="groupEditModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeGroupEditModal()">&times;</span>
            <h3>编辑分组</h3>
            <form onsubmit="return ajaxFormSubmit(event, 'update_group')">
                <input type="hidden" name="group_id" id="edit_group_id">
                <div class="form-group">
                    <label for="edit_group_name">分组名称</label>
                    <input type="text" id="edit_group_name" name="group_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_group_description">分组描述</label>
                    <input type="text" id="edit_group_description" name="group_description">
                </div>
                <button type="submit" class="btn">保存修改</button>
            </form>
        </div>
    </div>

    <div class="modal" id="chatEditModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeChatEditModal()">&times;</span>
            <h3>编辑对话</h3>
            <form id="updateChatForm" onsubmit="return false" enctype="multipart/form-data">
                <input type="hidden" name="chat_id" id="edit_chat_id">
                <input type="hidden" name="chat_type" id="edit_chat_type">
                
                <div class="form-group">
                    <label for="edit_name">对话主题/昵称</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_chat_group_id">分组</label>
                    <select id="edit_chat_group_id" name="group_id">
                        <option value="">未分类</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo htmlspecialchars($group['id']); ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="single_chat_fields">
                    <div class="form-group">
                        <label for="edit_sender">发送者</label>
                        <select id="edit_sender" name="sender">
                            <option value="customer">客户（左边）</option>
                            <option value="me">我（右边）</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_content">内容</label>
                        <textarea id="edit_content" name="content"></textarea>
                    </div>
                    <input type="hidden" name="existing_images" id="edit_existing_images">
                    <div class="form-group">
                        <label>现有图片</label>
                        <div class="existing-images" id="edit_existing_images_container"></div>
                    </div>
                    <div class="form-group">
                        <label>添加新图片</label>
                        <div class="image-upload-area" onclick="document.getElementById('edit_new_photos').click()">
                            <input type="file" id="edit_new_photos" multiple accept="image/*" onchange="handleNewImages(this)">
                            <p>点击或拖拽图片到此处上传</p>
                        </div>
                        <div class="new-images-preview" id="edit_new_images_preview"></div>
                    </div>
                </div>
                
                <div id="multi_chat_fields" style="display:none;">
                    <div id="edit_messages_container"></div>
                    <button type="button" class="btn-add-message" onclick="addEditMessage()">+ 添加消息</button>
                </div>
                
                <button type="button" class="btn" onclick="submitEditForm()">保存修改</button>
            </form>
        </div>
    </div>

    <div class="modal" id="imgPreviewModal" onclick="closeImgPreview()">
        <span class="close-modal" onclick="closeImgPreview()">&times;</span>
        <img src="" id="previewImage" style="max-width: 90%; max-height: 90%; border-radius: 16px;">
    </div>

    <script>
        let messageIndex = 3;
        const chatData = <?php echo json_encode($chats); ?>;
        const groupsData = <?php echo json_encode($groups); ?>;

        function showMessage(text, type = 'success') {
            const container = document.getElementById('messageContainer');
            container.innerHTML = '<div class="message ' + type + '">' + text + '</div>';
            setTimeout(() => {
                container.innerHTML = '';
            }, 3000);
        }

        function showToast(text, type = 'success') {
            const existing = document.querySelector('.toast');
            if (existing) existing.remove();
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.innerHTML = '<span class="toast-icon">' + (type === 'success' ? '✓' : '✗') + '</span><span>' + text + '</span>';
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'toastOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }

        function openImgPreview(src) {
            document.getElementById('previewImage').src = src;
            document.getElementById('imgPreviewModal').classList.add('active');
        }

        function closeImgPreview() {
            document.getElementById('imgPreviewModal').classList.remove('active');
        }

        function setButtonLoading(btn, loading) {
            if (loading) {
                btn.disabled = true;
                btn.innerHTML = '<span class="loading"></span>' + btn.textContent;
            } else {
                btn.disabled = false;
                btn.innerHTML = btn.textContent.replace('<span class="loading"></span>', '');
            }
        }

        async function ajaxFormSubmit(event, action, reloadOnSuccess = true) {
            event.preventDefault();
            const form = event.target;
            const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            setButtonLoading(btn, true);

            try {
                const formData = new FormData(form);
                formData.append('action', action);
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Response:', action, result);

                if (result.success) {
                    showToast(result.message || '操作成功', 'success');
                    if (reloadOnSuccess) {
                        // 保存滚动位置
                        sessionStorage.setItem('scrollPosition', window.scrollY);
                        setTimeout(() => location.reload(), 500);
                    }
                } else {
                    showMessage(result.message || '操作失败', 'error');
                }
            } catch (e) {
                console.error('Error:', e);
                showMessage('请求失败: ' + e.message, 'error');
            } finally {
                setButtonLoading(btn, false);
            }
            return false;
        }

        async function deleteChatById(chatId) {
            if (!confirm('确定要删除这条对话吗？')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_chat');
                formData.append('id', chatId);
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Delete response:', result);

                if (result.success) {
                    showToast(result.message || '删除成功', 'success');
                    // 保存滚动位置
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showMessage(result.message || '删除失败', 'error');
                }
            } catch (e) {
                console.error('Delete error:', e);
                showMessage('请求失败', 'error');
            }
        }

        async function deleteGroupById(groupId) {
            if (!confirm('确定要删除该分组吗？该分组下的所有对话已一并删除！')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_group');
                formData.append('id', groupId);
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Delete group response:', result);

                if (result.success) {
                    showToast(result.message || '删除成功', 'success');
                    // 保存滚动位置
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showMessage(result.message || '删除失败', 'error');
                }
            } catch (e) {
                console.error('Delete group error:', e);
                showMessage('请求失败', 'error');
            }
        }

        async function updateChatGroup(chatId, newGroupId) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_chat_group');
                formData.append('chat_id', chatId);
                formData.append('new_group_id', newGroupId);
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Update group response:', result);

                if (result.success) {
                    showToast(result.message || '分组修改成功', 'success');
                    // 保存滚动位置
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showMessage(result.message || '修改失败', 'error');
                }
            } catch (e) {
                console.error('Update group error:', e);
                showMessage('请求失败', 'error');
            }
        }

        function changeMessageSender(selectEl, index, isEdit = false) {
            const msgBlock = selectEl.closest(isEdit ? '.edit-msg-item' : '.msg-block');
            const sender = selectEl.value;
            const hiddenInput = document.getElementById(isEdit ? 'msg_sender_' + index : 'sender_' + index);
            
            if (hiddenInput) {
                hiddenInput.value = sender;
            }
            
            // 更新样式
            msgBlock.className = (isEdit ? 'edit-msg-item' : 'msg-block') + ' ' + sender;
            
            // 更新 placeholder
            const textarea = msgBlock.querySelector('textarea');
            if (textarea && !isEdit) {
                textarea.placeholder = sender === 'customer' ? '客户的问题...' : '我的回复...';
            }
        }

        function addMessage() {
            const container = document.getElementById('messages_container');
            const defaultSender = 'customer'; // 默认添加客户消息
            
            const msgBlock = document.createElement('div');
            msgBlock.className = 'msg-block ' + defaultSender;
            msgBlock.innerHTML = `
                <div class="msg-block-header">
                    <select class="sender-select" onchange="changeMessageSender(this, ${messageIndex})">
                        <option value="customer" selected>客户</option>
                        <option value="me">我</option>
                    </select>
                    <span class="msg-index">消息 ${messageIndex}</span>
                    <span class="remove-msg" onclick="removeMessage(this)">删除</span>
                </div>
                <input type="hidden" name="sender_${messageIndex}" id="sender_${messageIndex}" value="${defaultSender}">
                <textarea name="content_${messageIndex}" placeholder="客户的问题..." required></textarea>
                <input type="file" name="photos_${messageIndex}[]" multiple accept="image/*" style="margin-top: 10px;">
            `;
            
            container.appendChild(msgBlock);
            document.getElementById('message_count').value = messageIndex;
            messageIndex++;
        }

        function removeMessage(element) {
            const msgBlock = element.closest('.msg-block');
            msgBlock.remove();
            
            const blocks = document.querySelectorAll('#messages_container .msg-block');
            document.getElementById('message_count').value = blocks.length;
            
            blocks.forEach((block, index) => {
                const idx = index + 1;
                const selectEl = block.querySelector('.sender-select');
                const currentSender = selectEl.value;
                
                // 更新选择框的 onchange 事件
                selectEl.onchange = function() {
                    changeMessageSender(this, idx);
                };
                
                // 更新隐藏输入
                const hiddenInput = block.querySelector('input[type="hidden"]');
                hiddenInput.name = 'sender_' + idx;
                hiddenInput.id = 'sender_' + idx;
                
                // 更新消息索引显示
                block.querySelector('.msg-index').textContent = '消息 ' + idx;
                
                // 更新其他表单元素
                block.querySelector('textarea').name = 'content_' + idx;
                block.querySelector('input[type="file"]').name = 'photos_' + idx + '[]';
            });
            messageIndex = blocks.length + 1;
        }

        function handleNewImages(input) {
            const preview = document.getElementById('edit_new_images_preview');
            if (input.files) {
                for (let file of input.files) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const item = document.createElement('div');
                        item.className = 'new-image-item';
                        item.innerHTML = '<img src="' + e.target.result + '" alt=""><button type="button" class="cancel-image-btn" onclick="this.parentElement.remove()">×</button>';
                        preview.appendChild(item);
                    };
                    reader.readAsDataURL(file);
                }
            }
            input.value = '';
        }

        function editGroup(groupId, groupName, groupDesc) {
            document.getElementById('edit_group_id').value = groupId;
            document.getElementById('edit_group_name').value = groupName;
            document.getElementById('edit_group_description').value = groupDesc;
            document.getElementById('groupEditModal').classList.add('active');
        }

        function closeGroupEditModal() {
            document.getElementById('groupEditModal').classList.remove('active');
        }

        function removeExistingImage(btn) {
            btn.closest('.existing-image-item').remove();
            updateExistingImagesInput();
        }

        function updateExistingImagesInput() {
            const container = document.getElementById('edit_existing_images_container');
            const input = document.getElementById('edit_existing_images');
            const images = [];
            container.querySelectorAll('.existing-image-item img').forEach(img => {
                images.push(img.dataset.path || img.src.split('/').pop());
            });
            input.value = JSON.stringify(images);
        }

        function deleteEditMessage(btn) {
            btn.closest('.edit-msg-item').remove();
            renumberEditMessages();
        }

        function renumberEditMessages() {
            const items = document.querySelectorAll('#edit_messages_container .edit-msg-item');
            items.forEach((item, index) => {
                const idx = index;
                const selectEl = item.querySelector('.sender-select');
                const currentSender = selectEl.value;
                
                // 更新选择框的 onchange 事件
                selectEl.onchange = function() {
                    changeMessageSender(this, idx, true);
                };
                
                // 更新隐藏输入
                const hiddenInput = item.querySelector('input[name^="msg_sender_"]');
                if (hiddenInput) {
                    hiddenInput.name = 'msg_sender_' + idx;
                    hiddenInput.id = 'msg_sender_' + idx;
                }
                
                // 更新消息索引显示
                const msgIndexEl = item.querySelector('.msg-index');
                if (msgIndexEl) {
                    msgIndexEl.textContent = '消息 ' + (index + 1);
                }
                
                // 更新其他表单元素
                const timestampInput = item.querySelector('input[name^="msg_timestamp_"]');
                if (timestampInput) timestampInput.name = 'msg_timestamp_' + idx;
                
                const existingImagesInput = item.querySelector('input[name^="msg_existing_images_"]');
                if (existingImagesInput) existingImagesInput.name = 'msg_existing_images_' + idx;
                
                const textarea = item.querySelector('textarea[name^="msg_content_"]');
                if (textarea) textarea.name = 'msg_content_' + idx;
                
                const fileInput = item.querySelector('input[name^="msg_photos_"]');
                if (fileInput) fileInput.name = 'msg_photos_' + idx + '[]';
            });
            document.querySelector('input[name="msg_count"]').value = items.length;
        }

        function addEditMessage() {
            const container = document.getElementById('edit_messages_container');
            const count = container.querySelectorAll('.edit-msg-item').length;
            const defaultSender = 'customer'; // 默认添加客户消息
            
            const item = document.createElement('div');
            item.className = 'edit-msg-item ' + defaultSender;
            item.innerHTML = `
                <div class="msg-block-header">
                    <select class="sender-select" onchange="changeMessageSender(this, ${count}, true)">
                        <option value="customer" selected>客户</option>
                        <option value="me">我</option>
                    </select>
                    <span class="msg-index">消息 ${count + 1}</span>
                </div>
                <input type="hidden" name="msg_timestamp_${count}" value="${Date.now()}">
                <input type="hidden" name="msg_existing_images_${count}" value="[]">
                <input type="hidden" name="msg_sender_${count}" id="msg_sender_${count}" value="${defaultSender}">
                <textarea name="msg_content_${count}" placeholder="客户的问题..." required></textarea>
                <div class="image-upload-area" onclick="this.nextElementSibling.click()"><p>点击添加图片</p></div>
                <input type="file" name="msg_photos_${count}[]" multiple accept="image/*" style="display:none;" onchange="handleNewImagesMulti(this)">
                <div class="new-images-preview"></div>
                <div class="edit-msg-actions">
                    <button type="button" class="delete-msg-btn" onclick="deleteEditMessage(this)">删除此消息</button>
                </div>
            `;
            container.appendChild(item);
            document.querySelector('input[name="msg_count"]').value = count + 1;
        }

        function handleNewImagesMulti(input) {
            const preview = input.nextElementSibling;
            if (input.files) {
                for (let file of input.files) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const item = document.createElement('div');
                        item.className = 'new-image-item';
                        item.innerHTML = '<img src="' + e.target.result + '" alt=""><button type="button" class="cancel-image-btn" onclick="this.parentElement.remove()">×</button>';
                        preview.appendChild(item);
                    };
                    reader.readAsDataURL(file);
                }
            }
        }

        function editChat(chatId) {
            const chat = chatData.find(c => c.id === chatId);
            if (!chat) return;

            document.getElementById('edit_chat_id').value = chat.id;
            document.getElementById('edit_name').value = chat.name || '';
            document.getElementById('edit_chat_group_id').value = chat.group_id || '';

            const isMulti = chat.messages && Array.isArray(chat.messages) && chat.messages.length > 0;

            if (isMulti) {
                document.getElementById('edit_chat_type').value = 'multi';
                document.getElementById('single_chat_fields').style.display = 'none';
                document.getElementById('multi_chat_fields').style.display = 'block';

                const container = document.getElementById('edit_messages_container');
                container.innerHTML = '';

                chat.messages.forEach((msg, index) => {
                    const item = document.createElement('div');
                    item.className = 'edit-msg-item ' + msg.sender;
                    
                    let existingImagesHtml = '';
                    if (msg.images && msg.images.length > 0) {
                        existingImagesHtml = '<div class="existing-images">';
                        msg.images.forEach(img => {
                            existingImagesHtml += '<div class="existing-image-item"><img src="../' + img + '" data-path="' + img + '" alt=""><button type="button" class="remove-image-btn" onclick="removeExistingImage(this)">×</button></div>';
                        });
                        existingImagesHtml += '</div>';
                    }
                    
                    item.innerHTML = `
                        <div class="msg-block-header">
                            <select class="sender-select" onchange="changeMessageSender(this, ${index}, true)">
                                <option value="customer" ${msg.sender === 'customer' ? 'selected' : ''}>客户</option>
                                <option value="me" ${msg.sender === 'me' ? 'selected' : ''}>我</option>
                            </select>
                            <span class="msg-index">消息 ${index + 1}</span>
                        </div>
                        <input type="hidden" name="msg_timestamp_${index}" value="${msg.timestamp || 0}">
                        <input type="hidden" name="msg_existing_images_${index}" value='${JSON.stringify(msg.images || [])}'>
                        <input type="hidden" name="msg_sender_${index}" id="msg_sender_${index}" value="${msg.sender}">
                        <textarea name="msg_content_${index}" required>${msg.content || ''}</textarea>
                        <label style="font-weight:bold;font-size:12px;color:#666;margin-top:8px;display:block;">现有图片：</label>
                        ${existingImagesHtml || '<p style="color:#999;font-size:12px;">无</p>'}
                        <div class="image-upload-area" onclick="this.nextElementSibling.click()"><p>点击添加新图片</p></div>
                        <input type="file" name="msg_photos_${index}[]" multiple accept="image/*" style="display:none;" onchange="handleNewImagesMulti(this)">
                        <div class="new-images-preview"></div>
                        <div class="edit-msg-actions">
                            <button type="button" class="delete-msg-btn" onclick="deleteEditMessage(this)">删除此消息</button>
                        </div>
                    `;
                    container.appendChild(item);
                });

                let msgCountInput = document.querySelector('input[name="msg_count"]');
                if (!msgCountInput) {
                    msgCountInput = document.createElement('input');
                    msgCountInput.type = 'hidden';
                    msgCountInput.name = 'msg_count';
                    document.getElementById('updateChatForm').appendChild(msgCountInput);
                }
                msgCountInput.value = chat.messages.length;
            } else {
                document.getElementById('edit_chat_type').value = 'single';
                document.getElementById('single_chat_fields').style.display = 'block';
                document.getElementById('multi_chat_fields').style.display = 'none';
                document.getElementById('edit_sender').value = chat.sender || 'customer';
                document.getElementById('edit_content').value = chat.content || '';
                
                const existingImages = chat.images || [];
                document.getElementById('edit_existing_images').value = JSON.stringify(existingImages);
                
                let imagesHtml = '';
                existingImages.forEach(img => {
                    imagesHtml += '<div class="existing-image-item"><img src="../' + img + '" data-path="' + img + '" alt=""><button type="button" class="remove-image-btn" onclick="removeExistingImage(this)">×</button></div>';
                });
                document.getElementById('edit_existing_images_container').innerHTML = imagesHtml || '<p style="color:#999;">无现有图片</p>';
                document.getElementById('edit_new_images_preview').innerHTML = '';
            }

            document.getElementById('chatEditModal').classList.add('active');
        }

        function closeChatEditModal() {
            document.getElementById('chatEditModal').classList.remove('active');
        }

        async function submitEditForm() {
            const form = document.getElementById('updateChatForm');
            const btn = form.querySelector('.btn');
            setButtonLoading(btn, true);

            const chatType = document.getElementById('edit_chat_type').value;

            try {
                const formData = new FormData(form);
                formData.append('action', chatType === 'multi' ? 'update_multi_chat' : 'update_chat');
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Update response:', result);

                if (result.success) {
                    showToast(result.message || '保存成功', 'success');
                    closeChatEditModal();
                    // 保存滚动位置
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showMessage(result.message || '保存失败', 'error');
                }
            } catch (e) {
                console.error('Update error:', e);
                showMessage('请求失败: ' + e.message, 'error');
            } finally {
                setButtonLoading(btn, false);
            }
        }

        async function logout(event) {
            event.preventDefault();
            if (!confirm('确定要退出登录吗？')) return false;

            try {
                const formData = new FormData();
                formData.append('action', 'logout');
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success && result.redirect) {
                    window.location.href = result.redirect;
                }
            } catch (e) {
                window.location.href = 'login.php';
            }
            return false;
        }

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // 页面加载时恢复滚动位置
        document.addEventListener('DOMContentLoaded', function() {
            const savedPosition = sessionStorage.getItem('scrollPosition');
            if (savedPosition) {
                setTimeout(() => {
                    window.scrollTo(0, parseInt(savedPosition));
                    sessionStorage.removeItem('scrollPosition');
                }, 100);
            }
        });
    </script>
</body>
</html>