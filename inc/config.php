<?php
define('ADMIN_PASSWORD', 'admin123');
define('CHAT_DIR', __DIR__ . '/../chat/');
define('PHOTOS_DIR', __DIR__ . '/../photos/');
define('PHOTOS_URL', 'photos/');
define('GROUPS_FILE', __DIR__ . '/../chat/groups.json');
define('SETTINGS_FILE', __DIR__ . '/../chat/settings.json');

function getSettings() {
    $defaultSettings = [
        'page_title' => '客户聊天展示',
        'page_subtitle' => '真实对话记录展示',
        'access_password_enabled' => false,
        'access_password' => 'view123'
    ];
    
    if (!file_exists(SETTINGS_FILE)) {
        return $defaultSettings;
    }
    
    $content = file_get_contents(SETTINGS_FILE);
    $settings = json_decode($content, true);
    return array_merge($defaultSettings, $settings ?: []);
}

function saveSettings($settings) {
    return file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE));
}

function initializeDirectories() {
    if (!file_exists(CHAT_DIR)) {
        mkdir(CHAT_DIR, 0755, true);
    }
    if (!file_exists(PHOTOS_DIR)) {
        mkdir(PHOTOS_DIR, 0755, true);
    }
}

initializeDirectories();
?>