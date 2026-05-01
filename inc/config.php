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
        'access_password' => 'view123',
        'ai_enabled' => false,
        'ai_api_url' => 'https://api.openai.com/v1/chat/completions',
        'ai_api_key' => '',
        'ai_model' => 'gpt-3.5-turbo',
        'ai_system_prompt' => '你是一个专业的话术顾问。请参考以下历史对话记录，根据用户的问题给出最合适的回复建议。回复要专业、友好，符合真实聊天场景。'
    ];
    
    if (!file_exists(SETTINGS_FILE)) {
        return $defaultSettings;
    }
    
    $content = file_get_contents(SETTINGS_FILE);
    $settings = json_decode($content, true);
    
    $result = array_merge($defaultSettings, $settings ?: []);
    
    if (!isset($result['page_title']) || is_null($result['page_title']) || $result['page_title'] === '') {
        $result['page_title'] = $defaultSettings['page_title'];
    }
    if (!isset($result['page_subtitle']) || is_null($result['page_subtitle']) || $result['page_subtitle'] === '') {
        $result['page_subtitle'] = $defaultSettings['page_subtitle'];
    }
    if (!isset($result['access_password']) || is_null($result['access_password']) || $result['access_password'] === '') {
        $result['access_password'] = $defaultSettings['access_password'];
    }
    if (!isset($result['ai_api_url']) || is_null($result['ai_api_url']) || $result['ai_api_url'] === '') {
        $result['ai_api_url'] = $defaultSettings['ai_api_url'];
    }
    if (!isset($result['ai_model']) || is_null($result['ai_model']) || $result['ai_model'] === '') {
        $result['ai_model'] = $defaultSettings['ai_model'];
    }
    if (!isset($result['ai_system_prompt']) || is_null($result['ai_system_prompt']) || $result['ai_system_prompt'] === '') {
        $result['ai_system_prompt'] = $defaultSettings['ai_system_prompt'];
    }
    
    return $result;
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