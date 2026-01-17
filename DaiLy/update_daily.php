<?php
include '../include/connect.php';

// Xử lý chỉnh sửa đại lý
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_agent') {
    $ma_dai_ly      = trim($_POST['ma_dai_ly'] ?? '');
    $ten_dai_ly     = trim($_POST['ten_dai_ly'] ?? '');
    $dia_chi        = trim($_POST['dia_chi'] ?? '');
    $sdt            = trim($_POST['sdt'] ?? '');
    $cccd           = trim($_POST['cccd'] ?? '');
    $nguoi_dai_dien = trim($_POST['nguoi_dai_dien'] ?? '');
    $so_hop_dong    = trim($_POST['so_hop_dong'] ?? '');
    $ngay_ky        = trim($_POST['ngay_ky'] ?? '');

    // Validation
    if (empty($ma_dai_ly) || empty($ten_dai_ly) || empty($dia_chi) || empty($sdt)) {
        header('Location: daily.php?status=error&message=' . urlencode('Vui lòng điền đầy đủ thông tin bắt buộc'));
        exit();
    } elseif (!preg_match('/^\d{10}$/', $sdt)) {
        header('Location: daily.php?status=error&message=' . urlencode('Số điện thoại phải có đúng 10 chữ số'));
        exit();
    } elseif (!empty($cccd) && !preg_match('/^\d{12}$/', $cccd)) {
        header('Location: daily.php?status=error&message=' . urlencode('CCCD/CMND phải có đúng 12 chữ số (nếu có nhập)'));
        exit();
    } elseif (!empty($so_hop_dong) && !preg_match('/^DL_[a-zA-Z]{2}_[0-9]{2}$/', $so_hop_dong)) {
        header('Location: daily.php?status=error&message=' . urlencode('Hợp đồng phải có định dạng DL_XX_XX (ví dụ: DL_AB_12)'));
        exit();
    } else {
        try {
            $ngay_ky_db = !empty($ngay_ky) ? $ngay_ky : null;

            $update_stmt = $pdo->prepare('
                UPDATE dai_ly SET
                ten_dai_ly = :ten_dai_ly,
                dia_chi = :dia_chi,
                sdt = :sdt,
                cccd = :cccd,
                nguoi_dai_dien = :nguoi_dai_dien,
                so_hop_dong = :so_hop_dong,
                ngay_ky = :ngay_ky
                WHERE ma_dai_ly = :ma_dai_ly
            ');
            $update_stmt->execute([
                ':ma_dai_ly'       => $ma_dai_ly,
                ':ten_dai_ly'      => $ten_dai_ly,
                ':dia_chi'         => $dia_chi,
                ':sdt'             => $sdt,
                ':cccd'            => $cccd ?: null,
                ':nguoi_dai_dien'  => $nguoi_dai_dien ?: null,
                ':so_hop_dong'     => $so_hop_dong ?: null,
                ':ngay_ky'         => $ngay_ky_db
            ]);

            // Kiểm tra xem có cập nhật được không
            if ($update_stmt->rowCount() > 0) {
                // Redirect để tránh resubmission
                header('Location: daily.php?status=success&message=' . urlencode('Cập nhật đại lý thành công (Mã: ' . $ma_dai_ly . ')'));
                exit();
            } else {
                header('Location: daily.php?status=error&message=' . urlencode('Không tìm thấy đại lý để cập nhật'));
                exit();
            }
        } catch (Exception $e) {
            // Redirect với lỗi
            header('Location: daily.php?status=error&message=' . urlencode('Lỗi khi cập nhật đại lý: ' . $e->getMessage()));
            exit();
        }
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: daily.php');
exit();
?>