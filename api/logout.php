<?php
/**
 * 登出接口
 * POST /api/logout.php
 */
require_once __DIR__ . '/../includes/config.php';

$user = getCurrentUser();
if ($user) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE users SET token = NULL, token_expires_at = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
    } catch (PDOException $e) {
        // ignore
    }
}
successResponse(null, '已退出登录');
