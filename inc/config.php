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
        'ai_system_prompt' => '你是一个专业的话术顾问。请参考以下历史对话记录，根据用户的问题给出最合适的回复而不是建议。不需要又回复内容以外的任何内容包括符号，回复要专业、友好，简洁，符合真实聊天场景，尽量靠近充值目标，并尽量根据我提供的全部对话中的内容进行回复。'
    ];
    
    if (!file_exists(SETTINGS_FILE)) {
        file_put_contents(SETTINGS_FILE, json_encode($defaultSettings, JSON_UNESCAPED_UNICODE));
        return $defaultSettings;
    }
    
    $content = file_get_contents(SETTINGS_FILE);
    $settings = json_decode($content, true);
    
    if (!$settings) {
        file_put_contents(SETTINGS_FILE, json_encode($defaultSettings, JSON_UNESCAPED_UNICODE));
        return $defaultSettings;
    }
    $result = $defaultSettings;
    foreach ($settings as $key => $value) {
        if (!is_null($value) && $value !== '') {
            $result[$key] = $value;
        }
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