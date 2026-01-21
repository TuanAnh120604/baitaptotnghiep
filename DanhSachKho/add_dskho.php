<?php
include '../include/connect.php';
include '../include/permissions.php';

if (!canCreate('danhsachkho')) {
    header('Location: danhsachkho.php?error=Không có quyền thêm kho');
    exit;
}

// Xử lý việc gửi biểu mẫu để thêm kho hàng mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add-warehouse'])) {
    $ten_kho = trim($_POST['warehouse-name'] ?? '');
    $thu_kho = trim($_POST['warehouse-keeper'] ?? '');
    $dia_chi = trim($_POST['warehouse-address'] ?? '');
    $vung = trim($_POST['warehouse-vung'] ?? '');
    $loai_kho = trim($_POST['warehouse-type'] ?? '');

    if (!empty($ten_kho) && !empty($dia_chi) && !empty($loai_kho)) {
        try {
            // Tạo mã kho tiếp theo
            $stmt = $pdo->prepare('SELECT MAX(ma_kho) AS max_code FROM kho');
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $max_code = $result['max_code'] ?? 'K000';

            // Trích xuất phần số và tăng nó lên.
            $next_number = (int)substr($max_code, 1) + 1;
            $new_code = 'K' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

            // Chèn kho hàng mới
            $stmt = $pdo->prepare('INSERT INTO kho (ma_kho, ten_kho, dia_chi, ma_vung, ma_nd, ma_loai_kho) VALUES (:ma_kho, :ten_kho, :dia_chi, :ma_vung, :ma_nd, :ma_loai_kho)');
            $stmt->execute([
                ':ma_kho' => $new_code,
                ':ten_kho' => $ten_kho,
                ':dia_chi' => $dia_chi,
                ':ma_vung' => $vung,
                ':ma_nd' => !empty($thu_kho) ? $thu_kho : null,
                ':ma_loai_kho' => $loai_kho
            ]);

            // Redirect để tránh resubmission
            header('Location: danhsachkho.php?status=success&message=' . urlencode('Thêm kho thành công (Mã: ' . $new_code . ')'));
            exit();
        } catch (PDOException $e) {
            // Redirect với lỗi
            header('Location: danhsachkho.php?status=error&message=' . urlencode('Lỗi khi thêm kho: ' . $e->getMessage()));
            exit();
        }
    } else {
        header('Location: danhsachkho.php?status=error&message=' . urlencode('Vui lòng điền đầy đủ thông tin.'));
        exit();
    }
}

// Nếu không phải POST request hoặc action không hợp lệ, redirect về trang chính
header('Location: danhsachkho.php');
exit();
?>