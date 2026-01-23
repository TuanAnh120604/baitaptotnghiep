<?php
include '../include/connect.php';

// Lấy các tham số lọc cho BIỂU ĐỒ LOẠI KHO
$chart1_ma_vung = isset($_GET['chart1_ma_vung']) ? $_GET['chart1_ma_vung'] : '';
$chart1_loai_kho = isset($_GET['chart1_loai_kho']) ? $_GET['chart1_loai_kho'] : '';
$chart1_don_vi_tinh = isset($_GET['chart1_don_vi_tinh']) ? $_GET['chart1_don_vi_tinh'] : '';
$chart1_ngay_bat_dau = isset($_GET['chart1_ngay_bat_dau']) ? $_GET['chart1_ngay_bat_dau'] : date('Y-01-01');
$chart1_ngay_ket_thuc = isset($_GET['chart1_ngay_ket_thuc']) ? $_GET['chart1_ngay_ket_thuc'] : date('Y-m-d');

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

// Lấy danh sách DVT dựa trên loại kho được chọn
$danh_sach_dvt = [];
if (!empty($chart1_loai_kho) && isset($mapping_loai_kho_hang[$chart1_loai_kho])) {
    $ma_loai_hang = $mapping_loai_kho_hang[$chart1_loai_kho];
    $stmt_dvt = $pdo->prepare("SELECT DISTINCT don_vi_tinh FROM hang_hoa WHERE ma_loai_hang = ? ORDER BY don_vi_tinh");
    $stmt_dvt->execute([$ma_loai_hang]);
    $danh_sach_dvt = $stmt_dvt->fetchAll(PDO::FETCH_ASSOC);
}

// ============ QUERY: BIỂU ĐỒ BIẾN ĐỘNG THEO LOẠI KHO ============
$sql_bieu_do_loai_kho = "
    SELECT 
        lk.ten_loai_kho,
        COALESCE(SUM(ct.so_luong_nhap), 0) as tong_nhap
    FROM kho k
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    LEFT JOIN phieu_nhap pn ON k.ma_kho = pn.ma_kho AND pn.ngay_nhap BETWEEN ? AND ?
    LEFT JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap
    LEFT JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
    WHERE 1=1
";

$params_bieu_do_loai_kho = [$chart1_ngay_bat_dau, $chart1_ngay_ket_thuc];

if (!empty($chart1_ma_vung)) {
    $sql_bieu_do_loai_kho .= " AND k.ma_vung = ?";
    $params_bieu_do_loai_kho[] = $chart1_ma_vung;
}

if (!empty($chart1_loai_kho)) {
    $sql_bieu_do_loai_kho .= " AND k.ma_loai_kho = ?";
    $params_bieu_do_loai_kho[] = $chart1_loai_kho;
}

if (!empty($chart1_don_vi_tinh)) {
    $sql_bieu_do_loai_kho .= " AND hh.don_vi_tinh = ?";
    $params_bieu_do_loai_kho[] = $chart1_don_vi_tinh;
}

$sql_bieu_do_loai_kho .= " GROUP BY lk.ten_loai_kho ORDER BY lk.ma_loai_kho";

$stmt_bieu_do = $pdo->prepare($sql_bieu_do_loai_kho);
$stmt_bieu_do->execute($params_bieu_do_loai_kho);
$data_bieu_do_loai_kho_temp = $stmt_bieu_do->fetchAll(PDO::FETCH_ASSOC);

