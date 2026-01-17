<meta charset="utf-8" />

<meta content="width=device-width, initial-scale=1.0" name="viewport" />
<title>Quản lý Đại lý - Agent Management System</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com" rel="preconnect" />
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet" />
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#2563EB", // Standard Tailwind Blue-600
                    "primary-dark": "#1D4ED8", // Darker blue for hover
                    "background-light": "#F3F4F6", // Gray-100
                    "background-dark": "#111827", // Gray-900
                    "surface-light": "#FFFFFF",
                    "surface-dark": "#1F2937", // Gray-800
                    "border-light": "#E5E7EB", // Gray-200
                    "border-dark": "#374151", // Gray-700
                },
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                },
                borderRadius: {
                    DEFAULT: "0.5rem", // 8px
                },
            },
        },
    };
</script>
</head>

<body class="bg-background-light dark:bg-background-dark text-gray-800 dark:text-gray-100 font-sans antialiased min-h-screen transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
        <aside class="hidden md:flex flex-col w-64 bg-surface-light dark:bg-surface-dark border-r border-border-light dark:border-border-dark transition-colors duration-200">
            <div class="h-16 flex items-center px-6 border-b border-border-light dark:border-border-dark">
                <span class="text-primary text-2xl mr-2">
                    <span class="material-icons-round">inventory_2</span>
                </span>
                <span class="text-lg font-bold tracking-tight">Quản lý kho</span>
            </div>
            <div class="flex-1 overflow-y-auto py-4">
                <nav class="space-y-1 px-3">
                    <?php
                    // Include permissions logic
                    include_once 'permissions.php';
                    ?>
                    <?php if (canView('thongke')): ?>
                    <a class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/index.php">
                        <span class="material-icons-round text-xl mr-3">analytics</span>
                        Thống kê
                    </a>
                    <?php endif; ?>
                    <?php if (canView('loaikho') || canView('danhsachkho')): ?>
                    <div>
                        <button id="khoMenu-btn" aria-expanded="false" onclick="toggleMenu('khoMenu')"
                            class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <span class="material-icons-round text-xl mr-3">warehouse</span>
                            <span class="flex-1 text-left">Kho</span>
                            <span class="material-icons-round text-lg text-gray-400">expand_more</span>
                        </button>

                        <div id="khoMenu" class="mt-1 ml-3 space-y-1 hidden" aria-hidden="true">
                            <?php if (canView('loaikho')): ?>
                            <a class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/LoaiKho/loaikho.php">
                                <span class="w-3 h-3 rounded-full bg-primary/20 mr-3"></span>
                                Loại kho
                            </a>
                            <?php endif; ?>
                            <?php if (canView('danhsachkho')): ?>
                            <a class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/DanhSachKho/danhsachkho.php">
                                <span class="w-3 h-3 rounded-full bg-primary/20 mr-3"></span>
                                Danh sách kho
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (canView('vattu') || canView('thanhpham')): ?>
                    <div>
                        <button id="productMenu-btn" aria-expanded="false" onclick="toggleMenu('productMenu')"
                            class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <span class="material-icons-round text-xl mr-3">category</span>
                            <span class="flex-1 text-left">Mặt hàng</span>
                            <span class="material-icons-round text-lg text-gray-400">expand_more</span>
                        </button>

                        <div id="productMenu" class="mt-1 ml-3 space-y-1 hidden" aria-hidden="true">
                            <?php if (canView('vattu')): ?>
                            <a class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/VatTu/vattu.php">
                                <span class="w-3 h-3 rounded-full bg-primary/20 mr-3"></span>
                                Vật tư
                            </a>
                            <?php endif; ?>
                            <?php if (canView('thanhpham')): ?>
                            <a class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/ThanhPham/thanhpham.php">
                                <span class="w-3 h-3 rounded-full bg-primary/20 mr-3"></span>
                                Thành phẩm
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (canView('thekho')): ?>
                    <a class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/TheKho/thekho.php">
                        <span class="material-icons-round text-xl mr-3">receipt_long</span>
                        Thẻ kho
                    </a>
                    <?php endif; ?>
                    <?php if (canView('phieunhap')): ?>
                    <a class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/PhieuNhap/phieunhap.php">
                        <span class="material-icons-round text-xl mr-3">input</span>
                        Phiếu nhập
                    </a>
                    <?php endif; ?>
                    <?php if (canView('phieuxuat')): ?>
                    <a class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/PhieuXuat/phieuxuat.php">
                        <span class="material-icons-round text-xl mr-3">output</span>
                        Phiếu xuất
                    </a>
                    <?php endif; ?>
                    <?php if (canView('daily') || canView('nhacungcap')): ?>
                    <div class="">
                        <button id="agentsMenu-btn" aria-expanded="false" onclick="toggleMenu('agentsMenu')" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            <span class="material-icons-round text-xl mr-3">storefront</span>
                            <span class="flex-1 text-left">Đại lý và Nhà cung cấp</span>
                            <span class="material-icons-round text-lg text-gray-400">expand_more</span>
                        </button>

                        <div id="agentsMenu" class="mt-1 ml-3 space-y-1 hidden" aria-hidden="true">
                            <?php if (canView('daily')): ?>
                            <a class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/DaiLy/daily.php">
                                <span class="w-3 h-3 rounded-full bg-primary/20 mr-3"></span>
                                Đại lý
                            </a>
                            <?php endif; ?>
                            <?php if (canView('nhacungcap')): ?>
                            <a class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/NhaCungCap/nhacungcap.php">
                                <span class="w-3 h-3 rounded-full bg-primary/20 mr-3"></span>
                                Nhà cung cấp
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (canView('nguoidung')): ?>
                    <a class="flex items-center px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/NguoiDung/nguoidung.php">
                        <span class="material-icons-round text-xl mr-3">group</span>
                        Người dùng
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="p-4 border-t border-border-light dark:border-border-dark">
               
                <div class="space-y-2">
                    <?php if (canView('caidat')): ?>
                    <a class="flex items-center w-full px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" href="/baitaptotnghiep-main/CaiDat/caidat.php">
                        <span class="material-icons-round text-xl mr-3">settings</span>
                        Cài đặt
                    </a>
                    <?php endif; ?>
                    <a class="flex items-center w-full px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" href="../dn/logout.php">
                        <span class="material-icons-round text-xl mr-3">logout</span>
                        Đăng xuất
                    </a>
                </div>
            </div>
        </aside>
        <script>
            function toggleMenu(id) {
                const el = document.getElementById(id);
                const btn = document.getElementById(id + '-btn');
                if (!el || !btn) return;

                const isHidden = el.classList.contains('hidden');

                if (isHidden) {
                    el.classList.remove('hidden');
                    el.setAttribute('aria-hidden', 'false');
                    btn.setAttribute('aria-expanded', 'true');
                    localStorage.setItem(id, 'open');
                } else {
                    el.classList.add('hidden');
                    el.setAttribute('aria-hidden', 'true');
                    btn.setAttribute('aria-expanded', 'false');
                    localStorage.removeItem(id);
                }
            }

            // Khôi phục trạng thái menu khi load trang
            document.addEventListener('DOMContentLoaded', function() {
                const menus = ['khoMenu', 'productMenu', 'agentsMenu'];

                menus.forEach(id => {
                    if (localStorage.getItem(id) === 'open') {
                        const el = document.getElementById(id);
                        const btn = document.getElementById(id + '-btn');
                        if (el && btn) {
                            el.classList.remove('hidden');
                            el.setAttribute('aria-hidden', 'false');
                            btn.setAttribute('aria-expanded', 'true');
                        }
                    }
                });

                // Load trang đầu tiên nếu cần (tùy chọn)
                // loadContent('/baitaptotnghiep-main/index.php');
            });

            // Hàm load nội dung chính (không reload trang)
            async function loadContent(url) {
                const main = document.getElementById('main-content');
                if (!main) return;

                try {
                    main.innerHTML = '<div class="text-center py-12"><p class="text-lg">Đang tải...</p></div>';

                    const response = await fetch(url);
                    if (!response.ok) throw new Error(`Lỗi ${response.status}`);

                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Lấy nội dung chính (ưu tiên id="main-content", nếu không thì toàn bộ body)
                    let newContent = doc.querySelector('#main-content') || doc.body;

                    main.innerHTML = newContent.innerHTML;

                    // Highlight menu item active
                    document.querySelectorAll('.menu-link').forEach(link => {
                        link.classList.remove('bg-primary/10', 'text-primary', 'font-semibold');
                    });

                    const activeLink = [...document.querySelectorAll('.menu-link')].find(link =>
                        link.getAttribute('onclick')?.includes(url)
                    );
                    if (activeLink) {
                        activeLink.classList.add('bg-primary/10', 'text-primary', 'font-semibold');
                    }

                    // Cập nhật URL trên thanh địa chỉ
                    history.pushState({
                        url
                    }, '', url);

                } catch (err) {
                    console.error(err);
                    main.innerHTML = `
                    <div class="text-center py-12 text-red-600 dark:text-red-400">
                        <h2 class="text-xl font-bold mb-2">Không tải được nội dung</h2>
                        <p>${err.message}</p>
                    </div>`;
                }
            }

            // Hỗ trợ nút back/forward trình duyệt
            window.addEventListener('popstate', (event) => {
                if (event.state && event.state.url) {
                    loadContent(event.state.url);
                }
            });
        </script>