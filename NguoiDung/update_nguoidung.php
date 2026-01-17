<?php
include '../include/connect.php';

// Xử lý chỉnh sửa người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $ma_nd = isset($_POST['ma_nd']) ? trim($_POST['ma_nd']) : '';
    $ten_nd = isset($_POST['ten_nd']) ? trim($_POST['ten_nd']) : '';
    $mat_khau = isset($_POST['mat_khau']) ? $_POST['mat_khau'] : '';
    $ma_vai_tro = isset($_POST['ma_vai_tro']) ? trim($_POST['ma_vai_tro']) : '';

    // Validation
    if (empty($ma_nd) || empty($ten_nd) || empty($ma_vai_tro)) {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Vui lòng điền đầy đủ thông tin'));
        exit();
    }

    try {
        // Kiểm tra ma_vai_tro có tồn tại trong table vai_tro không
        $check_role_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM vai_tro WHERE ma_vai_tro = :ma_vai_tro');
        $check_role_stmt->execute([':ma_vai_tro' => $ma_vai_tro]);
        $role_exists = $check_role_stmt->fetch(PDO::FETCH_ASSOC);

        if ($role_exists['count'] == 0) {
            header('Location: nguoidung.php?status=error&message=' . urlencode('Vai trò không hợp lệ'));
            exit();
        }

        // Nếu có mật khẩu mới, hash nó
        if (!empty($mat_khau)) {
            $hashed_password = password_hash($mat_khau, PASSWORD_BCRYPT);
            $update_stmt = $pdo->prepare('UPDATE nguoi_dung SET ten_nd = :ten_nd, mat_khau = :mat_khau, ma_vai_tro = :ma_vai_tro WHERE ma_nd = :ma_nd');
            $update_stmt->execute([
                ':ma_nd' => $ma_nd,
                ':ten_nd' => $ten_nd,
                ':mat_khau' => $hashed_password,
                ':ma_vai_tro' => $ma_vai_tro
            ]);
        } else {
            // Không cập nhật mật khẩu nếu để trống
            $update_stmt = $pdo->prepare('UPDATE nguoi_dung SET ten_nd = :ten_nd, ma_vai_tro = :ma_vai_tro WHERE ma_nd = :ma_nd');
            $update_stmt->execute([
                ':ma_nd' => $ma_nd,
                ':ten_nd' => $ten_nd,
                ':ma_vai_tro' => $ma_vai_tro
            ]);
        }

        // Redirect để tránh resubmission
        header('Location: nguoidung.php?status=success&message=' . urlencode('Cập nhật người dùng thành công (Mã: ' . $ma_nd . ')'));
        exit();
    } catch (Exception $e) {
        // Redirect với lỗi
        header('Location: nguoidung.php?status=error&message=' . urlencode('Lỗi: ' . $e->getMessage()));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: nguoidung.php');
exit();
?>