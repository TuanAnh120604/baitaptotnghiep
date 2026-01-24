<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$ma_vung = isset($_GET['ma_vung']) ? $_GET['ma_vung'] : '';
$loai_kho = isset($_GET['loai_kho']) ? $_GET['loai_kho'] : '';
$don_vi_tinh = isset($_GET['don_vi_tinh']) ? $_GET['don_vi_tinh'] : '';
$ngay_bat_dau = isset($_GET['ngay_bat_dau']) ? $_GET['ngay_bat_dau'] : date('Y-01-01');
$ngay_ket_thuc = isset($_GET['ngay_ket_thuc']) ? $_GET['ngay_ket_thuc'] : date('Y-m-d');

// Mapping giữa loại kho và loại hàng
$mapping_loai_kho_hang = [
    'L001' => 'M001',
    'L002' => 'M002',
    'L003' => 'M003',
    'L004' => 'M004'
];

// Xây dựng điều kiện lọc kho theo quyền
$kho_condition = '';
$kho_params = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $kho_condition = 'AND k.ma_nd = ?';
    $kho_params[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $kho_condition = 'AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = ?
    )';
    $kho_params[] = $ma_nd;
}

// Lấy danh sách vùng miền (theo quyền)
$sql_vung = "SELECT * FROM vung_mien WHERE 1=1";
$params_vung = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM kho WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM phan_quyen WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
}
$sql_vung .= " ORDER BY ten_vung";
$stmt_vung = $pdo->prepare($sql_vung);
$stmt_vung->execute($params_vung);
$danh_sach_vung = $stmt_vung->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách loại kho (theo quyền)
$sql_loai_kho = "SELECT DISTINCT lk.* FROM loai_kho lk JOIN kho k ON lk.ma_loai_kho = k.ma_loai_kho WHERE 1=1";
$params_loai_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_nd = ?";
    $params_loai_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = ?
    )";
    $params_loai_kho[] = $ma_nd;
}
$sql_loai_kho .= " ORDER BY lk.ma_loai_kho";
$stmt_loai_kho = $pdo->prepare($sql_loai_kho);
$stmt_loai_kho->execute($params_loai_kho);
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách DVT dựa trên loại kho được chọn
$danh_sach_dvt = [];
if (!empty($loai_kho) && isset($mapping_loai_kho_hang[$loai_kho])) {
    $ma_loai_hang = $mapping_loai_kho_hang[$loai_kho];
    $sql_dvt = "SELECT DISTINCT don_vi_tinh FROM hang_hoa WHERE ma_loai_hang = ?";
    $params_dvt = [$ma_loai_hang];
    
    // Thêm điều kiện phân quyền nếu cần
    if (!empty($kho_condition)) {
        // Lấy DVT từ các kho có quyền
        $sql_dvt = "
            SELECT DISTINCT hh.don_vi_tinh 
            FROM hang_hoa hh
            JOIN the_kho tk ON hh.ma_hang = tk.ma_hang
            JOIN kho k ON tk.ma_kho = k.ma_kho
            WHERE hh.ma_loai_hang = ?
        ";
        if ($role === 'Thủ kho' && $ma_nd) {
            $sql_dvt .= " AND k.ma_nd = ?";
            $params_dvt[] = $ma_nd;
        } elseif ($role === 'Quản lý kho' && $ma_nd) {
            $sql_dvt .= " AND k.ma_kho IN (
                SELECT k2.ma_kho 
                FROM kho k2 
                JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
                WHERE pq.ma_nd = ?
            )";
            $params_dvt[] = $ma_nd;
        }
    }
    $sql_dvt .= " ORDER BY don_vi_tinh";
    $stmt_dvt = $pdo->prepare($sql_dvt);
    $stmt_dvt->execute($params_dvt);
    $danh_sach_dvt = $stmt_dvt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Tổng Hợp - Báo Cáo Kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .body {
        font-family: "Inter", "sans-serif";
    }

    .container-main {
        background-color: white;
        max-height: calc(100vh - 40px);
        overflow-x: hidden;
        overflow-y: auto; 
    }

    .filter-section {
        background-color: #f9f9f9;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        margin-left: 10px;
        margin-right: 10px;
    }

    .report-title {
        text-align: center;
        color: #333;
        margin-bottom: 30px;
        border-bottom: 3px solid #007bff;
        padding-bottom: 15px;
    }

    .report-title h1 {
        font-weight: bold;
        margin-bottom: 10px;
        font-size: 24px;
        color: #007bff;
    }

    .btn-back {
        background-color: #007bff;
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

    .btn-export {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 30px;
        font-size: 16px;
        font-weight: bold;
    }

    .btn-export:hover {
        background-color: #1c76bb;
        color: white;
    }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-[#111418] dark:text-white min-h-screen min-h-0">
    <?php include '../include/sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-h-screen relative">
        <?php include '../include/header.php'; ?>
        <div class="container-main">
            <a href="baocao_bancandoi.php" class="btn btn-secondary btn-back">
                ← 
            </a>

            <div class="report-title">
                <h1>📑 BÁO CÁO TỔNG HỢP</h1>
                <p>Xuất Excel báo cáo tổng hợp gồm 3 bảng: Biến động kho, Biến động hàng hóa, Bảng cân đối</p>
            </div>

            <!-- Phần lọc dữ liệu -->
            <div class="filter-section">
                <h3 class="mb-3">Bộ lọc báo cáo</h3>
                <form method="GET" action="xuat_excel_bao_cao_tong_hop.php" class="row g-3" id="filterForm">
                    <div style="display: flex; align-items: center; justify-content: space-between;" >
                        <div class="col-md-3">
                            <label for="ma_vung" class="form-label">Vùng miền</label>
                            <select class="form-select" id="ma_vung" name="ma_vung">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_vung as $v): ?>
                                <option value="<?php echo htmlspecialchars($v['ma_vung']); ?>"
                                    <?php echo $ma_vung == $v['ma_vung'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v['ten_vung']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="loai_kho" class="form-label">Loại kho</label>
                            <select class="form-select" id="loai_kho" name="loai_kho" onchange="updateDVTList()">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_loai_kho as $lk): ?>
                                <option value="<?php echo htmlspecialchars($lk['ma_loai_kho']); ?>"
                                    <?php echo $loai_kho == $lk['ma_loai_kho'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lk['ten_loai_kho']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="don_vi_tinh" class="form-label">Đơn vị tính</label>
                            <select class="form-select" id="don_vi_tinh" name="don_vi_tinh">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_dvt as $dvt): ?>
                                <option value="<?php echo htmlspecialchars($dvt['don_vi_tinh']); ?>"
                                    <?php echo $don_vi_tinh == $dvt['don_vi_tinh'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dvt['don_vi_tinh']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 175px;">
                        <div class="col-md-3">
                            <label for="ngay_bat_dau" class="form-label">Từ ngày</label>
                            <input type="date" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau"
                                value="<?php echo htmlspecialchars($ngay_bat_dau); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="ngay_ket_thuc" class="form-label">Đến ngày</label>
                            <input type="date" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc"
                                value="<?php echo htmlspecialchars($ngay_ket_thuc); ?>">
                        </div>
                        <div class="col-md-12" style=" margin-top: 30px;">
                            <button type="submit" class="btn btn-export">
                                📥 Xuất Excel Báo Cáo Tổng Hợp
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="filter-section">
                <h5>📋 Nội dung báo cáo tổng hợp:</h5>
                <ul>
                    <li><strong>Bảng 1:</strong> Biến động kho - Tình hình nhập xuất theo ngày theo đơn vị tính</li>
                    <li><strong>Bảng 2:</strong> Biến động hàng hóa của loại kho - Tình hình nhập xuất, tồn đầu, tồn cuối</li>
                    <li><strong>Bảng 3:</strong> Bảng cân đối kho - Tổng hợp theo loại kho và đơn vị tính</li>
                </ul>
            </div>
        </div>
    </div>
    </div>

    <script>
    // Mapping giữa loại kho và loại hàng
    const mappingLoaiKhoHang = {
        'L001': 'M001',
        'L002': 'M002',
        'L003': 'M003',
        'L004': 'M004'
    };

    // Dữ liệu tất cả hàng hóa
    const allHangHoa = <?php 
    $stmt_all = $pdo->query("SELECT ma_hang, ten_hang, ma_loai_hang, don_vi_tinh FROM hang_hoa ORDER BY ma_hang");
    $all_hangs = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($all_hangs);
?>;

    // Cập nhật danh sách DVT
    function updateDVTList() {
        const loaiKhoSelect = document.getElementById('loai_kho');
        const donViTinhSelect = document.getElementById('don_vi_tinh');
        const selectedLoaiKho = loaiKhoSelect.value;

        let selectedLoaiHang = '';
        if (selectedLoaiKho && mappingLoaiKhoHang[selectedLoaiKho]) {
            selectedLoaiHang = mappingLoaiKhoHang[selectedLoaiKho];
        }

        let filteredHangs;
        if (selectedLoaiHang) {
            filteredHangs = allHangHoa.filter(h => h.ma_loai_hang === selectedLoaiHang);
        } else {
            filteredHangs = allHangHoa;
        }

        const uniqueDVTs = [...new Set(filteredHangs.map(h => h.don_vi_tinh))];
        uniqueDVTs.sort();

        const currentValue = donViTinhSelect.value;
        donViTinhSelect.innerHTML = '<option value="">-- Tất cả --</option>';

        uniqueDVTs.forEach(dvt => {
            const option = document.createElement('option');
            option.value = dvt;
            option.textContent = dvt;
            donViTinhSelect.appendChild(option);
        });

        if (uniqueDVTs.includes(currentValue)) {
            donViTinhSelect.value = currentValue;
        } else {
            donViTinhSelect.value = '';
        }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
