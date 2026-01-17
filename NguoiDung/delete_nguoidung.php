<?php
include '../include/connect.php';

// Xử lý xóa người dùng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $ma_nd = isset($_POST['ma_nd']) ? trim($_POST['ma_nd']) : '';

    // Validation
    if (empty($ma_nd)) {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Mã người dùng không hợp lệ'));
        exit();
    }

    try {
        // Xóa người dùng từ database
        $delete_stmt = $pdo->prepare('DELETE FROM nguoi_dung WHERE ma_nd = :ma_nd');
        $delete_stmt->execute([
            ':ma_nd' => $ma_nd
        ]);

        // Kiểm tra xem có xóa được không
        if ($delete_stmt->rowCount() > 0) {
            // Redirect để tránh resubmission
            header('Location: nguoidung.php?status=success&message=' . urlencode('Xóa người dùng thành công (Mã: ' . $ma_nd . ')'));
            exit();
        } else {
            header('Location: nguoidung.php?status=error&message=' . urlencode('Không tìm thấy người dùng để xóa'));
            exit();
        }
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