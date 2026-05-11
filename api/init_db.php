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
    
    // 创建角色表
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE COMMENT '角色标识',
        label VARCHAR(100) NOT NULL COMMENT '角色显示名',
        description TEXT COMMENT '角色描述',
        is_system TINYINT DEFAULT 0 COMMENT '1=系统内置不可删除',
        permissions JSON COMMENT '权限列表',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表'");
    
    // 创建用户表
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE COMMENT '登录账号',
        password VARCHAR(255) NOT NULL COMMENT '密码哈希',
        name VARCHAR(100) NOT NULL COMMENT '姓名',
        email VARCHAR(100) COMMENT '邮箱',
        role_id INT NOT NULL COMMENT '角色ID',
        status TINYINT DEFAULT 1 COMMENT '1=启用, 0=禁用',
        token VARCHAR(255) COMMENT '登录令牌',
        token_expires_at DATETIME COMMENT '令牌过期时间',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表'");
    
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
        status ENUM('pending','approved','rejected') DEFAULT 'pending' COMMENT '审核状态',
        uploaded_by INT DEFAULT NULL COMMENT '上传者用户ID',
        download_count INT DEFAULT 0 COMMENT '下载次数',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES file_categories(id) ON DELETE CASCADE,
        INDEX idx_product (product_id),
        INDEX idx_category (category_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件列表'");
    
    // 检查并添加 files 表的 status 和 uploaded_by 列（兼容已存在表）
    $cols = $pdo->query("SHOW COLUMNS FROM files LIKE 'status'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE files ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending' COMMENT '审核状态'");
    }
    $cols = $pdo->query("SHOW COLUMNS FROM files LIKE 'uploaded_by'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE files ADD COLUMN uploaded_by INT DEFAULT NULL COMMENT '上传者用户ID'");
        $pdo->exec("ALTER TABLE files ADD INDEX idx_uploaded_by (uploaded_by)");
    }
    
    // 插入默认角色
    global $ALL_PERMISSIONS;
    $allPerms = array_keys($ALL_PERMISSIONS);
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (id, name, label, description, is_system, permissions) VALUES (?, ?, ?, ?, ?, ?)");
    $roles = [
        [1, 'super_admin', '系统超管', '系统内置超级管理员，拥有所有权限', 1, json_encode($allPerms)],
        [2, 'admin', '管理员', '可管理资料、审核、用户，但不能管理角色', 1, json_encode(['product.view','product.upload','product.audit','product.delete','wiki.view','wiki.edit','wiki.audit','account.view','account.create','account.edit','account.delete','system.log'])],
        [3, 'uploader', '资料上传员', '可上传资料和编辑知识库', 1, json_encode(['product.view','product.upload','wiki.view','wiki.edit'])],

    ];
    foreach ($roles as $r) {
        $stmt->execute($r);
    }
    
    // 插入默认超管账号
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (id, username, password, name, email, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'super', password_hash('super123', PASSWORD_DEFAULT), '系统超管', 'super@xazx.com', 1, 1]);
    $stmt->execute([2, 'admin', password_hash('admin123', PASSWORD_DEFAULT), '管理员', 'admin@xazx.com', 2, 1]);
    
    // 插入产品数据
    $stmt = $pdo->prepare("INSERT IGNORE INTO products (id, name, code, sort_order) VALUES (?, ?, ?, ?)");
    $products = [
        [1, '网站监测系统', 'website_monitor', 1],
        [2, 'WAF', 'waf', 2],
        [3, '动态防御', 'dynamic_defense', 3],
        [4, '全流量分析', 'traffic_analysis', 4],
        [5, 'API模块', 'api_module', 5],
        [6, '大模型安全', 'llm_security', 6],
        [7, '其他', 'other', 7],
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
