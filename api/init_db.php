<?php
/**
 * 初始化数据库（首次运行）
 */
require_once __DIR__ . '/../includes/config.php';

try {
    // 先连接不指定数据库，创建数据库
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    // 创建产品表
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT '产品名称',
        code VARCHAR(50) NOT NULL UNIQUE COMMENT '产品代码',
        sort_order INT DEFAULT 0 COMMENT '排序',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='产品分类'");
    
    // 创建文件分类表
    $pdo->exec("CREATE TABLE IF NOT EXISTS file_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT '分类名称',
        code VARCHAR(50) NOT NULL UNIQUE COMMENT '分类代码',
        sort_order INT DEFAULT 0 COMMENT '排序',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件分类'");
    
    // 创建文件表
    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL COMMENT '文件标题',
        product_id INT NOT NULL COMMENT '产品ID',
        category_id INT NOT NULL COMMENT '文件分类ID',
        file_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
        file_path VARCHAR(500) NOT NULL COMMENT '存储路径',
        file_size BIGINT DEFAULT 0 COMMENT '文件大小(字节)',
        file_type VARCHAR(50) COMMENT '文件类型',
        description TEXT COMMENT '文件描述',
        download_count INT DEFAULT 0 COMMENT '下载次数',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES file_categories(id) ON DELETE CASCADE,
        INDEX idx_product (product_id),
        INDEX idx_category (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件列表'");
    
    // 插入产品数据
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (id, name, code, sort_order) VALUES (?, ?, ?, ?)");
    $products = [
        [1, '网站监测系统', 'website_monitor', 1],
        [2, 'WAF', 'waf', 2],
        [3, '动态防御', 'dynamic_defense', 3],
        [4, '全流量威胁分析系统', 'traffic_analysis', 4],
        [5, 'API功能模块', 'api_module', 5],
    ];
    foreach ($products as $p) {
        $stmt->execute($p);
    }
    
    // 插入文件分类数据
    $stmt = $pdo->prepare("INSERT IGNORE INTO file_categories (id, name, code, sort_order) VALUES (?, ?, ?, ?)");
    $categories = [
        [1, '白皮书', 'whitepaper', 1],
        [2, '操作手册', 'manual', 2],
        [3, '功能清单', 'feature_list', 3],
        [4, '功能说明', 'feature_desc', 4],
        [5, '宣传PPT', 'promo_ppt', 5],
        [6, '宣传折页', 'promo_brochure', 6],
        [7, 'FQA', 'fqa', 7],
        [8, '相关图片资料', 'images', 8],
    ];
    foreach ($categories as $c) {
        $stmt->execute($c);
    }
    
    successResponse(null, '数据库初始化成功');
} catch (PDOException $e) {
    errorResponse('数据库初始化失败: ' . $e->getMessage(), 500);
}
