<?php
include '../include/connect.php';

// Xử lý việc gửi biểu mẫu để xóa kho hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete-warehouse'])) {
    $ma_kho = trim($_POST['delete-warehouse']);

    // Lấy các parameter để redirect về trang cũ
    $redirect_page = $_POST['redirect_page'] ?? '1';
    $redirect_search = $_POST['redirect_search'] ?? '';
    $redirect_filter = $_POST['redirect_filter'] ?? '';

    // Xây dựng URL redirect
    $redirect_url = 'danhsachkho.php?page=' . urlencode($redirect_page);
    $redirect_url .= '&status=success&message=' . urlencode('Xóa kho thành công (Mã: ' . $ma_kho . ')');

    if (!empty($redirect_search)) {
        $redirect_url .= '&search=' . urlencode($redirect_search);
    }
    if (!empty($redirect_filter)) {
        $redirect_url .= '&filter_loai_kho=' . urlencode($redirect_filter);
    }

    if (!empty($ma_kho)) {
        try {
            $stmt = $pdo->prepare('DELETE FROM kho WHERE ma_kho = :ma_kho');
            $stmt->execute([':ma_kho' => $ma_kho]);

            // Kiểm tra xem có xóa được không
            if ($stmt->rowCount() > 0) {
                // Redirect về trang cũ
                header('Location: ' . $redirect_url);
                exit();
            } else {
                header('Location: danhsachkho.php?page=' . urlencode($redirect_page) . '&status=error&message=' . urlencode('Không tìm thấy kho để xóa'));
                exit();
            }
        } catch (PDOException $e) {
            // Redirect với lỗi
            header('Location: danhsachkho.php?page=' . urlencode($redirect_page) . '&status=error&message=' . urlencode('Lỗi khi xóa kho: ' . $e->getMessage()));
            exit();
        }
    } else {
        header('Location: danhsachkho.php?page=' . urlencode($redirect_page) . '&status=error&message=' . urlencode('Mã kho không hợp lệ.'));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: danhsachkho.php');
exit();
