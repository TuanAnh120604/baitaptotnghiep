<?php
include '../include/connect.php';

// Xử lý xóa nhà cung cấp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_supplier') {
    $ma_ncc = trim($_POST['ma_ncc'] ?? '');

    // Validation
    if (empty($ma_ncc)) {
        header('Location: nhacungcap.php?status=error&message=' . urlencode('Mã nhà cung cấp không hợp lệ'));
        exit();
    }

    try {
        // Xóa nhà cung cấp từ database
        $stmt = $pdo->prepare('DELETE FROM nha_cung_cap WHERE ma_ncc = :ma_ncc');
        $stmt->execute([':ma_ncc' => $ma_ncc]);

        // Kiểm tra xem có xóa được không
        if ($stmt->rowCount() > 0) {
            // Redirect để tránh resubmission
            header('Location: nhacungcap.php?status=success&message=' . urlencode('Xóa nhà cung cấp thành công (Mã: ' . $ma_ncc . ')'));
            exit();
        } else {
            header('Location: nhacungcap.php?status=error&message=' . urlencode('Không tìm thấy nhà cung cấp để xóa'));
            exit();
        }
    } catch (Exception $e) {
        // Redirect với lỗi
        header('Location: nhacungcap.php?status=error&message=' . urlencode('Lỗi: ' . $e->getMessage()));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: nhacungcap.php');
exit();
?>