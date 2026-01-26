<?php
include '../include/connect.php';

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

?>