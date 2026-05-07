<?php
/**
 * 文件上传接口
 * POST /api/upload.php
 * 参数: product_id, category_id, title, description, file
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('请求方式错误');
}

// 获取参数
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

// 验证参数
if ($product_id <= 0) {
    errorResponse('请选择产品分类');
}
if ($category_id <= 0) {
    errorResponse('请选择文件分类');
}
if (empty($title)) {
    errorResponse('请输入文件标题');
}
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $error = isset($_FILES['file']) ? $_FILES['file']['error'] : '未上传文件';
    errorResponse('文件上传失败: ' . $error);
}

$file = $_FILES['file'];

// 检查文件大小
if ($file['size'] > MAX_FILE_SIZE) {
    errorResponse('文件大小超过限制（最大50MB）');
}

// 检查文件类型
$mimeType = $file['type'];
if (!isset(ALLOWED_TYPES[$mimeType])) {
    errorResponse('不支持的文件类型: ' . $mimeType);
}

$ext = ALLOWED_TYPES[$mimeType];

// 创建存储目录
$productDir = UPLOAD_DIR . $product_id . '/';
$categoryDir = $productDir . $category_id . '/';
if (!is_dir($categoryDir)) {
    mkdir($categoryDir, 0755, true);
}

// 生成唯一文件名
$uniqueName = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
$targetPath = $categoryDir . $uniqueName;
$relativePath = 'uploads/' . $product_id . '/' . $category_id . '/' . $uniqueName;

// 移动文件
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    errorResponse('文件保存失败');
}

// 写入数据库
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO files (title, product_id, category_id, file_name, file_path, file_size, file_type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $title,
        $product_id,
        $category_id,
        $file['name'],
        $relativePath,
        $file['size'],
        $ext,
        $description
    ]);
    
    $fileId = $pdo->lastInsertId();
    successResponse([
        'id' => $fileId,
        'title' => $title,
        'file_name' => $file['name'],
        'file_path' => $relativePath,
        'file_size' => $file['size'],
        'file_type' => $ext
    ], '上传成功');
} catch (PDOException $e) {
    // 删除已上传的文件
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    errorResponse('数据库操作失败: ' . $e->getMessage(), 500);
}
