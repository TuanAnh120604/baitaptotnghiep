<?php
include '../include/connect.php';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Kho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .body {
        font-family: "Inter", "sans-serif";
    }

    .container-main {
        background-color: white;    
        overflow: auto;
    }

    .report-title {
        text-align: center;
        color: #333;
        margin-top: 20px;
        margin-bottom: 40px;
        border-bottom: 3px solid #202020;
        padding-bottom: 20px;
    }

    .report-title h1 {
        font-weight: bold;
        font-size: 2.5em;
        margin-bottom: 10px;
        color: #202020;
    }

    .report-title p {
        color: #666;
        font-size: 1.1em;
        margin: 5px 0;
    }

    .cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        display: flex;
        justify-content: center;
        gap: 10px;
        margin: auto 15px;
    }

    .report-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
        border-top: 4px solid;
        cursor: pointer;
    }

    .report-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        text-decoration: none;
        color: inherit;
    }

    .report-card.card-chart1 {
        border-top-color: #007bff;
    }

    .report-card.card-chart1 .card-icon {
        color: #007bff;
    }

    .report-card.card-chart2 {
        border-top-color: #007bff;
    }

    .report-card.card-chart2 .card-icon {
        color: #007bff;
    }

    .report-card.card-table {
        border-top-color: #007bff;
    }

    .report-card.card-table .card-icon {
        color: #007bff;
    }

    .card-icon {
        font-size: 3em;
        margin-bottom: 15px;
        display: block;
    }

    .card-title {
        font-size: 1.5em;
        font-weight: bold;
        margin-bottom: 10px;
    }

    .card-description {
        color: #666;
        font-size: 0.95em;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .card-features {
        font-size: 0.85em;
        color: #999;
    }

    .card-features ul {
        margin: 0;
        padding-left: 20px;
    }

    .card-features li {
        margin: 5px 0;
    }

    .btn-access {
        display: inline-block;
        margin-top: 15px;
        padding: 8px 20px;
        background-color: #007bff;
        color: white;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: background-color 0.3s;
    }

    .btn-access:hover {
        background-color: #0056b3;
        text-decoration: none;
    }

    .summary-section {
        padding: 30px;
        margin-top: 40px;
    }

    .summary-section h3 {
        color: #333;
        margin-bottom: 20px;
        font-weight: bold;
    }

    .summary-text {
        color: #666;
        line-height: 1.8;
        margin-bottom: 15px;
    }
    </style>
</head>

<body
    class="bg-background-light dark:bg-background-dark font-display text-[#111418] dark:text-white min-h-screen min-h-0">

    <?php include '../include/sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-h-screen relative">
        <?php include '../include/header.php'; ?>
        <div class="container-main">
            <div class="report-title">
                <h1>📊 HỆ THỐNG BÁO CÁO KHO</h1>
                <p>Lựa chọn báo cáo bạn muốn xem</p>
                <p style="font-size: 0.9em; color: #999; margin-top: 15px;">
                    Mỗi báo cáo có bộ lọc riêng độc lập
                </p>
            </div>

            <!-- Các thẻ báo cáo -->
            <div class="cards-container">
                <!-- Card 1: Biểu đồ loại kho -->
                <a href="baocao_chart1_loai_kho.php" class="report-card card-chart1">
                    <span class="card-icon">📈</span>
                    <div class="card-title">Biểu Đồ Loại Kho</div>
                    <div class="card-description">
                        Xem biểu đồ tổng lượng nhập theo từng loại kho
                    </div>
                    <div class="card-features">
                        <strong>Bộ lọc:</strong>
                        <ul>
                            <li>Vùng miền</li>
                            <li>Loại kho</li>
                            <li>Đơn vị tính</li>
                            <li>Khoảng thời gian</li>
                        </ul>
                    </div>
                    <div class="btn-access">Xem báo cáo →</div>
                </a>

                <!-- Card 2: Biểu đồ hàng hóa -->
                <a href="baocao_chart2_hang_hoa.php" class="report-card card-chart2">
                    <span class="card-icon">📊</span>
                    <div class="card-title">Biểu Đồ Hàng Hóa</div>
                    <div class="card-description">
                        Xem biến động nhập/xuất hàng hóa theo từng ngày
                    </div>
                    <div class="card-features">
                        <strong>Bộ lọc:</strong>
                        <ul>
                            <li>Vùng miền</li>
                            <li>Loại kho</li>
                            <li>Hàng hóa</li>
                            <li>Khoảng thời gian</li>
                        </ul>
                    </div>
                    <div class="btn-access">Xem báo cáo →</div>
                </a>

                <!-- Card 3: Bảng cân đối -->
                <a href="baocao_table_can_doi.php" class="report-card card-table">
                    <span class="card-icon">📋</span>
                    <div class="card-title">Bảng Cân Đối</div>
                    <div class="card-description">
                        Xem chi tiết tồn kho, nhập, xuất cho từng hàng hóa
                    </div>
                    <div class="card-features">
                        <strong>Bộ lọc:</strong>
                        <ul>
                            <li>Vùng miền</li>
                            <li>Loại kho</li>
                            <li>Khoảng thời gian</li>
                        </ul>
                    </div>
                    <div class="btn-access">Xem báo cáo →</div>
                </a>
            </div>

            <!-- Phần tóm tắt -->
            <div class="summary-section">
                <h3>ℹ️ Hướng dẫn sử dụng</h3>
                <div class="summary-text">
                    <strong>Biểu Đồ Loại Kho:</strong> Hiển thị tổng lượng hàng nhập vào mỗi loại kho.
                    Sử dụng bộ lọc để phân tích theo vùng miền, loại kho hoặc đơn vị tính cụ thể.
                </div>
                <div class="summary-text">
                    <strong>Biểu Đồ Hàng Hóa:</strong> Hiển thị xu hướng nhập/xuất hàng hóa qua các ngày.
                    Lọc theo vùng, loại kho hoặc hàng hóa cụ thể để theo dõi chi tiết.
                </div>
                <div class="summary-text">
                    <strong>Bảng Cân Đối:</strong> Cung cấp chi tiết đầy đủ về tồn kho (tồn đầu kỳ, nhập, xuất, tồn cuối
                    kỳ)
                    cho mỗi hàng hóa trong kho.
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>