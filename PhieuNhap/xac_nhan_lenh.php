<?php
include '../include/connect.php';
include '../include/permissions.php';

header('Content-Type: application/json');

// Chỉ Thủ kho hoặc Quản lý kho mới được xác nhận
$role = trim($_SESSION['role'] ?? '');
if ($role !== 'Thủ kho' && $role !== 'Quản lý kho') {
    echo json_encode(['success' => false, 'error' => 'Chỉ Thủ kho hoặc Quản lý kho mới được xác nhận lệnh giao.']);
    exit;
}

$ma_nd = $_SESSION['MaND'] ?? null;
if (!$ma_nd) {
    echo json_encode(['success' => false, 'error' => 'Không xác định được người dùng.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Phương thức không hợp lệ.']);
    exit;
}

$ma_lenh = trim($_POST['ma_lenh'] ?? '');
if (empty($ma_lenh)) {
    echo json_encode(['success' => false, 'error' => 'Thiếu mã lệnh kho.']);
    exit;
}

try {
    // Kiểm tra tồn tại lệnh
    $stmt = $pdo->prepare("SELECT MaLenh, TrangThai FROM lenhkho WHERE MaLenh = ?");
    $stmt->execute([$ma_lenh]);
    $phieu = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$phieu) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy lệnh kho.']);
        exit;
    }

    if ($phieu['TrangThai'] === 'da_xac_nhan') {
        echo json_encode(['success' => false, 'error' => 'Lệnh kho này đã được xác nhận trước đó.']);
        exit;
    }

    // Cập nhật trạng thái
    $update = $pdo->prepare("UPDATE lenhkho SET TrangThai = 'da_xac_nhan' WHERE MaLenh = ?");
    $update->execute([$ma_lenh]);

    echo json_encode(['success' => true, 'message' => 'Xác nhận lệnh giao thành công.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
