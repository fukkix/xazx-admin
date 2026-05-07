<?php
/**
 * 获取分类数据
 * GET /api/categories.php
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('请求方式错误');
}

try {
    $pdo = getDB();
    
    // 获取产品列表
    $stmt = $pdo->query("SELECT id, name, code FROM products ORDER BY sort_order");
    $products = $stmt->fetchAll();
    
    // 获取文件分类列表
    $stmt = $pdo->query("SELECT id, name, code FROM file_categories ORDER BY sort_order");
    $categories = $stmt->fetchAll();
    
    successResponse([
        'products' => $products,
        'file_categories' => $categories
    ]);
} catch (PDOException $e) {
    errorResponse('查询失败: ' . $e->getMessage(), 500);
}
