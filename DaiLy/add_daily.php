<?php
include '../include/connect.php';

// Xử lý thêm đại lý
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_agent') {
    $ten_dai_ly      = trim($_POST['ten_dai_ly'] ?? '');
    $dia_chi         = trim($_POST['dia_chi'] ?? '');
    $sdt             = trim($_POST['sdt'] ?? '');
    $cccd            = trim($_POST['cccd'] ?? '');
    $nguoi_dai_dien  = trim($_POST['nguoi_dai_dien'] ?? '');
    $so_hop_dong     = trim($_POST['so_hop_dong'] ?? '');
    $ngay_ky         = trim($_POST['ngay_ky'] ?? '');

    // Validation
    if (empty($ten_dai_ly) || empty($dia_chi) || empty($sdt)) {
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
            // Tạo mã đại lý tự động
            $stmt = $pdo->query('SELECT MAX(CAST(SUBSTRING(ma_dai_ly, 3) AS UNSIGNED)) as max_id FROM dai_ly WHERE ma_dai_ly LIKE "DL%"');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = ($result['max_id'] ?? 0) + 1;
            $ma_dai_ly = 'DL' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

            $ngay_ky_db = !empty($ngay_ky) ? $ngay_ky : null;

            $insert_stmt = $pdo->prepare('
                INSERT INTO dai_ly
                (ma_dai_ly, ten_dai_ly, dia_chi, sdt, cccd, nguoi_dai_dien, so_hop_dong, ngay_ky)
                VALUES (:ma_dai_ly, :ten_dai_ly, :dia_chi, :sdt, :cccd, :nguoi_dai_dien, :so_hop_dong, :ngay_ky)
            ');
            $insert_stmt->execute([
                ':ma_dai_ly'       => $ma_dai_ly,
                ':ten_dai_ly'      => $ten_dai_ly,
                ':dia_chi'         => $dia_chi,
                ':sdt'             => $sdt,
                ':cccd'            => $cccd ?: null,
                ':nguoi_dai_dien'  => $nguoi_dai_dien ?: null,
                ':so_hop_dong'     => $so_hop_dong ?: null,
                ':ngay_ky'         => $ngay_ky_db
            ]);

            // Redirect để tránh resubmission
            header('Location: daily.php?status=success&message=' . urlencode('Thêm đại lý thành công (Mã: ' . $ma_dai_ly . ')'));
            exit();
        } catch (Exception $e) {
            // Redirect với lỗi
            header('Location: daily.php?status=error&message=' . urlencode('Lỗi khi thêm đại lý: ' . $e->getMessage()));
            exit();
        }
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: daily.php');
exit();
?>