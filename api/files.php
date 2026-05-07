<?php
/**
 * 文件列表查询接口
 * GET /api/files.php?product_id=1&category_id=2&keyword=xxx&page=1&size=20
 */
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('请求方式错误');
}

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$size = isset($_GET['size']) ? min(50, max(1, intval($_GET['size']))) : 20;
$offset = ($page - 1) * $size;

try {
    $pdo = getDB();
    
    // 构建查询条件
    $where = [];
    $params = [];
    
    if ($product_id > 0) {
        $where[] = 'f.product_id = ?';
        $params[] = $product_id;
    }
    if ($category_id > 0) {
        $where[] = 'f.category_id = ?';
        $params[] = $category_id;
    }
    if (!empty($keyword)) {
        $where[] = '(f.title LIKE ? OR f.file_name LIKE ? OR f.description LIKE ?)';
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    
    $whereStr = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
    
    // 查询总数
    $countSql = "SELECT COUNT(*) FROM files f $whereStr";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // 查询列表
    $sql = "SELECT f.*, p.name as product_name, c.name as category_name 
            FROM files f 
            LEFT JOIN products p ON f.product_id = p.id 
            LEFT JOIN file_categories c ON f.category_id = c.id 
            $whereStr 
            ORDER BY f.created_at DESC 
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$size, $offset]));
    $list = $stmt->fetchAll();
    
    // 格式化文件大小
    foreach ($list as &$item) {
        $item['file_size_human'] = formatFileSize($item['file_size']);
        $item['created_at'] = date('Y-m-d H:i', strtotime($item['created_at']));
    }
    
    successResponse([
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'size' => $size,
        'pages' => ceil($total / $size)
    ]);
} catch (PDOException $e) {
    errorResponse('查询失败: ' . $e->getMessage(), 500);
}

function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    return round($size, 2) . ' ' . $units[$unitIndex];
}
