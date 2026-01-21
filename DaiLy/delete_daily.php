<?php
include '../include/connect.php';

// Xử lý xóa đại lý
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_agent') {
    $ma_dai_ly = trim($_POST['ma_dai_ly'] ?? '');

    // Validation
    if (empty($ma_dai_ly)) {
        header('Location: daily.php?status=error&message=' . urlencode('Mã đại lý không hợp lệ'));
        exit();
    }

    try {
        // Xóa đại lý từ database
        $stmt = $pdo->prepare('DELETE FROM dai_ly WHERE ma_dai_ly = :ma_dai_ly');
        $stmt->execute([':ma_dai_ly' => $ma_dai_ly]);

        // Kiểm tra xem có xóa được không
        if ($stmt->rowCount() > 0) {
            // Redirect để tránh resubmission
            header('Location: daily.php?status=success&message=' . urlencode('Xóa đại lý thành công (Mã: ' . $ma_dai_ly . ')'));
            exit();
        } else {
            header('Location: daily.php?status=error&message=' . urlencode('Không tìm thấy đại lý để xóa'));
            exit();
        }
    } catch (Exception $e) {
        // Redirect với lỗi
        header('Location: daily.php?status=error&message=' . urlencode('Lỗi khi xóa đại lý: ' . $e->getMessage()));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: daily.php');
exit();
?>