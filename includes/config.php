<?php
/**
 * 数据库配置
 */

// 禁止 PHP 直接输出错误信息（防止破坏 JSON 响应）
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 跨域设置（开发环境）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Token, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'xazx_files');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

/**
 * 上传配置
 */
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_TYPES', [
    'application/pdf' => 'pdf',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip',
    'text/markdown' => 'md',
    'text/plain' => 'txt',
]);

/**
 * 权限定义
 */
$ALL_PERMISSIONS = [
    'product.view' => '查看产品资料',
    'product.upload' => '上传产品资料',
    'product.audit' => '审核产品资料',
    'product.delete' => '删除产品资料',
    'wiki.view' => '查看知识库',
    'wiki.edit' => '编辑知识库',
    'wiki.audit' => '审核知识库',
    'account.view' => '查看账号列表',
    'account.create' => '创建账号',
    'account.edit' => '编辑账号',
    'account.delete' => '删除/禁用账号',
    'role.manage' => '管理角色权限',
    'system.log' => '查看系统日志',
];

/**
 * 产品分类
 */
$PRODUCTS = [
    ['id' => 1, 'name' => '网站监测系统', 'code' => 'website_monitor'],
    ['id' => 2, 'name' => 'WAF', 'code' => 'waf'],
    ['id' => 3, 'name' => '动态防御', 'code' => 'dynamic_defense'],
    ['id' => 4, 'name' => '全流量分析', 'code' => 'traffic_analysis'],
    ['id' => 5, 'name' => 'API模块', 'code' => 'api_module'],
    ['id' => 6, 'name' => '大模型安全', 'code' => 'llm_security'],
    ['id' => 7, 'name' => '其他', 'code' => 'other'],
];

/**
 * 文件分类
 */
$FILE_CATEGORIES = [
    ['id' => 1, 'name' => '白皮书', 'code' => 'whitepaper'],
    ['id' => 2, 'name' => '操作手册', 'code' => 'manual'],
    ['id' => 3, 'name' => '功能清单', 'code' => 'feature_list'],
    ['id' => 4, 'name' => '功能说明', 'code' => 'feature_desc'],
    ['id' => 5, 'name' => '宣传PPT', 'code' => 'promo_ppt'],
    ['id' => 6, 'name' => '宣传折页', 'code' => 'promo_brochure'],
    ['id' => 7, 'name' => 'FQA', 'code' => 'fqa'],
    ['id' => 8, 'name' => '相关图片资料', 'code' => 'images'],
];

/**
 * 获取数据库连接
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            jsonResponse(['error' => '数据库连接失败: ' . $e->getMessage()], 500);
        }
    }
    return $pdo;
}

/**
 * JSON 响应
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 统一错误处理
 */
function errorResponse($message, $code = 400) {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

/**
 * 统一成功响应
 */
function successResponse($data = null, $message = '操作成功') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * 获取当前登录用户
 */
function getCurrentUser() {
    $token = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if (empty($token)) return null;
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, r.label as role_label, r.permissions as role_permissions, r.is_system as role_is_system 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.id 
                               WHERE u.token = ? AND u.token_expires_at > NOW() AND u.status = 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $user['permissions'] = json_decode($user['role_permissions'] ?? '[]', true);
            unset($user['password'], $user['role_permissions']);
        }
        return $user;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * 要求登录
 */
function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        errorResponse('未登录或登录已过期', 401);
    }
    return $user;
}

/**
 * 要求指定权限
 */
function requirePermission($permission) {
    $user = requireAuth();
    $permissions = $user['permissions'] ?? [];
    if (!in_array($permission, $permissions)) {
        errorResponse('权限不足: ' . $permission, 403);
    }
    return $user;
}

/**
 * 检查是否有指定权限
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    $permissions = $user['permissions'] ?? [];
    return in_array($permission, $permissions);
}
