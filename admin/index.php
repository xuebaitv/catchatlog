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

                if (isset($_FILES['photos']) && !empty($_FILES['photos']['tmp_name'])) {
                    $fileCount = is_array($_FILES['photos']['name']) ? count($_FILES['photos']['name']) : 1;
                    for ($key = 0; $key < $fileCount; $key++) {
                        if (is_array($_FILES['photos']['name'])) {
                            $error = $_FILES['photos']['error'][$key];
                            $tmpName = $_FILES['photos']['tmp_name'][$key];
                            $fileName = $_FILES['photos']['name'][$key];
                            $fileType = $_FILES['photos']['type'][$key];
                            $fileSize = $_FILES['photos']['size'][$key];
                        } else {
                            $error = $_FILES['photos']['error'];
                            $tmpName = $_FILES['photos']['tmp_name'];
                            $fileName = $_FILES['photos']['name'];
                            $fileType = $_FILES['photos']['type'];
                            $fileSize = $_FILES['photos']['size'];
                        }
                        
                        if ($error === UPLOAD_ERR_OK && !empty($tmpName)) {
                            $file = [
                                'name' => $fileName,
                                'type' => $fileType,
                                'tmp_name' => $tmpName,
                                'error' => $error,
                                'size' => $fileSize
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
                    updateDataVersion();
                    $message = '对话添加成功，图片数: ' . count($images);
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
                    $fileField = 'photos_' . $i;
                    
                    if (isset($_FILES[$fileField]) && !empty($_FILES[$fileField]['tmp_name'])) {
                        $fileCount = is_array($_FILES[$fileField]['name']) ? count($_FILES[$fileField]['name']) : 1;
                        for ($key = 0; $key < $fileCount; $key++) {
                            if (is_array($_FILES[$fileField]['name'])) {
                                $error = $_FILES[$fileField]['error'][$key];
                                $tmpName = $_FILES[$fileField]['tmp_name'][$key];
                                $fileName = $_FILES[$fileField]['name'][$key];
                                $fileType = $_FILES[$fileField]['type'][$key];
                                $fileSize = $_FILES[$fileField]['size'][$key];
                            } else {
                                $error = $_FILES[$fileField]['error'];
                                $tmpName = $_FILES[$fileField]['tmp_name'];
                                $fileName = $_FILES[$fileField]['name'];
                                $fileType = $_FILES[$fileField]['type'];
                                $fileSize = $_FILES[$fileField]['size'];
                            }
                            
                            if ($error === UPLOAD_ERR_OK && !empty($tmpName)) {
                                $file = [
                                    'name' => $fileName,
                                    'type' => $fileType,
                                    'tmp_name' => $tmpName,
                                    'error' => $error,
                                    'size' => $fileSize
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
                    updateDataVersion();
                    $message = '对话添加成功';
                } else {
                    $message = '添加失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'delete_chat':
                $id = $_POST['id'];
                if (deleteChat($id)) {
                    updateDataVersion();
                    $message = '对话删除成功';
                } else {
                    $message = '删除失败，请重试';
                    $messageType = 'error';
                }
                break;
            case 'batch_delete_chat':
                $ids = isset($_POST['ids']) ? json_decode($_POST['ids'], true) : [];
                $deletedCount = 0;
                foreach ($ids as $id) {
                    if (deleteChat($id)) {
                        $deletedCount++;
                    }
                }
                if ($deletedCount > 0) {
                    updateDataVersion();
                    $message = '成功删除 ' . $deletedCount . ' 条对话';
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
                        
                        if (isset($_FILES['photos']) && !empty($_FILES['photos']['tmp_name'])) {
                            $fileCount = is_array($_FILES['photos']['name']) ? count($_FILES['photos']['name']) : 1;
                            for ($key = 0; $key < $fileCount; $key++) {
                                if (is_array($_FILES['photos']['name'])) {
                                    $error = $_FILES['photos']['error'][$key];
                                    $tmpName = $_FILES['photos']['tmp_name'][$key];
                                    $fileName = $_FILES['photos']['name'][$key];
                                    $fileType = $_FILES['photos']['type'][$key];
                                    $fileSize = $_FILES['photos']['size'][$key];
                                } else {
                                    $error = $_FILES['photos']['error'];
                                    $tmpName = $_FILES['photos']['tmp_name'];
                                    $fileName = $_FILES['photos']['name'];
                                    $fileType = $_FILES['photos']['type'];
                                    $fileSize = $_FILES['photos']['size'];
                                }
                                
                                if ($error === UPLOAD_ERR_OK && !empty($tmpName)) {
                                    $file = [
                                        'name' => $fileName,
                                        'type' => $fileType,
                                        'tmp_name' => $tmpName,
                                        'error' => $error,
                                        'size' => $fileSize
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
                            updateDataVersion();
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
                            $fileField = 'msg_photos_' . $i;
                            
                            if (isset($_FILES[$fileField]) && !empty($_FILES[$fileField]['tmp_name'])) {
                                $fileCount = is_array($_FILES[$fileField]['name']) ? count($_FILES[$fileField]['name']) : 1;
                                for ($key = 0; $key < $fileCount; $key++) {
                                    if (is_array($_FILES[$fileField]['name'])) {
                                        $error = $_FILES[$fileField]['error'][$key];
                                        $tmpName = $_FILES[$fileField]['tmp_name'][$key];
                                        $fileName = $_FILES[$fileField]['name'][$key];
                                        $fileType = $_FILES[$fileField]['type'][$key];
                                        $fileSize = $_FILES[$fileField]['size'][$key];
                                    } else {
                                        $error = $_FILES[$fileField]['error'];
                                        $tmpName = $_FILES[$fileField]['tmp_name'];
                                        $fileName = $_FILES[$fileField]['name'];
                                        $fileType = $_FILES[$fileField]['type'];
                                        $fileSize = $_FILES[$fileField]['size'];
                                    }
                                    
                                    if ($error === UPLOAD_ERR_OK && !empty($tmpName)) {
                                        $file = [
                                            'name' => $fileName,
                                            'type' => $fileType,
                                            'tmp_name' => $tmpName,
                                            'error' => $error,
                                            'size' => $fileSize
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
                            updateDataVersion();
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
                            updateDataVersion();
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
                    updateDataVersion();
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
                        updateDataVersion();
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
                    updateDataVersion();
                    $message = '分组删除成功（该分组下的对话已一并删除）';
                } else {
                    $message = '分组删除失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'update_settings':
                $pageTitle = $_POST['page_title'];
                $pageSubtitle = $_POST['page_subtitle'];
                $accessPasswordEnabled = isset($_POST['access_password_enabled']) ? true : false;
                $accessPassword = $_POST['access_password'];
                
                $settings = getSettings();
                $settings['page_title'] = $pageTitle;
                $settings['page_subtitle'] = $pageSubtitle;
                $settings['access_password_enabled'] = $accessPasswordEnabled;
                $settings['access_password'] = $accessPassword;
                
                if (saveSettings($settings)) {
                    updateDataVersion();
                    $message = '页面设置保存成功';
                } else {
                    $message = '保存失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'update_ai_settings':
                $aiEnabled = isset($_POST['ai_enabled']) ? true : false;
                $aiApiUrl = $_POST['ai_api_url'];
                $aiApiKey = $_POST['ai_api_key'];
                $aiModel = $_POST['ai_model'];
                $aiSystemPrompt = $_POST['ai_system_prompt'];
                
                $settings = getSettings();
                $settings['ai_enabled'] = $aiEnabled;
                $settings['ai_api_url'] = $aiApiUrl;
                $settings['ai_api_key'] = $aiApiKey;
                $settings['ai_model'] = $aiModel;
                $settings['ai_system_prompt'] = $aiSystemPrompt;
                
                if (saveSettings($settings)) {
                    updateDataVersion();
                    $message = 'AI设置保存成功';
                } else {
                    $message = '保存失败，请重试';
                    $messageType = 'error';
                }
                break;

            case 'change_password':
                $oldPassword = $_POST['old_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                $settings = getSettings();
                
                if ($oldPassword !== $settings['admin_password']) {
                    $message = '当前密码错误';
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = '两次输入的密码不一致';
                    $messageType = 'error';
                } elseif (strlen($newPassword) < 4) {
                    $message = '密码长度至少4位';
                    $messageType = 'error';
                } else {
                    $settings['admin_password'] = $newPassword;
                    if (saveSettings($settings)) {
                        $message = '密码修改成功，请重新登录';
                        logout();
                    } else {
                        $message = '密码修改失败，请检查文件权限';
                        $messageType = 'error';
                    }
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

            case 'reset_all':
                $resetSuccess = true;
                $messages = [];

                $mindmapDir = __DIR__ . '/../mindmap/data';
                if (is_dir($mindmapDir)) {
                    $files = glob($mindmapDir . '/*.json');
                    foreach ($files as $file) {
                        if (unlink($file)) {
                            $messages[] = "已删除思维导图文件: " . basename($file);
                        } else {
                            $resetSuccess = false;
                            $messages[] = "删除思维导图文件失败: " . basename($file);
                        }
                    }
                } else {
                    @mkdir($mindmapDir, 0777, true);
                }

                $photosDir = __DIR__ . '/../photos';
                if (is_dir($photosDir)) {
                    $files = glob($photosDir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            if (unlink($file)) {
                                $messages[] = "已删除图片文件: " . basename($file);
                            } else {
                                $resetSuccess = false;
                                $messages[] = "删除图片文件失败: " . basename($file);
                            }
                        }
                    }
                } else {
                    @mkdir($photosDir, 0777, true);
                }

                $memoFile = __DIR__ . '/../memo/memos.json';
                if (file_exists($memoFile)) {
                    if (unlink($memoFile)) {
                        $messages[] = "已删除备忘录数据";
                    } else {
                        $resetSuccess = false;
                        $messages[] = "删除备忘录数据失败";
                    }
                }

                $chatDir = __DIR__ . '/../chat';
                if (is_dir($chatDir)) {
                    $files = glob($chatDir . '/*.json');
                    foreach ($files as $file) {
                        if (basename($file) !== 'settings.json' && basename($file) !== 'groups.json' && basename($file) !== 'data_version.json') {
                            if (unlink($file)) {
                                $messages[] = "已删除会话文件: " . basename($file);
                            } else {
                                $resetSuccess = false;
                                $messages[] = "删除会话文件失败: " . basename($file);
                            }
                        }
                    }
                } else {
                    @mkdir($chatDir, 0777, true);
                }

                $settingsFile = __DIR__ . '/../chat/settings.json';
                if (file_exists($settingsFile)) {
                    $defaultSettings = [
                        'page_title' => '实时对话演示',
                        'page_subtitle' => '这是一段可以自定义的副标题',
                        'admin_password' => 'admin123',
                        'access_password_enabled' => false,
                        'access_password' => 'view123',
                        'ai_enabled' => false,
                        'ai_api_url' => 'https://api.openai.com/v1/chat/completions',
                        'ai_api_key' => '',
                        'ai_model' => 'gpt-3.5-turbo',
                        'ai_system_prompt' => '你是一个专业的客服助手，请帮助用户快速准确地回复客户问题。'
                    ];
                    if (file_put_contents($settingsFile, json_encode($defaultSettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                        $messages[] = "已重置系统设置为初始值";
                    } else {
                        $resetSuccess = false;
                        $messages[] = "重置系统设置失败";
                    }
                } else {
                    @file_put_contents($settingsFile, json_encode([
                        'page_title' => '实时对话演示',
                        'page_subtitle' => '这是一段可以自定义的副标题',
                        'admin_password' => 'admin123',
                        'access_password_enabled' => false,
                        'access_password' => 'view123',
                        'ai_enabled' => false,
                        'ai_api_url' => 'https://api.openai.com/v1/chat/completions',
                        'ai_api_key' => '',
                        'ai_model' => 'gpt-3.5-turbo',
                        'ai_system_prompt' => '你是一个专业的客服助手，请帮助用户快速准确地回复客户问题。'
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $messages[] = "已创建初始系统设置";
                }

                $groupsFile = __DIR__ . '/../chat/groups.json';
                if (file_exists($groupsFile)) {
                    if (unlink($groupsFile)) {
                        $messages[] = "已删除分组数据";
                    } else {
                        $resetSuccess = false;
                        $messages[] = "删除分组数据失败";
                    }
                }

                $dataVersionFile = __DIR__ . '/../chat/data_version.json';
                if (file_exists($dataVersionFile)) {
                    @file_put_contents($dataVersionFile, json_encode(['version' => 1]));
                }

                $message = $resetSuccess ? '系统已完全重置为初始状态！' : '部分重置操作失败，请检查文件权限';
                $messageType = $resetSuccess ? 'success' : 'error';
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

$groupedChats = [];
$groupedChats[''] = ['name' => '未分类', 'chats' => []];
foreach ($groups as $group) {
    $groupedChats[$group['id']] = ['name' => $group['name'], 'chats' => []];
}

foreach ($chats as $chat) {
    $groupId = isset($chat['group_id']) ? $chat['group_id'] : '';
    if (!isset($groupedChats[$groupId])) {
        $groupId = '';
    }
    $groupedChats[$groupId]['chats'][] = $chat;
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
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💬</text></svg>">
    <title>后台管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', sans-serif; background: #f0f2f5; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }

        @media (min-width: 1600px) {
            .container { max-width: 1600px; }
        }

        @media (min-width: 1900px) {
            .container { max-width: 1800px; }
        }

        @media (min-width: 2400px) {
            .container { max-width: 2200px; }
        }
        .header { margin-bottom: 20px; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { color: #333; }
        .nav-bar { display: flex; gap: 10px; background: white; padding: 12px 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); flex-wrap: wrap; }
        .nav-btn { padding: 8px 16px; background: #f1f3f4; color: #5f6368; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .nav-btn:hover { background: #e8eaed; color: #1a73e8; }
        .nav-btn:active { background: #d2e3fc; color: #1a73e8; }
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
        .form-switch { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .form-switch label { margin: 0; color: #333; font-weight: bold; }
        .switch { position: relative; display: inline-block; width: 48px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #1a73e8; }
        input:checked + .slider:before { transform: translateX(24px); }
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
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .chat-group { margin-bottom: 15px; border: 1px solid #eee; border-radius: 8px; overflow: hidden; }
        .chat-group-header { display: flex; align-items: center; padding: 12px 15px; background: #f8f9fa; cursor: pointer; user-select: none; transition: background 0.2s; }
        .chat-group-header:hover { background: #e9ecef; }
        .chat-group-toggle { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; margin-right: 10px; transition: transform 0.2s; font-size: 14px; }
        .chat-group.collapsed .chat-group-toggle { transform: rotate(-90deg); }
        .chat-group-name { font-weight: bold; color: #333; flex: 1; }
        .chat-group-count { background: #1a73e8; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
        .chat-group-body { display: block; }
        .chat-group.collapsed .chat-group-body { display: none; }
        .chat-group .chat-table { border-radius: 0; margin-bottom: 0; }
        .chat-content-preview { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #666; }
        .chat-images-preview { display: flex; gap: 5px; flex-wrap: wrap; }
        .chat-images-preview img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; cursor: pointer; transition: transform 0.2s; }
        .chat-images-preview img:hover { transform: scale(1.1); }
        .sender-tag { display: inline-block; padding: 2px 8px; background: #e8f5e9; color: #10b981; border-radius: 4px; font-size: 12px; }
        .sender-tag.customer { background: #f3e8ff; color: #667eea; }
        .action-btns { display: flex; gap: 8px; }
        .action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.2s; }
        .change-group { max-width: 100px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
        .action-btn.delete { background: #ff4444; color: white; }
        .action-btn.delete:hover { background: #d32f2f; transform: translateY(-1px); }
        .action-btn.edit { background: #1a73e8; color: white; }
        .action-btn.edit:hover { background: #1557b0; transform: translateY(-1px); }
        .no-chats { text-align: center; color: #999; padding: 50px; }
        .group-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; max-height: 300px; overflow-y: auto; padding-right: 5px; }
        .group-list::-webkit-scrollbar { width: 6px; }
        .group-list::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .group-list::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        .group-list::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        .group-item { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #1a73e8; transition: all 0.3s; }
        .group-item h3 { color: #333; margin-bottom: 5px; font-size: 16px; }
        .group-item p { color: #666; font-size: 12px; margin-bottom: 10px; }
        .group-item .group-actions { display: flex; gap: 5px; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { 
            .two-col { grid-template-columns: 1fr; } 
            .container { padding: 15px; }
            .card { padding: 18px; }
            .card h2 { font-size: 18px; }
            .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .chat-table { min-width: 380px; }
            .chat-table th, .chat-table td { padding: 8px 6px; font-size: 13px; }
            .chat-content-preview { max-width: 100px; }
            .action-btns { flex-direction: column; gap: 4px; }
            .action-btn { width: 100%; text-align: center; }
            .header h1 { font-size: 22px; }
            .modal-content { padding: 20px; width: 100%; }
            .chat-group-header { padding: 10px 12px; }
            .chat-group-name { font-size: 14px; }
        }
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
            <div class="header-top">
                <h1>⚙️ 后台管理</h1>
                <form id="logoutForm" onsubmit="return logout(event)">
                    <button type="submit" class="logout-btn">退出登录</button>
                </form>
            </div>
            <div class="nav-bar">
                <button type="button" class="nav-btn" onclick="scrollToCard('card-groups')">📁 分组管理</button>
                <button type="button" class="nav-btn" onclick="scrollToCard('card-add-chat')">💬 添加会话</button>
                <a href="../mindmap/index.php" class="nav-btn" style="text-decoration:none;display:flex;align-items:center;">🧠 思维导图</a>
                <button type="button" class="nav-btn" onclick="scrollToCard('card-settings')">⚙️ 页面设置</button>
                <button type="button" class="nav-btn" onclick="scrollToCard('card-ai-settings')">🤖 AI设置</button>
                <button type="button" class="nav-btn" onclick="scrollToCard('card-password')">🔐 修改密码</button>
                <button type="button" class="nav-btn" onclick="scrollToCard('card-chat-list')">📋 对话列表</button>
            </div>
        </div>

        <div id="messageContainer"></div>

        <div class="two-col">
            <div class="card" id="card-groups">
                <h2>📁 分组管理</h2>
                <form id="addGroupForm" onsubmit="return ajaxFormSubmit(event, 'add_group')">
                    <div class="form-group">
                        <label for="group_name">分组名称</label>
                        <input type="text" id="group_name" name="group_name" placeholder="例如：备忘、场景一" required>
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

            <div class="card" id="card-add-chat">
                <h2>💬 添加会话</h2>
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
                <button type="submit" class="btn">添加会话</button>
            </form>
        </div>
        </div>

        <div class="two-col">
            <div class="card" id="card-settings">
                <h2>⚙️ 页面设置</h2>
                <div style="padding: 20px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; margin-bottom: 20px;">
                    <div style="font-weight: bold; color: #856404; margin-bottom: 10px;">⚠️ 危险操作区域</div>
                    <p style="margin-bottom: 15px; color: #856404;">此操作将删除所有数据并还原为初始设置，包括思维导图、备忘录、会话数据等，操作不可恢复！</p>
                    <button type="button" class="btn btn-danger" onclick="openResetConfirmModal()">🔄 一键重置系统</button>
                </div>
                <form onsubmit="return ajaxFormSubmit(event, 'update_settings')">
                    <?php $settings = getSettings(); ?>
                    <div class="form-switch">
                        <label class="switch">
                            <input type="checkbox" id="access_password_enabled" name="access_password_enabled" value="1" <?php echo $settings['access_password_enabled'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <label for="access_password_enabled">启用前端访问密码保护</label>
                    </div>
                    <div class="form-group">
                        <label for="page_title">页面标题</label>
                        <input type="text" id="page_title" name="page_title" value="<?php echo htmlspecialchars($settings['page_title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="page_subtitle">页面子标题</label>
                        <input type="text" id="page_subtitle" name="page_subtitle" value="<?php echo htmlspecialchars($settings['page_subtitle']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="access_password">前端访问密码（默认：view123）</label>
                        <input type="text" id="access_password" name="access_password" value="<?php echo htmlspecialchars($settings['access_password']); ?>" required minlength="4">
                    </div>
                    <button type="submit" class="btn">保存设置</button>
                </form>
            </div>

            <div class="card" id="card-password">
                <h2>🔐 修改密码</h2>
                <form onsubmit="return ajaxFormSubmit(event, 'change_password')">
                    <div class="form-group">
                        <label for="old_password">当前密码</label>
                        <input type="password" id="old_password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">新密码</label>
                        <input type="password" id="new_password" name="new_password" required minlength="4">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">确认新密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="4">
                    </div>
                    <button type="submit" class="btn">修改密码</button>
                </form>
            </div>
        </div>

        <div class="card" id="card-ai-settings">
        <h2>🤖 AI设置</h2>
        <form onsubmit="return ajaxFormSubmit(event, 'update_ai_settings')">
                <?php $settings = getSettings(); ?>
                <div class="form-switch">
                    <label class="switch">
                        <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?php echo $settings['ai_enabled'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <label for="ai_enabled">启用AI推荐话术功能</label>
                </div>
                <div class="two-col" style="margin: 0 -10px;">
                    <div class="form-group" style="padding: 0 10px; margin-bottom: 0;">
                        <label for="ai_api_url">API接口地址</label>
                        <input type="text" id="ai_api_url" name="ai_api_url" value="<?php echo htmlspecialchars($settings['ai_api_url']); ?>" placeholder="https://api.openai.com/v1/chat/completions">
                    </div>
                    <div class="form-group" style="padding: 0 10px; margin-bottom: 0;">
                        <label for="ai_model">模型名称</label>
                        <input type="text" id="ai_model" name="ai_model" value="<?php echo htmlspecialchars($settings['ai_model']); ?>" placeholder="例如：gpt-3.5-turbo">
                    </div>
                </div>
                <div class="form-group">
                    <label for="ai_api_key">API Key（sk-...）</label>
                    <input type="password" id="ai_api_key" name="ai_api_key" value="<?php echo htmlspecialchars($settings['ai_api_key']); ?>" placeholder="输入你的API密钥">
                </div>
                <div class="form-group">
                    <label for="ai_system_prompt">系统提示词（AI角色设定）</label>
                    <textarea id="ai_system_prompt" name="ai_system_prompt" rows="3"><?php echo htmlspecialchars($settings['ai_system_prompt']); ?></textarea>
                </div>
                <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                    <div style="font-weight: bold; margin-bottom: 10px; color: #333;">🔧 环境诊断</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 13px;">
                        <div>✅ PHP版本：<?php echo phpversion(); ?></div>
                        <div><?php echo function_exists('curl_init') ? '✅ CURL扩展：已启用' : '❌ CURL扩展：未启用'; ?></div>
                        <div><?php echo function_exists('json_decode') ? '✅ JSON支持：已启用' : '❌ JSON支持：未启用'; ?></div>
                        <div><?php echo ini_get('allow_url_fopen') ? '✅ allow_url_fopen：开启' : '⚠️ allow_url_fopen：关闭'; ?></div>
                    </div>
                </div>
                <button type="submit" class="btn">保存AI设置</button>
            </form>
        </div>

        <div class="card" id="card-chat-list">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">📋 对话列表</h2>
                <button type="button" class="btn btn-danger btn-small" id="batchDeleteBtn" onclick="batchDeleteChats()" disabled>🗑️ 批量删除 (<span id="selectedCount">0</span>)</button>
            </div>
            <div id="chatListContainer">
                <?php if (empty($chats)): ?>
                    <div class="no-chats">暂无对话内容</div>
                <?php else: ?>
                    <?php foreach ($groupedChats as $groupId => $groupData): ?>
                        <?php if (empty($groupData['chats'])) continue; ?>
                        <div class="chat-group collapsed" data-group-id="<?php echo htmlspecialchars($groupId); ?>" id="chat-group-<?php echo htmlspecialchars($groupId ?: 'ungrouped'); ?>">
                            <div class="chat-group-header" onclick="toggleChatGroup('<?php echo htmlspecialchars($groupId ?: 'ungrouped'); ?>')">
                                <span class="chat-group-toggle">▼</span>
                                <span class="chat-group-name"><?php echo htmlspecialchars($groupData['name']); ?></span>
                                <span class="chat-group-count"><?php echo count($groupData['chats']); ?> 条</span>
                            </div>
                            <div class="chat-group-body">
                                <div class="table-container">
                                    <table class="chat-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;"><input type="checkbox" class="group-select-all" onclick="toggleGroupSelectAll('<?php echo htmlspecialchars($groupId ?: 'ungrouped'); ?>')"></th>
                                                <th>昵称/主题</th>
                                                <th>时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($groupData['chats'] as $chat): ?>
                                                <tr data-chat-id="<?php echo htmlspecialchars($chat['id']); ?>" class="chat-row">
                                                    <td><input type="checkbox" class="chat-checkbox" value="<?php echo htmlspecialchars($chat['id']); ?>" data-group="<?php echo htmlspecialchars($groupId ?: 'ungrouped'); ?>"></td>
                                                    <td><?php echo htmlspecialchars($chat['name']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', $chat['timestamp']); ?></td>
                                                    <td>
                                                        <div class="action-btns">
                                                            <button type="button" class="action-btn edit btn-small" onclick="editChat('<?php echo $chat['id']; ?>')">编辑</button>
                                                            <button type="button" class="action-btn delete btn-small" onclick="deleteChatById('<?php echo $chat['id']; ?>')">删除</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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

    <div class="modal" id="resetConfirmModal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeResetConfirmModal()">&times;</span>
            <h3 style="color: #ff4444;">⚠️ 危险确认</h3>
            <div style="padding: 15px; background: #ffebee; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #f44336;">
                <p style="color: #c62828; font-weight: bold; margin-bottom: 10px;">此操作将：</p>
                <ul style="color: #c62828; padding-left: 20px; margin-bottom: 10px;">
                    <li>删除所有思维导图数据</li>
                    <li>删除所有备忘录数据</li>
                    <li>删除所有会话文件</li>
                    <li>删除所有分组数据</li>
                    <li>删除photos文件夹内所有图片文件</li>
                    <li>还原系统设置为初始值</li>
                </ul>
                <p style="color: #c62828; font-weight: bold;">⚠️ 此操作不可恢复！</p>
            </div>
            <p style="margin-bottom: 20px;">请再次确认您要执行此操作！</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeResetConfirmModal()">取消</button>
                <button type="button" class="btn btn-danger" id="confirmResetBtn" onclick="confirmResetAll()">确认重置所有数据</button>
            </div>
        </div>
    </div>

    <script>
        let messageIndex = 3;
        const chatData = <?php echo json_encode($chats); ?>;
        const groupsData = <?php echo json_encode($groups); ?>;

        function openResetConfirmModal() {
            document.getElementById('resetConfirmModal').classList.add('active');
        }

        function closeResetConfirmModal() {
            document.getElementById('resetConfirmModal').classList.remove('active');
        }

        async function confirmResetAll() {
            const btn = document.getElementById('confirmResetBtn');
            setButtonLoading(btn, true);

            try {
                const formData = new FormData();
                formData.append('action', 'reset_all');
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Reset response:', result);

                if (result.success) {
                    showToast(result.message || '系统重置成功', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(result.message || '重置失败', 'error');
                }
            } catch (e) {
                console.error('Reset error:', e);
                showMessage('请求失败: ' + e.message, 'error');
            } finally {
                setButtonLoading(btn, false);
            }
        }

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

        function scrollToCard(cardId) {
            const card = document.getElementById(cardId);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                card.style.transition = 'box-shadow 0.3s';
                card.style.boxShadow = '0 0 0 3px rgba(26, 115, 232, 0.3)';
                setTimeout(() => {
                    card.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                }, 1500);
            }
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

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.chat-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('batchDeleteBtn').disabled = count === 0;
        }

        function toggleSelectAllChats() {
            const selectAll = document.getElementById('selectAllChats');
            const checkboxes = document.querySelectorAll('.chat-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateSelectedCount();
        }

        async function batchDeleteChats() {
            const checkboxes = document.querySelectorAll('.chat-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            if (ids.length === 0) {
                showToast('请先选择要删除的对话', 'error');
                return;
            }
            
            if (!confirm('确定要删除选中的 ' + ids.length + ' 条对话吗？')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'batch_delete_chat');
                formData.append('ids', JSON.stringify(ids));
                formData.append('X-Requested-With', 'XMLHttpRequest');

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message || '批量删除成功', 'success');
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showMessage(result.message || '删除失败', 'error');
                }
            } catch (e) {
                console.error('Batch delete error:', e);
                showMessage('请求失败', 'error');
            }
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('chat-checkbox')) {
                updateSelectedCount();
            }
        });

        function toggleChatGroup(groupId) {
            const group = document.getElementById('chat-group-' + groupId);
            if (group) {
                group.classList.toggle('collapsed');
            }
        }

        function toggleGroupSelectAll(groupId) {
            const group = document.getElementById('chat-group-' + groupId);
            const groupCheckbox = group.querySelector('.group-select-all');
            const checkboxes = group.querySelectorAll('.chat-checkbox');
            checkboxes.forEach(cb => cb.checked = groupCheckbox.checked);
            updateSelectedCount();
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('group-select-all')) {
                const group = e.target.closest('.chat-group');
                const groupId = group.dataset.groupId || 'ungrouped';
                toggleGroupSelectAll(groupId);
            }
        });

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
            const defaultSender = 'customer';
            
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
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js');
            });
        }
    </script>
</body>
</html>