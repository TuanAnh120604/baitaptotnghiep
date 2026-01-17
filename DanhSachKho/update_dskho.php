<?php
include '../include/connect.php';

// Xử lý việc gửi biểu mẫu để cập nhật kho hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update-warehouse'])) {
    $ma_kho = trim($_POST['ma_kho'] ?? '');
    $ten_kho = trim($_POST['warehouse-name'] ?? '');
    $dia_chi = trim($_POST['warehouse-address'] ?? '');
    $vung = trim($_POST['warehouse-vung'] ?? '');
    $thu_kho = trim($_POST['warehouse-keeper'] ?? '');
    $loai_kho = trim($_POST['warehouse-type'] ?? '');

    if (!empty($ma_kho) && !empty($ten_kho) && !empty($dia_chi) && !empty($loai_kho)) {
        try {
            // Cập nhật kho hàng
            $stmt = $pdo->prepare('UPDATE kho SET ten_kho = :ten_kho, dia_chi = :dia_chi, ma_vung = :ma_vung, ma_nd = :thu_kho, ma_loai_kho = :loai_kho WHERE ma_kho = :ma_kho');
            $stmt->execute([
                ':ma_kho' => $ma_kho,
                ':ten_kho' => $ten_kho,
                ':dia_chi' => $dia_chi,
                ':ma_vung' => $vung,
                ':thu_kho' => !empty($thu_kho) ? $thu_kho : null,
                ':loai_kho' => $loai_kho
            ]);

            // Kiểm tra xem có cập nhật được không
            if ($stmt->rowCount() > 0) {
                // Redirect để tránh resubmission
                header('Location: danhsachkho.php?status=success&message=' . urlencode('Cập nhật kho thành công (Mã: ' . $ma_kho . ')'));
                exit();
            } else {
                header('Location: danhsachkho.php?status=error&message=' . urlencode('Không tìm thấy kho để cập nhật'));
                exit();
            }
        } catch (PDOException $e) {
            // Redirect với lỗi
            header('Location: danhsachkho.php?status=error&message=' . urlencode('Lỗi khi cập nhật kho: ' . $e->getMessage()));
            exit();
        }
    } else {
        header('Location: danhsachkho.php?status=error&message=' . urlencode('Vui lòng điền đầy đủ thông tin bắt buộc.'));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: danhsachkho.php');
exit();
?>