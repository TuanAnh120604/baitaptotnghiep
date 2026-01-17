<?php
include '../include/connect.php';

// Xử lý chỉnh sửa nhà cung cấp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $ma_ncc = isset($_POST['ma_ncc']) ? trim($_POST['ma_ncc']) : '';
    $ten_ncc = isset($_POST['ten_ncc']) ? trim($_POST['ten_ncc']) : '';
    $sdt = isset($_POST['sdt']) ? trim($_POST['sdt']) : '';
    $dia_chi = isset($_POST['dia_chi']) ? trim($_POST['dia_chi']) : '';
    $hop_dong = isset($_POST['hop_dong']) ? trim($_POST['hop_dong']) : '';

    if (empty($ma_ncc) || empty($ten_ncc) || empty($sdt) || empty($dia_chi)) {
        header('Location: nhacungcap.php?status=error&message=' . urlencode('Vui lòng điền đầy đủ thông tin'));
        exit();
    } elseif (!preg_match('/^\d{10}$/', $sdt)) {
        header('Location: nhacungcap.php?status=error&message=' . urlencode('Số điện thoại phải có đúng 10 chữ số'));
        exit();
    } elseif (!empty($hop_dong) && !preg_match('/^HD-\d{4}\/\d{2}$/', $hop_dong)) {
        header('Location: nhacungcap.php?status=error&message=' . urlencode('Hợp đồng phải có định dạng HD-YYYY/NN (ví dụ: HD-2004/01)'));
        exit();
    } else {
        try {
            $update_stmt = $pdo->prepare('UPDATE nha_cung_cap SET ten_ncc = :ten_ncc, sdt = :sdt, dia_chi = :dia_chi, hop_dong = :hop_dong WHERE ma_ncc = :ma_ncc');
            $update_stmt->execute([
                ':ma_ncc' => $ma_ncc,
                ':ten_ncc' => $ten_ncc,
                ':sdt' => $sdt,
                ':dia_chi' => $dia_chi,
                ':hop_dong' => $hop_dong ?: null
            ]);

            // Kiểm tra xem có cập nhật được không
            if ($update_stmt->rowCount() > 0) {
                // Redirect để tránh resubmission
                header('Location: nhacungcap.php?status=success&message=' . urlencode('Cập nhật nhà cung cấp thành công (Mã: ' . $ma_ncc . ')'));
                exit();
            } else {
                header('Location: nhacungcap.php?status=error&message=' . urlencode('Không tìm thấy nhà cung cấp để cập nhật'));
                exit();
            }
        } catch (Exception $e) {
            // Redirect với lỗi
            header('Location: nhacungcap.php?status=error&message=' . urlencode('Lỗi: ' . $e->getMessage()));
            exit();
        }
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: nhacungcap.php');
exit();
?>