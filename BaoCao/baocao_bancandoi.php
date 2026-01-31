<?php
include '../include/connect.php';
?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Nếu bạn đã có tailwind config riêng thì thay bằng link build css -->
    <!-- <link href="/css/output.css" rel="stylesheet"> -->

</head>

<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 antialiased flex flex-col" style="font-family: 'Inter', sans-serif;">

    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col">

        <?php include '../include/header.php'; ?>

        <main class="flex-1 overflow-auto bg-white dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

                <!-- Tiêu đề -->
                <div class="text-center mb-12">
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white tracking-tight">
                        📊 HỆ THỐNG BÁO CÁO KHO
                    </h1>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">
                        Lựa chọn báo cáo bạn muốn xem
                    </p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-500">
                        Mỗi báo cáo có bộ lọc riêng độc lập
                    </p>
                </div>

                <!-- Grid các thẻ báo cáo -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <a href="baocao_chart1_loai_kho.php"
                        class="group bg-white dark:bg-gray-700 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border-t-4 border-blue-600 flex flex-col h-full min-h-[420px]">

                        <div class="pt-10 pb-6 text-center text-6xl text-blue-600">📊</div>
                        <h2 class="px-6 text-2xl font-bold text-center text-gray-800 dark:text-gray-100 min-h-[60px] flex items-center justify-center">
                            Biến động hàng hoá theo kho
                        </h2>
                        <p class="px-6 mt-3 text-gray-600 dark:text-gray-300 text-center text-sm">
                            Biểu đồ biểu diễn lượng tồn kho của mặt hàng với các kho.
                        </p>
                        <div class="px-6 mt-4 text-sm text-gray-500 dark:text-gray-400 flex-grow">
                            <strong class="block mb-2">Bộ lọc:</strong>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Vùng miền</li>
                                <li>Loại kho</li>
                                <li>Hàng hóa</li>
                                <li>Khoảng thời gian</li>
                            </ul>
                        </div>
                        <div class="px-6 pb-8 mt-6">
                            <div class="w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-center transition-colors duration-200 group-hover:bg-blue-700">
                                Xem báo cáo →
                            </div>
                        </div>
                    </a>
                    <!-- Card 2 -->
                    <a href="baocao_chart2_hang_hoa.php"
                        class="group bg-white dark:bg-gray-700 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border-t-4 border-blue-600 flex flex-col h-full min-h-[420px]">

                        <div class="pt-10 pb-6 text-center text-6xl text-blue-600">📊</div>
                        <h2 class="px-6 text-2xl font-bold text-center text-gray-800 dark:text-gray-100 min-h-[60px] flex items-center justify-center">
                            Biến động tồn kho theo danh mục hàng hoá
                        </h2>
                        <p class="px-6 mt-3 text-gray-600 dark:text-gray-300 text-center text-sm">
                            Biểu đồ tồn kho của các mặt hàng theo ngày.
                        </p>
                        <div class="px-6 mt-4 text-sm text-gray-500 dark:text-gray-400 flex-grow">
                            <strong class="block mb-2">Bộ lọc:</strong>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Vùng miền</li>
                                <li>Loại kho</li>
                                 <li>Kho</li>
                                <li>Hàng hóa</li>
                                <li>Khoảng thời gian</li>
                            </ul>
                        </div>
                        <div class="px-6 pb-8 mt-6">
                            <div class="w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-center transition-colors duration-200 group-hover:bg-blue-700">
                                Xem báo cáo →
                            </div>
                        </div>
                    </a>

                    <!-- Card 3 -->
                    <a href="baocao_table_can_doi.php"
                        class="group bg-white dark:bg-gray-700 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border-t-4 border-blue-600 flex flex-col h-full min-h-[420px]">

                        <div class="pt-10 pb-6 text-center text-6xl text-blue-600">📋</div>
                        <h2 class="px-6 text-2xl font-bold text-center text-gray-800 dark:text-gray-100 min-h-[60px] flex items-center justify-center">
                            Bảng nhập-xuất-tồn
                        </h2>
                        <p class="px-6 mt-3 text-gray-600 dark:text-gray-300 text-center text-sm">
                            Xem chi tiết tồn kho, nhập, xuất cho từng hàng hóa
                        </p>
                        <div class="px-6 mt-4 text-sm text-gray-500 dark:text-gray-400 flex-grow">
                            <strong class="block mb-2">Bộ lọc:</strong>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Vùng miền</li>
                                <li>Loại kho</li>
                                <li>Khoảng thời gian</li>
                            </ul>
                        </div>
                        <div class="px-6 pb-8 mt-6">
                            <div class="w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-center transition-colors duration-200 group-hover:bg-blue-700">
                                Xem báo cáo →
                            </div>
                        </div>
                    </a>

                    <!-- Card 4 - Báo cáo tổng hợp -->
                    <a href="baocao_tong_hop.php"
                        class="group bg-white dark:bg-gray-700 rounded-xl shadow-lg hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 border-t-4 border-blue-600 flex flex-col h-full min-h-[420px]">

                        <div class="pt-10 pb-6 text-center text-6xl text-blue-600">📑</div>
                        <h2 class="px-6 text-2xl font-bold text-center text-gray-800 dark:text-gray-100 min-h-[60px] flex items-center justify-center">
                            Xuất báo cáo
                        </h2>
                        <p class="px-6 mt-3 text-gray-600 dark:text-gray-300 text-center text-sm">
                            Xuất Excel báo cáo tổng hợp 3 bảng: biến động kho, biến động hàng hóa, bảng cân đối
                        </p>
                        <div class="px-6 mt-4 text-sm text-gray-500 dark:text-gray-400 flex-grow">
                            <strong class="block mb-2">Bộ lọc:</strong>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Vùng miền</li>
                                <li>Loại kho</li>
                                <li>Đơn vị tính</li>
                                <li>Khoảng thời gian</li>
                            </ul>
                        </div>
                        <div class="px-6 pb-8 mt-6">
                            <div class="w-full py-3 px-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-center transition-colors duration-200 group-hover:bg-blue-700">
                                Xem báo cáo →
                            </div>
                        </div>
                    </a>

                </div>

                <!-- Phần hướng dẫn -->
                <section class="mt-16 bg-white dark:bg-gray-700 rounded-xl shadow-lg p-8">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6 flex items-center gap-3">
                        ℹ️ CHÚ THÍCH
                    </h3>
                    <div class="space-y-6 text-gray-600 dark:text-gray-300 leading-relaxed">
                        <p><strong>Biến Động Hàng Hoá Theo Kho:</strong> Hiển thị tổng lượng tồn kho của các kho theo mặt hàng. Sử dụng bộ lọc để phân tích theo vùng miền, loại kho và hàng hoá.</p>
                        <p><strong> Biểu Động Tồn Kho Theo Danh Mục Hàng Hoá:</strong> Hiển thị lượng tồn kho của các mặt hàng theo kho. Lọc theo vùng, loại kho, kho và các hàng hoá để theo dõi chi tiết.</p>
                        <p><strong>Bảng Cân Đối:</strong> Cung cấp chi tiết đầy đủ về tồn kho (tồn đầu kỳ, nhập, xuất, tồn cuối kỳ) cho mỗi hàng hóa trong kho.</p>
                    </div>
                </section>

            </div>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Nếu bạn vẫn muốn giữ bootstrap cho một số component thì để lại, còn không thì có thể bỏ -->

</body>

</html>