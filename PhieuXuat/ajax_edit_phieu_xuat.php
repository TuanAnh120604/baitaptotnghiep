<?php
// Tạo connection riêng không throw exception
try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1:3306;dbname=quan_ly_kho;charset=utf8mb4",
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT] // Không throw exception
    );
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_form':
        // Lấy dữ liệu form sửa
        $ma_phieu_xuat = $_GET['ma'] ?? '';
        if (!$ma_phieu_xuat) {
            echo json_encode(['error' => 'Thiếu mã phiếu xuất']);
            exit;
        }

        /* Lấy phiếu xuất */
        $stmt = $pdo->prepare("SELECT * FROM phieu_xuat WHERE ma_phieu_xuat=?");
        $stmt->execute([$ma_phieu_xuat]);
        $phieu = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$phieu) {
            echo json_encode(['error' => 'Không tìm thấy phiếu xuất']);
            exit;
        }

        /* Chi tiết */
        $stmt = $pdo->prepare("
            SELECT ct.*, hh.ten_hang
            FROM ct_phieu_xuat ct
            JOIN hang_hoa hh ON ct.ma_hang=hh.ma_hang
            WHERE ma_phieu_xuat=?
        ");
        $stmt->execute([$ma_phieu_xuat]);
        $chi_tiet = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* Danh sách */
        $kho_list = $pdo->query("SELECT ma_kho,ten_kho FROM kho ORDER BY ten_kho")->fetchAll(PDO::FETCH_ASSOC);
        $dai_ly_list = $pdo->query("SELECT ma_dai_ly,ten_dai_ly FROM dai_ly ORDER BY ten_dai_ly")->fetchAll(PDO::FETCH_ASSOC);
        $nguoi_list = $pdo->query("SELECT ma_nd,ten_nd FROM nguoi_dung ORDER BY ten_nd")->fetchAll(PDO::FETCH_ASSOC);
        $don_vi_list = $pdo->query("SELECT DISTINCT don_vi_nhan FROM phieu_xuat WHERE don_vi_nhan<>'' ORDER BY don_vi_nhan")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'phieu' => $phieu,
            'chi_tiet' => $chi_tiet,
            'kho_list' => $kho_list,
            'dai_ly_list' => $dai_ly_list,
            'nguoi_list' => $nguoi_list,
            'don_vi_list' => $don_vi_list
        ]);
        break;

    case 'update':
        // Cập nhật phiếu xuất
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Phương thức không được hỗ trợ']);
            exit;
        }

        $ma_phieu_xuat = $_POST['ma_phieu_xuat'] ?? '';
        if (!$ma_phieu_xuat) {
            echo json_encode(['error' => 'Thiếu mã phiếu xuất']);
            exit;
        }


        // Validate required fields
        $errors = [];
        if (empty($_POST['ma_nd'])) $errors[] = 'Thiếu mã người dùng';
        if (empty($_POST['ngay_xuat'])) $errors[] = 'Thiếu ngày xuất';
        if (empty($_POST['loai_xuat'])) $errors[] = 'Thiếu loại xuất';
        if (empty($_POST['ma_kho'])) $errors[] = 'Thiếu mã kho';

        if (!empty($errors)) {
            echo json_encode(['error' => implode(', ', $errors)]);
            exit;
        }

        // Validate foreign keys
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM nguoi_dung WHERE ma_nd = ?");
        $stmt_check->execute([$_POST['ma_nd']]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['error' => 'Người dùng không tồn tại']);
            exit;
        }

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM kho WHERE ma_kho = ?");
        $stmt_check->execute([$_POST['ma_kho']]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['error' => 'Kho không tồn tại']);
            exit;
        }

        if (!empty($_POST['ma_dai_ly'])) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM dai_ly WHERE ma_dai_ly = ?");
            $stmt_check->execute([$_POST['ma_dai_ly']]);
            if ($stmt_check->fetchColumn() == 0) {
                echo json_encode(['error' => 'Đại lý không tồn tại']);
                exit;
            }
        }

        // Update phiếu xuất
        $stmt = $pdo->prepare("
            UPDATE phieu_xuat SET
                ma_nd=?, ngay_xuat=?, nguoi_nhan=?, don_vi_nhan=?, loai_xuat=?, ma_kho=?, ma_dai_ly=?
            WHERE ma_phieu_xuat=?
        ");
        // Xử lý ma_dai_ly: nếu rỗng thì set thành NULL
        $ma_dai_ly = !empty($_POST['ma_dai_ly']) ? $_POST['ma_dai_ly'] : null;

        $result = $stmt->execute([
            $_POST['ma_nd'],
            $_POST['ngay_xuat'],
            $_POST['nguoi_nhan'] ?? '',
            $_POST['don_vi_nhan'] ?? '',
            $_POST['loai_xuat'],
            $_POST['ma_kho'],
            $ma_dai_ly,
            $ma_phieu_xuat
        ]);

        if ($result) {
            // Xóa chi tiết cũ
            $pdo->prepare("DELETE FROM ct_phieu_xuat WHERE ma_phieu_xuat=?")->execute([$ma_phieu_xuat]);

            // Thêm chi tiết mới nếu có
            if (!empty($_POST['hang_hoa']) && is_array($_POST['hang_hoa'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO ct_phieu_xuat(ma_ctpx,ma_phieu_xuat,ma_hang,so_luong_xuat,don_gia_xuat,thanh_tien)
                    VALUES (?,?,?,?,?,?)
                ");

                foreach ($_POST['hang_hoa'] as $i => $it) {
                    if (!empty($it['ma_hang'])) {
                        $sl = (int)($it['so_luong'] ?? 0);
                        $dg = (float)($it['don_gia'] ?? 0);
                        $stmt->execute([
                            $ma_phieu_xuat . '-' . $it['ma_hang'],
                            $ma_phieu_xuat,
                            $it['ma_hang'],
                            $sl,
                            $dg,
                            $sl * $dg
                        ]);
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['error' => 'Lỗi cập nhật: ' . implode(', ', $stmt->errorInfo())]);
        }
        break;

    default:
        echo json_encode(['error' => 'Action không hợp lệ']);
        break;
}
?>