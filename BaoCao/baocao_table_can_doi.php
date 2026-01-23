<?php
include '../include/connect.php';


// Lấy các tham số lọc
$table_ma_vung = isset($_GET['table_ma_vung']) ? $_GET['table_ma_vung'] : '';
$table_loai_kho = isset($_GET['table_loai_kho']) ? $_GET['table_loai_kho'] : '';
$table_ma_kho = isset($_GET['table_ma_kho']) ? $_GET['table_ma_kho'] : '';
$table_ngay_bat_dau = isset($_GET['table_ngay_bat_dau']) ? $_GET['table_ngay_bat_dau'] : date('Y-01-01');
$table_ngay_ket_thuc = isset($_GET['table_ngay_ket_thuc']) ? $_GET['table_ngay_ket_thuc'] : date('Y-m-d');

// Lấy danh sách vùng miền
$stmt_vung = $pdo->query("SELECT * FROM vung_mien ORDER BY ten_vung");
$danh_sach_vung = $stmt_vung->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách loại kho
$stmt_loai_kho = $pdo->query("SELECT * FROM loai_kho ORDER BY ma_loai_kho");
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách kho dựa trên vùng miền và loại kho được chọn
$danh_sach_kho = [];
$sql_kho = "SELECT k.* FROM kho k JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho WHERE 1=1";
$params_kho = [];

if (!empty($table_ma_vung)) {
    $sql_kho .= " AND k.ma_vung = ?";
    $params_kho[] = $table_ma_vung;
}

if (!empty($table_loai_kho)) {
    $sql_kho .= " AND k.ma_loai_kho = ?";
    $params_kho[] = $table_loai_kho;
}

$sql_kho .= " ORDER BY k.ma_kho";
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$danh_sach_kho = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);

// ============ QUERY: BẢNG CÂN ĐỐI KHO CHI TIẾT ============
// Lấy dữ liệu từ bảng the_kho (thẻ kho) - bảng ghi chép chính xác tồn kho
$sql_bang = "
    SELECT 
        hh.ma_hang,
        hh.ten_hang,
        hh.don_vi_tinh,
        lk.ten_loai_kho,
        k.ten_kho,
        COALESCE((
            SELECT COALESCE(so_luong_ton, 0) 
            FROM the_kho 
            WHERE ma_hang = hh.ma_hang 
            AND ma_kho = k.ma_kho
            AND ngay < ? 
            ORDER BY ngay DESC, so_ct DESC 
            LIMIT 1
        ), 0) as ton_dau_ky,
        COALESCE((
            SELECT SUM(ct.so_luong_nhap) 
            FROM ct_phieu_nhap ct
            JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
            WHERE ct.ma_hang = hh.ma_hang 
            AND pn.ma_kho = k.ma_kho
            AND pn.ngay_nhap >= ? 
            AND pn.ngay_nhap <= ?
        ), 0) as luong_nhap,
        COALESCE((
            SELECT SUM(ct.so_luong_xuat) 
            FROM ct_phieu_xuat ct
            JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
            WHERE ct.ma_hang = hh.ma_hang 
            AND px.ma_kho = k.ma_kho
            AND px.ngay_xuat >= ? 
            AND px.ngay_xuat <= ?
        ), 0) as luong_xuat
    FROM (
        SELECT DISTINCT ma_hang, ma_kho FROM the_kho
    ) tk
    JOIN hang_hoa hh ON tk.ma_hang = hh.ma_hang
    JOIN kho k ON tk.ma_kho = k.ma_kho
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    WHERE 1=1
";

$params_bang = [$table_ngay_bat_dau, $table_ngay_bat_dau, $table_ngay_ket_thuc, $table_ngay_bat_dau, $table_ngay_ket_thuc];

if (!empty($table_ma_vung)) {
    $sql_bang .= " AND k.ma_vung = ?";
    $params_bang[] = $table_ma_vung;
}

if (!empty($table_loai_kho)) {
    $sql_bang .= " AND k.ma_loai_kho = ?";
    $params_bang[] = $table_loai_kho;
}

if (!empty($table_ma_kho)) {
    $sql_bang .= " AND k.ma_kho = ?";
    $params_bang[] = $table_ma_kho;
}

$sql_bang .= " GROUP BY hh.ma_hang, k.ma_kho ORDER BY k.ma_kho, hh.ma_hang";

