<?php
include '../include/connect.php';


// Lấy các tham số lọc
$chart2_ma_vung = isset($_GET['chart2_ma_vung']) ? $_GET['chart2_ma_vung'] : '';
$chart2_loai_kho = isset($_GET['chart2_loai_kho']) ? $_GET['chart2_loai_kho'] : '';
$chart2_ma_hang = isset($_GET['chart2_ma_hang']) ? $_GET['chart2_ma_hang'] : '';
$chart2_ngay_bat_dau = isset($_GET['chart2_ngay_bat_dau']) ? $_GET['chart2_ngay_bat_dau'] : date('Y-01-01');
$chart2_ngay_ket_thuc = isset($_GET['chart2_ngay_ket_thuc']) ? $_GET['chart2_ngay_ket_thuc'] : date('Y-m-d');

// Mapping giữa loại kho và loại hàng
$mapping_loai_kho_hang = [
    'L001' => 'M001',
    'L002' => 'M002',
    'L003' => 'M003',
    'L004' => 'M004'
];

// Lấy danh sách vùng miền
$stmt_vung = $pdo->query("SELECT * FROM vung_mien ORDER BY ten_vung");
$danh_sach_vung = $stmt_vung->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách loại kho
$stmt_loai_kho = $pdo->query("SELECT * FROM loai_kho ORDER BY ma_loai_kho");
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách hàng hóa
$danh_sach_hang = [];
if (!empty($chart2_loai_kho) && isset($mapping_loai_kho_hang[$chart2_loai_kho])) {
    $ma_loai_hang = $mapping_loai_kho_hang[$chart2_loai_kho];
    $stmt_hang = $pdo->prepare("SELECT * FROM hang_hoa WHERE ma_loai_hang = ? ORDER BY ma_hang");
    $stmt_hang->execute([$ma_loai_hang]);
    $danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_hang = $pdo->query("SELECT * FROM hang_hoa ORDER BY ma_hang");
    $danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);
}

// ============ QUERY: BIỂU ĐỒ HÀNG HÓA THEO NGÀY ============
$ngay_hien_tai = strtotime($chart2_ngay_bat_dau);
$ngay_ket_thuc_ts = strtotime($chart2_ngay_ket_thuc);
$danh_sach_ngay = [];

while ($ngay_hien_tai <= $ngay_ket_thuc_ts) {
    $danh_sach_ngay[date('Y-m-d', $ngay_hien_tai)] = [
        'nhap' => 0,
        'xuat' => 0,
        'ton' => 0
    ];
    $ngay_hien_tai = strtotime('+1 day', $ngay_hien_tai);
}

// Lấy dữ liệu nhập theo ngày
$sql_daily_nhap = "
    SELECT 
        DATE(pn.ngay_nhap) as ngay,
        SUM(ct.so_luong_nhap) as so_luong
    FROM phieu_nhap pn
    JOIN kho k ON pn.ma_kho = k.ma_kho
    JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap
    WHERE pn.ngay_nhap BETWEEN ? AND ?
";

$params_daily = [$chart2_ngay_bat_dau, $chart2_ngay_ket_thuc];

if (!empty($chart2_ma_vung)) {
    $sql_daily_nhap .= " AND k.ma_vung = ?";
    $params_daily[] = $chart2_ma_vung;
}

if (!empty($chart2_loai_kho)) {
    $sql_daily_nhap .= " AND k.ma_loai_kho = ?";
    $params_daily[] = $chart2_loai_kho;
}

if (!empty($chart2_ma_hang)) {
    $sql_daily_nhap .= " AND ct.ma_hang = ?";
    $params_daily[] = $chart2_ma_hang;
}

$sql_daily_nhap .= " GROUP BY DATE(pn.ngay_nhap) ORDER BY pn.ngay_nhap";

$stmt_daily = $pdo->prepare($sql_daily_nhap);
$stmt_daily->execute($params_daily);
$daily_nhap = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

// Lấy dữ liệu xuất theo ngày
$sql_daily_xuat = "
    SELECT 
        DATE(px.ngay_xuat) as ngay,
        SUM(ct.so_luong_xuat) as so_luong
    FROM phieu_xuat px
    JOIN kho k ON px.ma_kho = k.ma_kho
    JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat
    WHERE px.ngay_xuat BETWEEN ? AND ?
";

$params_daily_xuat = [$chart2_ngay_bat_dau, $chart2_ngay_ket_thuc];

if (!empty($chart2_ma_vung)) {
    $sql_daily_xuat .= " AND k.ma_vung = ?";
    $params_daily_xuat[] = $chart2_ma_vung;
}

if (!empty($chart2_loai_kho)) {
    $sql_daily_xuat .= " AND k.ma_loai_kho = ?";
    $params_daily_xuat[] = $chart2_loai_kho;
}

if (!empty($chart2_ma_hang)) {
    $sql_daily_xuat .= " AND ct.ma_hang = ?";
    $params_daily_xuat[] = $chart2_ma_hang;
}

$sql_daily_xuat .= " GROUP BY DATE(px.ngay_xuat) ORDER BY px.ngay_xuat";

$stmt_daily_xuat = $pdo->prepare($sql_daily_xuat);
$stmt_daily_xuat->execute($params_daily_xuat);
$daily_xuat = $stmt_daily_xuat->fetchAll(PDO::FETCH_ASSOC);

