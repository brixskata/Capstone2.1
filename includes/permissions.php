<?php
// Permission helper functions for role-based access control

/**
 * Check if current user has a specific permission
 * Checks both role-based permissions and individual user permissions
 */
function hasPermission(PDO $pdo, $permission_name, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Super Admin always has all permissions
    if (isSuperAdmin($pdo, $user_id)) {
        return true;
    }
    
    try {
        // Check role-based permissions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            JOIN users u ON rp.usertype_id = u.usertype_id
            WHERE u.user_id = ? AND p.permission_name = ?
        ");
        $stmt->execute([$user_id, $permission_name]);
        $hasRolePermission = $stmt->fetchColumn() > 0;
        
        if ($hasRolePermission) {
            return true;
        }
        
        // Check individual user permissions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.permission_id
            WHERE up.user_id = ? AND p.permission_name = ?
        ");
        $stmt->execute([$user_id, $permission_name]);
        return $stmt->fetchColumn() > 0;
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if current user is Super Admin
 */
function isSuperAdmin(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users u
            JOIN user_type ut ON u.usertype_id = ut.usertype_id
            WHERE u.user_id = ? AND ut.role = 'super_admin'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if current user is Admin (but not Super Admin)
 */
function isAdmin(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users u
            JOIN user_type ut ON u.usertype_id = ut.usertype_id
            WHERE u.user_id = ? AND ut.role = 'admin'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if current user is Customer
 */
function isCustomer(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users u
            JOIN user_type ut ON u.usertype_id = ut.usertype_id
            WHERE u.user_id = ? AND ut.role = 'customer'
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get user's role name
 */
function getUserRole(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return 'guest';
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT ut.role 
            FROM users u
            JOIN user_type ut ON u.usertype_id = ut.usertype_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['role'] : 'guest';
    } catch (Exception $e) {
        return 'guest';
    }
}

/**
 * Require permission - redirect if user doesn't have permission
 */
function requirePermission(PDO $pdo, $permission_name, $redirect_url = 'login.php') {
    if (!hasPermission($pdo, $permission_name)) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Require Super Admin access
 */
function requireSuperAdmin(PDO $pdo, $redirect_url = 'login.php') {
    if (!isSuperAdmin($pdo)) {
        $_SESSION['error'] = "This page requires Super Admin access.";
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Require Admin access (Super Admin or Admin)
 */
function requireAdmin(PDO $pdo, $redirect_url = 'login.php') {
    if (!isSuperAdmin($pdo) && !isAdmin($pdo)) {
        $_SESSION['error'] = "This page requires Admin access.";
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Get all permissions for a user (both role-based and individual)
 */
function getUserPermissions(PDO $pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return [];
    }
    
    try {
        // Get role-based permissions
        $stmt = $pdo->prepare("
            SELECT p.permission_name, p.description, p.module
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            JOIN users u ON rp.usertype_id = u.usertype_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $rolePermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get individual user permissions
        $stmt = $pdo->prepare("
            SELECT p.permission_name, p.description, p.module
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.permission_id
            WHERE up.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $userPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge and deduplicate
        $allPermissions = array_merge($rolePermissions, $userPermissions);
        $uniquePermissions = [];
        foreach ($allPermissions as $perm) {
            $uniquePermissions[$perm['permission_name']] = $perm;
        }
        
        return array_values($uniquePermissions);
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get user permissions grouped by module
 */
function getUserPermissionsByModule(PDO $pdo, $user_id = null) {
    $permissions = getUserPermissions($pdo, $user_id);
    $grouped = [];
    
    foreach ($permissions as $perm) {
        $module = $perm['module'] ?? 'system';
        if (!isset($grouped[$module])) {
            $grouped[$module] = [];
        }
        $grouped[$module][] = $perm;
    }
    
    return $grouped;
}

/**
 * Check if user has access to a specific module
 */
function hasModuleAccess(PDO $pdo, $module, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Super Admin has access to all modules
    if (isSuperAdmin($pdo, $user_id)) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM (
                SELECT p.permission_id
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.permission_id
                JOIN users u ON rp.usertype_id = u.usertype_id
                WHERE u.user_id = ? AND p.module = ?
                
                UNION
                
                SELECT p.permission_id
                FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.permission_id
                WHERE up.user_id = ? AND p.module = ?
            ) as combined_permissions
        ");
        $stmt->execute([$user_id, $module, $user_id, $module]);
        return $stmt->fetchColumn() > 0;
        
    } catch (Exception $e) {
        return false;
    }
}
?>
