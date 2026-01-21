<?php
include '../include/connect.php';
include '../include/permissions.php';
include '../include/update_the_kho.php';

header('Content-Type: application/json');

// Chỉ thủ kho mới được xác nhận
$role = trim($_SESSION['role'] ?? '');
if ($role !== 'Thủ kho') {
    echo json_encode(['success' => false, 'error' => 'Chỉ thủ kho mới được xác nhận phiếu.']);
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

$ma_phieu = trim($_POST['ma_phieu'] ?? '');
$loai = trim($_POST['loai'] ?? ''); // 'nhap' hoặc 'xuat'

if (empty($ma_phieu) || empty($loai)) {
    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin phiếu.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($loai === 'nhap') {
        // Kiểm tra phiếu có tồn tại và thuộc kho của thủ kho không
        $check_sql = "
            SELECT pn.ma_phieu_nhap, pn.ma_kho, pn.trang_thai, k.ma_nd
            FROM phieu_nhap pn
            JOIN kho k ON pn.ma_kho = k.ma_kho
            WHERE pn.ma_phieu_nhap = ? AND k.ma_nd = ?
        ";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$ma_phieu, $ma_nd]);
        $phieu = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            throw new Exception('Không tìm thấy phiếu hoặc bạn không có quyền xác nhận phiếu này.');
        }

        if ($phieu['trang_thai'] === 'da_xac_nhan') {
            throw new Exception('Phiếu này đã được xác nhận rồi.');
        }

        // Cập nhật trạng thái phiếu
        $update_sql = "UPDATE phieu_nhap SET trang_thai = 'da_xac_nhan' WHERE ma_phieu_nhap = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$ma_phieu]);

        // Lấy danh sách mã hàng trong phiếu để cập nhật thẻ kho
        $ct_sql = "SELECT DISTINCT ma_hang FROM ct_phieu_nhap WHERE ma_phieu_nhap = ?";
        $ct_stmt = $pdo->prepare($ct_sql);
        $ct_stmt->execute([$ma_phieu]);
        $ds_ma_hang = $ct_stmt->fetchAll(PDO::FETCH_COLUMN);

        // Cập nhật thẻ kho
        if (!empty($ds_ma_hang)) {
            cap_nhat_the_kho_theo_phieu($pdo, $phieu['ma_kho'], $ds_ma_hang);
        }

    } elseif ($loai === 'xuat') {
        // Kiểm tra phiếu có tồn tại và thuộc kho của thủ kho không
        $check_sql = "
            SELECT px.ma_phieu_xuat, px.ma_kho, px.trang_thai, k.ma_nd
            FROM phieu_xuat px
            JOIN kho k ON px.ma_kho = k.ma_kho
            WHERE px.ma_phieu_xuat = ? AND k.ma_nd = ?
        ";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$ma_phieu, $ma_nd]);
        $phieu = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            throw new Exception('Không tìm thấy phiếu hoặc bạn không có quyền xác nhận phiếu này.');
        }

        if ($phieu['trang_thai'] === 'da_xac_nhan') {
            throw new Exception('Phiếu này đã được xác nhận rồi.');
        }

        // Cập nhật trạng thái phiếu
        $update_sql = "UPDATE phieu_xuat SET trang_thai = 'da_xac_nhan' WHERE ma_phieu_xuat = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$ma_phieu]);

        // Lấy danh sách mã hàng trong phiếu để cập nhật thẻ kho
        $ct_sql = "SELECT DISTINCT ma_hang FROM ct_phieu_xuat WHERE ma_phieu_xuat = ?";
        $ct_stmt = $pdo->prepare($ct_sql);
        $ct_stmt->execute([$ma_phieu]);
        $ds_ma_hang = $ct_stmt->fetchAll(PDO::FETCH_COLUMN);

        // Cập nhật thẻ kho
        if (!empty($ds_ma_hang)) {
            cap_nhat_the_kho_theo_phieu($pdo, $phieu['ma_kho'], $ds_ma_hang);
        }
    } else {
        throw new Exception('Loại phiếu không hợp lệ.');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Xác nhận phiếu thành công! Thẻ kho đã được cập nhật.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
