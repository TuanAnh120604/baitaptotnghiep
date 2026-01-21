<?php
include '../include/connect.php';

// Khởi tạo mảng lỗi
$errors = [];
$submitted_data = [];

// Xử lý chỉnh sửa nhà cung cấp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $ma_ncc = isset($_POST['ma_ncc']) ? trim($_POST['ma_ncc']) : '';
    $ten_ncc = isset($_POST['ten_ncc']) ? trim($_POST['ten_ncc']) : '';
    $sdt = isset($_POST['sdt']) ? trim($_POST['sdt']) : '';
    $dia_chi = isset($_POST['dia_chi']) ? trim($_POST['dia_chi']) : '';
    $hop_dong = isset($_POST['hop_dong']) ? trim($_POST['hop_dong']) : '';

    // Lưu dữ liệu đã submit
    $submitted_data = [
        'ma_ncc' => $ma_ncc,
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
        // Kiểm tra số điện thoại không được trùng (bỏ qua chính nó)
        $check_sdt = $pdo->prepare('SELECT COUNT(*) as count FROM nha_cung_cap WHERE sdt = ? AND ma_ncc != ?');
        $check_sdt->execute([$sdt, $ma_ncc]);
        if ($check_sdt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            $errors['sdt'] = 'Số điện thoại này đã tồn tại ở nhà cung cấp khác';
        }
    }

    if (empty($dia_chi)) {
        $errors['dia_chi'] = 'Địa chỉ không được trống';
    }

    if (!empty($hop_dong) && !preg_match('/^HD-\d{4}\/\d{2}$/', $hop_dong)) {
        $errors['hop_dong'] = 'Hợp đồng phải có định dạng HD-YYYY/NN (ví dụ: HD-2004/01)';
    }

    // Nếu không có lỗi, cập nhật dữ liệu
    if (empty($errors)) {
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
                $_SESSION['success_message'] = 'Cập nhật nhà cung cấp thành công (Mã: ' . $ma_ncc . ')';
                header('Location: nhacungcap.php');
                exit();
            } else {
                $errors['general'] = 'Không tìm thấy nhà cung cấp để cập nhật';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Lỗi khi cập nhật: ' . $e->getMessage();
        }
    }

    // Nếu có lỗi, lưu vào session và redirect
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $submitted_data;
        header('Location: nhacungcap.php');
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: nhacungcap.php');
exit();