// Gộp dữ liệu loại kho
$data_bieu_do = [];
foreach ($data_bieu_do_loai_kho_temp as $row) {
    $key = $row['ten_loai_kho'];
    if (!isset($data_bieu_do[$key])) {
        $data_bieu_do[$key] = ['nhap' => 0, 'xuat' => 0, 'ton' => 0];
    }
    $data_bieu_do[$key]['nhap'] += (int)$row['tong_nhap'];
    $data_bieu_do[$key]['xuat'] = 0;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biểu Đồ Loại Kho - Báo Cáo Kho</title>
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
        margin-bottom: 5px;
        font-size: 24px;
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
                <h1>📈 BIỂU ĐỒ BIẾN ĐỘNG THEO LOẠI KHO</h1>
                <p>Xem tổng lượng nhập theo loại kho trong khoảng thời gian được chọn</p>
            </div>

            <!-- Phần lọc dữ liệu -->
            <div class="filter-section">
                <h3 class="mb-3">Bộ lọc biểu đồ</h3>
                <form method="GET" class="row g-3" id="filterForm">
                    <div class="col-md-2">
                        <label for="chart1_ma_vung" class="form-label">Vùng miền</label>
                        <select class="form-select" id="chart1_ma_vung" name="chart1_ma_vung">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_vung as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['ma_vung']); ?>"
                                <?php echo $chart1_ma_vung == $v['ma_vung'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['ten_vung']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="chart1_loai_kho" class="form-label">Loại kho</label>
                        <select class="form-select" id="chart1_loai_kho" name="chart1_loai_kho"
                            onchange="updateDVTList()">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_loai_kho as $lk): ?>
                            <option value="<?php echo htmlspecialchars($lk['ma_loai_kho']); ?>"
                                <?php echo $chart1_loai_kho == $lk['ma_loai_kho'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lk['ten_loai_kho']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="chart1_don_vi_tinh" class="form-label">Đơn vị tính</label>
                        <select class="form-select" id="chart1_don_vi_tinh" name="chart1_don_vi_tinh">
                            <option value="">-- Tất cả --</option>
                            <?php foreach ($danh_sach_dvt as $dvt): ?>
                            <option value="<?php echo htmlspecialchars($dvt['don_vi_tinh']); ?>"
                                <?php echo $chart1_don_vi_tinh == $dvt['don_vi_tinh'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dvt['don_vi_tinh']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="chart1_ngay_bat_dau" class="form-label">Từ ngày</label>
                        <input type="date" class="form-control" id="chart1_ngay_bat_dau" name="chart1_ngay_bat_dau"
                            value="<?php echo htmlspecialchars($chart1_ngay_bat_dau); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="chart1_ngay_ket_thuc" class="form-label">Đến ngày</label>
                        <input type="date" class="form-control" id="chart1_ngay_ket_thuc" name="chart1_ngay_ket_thuc"
                            value="<?php echo htmlspecialchars($chart1_ngay_ket_thuc); ?>">
                    </div>
                    <div class="col-md-2" style="margin-top: 27px;">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary w-100">Lọc dữ liệu</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Biểu đồ biến động theo loại kho -->
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <canvas id="chartLoaiKho"></canvas>
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

    // Cập nhật danh sách DVT
    function updateDVTList() {
        const loaiKhoSelect = document.getElementById('chart1_loai_kho');
        const donViTinhSelect = document.getElementById('chart1_don_vi_tinh');
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

    // Dữ liệu biểu đồ
    const dataLoaiKho = <?php echo json_encode($data_bieu_do); ?>;
    const labelsLoaiKho = Object.keys(dataLoaiKho);
    const nhapLoaiKho = labelsLoaiKho.map(label => dataLoaiKho[label].nhap);
    const xuatLoaiKho = labelsLoaiKho.map(label => dataLoaiKho[label].xuat);
    const tonLoaiKho = labelsLoaiKho.map(label => dataLoaiKho[label].ton);

    const ctxLoaiKho = document.getElementById('chartLoaiKho').getContext('2d');
    new Chart(ctxLoaiKho, {
        type: 'bar',
        data: {
            labels: labelsLoaiKho,
            datasets: [{
                    label: 'Lượng nhập',
                    data: nhapLoaiKho,
                    backgroundColor: '#28a745',
                    borderColor: '#20c997',
                    borderWidth: 1
                },
                {
                    label: 'Lượng xuất',
                    data: xuatLoaiKho,
                    backgroundColor: '#dc3545',
                    borderColor: '#e74c3c',
                    borderWidth: 1
                },
                {
                    label: 'Tồn cuối kỳ',
                    data: tonLoaiKho,
                    backgroundColor: '#007bff',
                    borderColor: '#0056b3',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Biến động theo loại kho'
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
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