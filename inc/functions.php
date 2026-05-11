<?php
function updateDataVersion() {
    $versionFile = CHAT_DIR . 'version.json';
    $currentVersion = 1;
    if (file_exists($versionFile)) {
        $content = file_get_contents($versionFile);
        $data = json_decode($content, true);
        if ($data && isset($data['version'])) {
            $currentVersion = intval($data['version']);
        }
    }
    $newVersion = $currentVersion + 1;
    file_put_contents($versionFile, json_encode(['version' => $newVersion, 'updated_at' => time()], JSON_UNESCAPED_UNICODE));
    return $newVersion;
}

function getChats($groupId = null) {
    $chats = [];
    if ($handle = opendir(CHAT_DIR)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != ".." && substr($entry, -5) === '.json' && $entry !== 'groups.json' && $entry !== 'settings.json' && $entry !== 'version.json') {
                $filePath = CHAT_DIR . $entry;
                $content = file_get_contents($filePath);
                if (empty($content) || trim($content) === '') {
                    @unlink($filePath);
                    continue;
                }
                $chat = json_decode($content, true);
                if ($chat && is_array($chat) && (!empty($chat['name']) || !empty($chat['content']) || !empty($chat['messages']))) {
                    $chat['id'] = basename($entry, '.json');
                    if ($groupId === null || (isset($chat['group_id']) && $chat['group_id'] === $groupId)) {
                        $chats[] = $chat;
                    }
                } else {
                    @unlink($filePath);
                }
            }
        }
        closedir($handle);
    }
    usort($chats, function($a, $b) {
        return (isset($b['timestamp']) ? $b['timestamp'] : 0) - (isset($a['timestamp']) ? $a['timestamp'] : 0);
    });
    return $chats;
}

function saveChat($data) {
    $id = uniqid();
    $data['timestamp'] = time();
    $data['id'] = $id;
    $filePath = CHAT_DIR . $id . '.json';
    return file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE));
}

function deleteChat($id) {
    $chatDirReal = realpath(CHAT_DIR);
    if (!$chatDirReal) {
        return false;
    }
    $filePath = CHAT_DIR . basename($id) . '.json';
    $realFilePath = realpath($filePath);
    if ($realFilePath && strpos($realFilePath, $chatDirReal . DIRECTORY_SEPARATOR) === 0 && file_exists($realFilePath) && is_file($realFilePath)) {
        return unlink($realFilePath);
    }
    return false;
}

function uploadPhoto($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '.' . $ext;
    $destination = PHOTOS_DIR . $fileName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $fileName;
    }
    return false;
}

function checkAuth() {
    return (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true);
}

function login($password) {
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_authenticated'] = true;
        return true;
    }
    return false;
}

function logout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_authenticated']);
    session_destroy();
}

function getGroups() {
    if (!file_exists(GROUPS_FILE)) {
        return [];
    }
    $content = file_get_contents(GROUPS_FILE);
    return json_decode($content, true) ?: [];
}

function saveGroups($groups) {
    return file_put_contents(GROUPS_FILE, json_encode($groups, JSON_UNESCAPED_UNICODE));
}

function addGroup($name, $description = '') {
    $groups = getGroups();
    $id = uniqid();
    $groups[$id] = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'created_at' => time()
    ];
    return saveGroups($groups);
}

function deleteGroup($id) {
    $groups = getGroups();
    if (!isset($groups[$id])) {
        return false;
    }
    unset($groups[$id]);
    saveGroups($groups);

    $chats = getChats();
    foreach ($chats as $chat) {
        if (isset($chat['group_id']) && $chat['group_id'] === $id) {
            deleteChat($chat['id']);
        }
    }
    return true;
}

function getChatsGrouped() {
    $groups = getGroups();
    $result = [];
    foreach ($groups as $group) {
        $result[$group['id']] = [
            'group' => $group,
            'chats' => getChats($group['id'])
        ];
    }
    $chatsWithoutGroup = getChats();
    $uncategorized = array_filter($chatsWithoutGroup, function($chat) {
        return !isset($chat['group_id']) || $chat['group_id'] === '';
    });
    if (!empty($uncategorized)) {
        $result['uncategorized'] = [
            'group' => ['id' => '', 'name' => '未分类', 'description' => ''],
            'chats' => array_values($uncategorized)
        ];
    }
    return $result;
}
?>