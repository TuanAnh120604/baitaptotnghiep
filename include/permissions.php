<?php
// Permissions logic
$role = trim($_SESSION['role'] ?? null);
$permissions = [
    'Thủ kho' => [
        'thongke' => ['view'],
        'loaikho' => ['view'],
        'danhsachkho' => ['view'],
        'phieunhap' => ['view'],
        'phieuxuat' => ['view'],
        'thekho' => ['view'],
        'vattu' => ['view'],
        'thanhpham' => ['view'],
        'daily' => ['view'],
        'nhacungcap' => ['view'],
        'caidat' => ['view'],
    ],
    'Quản lý kho' => [
        'thongke' => ['view'],
        'loaikho' => ['view'],
        'danhsachkho' => ['view'],
        'phieunhap' => ['view', 'create', 'edit', 'delete'],
        'phieuxuat' => ['view', 'create', 'edit', 'delete'],
        'thekho' => ['view'],
        'vattu' => ['view', 'create', 'edit'],
        'thanhpham' => ['view', 'create', 'edit'],
        'daily' => ['view', 'create', 'edit', 'delete'],
        'nhacungcap' => ['view', 'create', 'edit', 'delete'],
        'caidat' => ['view'],
        'nguoidung' => ['view'],
    ],
    'Ban giám đốc' => [
        '*' => ['view']
    ],
    'Admin' => [
        '*' => ['view', 'create', 'edit', 'delete']
    ]
];

function canView($page) {
    global $permissions, $role;
    $rolePerms = $permissions[$role] ?? [];
    if (isset($rolePerms['*'])) {
        return in_array('view', $rolePerms['*']);
    }
    return isset($rolePerms[$page]) && in_array('view', $rolePerms[$page]);
}

function canCreate($page) {
    global $permissions, $role;
    $rolePerms = $permissions[$role] ?? [];
    if (isset($rolePerms['*'])) {
        return in_array('create', $rolePerms['*']);
    }
    return isset($rolePerms[$page]) && in_array('create', $rolePerms[$page]);
}

function canEdit($page) {
    global $permissions, $role;
    $rolePerms = $permissions[$role] ?? [];
    if (isset($rolePerms['*'])) {
        return in_array('edit', $rolePerms['*']);
    }
    return isset($rolePerms[$page]) && in_array('edit', $rolePerms[$page]);
}

function canDelete($page) {
    global $permissions, $role;
    $rolePerms = $permissions[$role] ?? [];
    if (isset($rolePerms['*'])) {
        return in_array('delete', $rolePerms['*']);
    }
    return isset($rolePerms[$page]) && in_array('delete', $rolePerms[$page]);
}

function checkAccess($page) {
    global $role;
    if (!$role) {
        header('Location: /baitaptotnghiep-main/dn/login.php');
        exit;
    }
    if (!canView($page)) {
        echo "<div class='flex items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900'>
                <div class='text-center'>
                    <h1 class='text-2xl font-bold text-red-600 dark:text-red-400 mb-4'>Truy cập bị từ chối</h1>
                    <p class='text-gray-700 dark:text-gray-300'>Bạn không đủ quyền để truy cập trang này.</p>
                    <p class='text-sm text-gray-500'>Role: " . htmlspecialchars($role) . "</p>
                </div>
              </div>";
        exit;
    }
}

function getUserPermissions() {
    global $permissions, $role;
    return $permissions[$role] ?? [];
}
?>