// Gộp dữ liệu theo ngày
foreach ($daily_nhap as $row) {
    $ngay = $row['ngay'];
    if (isset($danh_sach_ngay[$ngay])) {
        $danh_sach_ngay[$ngay]['nhap'] = (int)$row['so_luong'];
    }
}

foreach ($daily_xuat as $row) {
    $ngay = $row['ngay'];
    if (isset($danh_sach_ngay[$ngay])) {
        $danh_sach_ngay[$ngay]['xuat'] = (int)$row['so_luong'];
    }
}

// Tính tồn theo ngày
$ton_hien_tai = 0;
foreach ($danh_sach_ngay as &$data) {
    $ton_hien_tai = $ton_hien_tai + $data['nhap'] - $data['xuat'];
    $data['ton'] = $ton_hien_tai;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biểu Đồ Hàng Hóa - Báo Cáo Kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
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
        margin-bottom: 20px;
        margin-left: 10px;
        margin-right: 10px;
    }

    .chart-container {
        position: relative;
        height: 400px;
        margin-bottom: 30px;
        background: white;
        padding: 15px;
        border-radius: 5px;
        border: 1px solid #ddd;
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
        font-size: 24px;
        margin-bottom: 10px;
        color: #0d6efd;
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
                <h1>📊 BIỂU ĐỒ BIẾN ĐỘNG THEO HÀNG HÓA</h1>
                <p>Xem biến động nhập/xuất hàng hóa theo từng ngày</p>
            </div>

            <!-- Phần lọc dữ liệu -->
            <div class="filter-section">
                <h5 class="mb-3">Bộ lọc biểu đồ</h5>
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-2">
                        <label for="chart2_ma_vung" class="form-label">Vùng miền</label>
                        <select class="form-select" id="chart2_ma_vung" name="chart2_ma_vung">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_vung as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['ma_vung']); ?>"
                                <?php echo $chart2_ma_vung == $v['ma_vung'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['ten_vung']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="chart2_loai_kho" class="form-label">Loại kho</label>
                        <select class="form-select" id="chart2_loai_kho" name="chart2_loai_kho"
                            onchange="updateHangHoaList()">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_loai_kho as $lk): ?>
                            <option value="<?php echo htmlspecialchars($lk['ma_loai_kho']); ?>"
                                <?php echo $chart2_loai_kho == $lk['ma_loai_kho'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lk['ten_loai_kho']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="chart2_ma_hang" class="form-label">Hàng hóa</label>
                        <select class="form-select" id="chart2_ma_hang" name="chart2_ma_hang">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_hang as $h): ?>
                            <option value="<?php echo htmlspecialchars($h['ma_hang']); ?>"
                                <?php echo $chart2_ma_hang == $h['ma_hang'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['ma_hang'] . ' - ' . $h['ten_hang']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="chart2_ngay_bat_dau" class="form-label">Từ ngày</label>
                        <input type="date" class="form-control" id="chart2_ngay_bat_dau" name="chart2_ngay_bat_dau"
                            value="<?php echo htmlspecialchars($chart2_ngay_bat_dau); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="chart2_ngay_ket_thuc" class="form-label">Đến ngày</label>
                        <input type="date" class="form-control" id="chart2_ngay_ket_thuc" name="chart2_ngay_ket_thuc"
                            value="<?php echo htmlspecialchars($chart2_ngay_ket_thuc); ?>">
                    </div>
                    <div class="col-md-2" style="margin-top: 27px;">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-warning w-100">Lọc dữ liệu</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Biểu đồ biến động theo ngày -->
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <canvas id="chartHangHoa"></canvas>
                    </div>
                </div>
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

    // Cập nhật danh sách hàng hóa
    function updateHangHoaList() {
        const loaiKhoSelect = document.getElementById('chart2_loai_kho');
        const maHangSelect = document.getElementById('chart2_ma_hang');
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

        const currentValue = maHangSelect.value;
        maHangSelect.innerHTML = '<option value="">-- Tất cả --</option>';

        filteredHangs.forEach(hang => {
            const option = document.createElement('option');
            option.value = hang.ma_hang;
            option.textContent = hang.ma_hang + ' - ' + hang.ten_hang + ' (' + hang.don_vi_tinh + ')';
            maHangSelect.appendChild(option);
        });

        maHangSelect.value = currentValue;
    }

    // Dữ liệu biểu đồ
    const dataNgay = <?php echo json_encode($danh_sach_ngay); ?>;
    const labelsNgay = Object.keys(dataNgay);
    const nhapNgay = labelsNgay.map(ngay => dataNgay[ngay].nhap);
    const xuatNgay = labelsNgay.map(ngay => dataNgay[ngay].xuat);
    const tonNgay = labelsNgay.map(ngay => dataNgay[ngay].ton);

    const ctxHang = document.getElementById('chartHangHoa').getContext('2d');
    new Chart(ctxHang, {
        type: 'line',
        data: {
            labels: labelsNgay,
            datasets: [{
                    label: 'Lượng nhập',
                    data: nhapNgay,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#28a745'
                },
                {
                    label: 'Lượng xuất',
                    data: xuatNgay,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#dc3545'
                },
                {
                    label: 'Tồn cuối kỳ',
                    data: tonNgay,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#007bff'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Biến động theo ngày'
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Ngày'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN');
                        }
                    }
                }
            }
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>