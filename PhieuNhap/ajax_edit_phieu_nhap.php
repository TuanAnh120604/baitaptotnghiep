<?php
include '../include/connect.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_form':
        // Lấy dữ liệu form sửa
        $ma_phieu = $_GET['ma'] ?? '';
        if (!$ma_phieu) {
            echo json_encode(['error' => 'Thiếu mã phiếu']);
            exit;
        }

        try {
            /* Lấy phiếu */
            $stmt = $pdo->prepare("SELECT * FROM phieu_nhap WHERE ma_phieu_nhap=?");
            $stmt->execute([$ma_phieu]);
            $phieu = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$phieu) {
                echo json_encode(['error' => 'Không tìm thấy phiếu']);
                exit;
            }

            /* Chi tiết */
            $stmt = $pdo->prepare("
            SELECT ct.*, hh.ten_hang
            FROM ct_phieu_nhap ct
            JOIN hang_hoa hh ON ct.ma_hang=hh.ma_hang
            WHERE ma_phieu_nhap=?
            ");
            $stmt->execute([$ma_phieu]);
            $chi_tiet = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /* Danh sách */
            $ncc_list = $pdo->query("SELECT ma_ncc,ten_ncc FROM nha_cung_cap ORDER BY ten_ncc")->fetchAll(PDO::FETCH_ASSOC);
            $kho_list = $pdo->query("SELECT ma_kho,ten_kho FROM kho ORDER BY ten_kho")->fetchAll(PDO::FETCH_ASSOC);
            $don_vi_list = $pdo->query("SELECT DISTINCT don_vi_giao FROM phieu_nhap WHERE don_vi_giao<>'' ORDER BY don_vi_giao")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'phieu' => $phieu,
                'chi_tiet' => $chi_tiet,
                'ncc_list' => $ncc_list,
                'kho_list' => $kho_list,
                'don_vi_list' => $don_vi_list
            ]);

        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'update':
        // Cập nhật phiếu nhập
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Phương thức không được hỗ trợ']);
            exit;
        }

        $ma_phieu = $_POST['ma_phieu'] ?? '';
        if (!$ma_phieu) {
            echo json_encode(['error' => 'Thiếu mã phiếu']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE phieu_nhap SET ngay_nhap=?,nguoi_giao=?,don_vi_giao=?,ma_kho=?,ma_ncc=? WHERE ma_phieu_nhap=?")
                ->execute([
                    $_POST['ngay_nhap'], $_POST['nguoi_giao'], $_POST['don_vi_giao'],
                    $_POST['ma_kho'], $_POST['ma_ncc'], $ma_phieu
                ]);

            $pdo->prepare("DELETE FROM ct_phieu_nhap WHERE ma_phieu_nhap=?")->execute([$ma_phieu]);

            $stmt = $pdo->prepare("INSERT INTO ct_phieu_nhap VALUES (?,?,?,?,?,?)");

            foreach ($_POST['hang_hoa'] as $i => $it) {
                $sl = (int)$it['so_luong'];
                $dg = (float)$it['don_gia'];
                $stmt->execute([
                    $ma_phieu . '-' . $it['ma_hang'],
                    $ma_phieu,
                    $it['ma_hang'],
                    $sl,
                    $dg,
                    $sl * $dg
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);

        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['error' => 'Action không hợp lệ']);
        break;
}
?>