<?php
include '../include/connect.php';

// Khởi tạo mảng lỗi và thông báo
$errors = [];
$success = false;
$submitted_data = [];

// Xử lý thêm nhà cung cấp mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $ten_ncc = isset($_POST['ten_ncc']) ? trim($_POST['ten_ncc']) : '';
    $sdt = isset($_POST['sdt']) ? trim($_POST['sdt']) : '';
    $dia_chi = isset($_POST['dia_chi']) ? trim($_POST['dia_chi']) : '';
    $hop_dong = isset($_POST['hop_dong']) ? trim($_POST['hop_dong']) : '';

    // Lưu dữ liệu đã submit để hiển thị lại
    $submitted_data = [
        'ten_ncc' => $ten_ncc,
        'sdt' => $sdt,
        'dia_chi' => $dia_chi,
        'hop_dong' => $hop_dong
    ];

    if (empty($ten_ncc)) {
        $errors['ten_ncc'] = 'Tên nhà cung cấp không được trống';
    }
    if (empty($sdt)) {
        $errors['sdt'] = 'Số điện thoại không được trống';
    } elseif (!preg_match('/^\d{10}$/', $sdt)) {
        $errors['sdt'] = 'Số điện thoại phải có đúng 10 chữ số';
    } else {
        // Kiểm tra số điện thoại không được trùng
        $check_sdt = $pdo->prepare('SELECT COUNT(*) as count FROM nha_cung_cap WHERE sdt = ?');
        $check_sdt->execute([$sdt]);
        if ($check_sdt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $errors['sdt'] = 'Số điện thoại này đã tồn tại';
        }
    }

    if (empty($dia_chi)) {
        $errors['dia_chi'] = 'Địa chỉ không được trống';
    }

    if (!empty($hop_dong) && !preg_match('/^HD-\d{4}\/\d{2}$/', $hop_dong)) {
        $errors['hop_dong'] = 'Hợp đồng phải có định dạng HD-YYYY/NN (ví dụ: HD-2004/01)';
    }

    // Nếu không có lỗi, thêm dữ liệu
    if (empty($errors)) {
        try {
            $stmt = $pdo->query('SELECT MAX(CAST(SUBSTRING(ma_ncc, 4) AS UNSIGNED)) as max_id FROM nha_cung_cap WHERE ma_ncc LIKE "NCC%"');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_id = ($result['max_id'] ?? 0) + 1;
            $ma_ncc = 'NCC' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

            $insert_stmt = $pdo->prepare('INSERT INTO nha_cung_cap (ma_ncc, ten_ncc, sdt, dia_chi, hop_dong) VALUES (:ma_ncc, :ten_ncc, :sdt, :dia_chi, :hop_dong)');
            $insert_stmt->execute([
                ':ma_ncc' => $ma_ncc,
                ':ten_ncc' => $ten_ncc,
                ':sdt' => $sdt,
                ':dia_chi' => $dia_chi,
                ':hop_dong' => $hop_dong ?: null
            ]);

            // Lưu thông báo vào session và redirect
            $_SESSION['success_message'] = 'Thêm nhà cung cấp thành công (Mã: ' . $ma_ncc . ')';
            header('Location: nhacungcap.php');
            exit();
        } catch (Exception $e) {
            $errors['general'] = 'Lỗi khi thêm: ' . $e->getMessage();
        }
    } else {
        // Có lỗi, lưu vào session và redirect
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $submitted_data;
        header('Location: nhacungcap.php');
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: nhacungcap.php');
exit();
