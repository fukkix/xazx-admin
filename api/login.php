<?php
/**
 * 登录接口
 * POST /api/login.php
 * 参数: username, password
 */
try {
    require_once __DIR__ . '/../includes/config.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('请求方式错误');
    }

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        errorResponse('请求体格式错误');
    }

    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? $data['password'] : '';

    if (empty($username) || empty($password)) {
        errorResponse('请输入账号和密码');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, r.label as role_label, r.permissions as role_permissions 
                           FROM users u 
                           JOIN roles r ON u.role_id = r.id 
                           WHERE u.username = ? AND u.status = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        errorResponse('账号不存在');
    }
    if (!password_verify($password, $user['password'])) {
        errorResponse('密码错误');
    }
    
    // 生成 token（兼容 PHP 5.6+）
    if (function_exists('random_bytes')) {
        $token = bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $token = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $token = md5(uniqid(mt_rand(), true) . time());
    }
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $stmt = $pdo->prepare("UPDATE users SET token = ?, token_expires_at = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);
    
    $permissions = json_decode($user['role_permissions'] ?? '[]', true);
    
    successResponse([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'role_label' => $user['role_label'],
        ],
        'permissions' => $permissions,
        'token' => $token,
    ], '登录成功');
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
