<?php
/**
 * 用户管理接口
 * GET /api/users.php    获取用户列表
 * POST /api/users.php   创建用户
 * PUT /api/users.php    更新用户
 * DELETE /api/users.php 删除/禁用用户
 */
require_once __DIR__ . '/../includes/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    requirePermission('account.view');
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT u.id, u.username, u.name, u.email, u.role_id, u.status, u.created_at, r.name as role_name, r.label as role_label 
                             FROM users u 
                             JOIN roles r ON u.role_id = r.id 
                             ORDER BY u.id");
        $users = $stmt->fetchAll();
        successResponse($users);
    } catch (PDOException $e) {
        errorResponse('查询失败: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST') {
    requirePermission('account.create');
    $data = json_decode(file_get_contents('php://input'), true);
    $username = isset($data['username']) ? trim($data['username']) : '';
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $role_id = isset($data['role_id']) ? intval($data['role_id']) : 0;
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (empty($username) || empty($name) || $role_id <= 0) {
        errorResponse('账号、姓名和角色不能为空');
    }
    if (empty($password)) {
        $password = 'xazx123456'; // 默认密码
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, email, role_id, status) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $email, $role_id]);
        successResponse(['id' => $pdo->lastInsertId()], '用户创建成功');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            errorResponse('账号已存在');
        }
        errorResponse('创建失败: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT') {
    requirePermission('account.edit');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $role_id = isset($data['role_id']) ? intval($data['role_id']) : 0;
    $status = isset($data['status']) ? intval($data['status']) : -1;
    $password = isset($data['password']) ? $data['password'] : '';
    
    if ($id <= 0) {
        errorResponse('参数错误');
    }
    
    try {
        $pdo = getDB();
        $fields = [];
        $params = [];
        
        if (!empty($name)) {
            $fields[] = 'name = ?';
            $params[] = $name;
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $params[] = $email;
        }
        if ($role_id > 0) {
            $fields[] = 'role_id = ?';
            $params[] = $role_id;
        }
        if ($status >= 0) {
            $fields[] = 'status = ?';
            $params[] = $status;
        }
        if (!empty($password)) {
            $fields[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            errorResponse('没有要更新的字段');
        }
        
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
        successResponse(null, '用户更新成功');
    } catch (PDOException $e) {
        errorResponse('更新失败: ' . $e->getMessage(), 500);
    }
}

if ($method === 'DELETE') {
    requirePermission('account.delete');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : 0;
    
    if ($id <= 0) {
        errorResponse('参数错误');
    }
    
    // 不允许删除自己
    $currentUser = requireAuth();
    if ($currentUser['id'] == $id) {
        errorResponse('不能删除当前登录账号');
    }
    
    try {
        $pdo = getDB();
        // 软删除：将状态设为禁用
        $stmt = $pdo->prepare("UPDATE users SET status = 0, token = NULL, token_expires_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        successResponse(null, '账号已禁用');
    } catch (PDOException $e) {
        errorResponse('操作失败: ' . $e->getMessage(), 500);
    }
}

errorResponse('请求方式不支持', 405);
