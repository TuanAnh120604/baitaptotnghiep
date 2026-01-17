<?php
include '../include/connect.php';

// Xử lý việc gửi biểu mẫu để xóa kho hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete-warehouse'])) {
    $ma_kho = trim($_POST['delete-warehouse']);

    if (!empty($ma_kho)) {
        try {
            $stmt = $pdo->prepare('DELETE FROM kho WHERE ma_kho = :ma_kho');
            $stmt->execute([':ma_kho' => $ma_kho]);

            // Kiểm tra xem có xóa được không
            if ($stmt->rowCount() > 0) {
                // Redirect để tránh resubmission
                header('Location: danhsachkho.php?status=success&message=' . urlencode('Xóa kho thành công (Mã: ' . $ma_kho . ')'));
                exit();
            } else {
                header('Location: danhsachkho.php?status=error&message=' . urlencode('Không tìm thấy kho để xóa'));
                exit();
            }
        } catch (PDOException $e) {
            // Redirect với lỗi
            header('Location: danhsachkho.php?status=error&message=' . urlencode('Lỗi khi xóa kho: ' . $e->getMessage()));
            exit();
        }
    } else {
        header('Location: danhsachkho.php?status=error&message=' . urlencode('Mã kho không hợp lệ.'));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: danhsachkho.php');
exit();
?>