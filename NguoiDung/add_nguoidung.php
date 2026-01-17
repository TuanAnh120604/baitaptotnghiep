<?php
include '../include/connect.php';

// Đặt header cho JSON response
header('Content-Type: application/json');

// Xử lý thêm người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $ten_nd = isset($_POST['ten_nd']) ? trim($_POST['ten_nd']) : '';
    $mat_khau = isset($_POST['mat_khau']) ? $_POST['mat_khau'] : '';
    $ma_vai_tro = isset($_POST['ma_vai_tro']) ? trim($_POST['ma_vai_tro']) : '';
    $ma_loai_kho = isset($_POST['ma_loai_kho']) ? trim($_POST['ma_loai_kho']) : '';
    $ma_vung = isset($_POST['ma_vung']) ? trim($_POST['ma_vung']) : '';
    $ma_kho = isset($_POST['ma_kho']) ? trim($_POST['ma_kho']) : '';

    // Validation
    if (empty($ten_nd) || empty($mat_khau) || empty($ma_vai_tro)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        exit();
    }

    // Validation bổ sung cho từng vai trò
    if ($ma_vai_tro === 'VT003' && (empty($ma_loai_kho) || empty($ma_vung))) {
        echo json_encode(['success' => false, 'message' => 'Quản lý kho cần chọn loại kho và vùng miền']);
        exit();
    }

    if ($ma_vai_tro === 'VT004' && empty($ma_kho)) {
        echo json_encode(['success' => false, 'message' => 'Thủ kho cần chọn kho phụ trách']);
        exit();
    }

    // Kiểm tra quy tắc business logic
    if ($ma_vai_tro === 'VT003') {
        // Kiểm tra xem đã có quản lý kho nào quản lý cùng loại kho và vùng miền chưa
        $check_quan_ly_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM phan_quyen WHERE ma_loai_kho = :ma_loai_kho AND ma_vung = :ma_vung');
        $check_quan_ly_stmt->execute([':ma_loai_kho' => $ma_loai_kho, ':ma_vung' => $ma_vung]);
        $result_quan_ly = $check_quan_ly_stmt->fetch(PDO::FETCH_ASSOC);

        if ($result_quan_ly['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Đã có quản lý kho phụ trách loại kho này tại vùng miền này.']);
            exit();
        }
    }

    if ($ma_vai_tro === 'VT004') {
        // Kiểm tra xem kho đã có thủ kho chưa
        $check_thu_kho_stmt = $pdo->prepare('SELECT ma_nd FROM kho WHERE ma_kho = :ma_kho');
        $check_thu_kho_stmt->execute([':ma_kho' => $ma_kho]);
        $result_thu_kho = $check_thu_kho_stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($result_thu_kho['ma_nd'])) {
            echo json_encode(['success' => false, 'message' => 'Kho này đã có thủ kho phụ trách.']);
            exit();
        }
    }

    try {
        // Tạo mã người dùng tự động (ví dụ: ND001, ND002, ...)
        $stmt = $pdo->query('SELECT MAX(CAST(SUBSTRING(ma_nd, 3) AS UNSIGNED)) as max_id FROM nguoi_dung WHERE ma_nd LIKE "ND%"');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_id = ($result['max_id'] ?? 0) + 1;
        $ma_nd = 'ND' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        // Hash mật khẩu
        $hashed_password = password_hash($mat_khau, PASSWORD_BCRYPT);

        // Insert vào database
        $insert_stmt = $pdo->prepare('INSERT INTO nguoi_dung (ma_nd, ten_nd, mat_khau, ma_vai_tro) VALUES (:ma_nd, :ten_nd, :mat_khau, :ma_vai_tro)');
        $insert_stmt->execute([
            ':ma_nd' => $ma_nd,
            ':ten_nd' => $ten_nd,
            ':mat_khau' => $hashed_password,
            ':ma_vai_tro' => $ma_vai_tro
        ]);

        // Thêm quyền nếu là Quản lý kho
        if ($ma_vai_tro === 'VT003') {
            // Tạo mã quyền tự động
            $stmt = $pdo->query('SELECT MAX(CAST(SUBSTRING(ma_quyen, 4) AS UNSIGNED)) as max_id FROM phan_quyen WHERE ma_quyen LIKE "PQ%"');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = ($result['max_id'] ?? 0) + 1;
            $ma_quyen = 'PQ' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

            $phan_quyen_stmt = $pdo->prepare('INSERT INTO phan_quyen (ma_quyen, ma_nd, ma_vung, ma_loai_kho) VALUES (:ma_quyen, :ma_nd, :ma_vung, :ma_loai_kho)');
            $phan_quyen_stmt->execute([
                ':ma_quyen' => $ma_quyen,
                ':ma_nd' => $ma_nd,
                ':ma_vung' => $ma_vung,
                ':ma_loai_kho' => $ma_loai_kho
            ]);
        }

        // Thêm kho cho Thủ kho (cập nhật bảng kho)
        if ($ma_vai_tro === 'VT004') {
            $update_kho_stmt = $pdo->prepare('UPDATE kho SET ma_nd = :ma_nd WHERE ma_kho = :ma_kho');
            $update_kho_stmt->execute([
                ':ma_nd' => $ma_nd,
                ':ma_kho' => $ma_kho
            ]);
        }

        // Trả về response thành công
        echo json_encode([
            'success' => true,
            'message' => 'Thêm người dùng thành công (Mã: ' . $ma_nd . ')',
            'ma_nd' => $ma_nd
        ]);
        exit();
    } catch (Exception $e) {
        // Trả về response lỗi
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'add_user') {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ']);
    exit();
}
?>