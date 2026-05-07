<?php
/**
 * 删除文件接口
 * POST /api/delete.php
 * 参数: id
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('请求方式错误');
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : 0;

if ($id <= 0) {
    errorResponse('参数错误');
}

try {
    $pdo = getDB();
    
    // 先查询文件路径
    $stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    
    if (!$file) {
        errorResponse('文件不存在');
    }
    
    // 删除物理文件
    $fullPath = __DIR__ . '/../' . $file['file_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    
    // 删除数据库记录
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse(null, '删除成功');
} catch (PDOException $e) {
    errorResponse('删除失败: ' . $e->getMessage(), 500);
}
