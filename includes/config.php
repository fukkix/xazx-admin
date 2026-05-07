<?php
/**
 * 数据库配置
 */

// 跨域设置（开发环境）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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
]);

/**
 * 产品分类
 */
$PRODUCTS = [
    ['id' => 1, 'name' => '网站监测系统', 'code' => 'website_monitor'],
    ['id' => 2, 'name' => 'WAF', 'code' => 'waf'],
    ['id' => 3, 'name' => '动态防御', 'code' => 'dynamic_defense'],
    ['id' => 4, 'name' => '全流量威胁分析系统', 'code' => 'traffic_analysis'],
    ['id' => 5, 'name' => 'API功能模块', 'code' => 'api_module'],
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
