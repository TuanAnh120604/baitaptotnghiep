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
            $pdo->prepare("UPDATE kho SET ma_nd = ? WHERE ma_kho = ?")
                ->execute([$ma_nd, $ma_kho]);
        }

        header('Location: nguoidung.php?status=success&message=' . urlencode("Thêm người dùng thành công ($ma_nd)"));
        exit();

    } catch (Exception $e) {
        header('Location: nguoidung.php?status=error&message=' . urlencode($e->getMessage()));
        exit();
    }
}


/* =========================
   CHỈNH SỬA NGƯỜI DÙNG
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user') {

    $ma_nd      = trim($_POST['ma_nd'] ?? '');
    $ten_nd     = trim($_POST['ten_nd'] ?? '');
    $mat_khau   = $_POST['mat_khau'] ?? '';
    $ma_vai_tro = trim($_POST['ma_vai_tro'] ?? '');

    $ma_loai_kho = $_POST['ma_loai_kho'] ?? '';
    $ma_vung     = $_POST['ma_vung'] ?? '';
    $ma_kho      = $_POST['ma_kho'] ?? '';

    if ($ma_nd === '' || $ten_nd === '' || $ma_vai_tro === '') {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Thiếu thông tin'));
        exit();
    }

    try {
        if ($mat_khau !== '') {
            $hashed = password_hash($mat_khau, PASSWORD_BCRYPT);
            $pdo->prepare("
                UPDATE nguoi_dung 
                SET ten_nd = ?, mat_khau = ?, ma_vai_tro = ?
                WHERE ma_nd = ?
            ")->execute([$ten_nd, $hashed, $ma_vai_tro, $ma_nd]);
        } else {
            $pdo->prepare("
                UPDATE nguoi_dung 
                SET ten_nd = ?, ma_vai_tro = ?
                WHERE ma_nd = ?
            ")->execute([$ten_nd, $ma_vai_tro, $ma_nd]);
        }

        /* ===== XỬ LÝ PHÂN QUYỀN ===== */

        // Xóa quyền cũ
        $pdo->prepare("DELETE FROM phan_quyen WHERE ma_nd = ?")->execute([$ma_nd]);
        $pdo->prepare("UPDATE kho SET ma_nd = NULL WHERE ma_nd = ?")->execute([$ma_nd]);

        // Quản lý kho
        if ($ma_vai_tro === 'VT003') {

            if ($ma_vung === '' || $ma_loai_kho === '') {
                throw new Exception('Quản lý kho cần chọn vùng & loại kho');
            }

            $check = $pdo->prepare("
                SELECT COUNT(*) FROM phan_quyen
                WHERE ma_vung = ? AND ma_loai_kho = ?
            ");
            $check->execute([$ma_vung, $ma_loai_kho]);

            if ($check->fetchColumn() > 0) {
                throw new Exception('Đã có quản lý kho cho vùng & loại kho này');
            }

            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(ma_quyen,3) AS UNSIGNED)) FROM phan_quyen");
            $ma_quyen = 'PQ' . str_pad(((int)$stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);

            $pdo->prepare("
                INSERT INTO phan_quyen (ma_quyen, ma_nd, ma_vung, ma_loai_kho)
                VALUES (?, ?, ?, ?)
            ")->execute([$ma_quyen, $ma_nd, $ma_vung, $ma_loai_kho]);
        }

        // Thủ kho
        if ($ma_vai_tro === 'VT004') {

            if ($ma_kho === '') {
                throw new Exception('Thủ kho cần chọn kho');
            }

            $check = $pdo->prepare("SELECT ma_nd FROM kho WHERE ma_kho = ?");
            $check->execute([$ma_kho]);
            $old = $check->fetchColumn();

            if ($old && $old !== $ma_nd) {
                throw new Exception('Kho này đã có thủ kho');
            }

            $pdo->prepare("UPDATE kho SET ma_nd = ? WHERE ma_kho = ?")
                ->execute([$ma_nd, $ma_kho]);
        }

        header('Location: nguoidung.php?status=success&message=' . urlencode("Cập nhật thành công ($ma_nd)"));
        exit();

    } catch (Exception $e) {
        header('Location: nguoidung.php?status=error&message=' . urlencode($e->getMessage()));
        exit();
    }
}


/* =========================
   XÓA NGƯỜI DÙNG
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {

    $ma_nd = trim($_POST['ma_nd'] ?? '');

    if ($ma_nd === '') {
        header('Location: nguoidung.php?status=error&message=' . urlencode('Mã người dùng không hợp lệ'));
        exit();
    }

    try {
        $pdo->prepare("DELETE FROM phan_quyen WHERE ma_nd = ?")->execute([$ma_nd]);
        $pdo->prepare("UPDATE kho SET ma_nd = NULL WHERE ma_nd = ?")->execute([$ma_nd]);
        $pdo->prepare("DELETE FROM nguoi_dung WHERE ma_nd = ?")->execute([$ma_nd]);

        header('Location: nguoidung.php?status=success&message=' . urlencode("Đã xóa ($ma_nd)"));
        exit();

    } catch (Exception $e) {
        header('Location: nguoidung.php?status=error&message=' . urlencode($e->getMessage()));
        exit();
    }
}

header('Location: nguoidung.php');
exit();
