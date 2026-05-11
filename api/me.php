<?php
/**
 * 获取当前登录用户信息
 * GET /api/me.php
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('请求方式错误');
}

$user = getCurrentUser();
if (!$user) {
    errorResponse('未登录', 401);
}

successResponse($user);
