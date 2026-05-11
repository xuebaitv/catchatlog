<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../inc/functions.php';
require_once '../inc/config.php';
$settings = getSettings();

$memoDataFile = __DIR__ . '/memos.json';

function getMemos() {
    global $memoDataFile;
    if (!file_exists($memoDataFile)) {
        return [];
    }
    $content = file_get_contents($memoDataFile);
    return json_decode($content, true) ?: [];
}

function saveMemos($memos) {
    global $memoDataFile;
    file_put_contents($memoDataFile, json_encode($memos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action !== '') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    if ($action === 'check_auth') {
        echo json_encode([
            'success' => true,
            'isEditMode' => checkAuth()
        ]);
        exit;
    }

    if ($action === 'verify_password') {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        if (login($password)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'msg' => '密码错误']);
        }
        exit;
    }

    if ($action === 'list') {
        echo json_encode(['success' => true, 'data' => getMemos()]);
        exit;
    }

    if (!checkAuth()) {
        echo json_encode(['success' => false, 'msg' => '无权限']);
        exit;
    }

    if ($action === 'add') {
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $bgColor = isset($_POST['bgColor']) ? $_POST['bgColor'] : '#fffef0';
        if ($title === '') $title = '新备忘录';
        $memos = getMemos();
        $newId = count($memos) > 0 ? max(array_column($memos, 'id')) + 1 : 1;
        $newMemo = [
            'id' => $newId,
            'title' => $title,
            'content' => '',
            'bgColor' => $bgColor,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];
        $memos[] = $newMemo;
        saveMemos($memos);
        if (function_exists('updateDataVersion')) updateDataVersion();
        echo json_encode(['success' => true, 'data' => $newMemo]);
        exit;
    }

    if ($action === 'update') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $bgColor = isset($_POST['bgColor']) ? $_POST['bgColor'] : '#fffef0';
        $memos = getMemos();
        foreach ($memos as &$m) {
            if ($m['id'] === $id) {
                $m['title'] = $title;
                $m['content'] = $content;
                $m['bgColor'] = $bgColor;
                $m['updatedAt'] = date('Y-m-d H:i:s');
                saveMemos($memos);
                if (function_exists('updateDataVersion')) updateDataVersion();
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'msg' => '未找到']);
        exit;
    }

    if ($action === 'delete') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $memos = getMemos();
        $newMemos = array_filter($memos, fn($m) => $m['id'] !== $id);
        saveMemos(array_values($newMemos));
        if (function_exists('updateDataVersion')) updateDataVersion();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'ai_organize') {
        if (!$settings['ai_enabled'] || empty($settings['ai_api_key'])) {
            echo json_encode(['success' => false, 'msg' => 'AI功能未启用，请在后台配置AI']);
            exit;
        }
        $rawContent = isset($_POST['raw_content']) ? $_POST['raw_content'] : '';
        $rawTitle = isset($_POST['raw_title']) ? $_POST['raw_title'] : '';
        if (empty(trim($rawContent))) {
            echo json_encode(['success' => false, 'msg' => '请先输入一些内容再让AI整理']);
            exit;
        }
        
        $userCustomPrompt = isset($_POST['user_prompt']) ? trim($_POST['user_prompt']) : '';
        
        $systemPrompt = '你是一个严格遵守规则的备忘录内容美化助手！最重要的规则是：绝对不删除、绝对不修改用户的任何一个原始文字字符！所有用户写的原文字符必须100%完整保留在结果中，一个字都不能少，一个字都不能改！你唯一允许做的事情：1. 合理使用HTML标签为原有文字添加格式效果：比如用<strong>给重点文字加粗，用<em>让文字变成斜体，用<u>下划线，用<del>删除线，用<h1>/<h2>/<h3>给内容加上合适的标题结构，用<ul>/<ol>/<li>把列表内容排版好看，用<a href="">给提到的链接加上超链接等。2. 绝对不要添加任何你自己生成的新内容文字！绝对不能改写用户的文字！用户要求自定义的优化指令，也必须在遵守所有原始文字完全保留的前提下执行。返回格式必须是严格的JSON，两个键：title（优化后的标题字符串，标题也只能基于用户原有的标题文字美化，不能乱改）和html（完整的内容HTML字符串）';
        
        if(!empty($userCustomPrompt)){
            $userPrompt = '用户自定义优化要求：' . $userCustomPrompt . "\n\n现有原始标题：" . $rawTitle . "\n所有原始内容必须100%完整保留！原始内容：\n" . $rawContent . "\n请严格遵守规则整理美化。";
        }else{
            $userPrompt = '现有原始标题：' . $rawTitle . "\n所有原始内容必须100%完整保留！原始内容：\n" . $rawContent . "\n仅做格式美化，不要删除任何文字！";
        }
        
        $requestData = [
            'model' => $settings['ai_model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.6,
            'max_tokens' => 2500
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
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
            echo json_encode(['success' => false, 'msg' => '网络错误：' . $error]);
            exit;
        }
        $result = json_decode($response, true);
        if ($httpCode !== 200 || !isset($result['choices'][0]['message']['content'])) {
            echo json_encode(['success' => false, 'msg' => 'AI服务错误：HTTP ' . $httpCode]);
            exit;
        }
        
        $aiOutput = $result['choices'][0]['message']['content'];
        $parsed = json_decode($aiOutput, true);
        if (!$parsed || !isset($parsed['title']) || !isset($parsed['html'])) {
            echo json_encode(['success' => false, 'msg' => 'AI返回格式异常，请重试']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'title' => $parsed['title'],
                'html' => $parsed['html']
            ]
        ]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>备忘录</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" />
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;position:relative;overflow-x:hidden}
        body::before{content:'';position:fixed;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle at 20% 30%,rgba(102,126,234,0.1) 0%,transparent 50%),radial-gradient(circle at 80% 70%,rgba(118,75,162,0.12) 0%,transparent 50%);animation:floatBg 20s ease-in-out infinite alternate;z-index:-1}
        @keyframes floatBg{0%{transform:translate(0,0) rotate(0deg)} 100%{transform:translate(30px,20px) rotate(3deg)}}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}

        .mobile-top-bar{display:none;position:sticky;top:0;z-index:110;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);box-shadow:0 2px 16px rgba(0,0,0,0.08);padding:12px 18px;align-items:center;justify-content:space-between}
        .mobile-top-bar h2{font-size:17px;color:#1a1a2e;font-weight:600}
        .mobile-top-bar a{color:#667eea;text-decoration:none;font-size:20px;padding:8px;border-radius:8px;transition:background 0.2s}
        .mobile-top-bar a:hover{background:rgba(102,126,234,0.1)}

        .desktop-top-bar{position:sticky;top:0;z-index:100;background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);box-shadow:0 2px 20px rgba(0,0,0,0.08);padding:16px 28px;display:flex;align-items:center;justify-content:space-between;border-radius:0 0 24px 24px}
        .desktop-top-bar-left{display:flex;align-items:center;gap:16px}
        .desktop-top-bar h1{font-size:22px;color:#1a1a2e;font-weight:700;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .mobile-top-bar-right-buttons{display:flex;gap:8px;align-items:center}
        .mobile-add-btn{background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:10px;padding:8px 14px;border:none;font-size:14px;font-weight:600;box-shadow:0 3px 12px rgba(102,126,234,0.3);display:flex;align-items:center;justify-content:center;min-height:36px;cursor:pointer}

        .btn{padding:10px 20px;border-radius:12px;border:none;cursor:pointer;font-size:14px;font-weight:600;transition:all 0.2s cubic-bezier(0.4,0,0.2,1);min-height:42px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:white;box-shadow:0 4px 14px rgba(102,126,234,0.3)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(102,126,234,0.4)}
        .btn-primary:active{transform:translateY(0)}
        .btn-danger{background:linear-gradient(135deg,#f093fb,#f5576c);color:white;box-shadow:0 4px 14px rgba(245,87,108,0.3)}
        .btn-danger:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(245,87,108,0.4)}
        .btn-ghost{background:transparent;color:#4b5563;text-decoration:none;display:inline-block;border:1px solid #e5e7eb}
        .btn-ghost:hover{background:#f8f9ff;border-color:#667eea;color:#667eea}
        .read-only-badge{background:linear-gradient(135deg,#f5af19,#f12711);color:white;padding:8px 18px;border-radius:30px;font-size:13px;font-weight:600;box-shadow:0 3px 12px rgba(241,39,17,0.2)}

        .container{max-width:1450px;margin:0 auto;padding:32px 20px}
        .waterfall{column-count:4;column-gap:20px;padding:10px}
        @media(max-width:1200px){.waterfall{column-count:3}}
        @media(max-width:900px){.waterfall{column-count:2}}
        @media(max-width:580px){.waterfall{column-count:1}}

        .memo-card{break-inside:avoid;margin-bottom:20px;border-radius:20px;padding:20px;box-shadow:0 8px 30px rgba(0,0,0,0.08);cursor:pointer;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);border:1px solid rgba(255,255,255,0.5);backdrop-filter:blur(4px)}
        .memo-card:hover{transform:translateY(-6px) scale(1.01);box-shadow:0 16px 48px rgba(0,0,0,0.15)}
        .memo-card h3{font-size:18px;margin-bottom:14px;color:#1a1a2e;line-height:1.45;font-weight:700}
        .memo-card .preview{font-size:15px;color:#374151;line-height:1.7;overflow:hidden;display:-webkit-box;-webkit-line-clamp:7;-webkit-box-orient:vertical}
        .memo-card .time{margin-top:16px;font-size:13px;color:#6b7280;display:flex;align-items:center;gap:6px}

        .empty-state{text-align:center;padding:100px 20px;color:white}
        .empty-state-icon{font-size:80px;margin-bottom:24px}
        .empty-state h3{font-size:24px;margin-bottom:12px;opacity:0.95}
        .empty-state p{font-size:16px;opacity:0.8}

        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);z-index:20000;display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeIn 0.2s ease-out}
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        .modal{background:white;border-radius:24px;width:96%;max-width:920px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(0,0,0,0.25);animation:slideUp 0.3s ease-out}
        @keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
        .modal-header{padding:20px 28px;border-bottom:1px solid #f0f0f5;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(to bottom,#fafbff,white)}
        .modal-header h2{font-size:20px;font-weight:700;color:#1a1a2e}
        .modal-body{padding:24px 28px;overflow-y:auto;flex:1}
        .modal-footer{padding:18px 28px;border-top:1px solid #f0f0f5;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;background:#fafbff}
        .close-btn{background:none;border:none;font-size:28px;cursor:pointer;color:#9ca3af;padding:4px 8px;border-radius:8px;transition:all 0.2s}
        .close-btn:hover{background:#f3f4f6;color:#374151}

        .input-field{width:100%;padding:14px 18px;border:2px solid #e5e7eb;border-radius:14px;font-size:16px;margin-bottom:20px;transition:border-color 0.2s}
        .input-field:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}

        .color-picker-row{display:flex;gap:14px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
        .color-picker-row label{font-size:15px;color:#374151;white-space:nowrap;font-weight:600}

        .ql-editor{min-height:320px;font-size:16px;line-height:1.7}
        .ql-container{font-size:16px;border-radius:0 0 14px 14px}
        .ql-toolbar{border-radius:14px 14px 0 0}
        .ql-toolbar,.ql-container{border-color:#e5e7eb}

        .toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%) translateY(20px);background:linear-gradient(135deg,#1a1a2e,#16213e);color:white;padding:14px 32px;border-radius:14px;z-index:30000;box-shadow:0 8px 32px rgba(0,0,0,0.2);font-weight:500;opacity:0;animation:toastIn 0.3s forwards}
        @keyframes toastIn{to{transform:translateX(-50%) translateY(0);opacity:1}}

        .color-swatch{width:40px;height:40px;border-radius:12px;border:3px solid transparent;cursor:pointer;transition:all 0.2s cubic-bezier(0.4,0,0.2,1);box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        .color-swatch:hover{transform:scale(1.15)}
        .color-swatch.active{border-color:#667eea;transform:scale(1.1)}
        .colors-row{display:flex;gap:10px;flex-wrap:wrap}

        .edit-mode-hidden{display:none !important;}

        .delete-context-menu{position:fixed;z-index:30000;background:white;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,0.2);padding:8px 0;min-width:160px;animation:popIn 0.15s ease-out}
        @keyframes popIn{from{transform:scale(0.9);opacity:0}to{transform:scale(1);opacity:1}}
        .delete-context-menu div{padding:12px 20px;cursor:pointer;color:#ef4444;font-weight:600;transition:background 0.15s}
        .delete-context-menu div:hover{background:#fef2f2}

        .modal.fullscreen-modal{position:fixed;width:100vw !important;height:100vh !important;max-width:100vw !important;max-height:100vh !important;border-radius:0 !important;top:0;left:0}

        .fullscreen-btn{background:none;border:none;font-size:20px;cursor:pointer;padding:4px 10px;border-radius:8px;transition:all 0.2s;color:#4b5563}
        .fullscreen-btn:hover{background:#f3f4f6;color:#111827}

        .fullscreen-viewer{position:fixed;inset:0;background:rgba(0,0,0,0.4);backdrop-filter:blur(12px);z-index:50000;display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeInViewer 0.3s ease-out}
        @keyframes fadeInViewer{from{opacity:0}to{opacity:1}}
        .viewer-card{width:100%;max-width:1200px;max-height:92vh;border-radius:28px;box-shadow:0 30px 90px rgba(0,0,0,0.3);display:flex;flex-direction:column;overflow:hidden;animation:slideUpViewer 0.4s cubic-bezier(0.4,0,0.2,1)}
        @keyframes slideUpViewer{from{transform:translateY(40px) scale(0.92);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}
        .viewer-header{padding:20px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(0,0,0,0.06);background:linear-gradient(to bottom,rgba(255,255,255,0.75),rgba(255,255,255,0.35));backdrop-filter:blur(10px)}
        .viewer-header h2{font-size:22px;font-weight:700;color:#1a1a2e;margin:0}
        .viewer-header-right{display:flex;gap:12px;align-items:center}
        .viewer-body{flex:1;overflow-y:auto;padding:32px 36px}
        .viewer-content h1,.viewer-content h2,.viewer-content h3,.viewer-content p{line-height:1.7;color:#1a1a2e}
        .viewer-content{font-size:17px;line-height:1.9}

        .memo-card{break-inside:avoid;margin-bottom:20px;border-radius:20px;padding:20px;box-shadow:0 8px 30px rgba(0,0,0,0.08);cursor:pointer;transition:all 0.4s cubic-bezier(0.4,0,0.2,1);border:1px solid rgba(255,255,255,0.55);backdrop-filter:blur(6px);position:relative;overflow:hidden}
        .memo-card::before{content:'';position:absolute;inset:-100%;background:linear-gradient(45deg,transparent,rgba(255,255,255,0.2),transparent);transform:rotate(45deg);transition:all 0.6s cubic-bezier(0.4,0,0.2,1);opacity:0}
        .memo-card:hover::before{inset:0;opacity:1}
        .memo-card:hover{transform:translateY(-10px) scale(1.025);box-shadow:0 25px 70px rgba(0,0,0,0.22);border-color:rgba(102,126,234,0.4)}
        .float-format-toolbar{position:fixed;z-index:35000;background:white;border-radius:12px;box-shadow:0 6px 30px rgba(0,0,0,0.18);padding:10px 12px;gap:6px;align-items:center;opacity:0 !important;pointer-events:none !important;transform:scale(0.85) !important;transition:all 0.2s cubic-bezier(0.4,0,0.2,1);display:flex}
        .float-format-toolbar.visible{opacity:1 !important;pointer-events:auto !important;transform:scale(1) !important;display:flex !important;animation:none}
        .float-format-toolbar button{width:36px;height:36px;border-radius:8px;border:none;background:#f8f9ff;cursor:pointer;font-size:16px;font-weight:600;color:#374151;transition:all 0.15s}
        .float-format-toolbar button:hover{background:#667eea;color:white}
        .float-format-toolbar .color-btn{width:28px;height:28px;border-radius:6px;border:2px solid transparent}
        .float-format-toolbar .color-btn:hover{transform:scale(1.25);border-color:#667eea}

        @media(max-width:768px){
            .mobile-top-bar{display:flex;}
            .desktop-top-bar{display:none;}
            .container{padding:20px 12px;}
            .modal{width:100%;border-radius:20px;max-height:95vh;}
            .modal-body{padding:18px 16px;}
            .btn{padding:12px 16px;}
            .waterfall{column-gap:16px;}
            .float-format-toolbar:not(.visible){opacity:0 !important;pointer-events:none !important;transform:scale(0.85) !important;display:flex}
            .float-format-toolbar{padding:8px 10px;gap:4px}
            .float-format-toolbar.visible{opacity:1 !important;pointer-events:auto !important;transform:scale(1) !important;display:flex !important;animation:none}
            .float-format-toolbar button{width:32px;height:32px;font-size:14px}
            .fullscreen-viewer{padding:10px;}
            .viewer-card{max-height:95vh;border-radius:22px;}
            .viewer-header{padding:16px 20px;}
            .viewer-body{padding:20px 16px;}
        }
    </style>
</head>
<body>
    <div class="mobile-top-bar">
        <a href="../index.php">🏠</a>
        <h2>📝 备忘录</h2>
        <div class="mobile-top-bar-right-buttons">
            <span id="mobileReadOnlyBadge" style="font-size:13px;color:#f59e0b;font-weight:600;display:none;">🔒</span>
            <button id="mobileEnterEditBtn" class="mobile-add-btn edit-mode-hidden">🔐</button>
            <button id="mobileAddMemoBtn" class="mobile-add-btn edit-mode-hidden" style="display:none;">+</button>
            <a href="../mindmap/index.php">🧠</a>
        </div>
    </div>
    <div class="desktop-top-bar">
        <div class="desktop-top-bar-left">
            <a href="../index.php" class="btn btn-ghost">🏠 返回主页</a>
            <h1>📝 备忘录</h1>
            <span id="readOnlyBadge" class="read-only-badge">🔒 只读模式</span>
        </div>
        <div style="display:flex;gap:12px">
            <button id="enterEditBtn" class="btn btn-primary">🔐 编辑</button>
            <button id="addMemoBtn" class="btn btn-primary edit-mode-hidden">➕ 新建备忘录</button>
        </div>
    </div>
    <div class="container">
        <div id="waterfall" class="waterfall"></div>
        <div id="emptyState" class="empty-state">
            <div class="empty-state-icon">📝</div>
            <h3>暂无备忘录</h3>
            <p>点右上角按钮创建你的第一个备忘录吧！</p>
        </div>
    </div>
    <div id="passwordModal" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:440px;">
            <div class="modal-header">
                <h2>输入管理员密码</h2>
                <button onclick="closeModal('passwordModal')" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <input type="password" id="passwordInput" placeholder="请输入密码..." class="input-field">
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('passwordModal')" class="btn btn-ghost">取消</button>
                <button onclick="submitPassword()" class="btn btn-primary">确认进入</button>
            </div>
        </div>
    </div>
    <div id="newMemoModal" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <h2>✨ 新建备忘录</h2>
                <button onclick="closeModal('newMemoModal')" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="newMemoTitleInput" class="input-field" placeholder="给备忘录起个好听的名字...">
                <div class="color-picker-row">
                    <label>🎨 选择背景色：</label>
                    <div class="colors-row" id="newMemoColors"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('newMemoModal')" class="btn btn-ghost">取消</button>
                <button onclick="confirmNewMemo()" class="btn btn-primary">创建并打开</button>
            </div>
        </div>
    </div>
    <div id="editorModal" class="modal-overlay" style="display:none;">
        <div id="editorModalInner" class="modal">
            <div class="modal-header">
                <h2 id="editorTitle">✏️ 编辑备忘录</h2>
                <div style="display:flex;gap:8px">
                    <button id="toggleFullscreenBtn" class="fullscreen-btn" onclick="toggleFullscreenEditor()">⛶</button>
                    <button onclick="closeModal('editorModal')" class="close-btn">&times;</button>
                </div>
            </div>
            <div class="modal-body">
                <input type="text" id="memoTitleInput" class="input-field" placeholder="给备忘录起个标题...">
                <div class="color-picker-row">
                    <label>🎨 背景色：</label>
                    <div class="colors-row" id="bgColors"></div>
                </div>
                <div id="editorContainer"></div>
            </div>
            <div class="modal-footer">
                <button id="deleteBtn" class="btn btn-danger edit-mode-hidden">🗑️ 删除</button>
                <div style="flex:1"></div>
                <button id="aiOrganizeBtn" onclick="doAiOrganize()" class="btn" style="background:linear-gradient(135deg,#8b5cf6,#ec4899);color:white;box-shadow:0 4px 14px rgba(139,92,246,0.3)">✨ AI整理</button>
                <button onclick="closeModal('editorModal')" class="btn btn-ghost">关闭</button>
                <button id="saveMemoBtn" onclick="saveMemo()" class="btn btn-primary">💾 保存</button>
            </div>
        </div>
    </div>

    <div id="fullscreenViewer" class="fullscreen-viewer" style="display:none;">
        <div id="viewerCard" class="viewer-card">
            <div class="viewer-header">
                <h2 id="viewerTitleDisplay">备忘录</h2>
                <div class="viewer-header-right">
                    <button id="viewerEditBtn" class="btn btn-primary" onclick="goToEditFromViewer()">✏️ 编辑</button>
                    <button class="btn btn-ghost" onclick="closeViewer()">✕ 关闭</button>
                </div>
            </div>
            <div class="viewer-body">
                <div id="viewerContent" class="viewer-content"></div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast" style="display:none;"></div>
    <div id="deleteMenu" class="delete-context-menu" style="display:none;" onmousedown="event.stopPropagation();">
        <div onclick="performDeleteSelectedMemo()">🗑️ 删除备忘录</div>
    </div>

    <div id="aiPromptModal" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:500px;">
            <div class="modal-header">
                <h2>✨ AI整理设置</h2>
                <button onclick="closeModal('aiPromptModal')" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <label style="display:block;margin-bottom:10px;color:#374151;font-weight:600;">自定义优化提示（可选）：</label>
                <textarea id="userAiPrompt" class="input-field" style="height:120px;resize:vertical;" placeholder="告诉AI你想怎么优化这段文本...&#10;&#10;如果不填，AI将默认只给你的内容加粗、加标题、排版美化，100%保留你所有原始文字丝毫不改！"></textarea>
                <p style="margin-top:12px;color:#6b7280;font-size:13px;">⚠️ 注意：AI绝对不会在你没有明确要求的情况下，删除或修改你的任何原始文字！</p>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('aiPromptModal')" class="btn btn-ghost">取消</button>
                <button id="confirmAiBtn" onclick="confirmAiOrganize()" class="btn" style="background:linear-gradient(135deg,#8b5cf6,#ec4899);color:white;box-shadow:0 4px 14px rgba(139,92,246,0.3)">🚀 开始整理</button>
            </div>
        </div>
    </div>

    <div id="floatToolbar" class="float-format-toolbar">
        <button onclick="formatQuick('bold')" title="加粗"><b>B</b></button>
        <button onclick="formatQuick('italic')" title="斜体"><i>I</i></button>
        <button onclick="formatQuick('underline')" title="下划线"><u>U</u></button>
        <button onclick="formatQuick('strike')" title="删除线"><s>S</s></button>
        <div style="width:1px;height:32px;background:#e5e7eb;margin:0 4px;"></div>
        <button onclick="formatQuick('size','small')" title="小">Aa</button>
        <button onclick="formatQuick('size','large')" title="大">Aa</button>
        <button onclick="formatQuick('size','huge')" title="特大">Aa</button>
        <div style="width:1px;height:32px;background:#e5e7eb;margin:0 4px;"></div>
        <div class="color-btn" style="background-color:#ef4444;" onclick="formatQuick('color','#ef4444')" title="红"></div>
        <div class="color-btn" style="background-color:#f59e0b;" onclick="formatQuick('color','#f59e0b')" title="橙"></div>
        <div class="color-btn" style="background-color:#10b981;" onclick="formatQuick('color','#10b981')" title="绿"></div>
        <div class="color-btn" style="background-color:#3b82f6;" onclick="formatQuick('color','#3b82f6')" title="蓝"></div>
        <div style="width:1px;height:32px;background:#e5e7eb;margin:0 4px;"></div>
        <div class="color-btn" style="background-color:#fecaca;" onclick="formatQuick('background','#fecaca')" title="红背景"></div>
        <div class="color-btn" style="background-color:#fef3c7;" onclick="formatQuick('background','#fef3c7')" title="黄背景"></div>
        <div class="color-btn" style="background-color:#d1fae5;" onclick="formatQuick('background','#d1fae5')" title="绿背景"></div>
        <div class="color-btn" style="background-color:#dbeafe;" onclick="formatQuick('background','#dbeafe')" title="蓝背景"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script>
        let isEditMode = false;
        let memos = [];
        let currentEditingId = null;
        let quill = null;
        let isFullscreenEditor = false;
        let currentViewingMemo = null;
        let floatToolbarHideTimer = null;

        function showFloatToolbar(){
            if(!quill || !quill.root.contains(document.activeElement)){
                hideFloatToolbar();
                return;
            }
            const sel=window.getSelection();
            if(!sel || sel.isCollapsed || sel.toString().trim().length===0){
                hideFloatToolbar();
                return;
            }
            const range=sel.getRangeAt(0);
            const rect=range.getBoundingClientRect();
            const tb=document.getElementById('floatToolbar');
            let nx=rect.left + (rect.width/2)-180;
            let ny=rect.top - 60;
            if(nx<10)nx=10;
            if(nx+380>window.innerWidth)nx=window.innerWidth-390;
            if(ny<10)ny=rect.bottom+20;
            tb.style.left=nx+'px';
            tb.style.top=ny+'px';
            tb.classList.add('visible');
            clearTimeout(floatToolbarHideTimer);
        }
        function hideFloatToolbar(){
            document.getElementById('floatToolbar').classList.remove('visible');
        }

        function formatQuick(formatName, formatVal){
            if(!quill)return;
            const index=quill.getSelection()?.index;
            if(index==null)return;
            if(formatVal){
                quill.format(formatName, formatVal, 'user');
            }else{
                const cur=quill.getFormat(index);
                quill.format(formatName, cur[formatName] ? false : true, 'user');
            }
            clearTimeout(floatToolbarHideTimer);
            floatToolbarHideTimer=setTimeout(hideFloatToolbar, 1500);
        }

        document.addEventListener('mouseup',()=>{
            setTimeout(showFloatToolbar,10);
        });
        document.getElementById('floatToolbar').addEventListener('mousedown',(e)=>e.stopPropagation());

        let isAiProcessing = false;
        let tempStashedRawTitle = '';
        let tempStashedRawHtml = '';
        
        function doAiOrganize(){
            if(!quill || !currentEditingId)return;
            document.getElementById('userAiPrompt').value='';
            openModal('aiPromptModal');
        }
        
        async function confirmAiOrganize(){
            if(!quill || !currentEditingId || isAiProcessing)return;
            
            isAiProcessing=true;
            const aiBtn=document.getElementById('confirmAiBtn');
            const originalText=aiBtn.textContent;
            aiBtn.disabled=true;
            aiBtn.innerHTML='<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top:2px solid white;border-radius:50%;animation:spin 0.8s linear infinite;margin-right:6px;"></span> AI整理中...';
            
            tempStashedRawHtml=quill.root.innerHTML;
            tempStashedRawTitle=document.getElementById('memoTitleInput').value.trim();
            const userPromptText=document.getElementById('userAiPrompt').value.trim();
            
            closeModal('aiPromptModal');
            
            const f=new FormData();
            f.append('action','ai_organize');
            f.append('raw_title',tempStashedRawTitle);
            f.append('raw_content',tempStashedRawHtml);
            f.append('user_prompt',userPromptText);
            
            try{
                const r=await fetch('index.php',{method:'POST',body:f});
                const d=await r.json();
                if(d.success){
                    document.getElementById('memoTitleInput').value=d.data.title;
                    quill.root.innerHTML=d.data.html;
                    showToast('🎉 AI整理完成！所有原始文字100%完整保留！');
                }else{
                    showToast('❌ '+d.msg);
                }
            }catch(err){
                showToast('❌ 网络请求失败');
            }
            
            isAiProcessing=false;
            aiBtn.disabled=false;
            aiBtn.textContent=originalText;
            tempStashedRawTitle='';
            tempStashedRawHtml='';
        }
        
        const bgColors = ['#fffef0','#f0fff4','#f0f7ff','#fff0f5','#f5f0ff','#fff8e1','#e8f5e9','#e3f2fd','#ffebee','#fafafa','#ffffff'];

        function showToast(msg){
            const t=document.getElementById('toast');
            t.textContent=msg;
            t.style.display='block';
            setTimeout(()=>{t.style.display='none';},2500);
        }

        function openModal(id){
            document.getElementById(id).style.display='flex';
        }
        function closeModal(id){
            document.getElementById(id).style.display='none';
            exitFullscreenEditor();
            document.getElementById('deleteMenu').style.display='none';
            globalCurrentDeleteId=null;
            hideFloatToolbar();
        }

        function toggleFullscreenEditor(){
            const m=document.getElementById('editorModalInner');
            const b=document.getElementById('toggleFullscreenBtn');
            isFullscreenEditor=!isFullscreenEditor;
            if(isFullscreenEditor){
                m.classList.add('fullscreen-modal');
                b.textContent='⛶';
            }else{
                m.classList.remove('fullscreen-modal');
                b.textContent='⛶';
            }
        }
        function exitFullscreenEditor(){
            const m=document.getElementById('editorModalInner');
            const b=document.getElementById('toggleFullscreenBtn');
            isFullscreenEditor=false;
            m.classList.remove('fullscreen-modal');
            b.textContent='⛶';
        }

        function openViewer(memo){
            currentViewingMemo=memo;
            document.getElementById('viewerTitleDisplay').textContent=memo.title;
            document.getElementById('viewerContent').innerHTML=memo.content||'';
            const bgCol=memo.bgColor||'#fffef0';
            document.getElementById('viewerCard').style.backgroundColor=bgCol;
            document.getElementById('fullscreenViewer').style.display='flex';
            document.getElementById('viewerEditBtn').style.display=isEditMode?'block':'none';
        }

        function closeViewer(){
            document.getElementById('fullscreenViewer').style.display='none';
            document.getElementById('deleteMenu').style.display='none';
            globalCurrentDeleteId=null;
            currentViewingMemo=null;
        }

        async function goToEditFromViewer(){
            if(!isEditMode){
                showToast('请先进入编辑模式！');
                closeViewer();
                return;
            }
            if(!currentViewingMemo){
                showToast('备忘录不存在！');
                closeViewer();
                return;
            }
            const mId=currentViewingMemo.id;
            closeViewer();
            const foundMemo=memos.find(x=>x.id===mId);
            if(foundMemo){
                await openEditorModal(foundMemo);
            }
        }

        async function checkAuth(){
            const f=new FormData();
            f.append('action','check_auth');
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            isEditMode=d.isEditMode;
            updateEditModeUI();
            if(currentViewingMemo){
                document.getElementById('viewerEditBtn').style.display=isEditMode?'block':'none';
            }
            await loadMemos();
        }

        async function submitPassword(){
            const p=document.getElementById('passwordInput').value;
            const f=new FormData();
            f.append('action','verify_password');
            f.append('password',p);
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            if(d.success){
                isEditMode=true;
                closeModal('passwordModal');
                document.getElementById('passwordInput').value='';
                updateEditModeUI();
                if(currentViewingMemo){
                    document.getElementById('viewerEditBtn').style.display='block';
                }
                await loadMemos();
                showToast('🎉 已进入编辑模式！');
            } else {
                showToast('❌ 密码错误');
            }
        }

        function updateEditModeUI(){
            const allEditHidden=document.querySelectorAll('.edit-mode-hidden');
            const mobileReadOnlyBadge=document.getElementById('mobileReadOnlyBadge');
            const mobileEnterEditBtn=document.getElementById('mobileEnterEditBtn');
            const mobileAddMemoBtn=document.getElementById('mobileAddMemoBtn');

            allEditHidden.forEach(e=>{
                if(isEditMode){
                    e.classList.remove('edit-mode-hidden');
                    e.style.display='';
                } else {
                    e.classList.add('edit-mode-hidden');
                }
            });

            document.getElementById('readOnlyBadge').style.display=isEditMode?'none':'block';
            document.getElementById('enterEditBtn').style.display=isEditMode?'none':'block';

            if(mobileReadOnlyBadge){
                mobileReadOnlyBadge.style.display=isEditMode?'none':'flex';
            }
            if(mobileEnterEditBtn){
                mobileEnterEditBtn.style.display=isEditMode?'none':'flex';
            }
            if(mobileAddMemoBtn){
                mobileAddMemoBtn.style.display=isEditMode?'flex':'none';
            }
        }

        async function loadMemos(){
            const f=new FormData();
            f.append('action','list');
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            if(d.success){
                memos=d.data||[];
                renderMemos();
            }
        }

        function renderMemos(){
            const w=document.getElementById('waterfall');
            const es=document.getElementById('emptyState');
            if(memos.length===0){
                w.innerHTML='';
                es.style.display='block';
                return;
            }
            es.style.display='none';
            w.innerHTML='';
            memos.forEach(m=>{
                const t=document.createElement('div');
                t.innerHTML=m.content||'';
                const txt=(t.textContent||t.innerText||'').substring(0,200);
                const card=document.createElement('div');
                card.className='memo-card';
                card.setAttribute('data-memo-id',m.id);
                card.style.backgroundColor=m.bgColor||'#fffef0';
                card.innerHTML=`<h3>${escapeHtml(m.title)}</h3>
                    <div class="preview">${escapeHtml(txt)}</div>
                    <div class="time">🕒 ${escapeHtml(m.updatedAt)}</div>`;
                card.onclick=()=>openViewer(m);
                w.appendChild(card);
            });
            
            setTimeout(()=>attachMemoCardEvents(), 0);
        }

        function attachMemoCardEvents(){
            const cards=document.querySelectorAll('.memo-card');
            cards.forEach(card=>{
                const id=parseInt(card.getAttribute('data-memo-id'));

                card.addEventListener('contextmenu',(e)=>{
                    if(!isEditMode)return;
                    e.preventDefault();
                    showSimpleDeleteMenu(e.clientX, e.clientY, id);
                });

                let longPressTimerId=null;
                let touchId=0;
                card.addEventListener('touchstart',(e)=>{
                    if(!isEditMode)return;
                    touchId=id;
                    longPressTimerId=setTimeout(()=>{
                        const t=e.touches[0];
                        showSimpleDeleteMenu(t.clientX, t.clientY, touchId);
                    },600);
                },{passive:true});

                card.addEventListener('touchend',()=>{
                    clearTimeout(longPressTimerId);
                },{passive:true});

                card.addEventListener('touchmove',()=>{
                    clearTimeout(longPressTimerId);
                },{passive:true});
            });
        }

        let globalCurrentDeleteId=null;
        function showSimpleDeleteMenu(x,y,deleteId){
            globalCurrentDeleteId=deleteId;
            const menu=document.getElementById('deleteMenu');
            menu.style.display='block';
            let nx=x,ny=y;
            if(x+180>window.innerWidth)nx=x-180;
            if(y+80>window.innerHeight)ny=y-80;
            menu.style.left=nx+'px';
            menu.style.top=ny+'px';
        }

        document.addEventListener('mousedown',(e)=>{
            if(!e.target.closest('.delete-context-menu')){
                document.getElementById('deleteMenu').style.display='none';
                globalCurrentDeleteId=null;
            }
        });
        document.addEventListener('touchstart',(e)=>{
            if(!e.target.closest('.delete-context-menu')){
                document.getElementById('deleteMenu').style.display='none';
                globalCurrentDeleteId=null;
            }
        });

        async function performDeleteSelectedMemo(){
            const theId=globalCurrentDeleteId;
            document.getElementById('deleteMenu').style.display='none';
            globalCurrentDeleteId=null;
            if(!isEditMode || !theId)return;
            if(!confirm('确定删除此备忘录？'))return;
            const f=new FormData();
            f.append('action','delete');
            f.append('id',theId);
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            if(d.success){
                await loadMemos();
                showToast('🗑️ 已删除');
            }
        }

        function escapeHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}

        async function addMemo(){
            if(!isEditMode)return;
            document.getElementById('newMemoTitleInput').value='';
            renderNewMemoColors('#fffef0');
            openModal('newMemoModal');
        }

        function renderNewMemoColors(activeColor){
            const c=document.getElementById('newMemoColors');
            c.innerHTML=bgColors.map(col=>`<div class="color-swatch ${col===activeColor?'active':''}" style="background-color:${col}" onclick="selectNewMemoBg('${col}')"></div>`).join('');
            newMemoSelectedBg=activeColor;
        }
        let newMemoSelectedBg='#fffef0';
        function selectNewMemoBg(col){newMemoSelectedBg=col;renderNewMemoColors(col);}

        async function confirmNewMemo(){
            const titleVal=document.getElementById('newMemoTitleInput').value.trim();
            const f=new FormData();
            f.append('action','add');
            f.append('title',titleVal);
            f.append('bgColor',newMemoSelectedBg);
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            if(d.success){
                closeModal('newMemoModal');
                await loadMemos();
                const m=memos.find(x=>x.id===d.data.id);
                if(m)await openEditorModal(m);
                showToast('✅ 新建成功');
            }
        }

        async function openEditorModal(m){
            if(!m || !m.id){
                showToast('备忘录无效！');
                return;
            }
            currentEditingId=m.id;
            document.getElementById('memoTitleInput').value=m.title||'';
            document.getElementById('editorTitle').textContent='✏️ 编辑备忘录';
            if(!quill){
                quill=new Quill('#editorContainer',{theme:'snow',modules:{toolbar:[
                    ['bold','italic','underline','strike'],
                    [{size:['small',false,'large','huge']}],
                    [{color:[]},{background:[]}],
                    [{list:'ordered'},{list:'bullet'}],
                    ['link'],
                    ['clean']
                ]}});
            }
            renderBgColors(m.bgColor||'#fffef0');
            quill.enable(true);
            quill.setContents([]);
            try{
                quill.clipboard.dangerouslyPasteHTML(m.content||'');
            }catch(e){
                quill.root.innerHTML=m.content||'';
            }
            document.getElementById('memoTitleInput').disabled=false;
            const tb=document.querySelector('.ql-toolbar');
            if(tb)tb.style.display='block';
            const colorPickerRow=document.querySelector('.color-picker-row');
            if(colorPickerRow)colorPickerRow.style.display='flex';
            document.getElementById('deleteBtn').style.display='block';
            document.getElementById('deleteBtn').classList.remove('edit-mode-hidden');
            document.getElementById('saveMemoBtn').style.display='block';
            document.getElementById('saveMemoBtn').classList.remove('edit-mode-hidden');
            openModal('editorModal');
        }

        function renderBgColors(activeColor){
            const c=document.getElementById('bgColors');
            c.innerHTML=bgColors.map(col=>`<div class="color-swatch ${col===activeColor?'active':''}" style="background-color:${col}" onclick="selectBgColor('${col}')"></div>`).join('');
            selectedBg=activeColor;
        }
        let selectedBg='#fffef0';
        function selectBgColor(col){selectedBg=col;renderBgColors(col);}

        async function saveMemo(){
            if(!isEditMode||!currentEditingId)return;
            const f=new FormData();
            f.append('action','update');
            f.append('id',currentEditingId);
            f.append('title',document.getElementById('memoTitleInput').value);
            f.append('content',quill.root.innerHTML);
            f.append('bgColor',selectedBg);
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            if(d.success){await loadMemos();closeModal('editorModal');showToast('💾 保存成功！');}
        }

        async function deleteMemo(){
            if(!isEditMode||!currentEditingId)return;
            if(!confirm('确定要删除这个备忘录吗？操作无法撤销！'))return;
            const f=new FormData();
            f.append('action','delete');
            f.append('id',currentEditingId);
            const r=await fetch('index.php',{method:'POST',body:f});
            const d=await r.json();
            if(d.success){await loadMemos();closeModal('editorModal');showToast('🗑️ 已删除');}
        }

        document.getElementById('enterEditBtn').onclick=()=>openModal('passwordModal');
        document.getElementById('mobileEnterEditBtn').onclick=()=>openModal('passwordModal');
        document.getElementById('addMemoBtn').onclick=addMemo;
        document.getElementById('mobileAddMemoBtn').onclick=addMemo;
        document.getElementById('deleteBtn').onclick=deleteMemo;
        document.getElementById('passwordInput').onkeydown=(e)=>{if(e.key==='Enter')submitPassword();};

        checkAuth();
    </script>
</body>
</html>
