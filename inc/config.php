<?php
define('ADMIN_PASSWORD', 'admin123');
define('CHAT_DIR', __DIR__ . '/../chat/');
define('PHOTOS_DIR', __DIR__ . '/../photos/');
define('PHOTOS_URL', 'photos/');
define('GROUPS_FILE', __DIR__ . '/../chat/groups.json');

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