$stmt_bang = $pdo->prepare($sql_bang);
$stmt_bang->execute($params_bang);
$ket_qua = $stmt_bang->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Cân Đối - Báo Cáo Kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .body {
        font-family: "Inter", "sans-serif";
    }

    .container-main {
        background-color: white;
        max-height: calc(100vh - 40px);
        overflow-x: hidden; /* chặn cuộn ngang */
        overflow-y: auto; 
    }

    .filter-section {
        background-color: #f9f9f9;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        padding: 15px;
        border-radius: 5px;
        margin-left: 10px;
        margin-right: 10px;
    }

    .report-title {
        text-align: center;
        color: #333;
        margin-bottom: 30px;
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 15px;
    }

    .report-title h1 {
        font-weight: bold;
        margin-bottom: 10px;
        font-size: 24px;
        color: #0d6efd;
    }

    .table-responsive {
        margin-top: 20px;
        max-height: calc(100vh - 400px);
        overflow-y: auto;
        border: 1px solid #f0f0f0;
        border-radius: 5px;
        margin-bottom: 5px;
    }

    table {
        border: 1px solid #f0f0f0;
        color: #202020;
        width: 100%;
        
    }

    table thead {
        background-color: #f0f0f0;
        color: black;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    table tbody tr:hover {
        background-color: #f5f5f5;
    }

    table tfoot {
        background-color: #f0f0f0;
        border-bottom: 1px solid #f0f0f0;
        font-weight: bold;
        position: sticky;
        bottom: 0;
        z-index: 10;
    }

    .text-right {
        text-align: right;
    }

    .btn-back {
        background-color: #0d6efd;
        margin-bottom: 15px;
        margin-top: 15px;
        margin-left: 15px;
        border: #f9f9f9;
    }

    .form-select {
        border-radius: 5px !important;
    }

    .form-control{
        border-radius: 5px !important;
    }

    .mb-3{
        font-weight: bold;
    }

    .mt-4{
        margin: auto ;
    }

    .g-3{
        display: flex;
    }

    .w-100{
        background-color: #0d6efd;
        color: #f9f9f9;
        border: #000;
    }

    .w-100:hover{
        background-color: #606060;
    }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark font-display text-[#111418] dark:text-white min-h-screen min-h-0">

    <?php include '../include/sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-h-screen relative">
        <?php include '../include/header.php'; ?>
        <div class="container-main">
            <a href="baocao_bancandoi.php" class="btn btn-secondary btn-back">
                ← 
            </a>

            <div class="report-title">
                <h1>📋 BẢNG CÂN ĐỐI KHO CHI TIẾT</h1>
                <p>Xem chi tiết tồn kho, nhập, xuất cho từng hàng hóa</p>
            </div>

            <!-- Phần lọc dữ liệu -->

            <div class="filter-section">
                <h3 class="mb-3">Bộ lọc bảng</h3>
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-2">
                        <label for="table_ma_vung" class="form-label">Vùng miền</label>
                        <select class="form-select" id="table_ma_vung" name="table_ma_vung">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_vung as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['ma_vung']); ?>"
                                <?php echo $table_ma_vung == $v['ma_vung'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['ten_vung']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="table_loai_kho" class="form-label">Loại kho</label>
                        <select class="form-select" id="table_loai_kho" name="table_loai_kho">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_loai_kho as $lk): ?>
                            <option value="<?php echo htmlspecialchars($lk['ma_loai_kho']); ?>"
                                <?php echo $table_loai_kho == $lk['ma_loai_kho'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lk['ten_loai_kho']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="table_ma_kho" class="form-label">Kho</label>
                        <select class="form-select" id="table_ma_kho" name="table_ma_kho">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_kho as $kho): ?>
                            <option value="<?php echo htmlspecialchars($kho['ma_kho']); ?>"
                                <?php echo $table_ma_kho == $kho['ma_kho'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kho['ten_kho']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="table_ngay_bat_dau" class="form-label">Từ ngày</label>
                        <input type="date" class="form-control" id="table_ngay_bat_dau" name="table_ngay_bat_dau"
                            value="<?php echo htmlspecialchars($table_ngay_bat_dau); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="table_ngay_ket_thuc" class="form-label">Đến ngày</label>
                        <input type="date" class="form-control" id="table_ngay_ket_thuc" name="table_ngay_ket_thuc"
                            value="<?php echo htmlspecialchars($table_ngay_ket_thuc); ?>">
                    </div>
                    <div class="col-md-2" style="margin-top: 27px;">
                        <label>&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger w-100">Lọc dữ liệu</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bảng cân đối kho -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 8%;">Mã hàng</th>
                                    <th style="width: 20%;">Tên hàng</th>
                                    <th style="width: 10%;">Đơn vị tính</th>
                                    <th style="width: 10%;">Loại kho</th>
                                    <th style="width: 12%;">Tên kho</th>
                                    <th style="width: 10%;" class="text-right">Tồn đầu kỳ</th>
                                    <th style="width: 10%;" class="text-right">Lượng nhập</th>
                                    <th style="width: 10%;" class="text-right">Lượng xuất</th>
                                    <th style="width: 10%;" class="text-right">Tồn cuối kỳ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                        $tong_ton_dau = 0;
                        $tong_nhap = 0;
                        $tong_xuat = 0;
                        $tong_ton_cuoi = 0;
                        
                        foreach ($ket_qua as $row): 
                            $ton_cuoi_ky = (int)$row['ton_dau_ky'] + (int)$row['luong_nhap'] - (int)$row['luong_xuat'];
                            $tong_ton_dau += (int)$row['ton_dau_ky'];
                            $tong_nhap += (int)$row['luong_nhap'];
                            $tong_xuat += (int)$row['luong_xuat'];
                            $tong_ton_cuoi += $ton_cuoi_ky;
                        ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['ma_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_hang']); ?></td>
                                    <td><?php echo htmlspecialchars($row['don_vi_tinh']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_loai_kho']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ten_kho']); ?></td>
                                    <td class="text-right"><strong><?php echo (int)$row['ton_dau_ky']; ?></strong></td>
                                    <td class="text-right"><strong><?php echo (int)$row['luong_nhap']; ?></strong></td>
                                    <td class="text-right"><strong><?php echo (int)$row['luong_xuat']; ?></strong></td>
                                    <td class="text-right"><strong><?php echo $ton_cuoi_ky; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: #f0f0f0; font-weight: bold;">
                                    <td colspan="5" class="text-right">TỔNG CỘNG:</td>
                                    <td class="text-right"><?php echo $tong_ton_dau; ?></td>
                                    <td class="text-right"><?php echo $tong_nhap; ?></td>
                                    <td class="text-right"><?php echo $tong_xuat; ?></td>
                                    <td class="text-right"><?php echo $tong_ton_cuoi; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>


        </div>
    </div>
    </div>




    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>