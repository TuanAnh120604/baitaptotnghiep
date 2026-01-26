<?php
include '../include/connect.php';

/* =========================
   THÊM NGƯỜI DÙNG
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {

    $ten_nd      = trim($_POST['ten_nd'] ?? '');
    $mat_khau    = $_POST['mat_khau'] ?? '';
    $ma_vai_tro  = trim($_POST['ma_vai_tro'] ?? '');
    $ma_loai_kho = trim($_POST['ma_loai_kho'] ?? '');
    $ma_vung     = trim($_POST['ma_vung'] ?? '');
    $ma_kho      = trim($_POST['ma_kho'] ?? '');

    if ($ten_nd === '' || $mat_khau === '' || $ma_vai_tro === '') {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Vui lòng điền đầy đủ thông tin'));
        exit();
    }

    if ($ma_vai_tro === 'VT003' && ($ma_loai_kho === '' || $ma_vung === '')) {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Quản lý kho cần chọn loại kho và vùng'));
        exit();
    }

    if ($ma_vai_tro === 'VT004' && $ma_kho === '') {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Thủ kho cần chọn kho'));
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Nếu là thủ kho: chỉ cho phép chọn kho CHƯA có thủ kho (kho.ma_nd NULL/rỗng)
        if ($ma_vai_tro === 'VT004') {
            $checkKho = $pdo->prepare("SELECT ma_nd FROM kho WHERE ma_kho = ? LIMIT 1");
            $checkKho->execute([$ma_kho]);
            $existingMaNd = $checkKho->fetchColumn();

            if ($existingMaNd === false) {
                throw new Exception('Kho không tồn tại');
            }
            if (!empty($existingMaNd)) {
                throw new Exception('Kho này đã có thủ kho, vui lòng chọn kho khác');
            }
        }

        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(ma_nd,3) AS UNSIGNED)) FROM nguoi_dung");
        $ma_nd = 'ND' . str_pad(((int)$stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);

        $hashed = password_hash($mat_khau, PASSWORD_BCRYPT);

        $pdo->prepare("
            INSERT INTO nguoi_dung (ma_nd, ten_nd, mat_khau, ma_vai_tro)
            VALUES (?, ?, ?, ?)
        ")->execute([$ma_nd, $ten_nd, $hashed, $ma_vai_tro]);

        if ($ma_vai_tro === 'VT003') {
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(ma_quyen,3) AS UNSIGNED)) FROM phan_quyen");
            $ma_quyen = 'PQ' . str_pad(((int)$stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);

            $pdo->prepare("
                INSERT INTO phan_quyen (ma_quyen, ma_nd, ma_vung, ma_loai_kho)
                VALUES (?, ?, ?, ?)
            ")->execute([$ma_quyen, $ma_nd, $ma_vung, $ma_loai_kho]);
        }

        if ($ma_vai_tro === 'VT004') {
            // Cập nhật có điều kiện để tránh overwrite thủ kho hiện có (race condition)
            $update = $pdo->prepare("UPDATE kho SET ma_nd = ? WHERE ma_kho = ? AND (ma_nd IS NULL OR ma_nd = '')");
            $update->execute([$ma_nd, $ma_kho]);

            if ($update->rowCount() !== 1) {
                throw new Exception('Kho này đã có thủ kho, vui lòng chọn kho khác');
            }
        }

        $pdo->commit();

        header('Location: nguoidung.php?status=success&message=' . urlencode("Thêm người dùng thành công ($ma_nd)"));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: nguoidung.php?status=error&message=' . urlencode($e->getMessage()));
        exit();
    }
}

header('Location: nguoidung.php');
exit();
?>