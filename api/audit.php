<?php
/**
 * 文件审核接口
 * POST /api/audit.php
 * 参数: id, status(pending/approved/rejected)
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('请求方式错误');
}

requirePermission('product.audit');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : 0;
$status = isset($data['status']) ? trim($data['status']) : '';

if ($id <= 0 || !in_array($status, ['approved', 'rejected'])) {
    errorResponse('参数错误');
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE files SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    $msg = $status === 'approved' ? '审核通过' : '已拒绝';
    successResponse(null, $msg);
} catch (PDOException $e) {
    errorResponse('操作失败: ' . $e->getMessage(), 500);
}
