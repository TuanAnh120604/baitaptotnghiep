<?php
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['ma_hang'])) {
    header('Location: thanhpham.php?error=' . urlencode('Thiếu thông tin mã hàng'));
    exit;
}

$ma_hang = trim($_GET['ma_hang']);
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($ma_hang === '') {
    $redirect_params = ['error' => 'Mã hàng không hợp lệ'];
    if ($page > 1) {
        $redirect_params['page'] = $page;
    }
    if ($q !== '') {
        $redirect_params['q'] = $q;
    }
    header('Location: thanhpham.php?' . http_build_query($redirect_params));
    exit;
}

try {
    // Bắt đầu transaction
    $pdo->beginTransaction();

    // Kiểm tra xem vật tư có tồn tại không
    $check_hang = $pdo->prepare("SELECT ma_hang, ten_hang FROM hang_hoa WHERE ma_hang = ?");
    $check_hang->execute([$ma_hang]);
    $hang_info = $check_hang->fetch(PDO::FETCH_ASSOC);

    if (!$hang_info) {
        $pdo->rollBack();
        $redirect_params = ['error' => 'Không tìm thấy vật tư cần xóa'];
        if ($page > 1) {
            $redirect_params['page'] = $page;
        }
        if ($q !== '') {
            $redirect_params['q'] = $q;
        }
        header('Location: thanhpham.php?' . http_build_query($redirect_params));
        exit;
    }

    // Kiểm tra xem vật tư có đang được sử dụng trong chi tiết phiếu nhập không
    $check_ctpn = $pdo->prepare("SELECT COUNT(*) FROM ct_phieu_nhap WHERE ma_hang = ?");
    $check_ctpn->execute([$ma_hang]);
    $count_ctpn = $check_ctpn->fetchColumn();

    if ($count_ctpn > 0) {
        $pdo->rollBack();
        $redirect_params = ['error' => 'Không thể xóa vật tư này vì đã được sử dụng trong phiếu nhập'];
        if ($page > 1) {
            $redirect_params['page'] = $page;
        }
        if ($q !== '') {
            $redirect_params['q'] = $q;
        }
        header('Location: thanhpham.php?' . http_build_query($redirect_params));
        exit;
    }

    // Kiểm tra xem vật tư có đang được sử dụng trong chi tiết phiếu xuất không
    $check_ctpx = $pdo->prepare("SELECT COUNT(*) FROM ct_phieu_xuat WHERE ma_hang = ?");
    $check_ctpx->execute([$ma_hang]);
    $count_ctpx = $check_ctpx->fetchColumn();

    if ($count_ctpx > 0) {
        $pdo->rollBack();
        $redirect_params = ['error' => 'Không thể xóa vật tư này vì đã được sử dụng trong phiếu xuất'];
        if ($page > 1) {
            $redirect_params['page'] = $page;
        }
        if ($q !== '') {
            $redirect_params['q'] = $q;
        }
        header('Location: thanhpham.php?' . http_build_query($redirect_params));
        exit;
    }

    // Xóa các thẻ kho liên quan trước
    $delete_the_kho = $pdo->prepare("DELETE FROM the_kho WHERE ma_hang = ?");
    $delete_the_kho->execute([$ma_hang]);

    // Xóa vật tư
    $delete_hang = $pdo->prepare("DELETE FROM hang_hoa WHERE ma_hang = ?");
    $delete_hang->execute([$ma_hang]);

    // Commit transaction
    $pdo->commit();

    // Đếm lại tổng số bản ghi sau khi xóa để xác định trang hợp lệ
    $items_per_page = 10;
    // Lấy mã loại "Thành phẩm"
    $stmt_loai = $pdo->prepare("SELECT ma_loai_hang FROM loai_hang WHERE ten_loai_hang = ?");
    $stmt_loai->execute(['Thành phẩm']);
    $ma_thanh_pham = $stmt_loai->fetchColumn();

    if ($ma_thanh_pham && $ma_thanh_pham !== '') {
        if ($q !== '') {
            $count_after_stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM hang_hoa
                WHERE ma_loai_hang = ?
                  AND (ma_hang LIKE ? OR ten_hang LIKE ?)
            ");
            $like = "%$q%";
            $count_after_stmt->execute([$ma_thanh_pham, $like, $like]);
        } else {
            $count_after_stmt = $pdo->prepare("
                SELECT COUNT(*) as total
                FROM hang_hoa
                WHERE ma_loai_hang = ?
            ");
            $count_after_stmt->execute([$ma_thanh_pham]);
        }
        $total_items_after = $count_after_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } else {
        $total_items_after = 0;
    }

    $total_pages_after = $items_per_page > 0 ? ceil($total_items_after / $items_per_page) : 1;

    // Xác định trang redirect hợp lệ
    $redirect_page = $page;
    if ($total_pages_after > 0) {
        if ($page > $total_pages_after) {
            $redirect_page = $total_pages_after;
        }
    } else {
        $redirect_page = 1;
    }

    $redirect_params = ['success' => 1, 'deleted' => $hang_info['ten_hang']];
    if ($redirect_page > 1) {
        $redirect_params['page'] = $redirect_page;
    }
    if ($q !== '') {
        $redirect_params['q'] = $q;
    }
    header('Location: thanhpham.php?' . http_build_query($redirect_params));
    exit;
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $pdo->rollBack();
    $redirect_params = ['error' => "Lỗi khi xóa: " . $e->getMessage()];
    if ($page > 1) {
        $redirect_params['page'] = $page;
    }
    if ($q !== '') {
        $redirect_params['q'] = $q;
    }
    header('Location: thanhpham.php?' . http_build_query($redirect_params));
    exit;
}
