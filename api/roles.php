<?php
/**
 * 角色管理接口
 * GET /api/roles.php    获取角色列表
 * POST /api/roles.php   创建角色
 * PUT /api/roles.php    更新角色
 * DELETE /api/roles.php 删除角色
 */
require_once __DIR__ . '/../includes/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 获取角色列表
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT id, name, label, description, is_system, permissions, created_at FROM roles ORDER BY id");
        $roles = $stmt->fetchAll();
        foreach ($roles as &$r) {
            $r['permissions'] = json_decode($r['permissions'] ?? '[]', true);
        }
        successResponse($roles);
    } catch (PDOException $e) {
        errorResponse('查询失败: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST') {
    requirePermission('role.manage');
    $data = json_decode(file_get_contents('php://input'), true);
    $name = isset($data['name']) ? trim($data['name']) : '';
    $label = isset($data['label']) ? trim($data['label']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $permissions = isset($data['permissions']) ? $data['permissions'] : [];
    
    if (empty($name) || empty($label)) {
        errorResponse('角色标识和显示名不能为空');
    }
    if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        errorResponse('角色标识只能包含小写字母、数字和下划线');
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO roles (name, label, description, is_system, permissions) VALUES (?, ?, ?, 0, ?)");
        $stmt->execute([$name, $label, $description, json_encode($permissions)]);
        successResponse(['id' => $pdo->lastInsertId()], '角色创建成功');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            errorResponse('角色标识已存在');
        }
        errorResponse('创建失败: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT') {
    requirePermission('role.manage');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : 0;
    $label = isset($data['label']) ? trim($data['label']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $permissions = isset($data['permissions']) ? $data['permissions'] : [];
    
    if ($id <= 0) {
        errorResponse('参数错误');
    }
    
    try {
        $pdo = getDB();
        // 检查是否是系统内置角色
        $stmt = $pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        if (!$role) {
            errorResponse('角色不存在');
        }
        
        $stmt = $pdo->prepare("UPDATE roles SET label = ?, description = ?, permissions = ? WHERE id = ?");
        $stmt->execute([$label, $description, json_encode($permissions), $id]);
        successResponse(null, '角色更新成功');
    } catch (PDOException $e) {
        errorResponse('更新失败: ' . $e->getMessage(), 500);
    }
}

if ($method === 'DELETE') {
    requirePermission('role.manage');
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? intval($data['id']) : 0;
    
    if ($id <= 0) {
        errorResponse('参数错误');
    }
    
    try {
        $pdo = getDB();
        // 检查是否是系统内置角色
        $stmt = $pdo->prepare("SELECT is_system FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();
        if (!$role) {
            errorResponse('角色不存在');
        }
        if ($role['is_system']) {
            errorResponse('系统内置角色不可删除');
        }
        // 检查是否有用户在使用该角色
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            errorResponse('该角色下仍有用户，无法删除');
        }
        
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        successResponse(null, '角色删除成功');
    } catch (PDOException $e) {
        errorResponse('删除失败: ' . $e->getMessage(), 500);
    }
}

errorResponse('请求方式不支持', 405);
