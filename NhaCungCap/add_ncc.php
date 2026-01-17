<?php
include '../include/connect.php';

// Xử lý thêm nhà cung cấp mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $ten_ncc = isset($_POST['ten_ncc']) ? trim($_POST['ten_ncc']) : '';
    $sdt = isset($_POST['sdt']) ? trim($_POST['sdt']) : '';
    $dia_chi = isset($_POST['dia_chi']) ? trim($_POST['dia_chi']) : '';
    $hop_dong = isset($_POST['hop_dong']) ? trim($_POST['hop_dong']) : '';

    if (empty($ten_ncc) || empty($sdt) || empty($dia_chi)) {
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

            // Redirect để tránh resubmission
            header('Location: nhacungcap.php?status=success&message=' . urlencode('Thêm nhà cung cấp thành công (Mã: ' . $ma_ncc . ')'));
            exit();
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