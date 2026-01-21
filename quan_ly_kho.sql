-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3307
-- Thời gian đã tạo: Th1 19, 2026 lúc 06:00 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quan_ly_kho`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ct_phieu_nhap`
--

CREATE TABLE `ct_phieu_nhap` (
  `ma_ctpn` varchar(20) NOT NULL,
  `ma_phieu_nhap` varchar(20) DEFAULT NULL,
  `ma_hang` varchar(20) DEFAULT NULL,
  `so_luong_nhap` int(11) NOT NULL,
  `don_gia` decimal(12,2) DEFAULT NULL,
  `thanh_tien` decimal(14,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `ct_phieu_nhap`
--

INSERT INTO `ct_phieu_nhap` (`ma_ctpn`, `ma_phieu_nhap`, `ma_hang`, `so_luong_nhap`, `don_gia`, `thanh_tien`) VALUES
('PN-TP-001-H003', 'PN-TP-001', 'H003', 17, 50.00, 850.00),
('PN-VT-001-H001', 'PN-VT-001', 'H001', 100, 25.00, 2500.00),
('PN-VT-002-H002', 'PN-VT-002', 'H002', 100, 30.00, 3000.00),
('PN-VT-003-H004', 'PN-VT-003', 'H004', 10, 50.00, 500.00),
('PN-VT-004-H005', 'PN-VT-004', 'H005', 4, 1.00, 4.00),
('PN-VT-005-H001', 'PN-VT-005', 'H001', 11, 25.00, 275.00),
('PN-VT-006-H001', 'PN-VT-006', 'H001', 122, 25.00, 3050.00),
('PN-VT-007-H005', 'PN-VT-007', 'H005', 12, 1.00, 12.00),
('PN-VT-008-H004', 'PN-VT-008', 'H004', 12, 50.00, 600.00),
('PN-VT-009-H001', 'PN-VT-009', 'H001', 12, 25.00, 300.00),
('PN-VT-009-H002', 'PN-VT-009', 'H002', 32, 30.00, 960.00),
('PN-VT-010-H004', 'PN-VT-010', 'H004', 11, 50.00, 550.00),
('PN-VT-011-H001', 'PN-VT-011', 'H001', 11, 25.00, 275.00),
('PN-VT-012-H017', 'PN-VT-012', 'H017', 11, 100.00, 1100.00),
('PN-VT-013-H002', 'PN-VT-013', 'H002', 12, 30.00, 360.00),
('PN-VT-014-H018', 'PN-VT-014', 'H018', 12, 120.00, 1440.00),
('PN-VT-015-H019', 'PN-VT-015', 'H019', 12, 112.00, 1344.00),
('PN-VT-016-H016', 'PN-VT-016', 'H016', 1222, 10.00, 12220.00),
('PN-VT-017-H022', 'PN-VT-017', 'H022', 12, 12.00, 144.00),
('PN-VT-018-H020', 'PN-VT-018', 'H020', 212, 12.00, 2544.00),
('PN-VT-019-H019', 'PN-VT-019', 'H019', 12, 112.00, 1344.00),
('PN-VT-020-H019', 'PN-VT-020', 'H019', 122, 112.00, 13664.00),
('PN-VT-021-H017', 'PN-VT-021', 'H017', 13, 100.00, 1300.00),
('PN-VT-022-H023', 'PN-VT-022', 'H023', 12, 12.00, 144.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ct_phieu_xuat`
--

CREATE TABLE `ct_phieu_xuat` (
  `ma_ctpx` varchar(20) NOT NULL,
  `ma_phieu_xuat` varchar(20) DEFAULT NULL,
  `ma_hang` varchar(20) DEFAULT NULL,
  `so_luong_xuat` int(11) NOT NULL,
  `don_gia_xuat` decimal(12,2) DEFAULT NULL,
  `thanh_tien` decimal(14,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `ct_phieu_xuat`
--

INSERT INTO `ct_phieu_xuat` (`ma_ctpx`, `ma_phieu_xuat`, `ma_hang`, `so_luong_xuat`, `don_gia_xuat`, `thanh_tien`) VALUES
('PX-TP-001-H003', 'PX-TP-001', 'H003', 10, 50.00, 500.00),
('PX-TP-002-H003', 'PX-TP-002', 'H003', 7, 50.00, 350.00),
('PX-VT-001-H001', 'PX-VT-001', 'H001', 20, 25.00, 500.00),
('PX-VT-002-H002', 'PX-VT-002', 'H002', 30, 30.00, 900.00),
('PX-VT-003-H001', 'PX-VT-003', 'H001', 10, 25.00, 250.00),
('PX-VT-004-H002', 'PX-VT-004', 'H002', 2, 30.00, 60.00),
('PX-VT-005-H004', 'PX-VT-005', 'H004', 6, 50.00, 300.00),
('PX-VT-006-H005', 'PX-VT-006', 'H005', 1, 1.00, 1.00),
('PX-VT-007-H001', 'PX-VT-007', 'H001', 1, 25.00, 25.00),
('PX-VT-008-H004', 'PX-VT-008', 'H004', 2, 50.00, 100.00),
('PX-VT-009-H001', 'PX-VT-009', 'H001', 1, 25.00, 25.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dai_ly`
--

CREATE TABLE `dai_ly` (
  `ma_dai_ly` varchar(20) NOT NULL,
  `ten_dai_ly` varchar(200) NOT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `sdt` varchar(15) DEFAULT NULL,
  `cccd` varchar(20) DEFAULT NULL,
  `nguoi_dai_dien` varchar(100) DEFAULT NULL,
  `so_hop_dong` varchar(100) DEFAULT NULL,
  `ngay_ky` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `dai_ly`
--

INSERT INTO `dai_ly` (`ma_dai_ly`, `ten_dai_ly`, `dia_chi`, `sdt`, `cccd`, `nguoi_dai_dien`, `so_hop_dong`, `ngay_ky`) VALUES
('DL001', 'Đại lý Hồng Phát', 'Yên Hòa, Hà Nội', '0987654321', '001204029752', 'Tuấn Anh', 'DL_AB_01', '2026-01-03'),
('DL002', 'Đại lý Hồng Đức', 'Hồ Chí Minh', '0987654321', '023456789123', 'Đỗ Văn Hùng', 'DL_AB_02', '2026-01-05'),
('DL003', 'Đại lý Văn Đạt', 'Đà Nẵng', '0123654789', '012365498754', 'Nguyễn Văn A', 'DL_VD_12', '2026-01-19'),
('DL004', 'Đại lý Tuấn Anh', 'Long Biên, Hà Nội', '0123654987', '012365987452', 'Nguyễn Văn B', 'DL_TA_13', '2026-01-13'),
('DL005', 'Đại lý Đăng Dũng', 'Hà Nội', '0123659874', '012345678912', 'Phan Đăng Xuất', 'DL_DD_00', '2026-01-21'),
('DL006', 'Đại lý Hùng Thắng', 'Bắc Giang', '0863521120', '002152220143', 'Phan Đăng', 'DL_AB_98', '2026-01-19'),
('DL007', 'Đại lý Hùng Vương', 'Bắc Ninh', '0253632201', '002135220142', 'Khánh Vương', 'DL_XI_09', '2026-01-23'),
('DL008', 'Đại lý Hùng Minh', 'Hàng Bài', '0865865521', '001252001240', 'Tuấn Minh', 'DL_OL_91', '2026-01-30'),
('DL009', 'Đại Lý Hàng Chuối', 'Hàng Chuối', '0568652201', '001230002415', 'Hàng Minh', 'DL_OI_09', '2026-01-23'),
('DL010', 'Đại lý Hùng Minh Vương', 'Hà Nội', '0125023550', '001230002415', 'Minh', 'DL_AI_98', '2026-01-23'),
('DL011', 'Đại Lý Hàng Khai', 'Hà Nội', '0586541120', '009829881982', 'Hàng Minh Minh', 'DL_OP_21', '2025-12-30');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hang_hoa`
--

CREATE TABLE `hang_hoa` (
  `ma_hang` varchar(20) NOT NULL,
  `ten_hang` varchar(200) NOT NULL,
  `don_gia` int(11) NOT NULL,
  `don_vi_tinh` varchar(50) NOT NULL,
  `muc_du_tru_min` int(11) DEFAULT 0,
  `muc_du_tru_max` int(11) DEFAULT 0,
  `ma_loai_hang` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `hang_hoa`
--

INSERT INTO `hang_hoa` (`ma_hang`, `ten_hang`, `don_gia`, `don_vi_tinh`, `muc_du_tru_min`, `muc_du_tru_max`, `ma_loai_hang`) VALUES
('H001', 'Bột mì', 25000, 'Kg', 10, 20, 'M001'),
('H002', 'Bột ngô', 30000, 'Kg', 10, 30, 'M001'),
('H003', 'Bánh bông ngô', 50000, 'Hộp', 50, 100, 'M004'),
('H004', 'Dầu', 50000, 'Lít', 10, 60, 'M002'),
('H005', 'Máy trộn', 1000000, 'cái', 2, 20, 'M003'),
('H006', 'Kẹo bông gòn', 12000, 'Thùng', 1, 100, 'M004'),
('H007', 'Kẹo kera', 12000, 'Thùng', 1, 100, 'M004'),
('H008', 'Kẹo chipchip', 15000, 'Thùng', 1, 100, 'M004'),
('H009', 'Bánh quy', 100000, 'Thùng', 1, 100, 'M004'),
('H010', 'Bánh quy dài', 10000, 'Thùng', 1, 100, 'M004'),
('H011', 'Bánh quy Momo', 12000, 'Thùng', 1, 100, 'M004'),
('H012', 'Bánh trứng', 10000, 'Thùng', 1, 100, 'M004'),
('H013', 'Bánh cá', 10000, 'Thùng', 1, 100, 'M004'),
('H014', 'Bánh chocopice', 10000, 'Thùng', 1, 1000, 'M004'),
('H015', 'Bánh trứng muối', 100000, 'Thùng', 1, 100, 'M004'),
('H016', 'Đường', 10000, 'Kg', 1, 22, 'M001'),
('H017', 'Bột mía', 100000, 'Kg', 1, 50, 'M001'),
('H018', 'Bột năng', 120000, 'Kg', 1, 20, 'M001'),
('H019', 'Bột nở', 112000, 'kg', 1, 12, 'M001'),
('H020', 'Mật ong', 12000, 'Kg', 1, 90, 'M001'),
('H021', 'Đường đỏ', 190000, 'Kg', 1, 20, 'M001'),
('H022', 'Đường mía đỏ', 12000, 'kg', 1, 40, 'M001'),
('H023', 'Đường Tinh', 12000, 'Kg', 1, 100, 'M001');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `kho`
--

CREATE TABLE `kho` (
  `ma_kho` varchar(20) NOT NULL,
  `ten_kho` varchar(100) NOT NULL,
  `dia_chi` varchar(255) NOT NULL,
  `ma_nd` varchar(100) DEFAULT NULL,
  `ma_loai_kho` varchar(11) DEFAULT NULL,
  `ma_vung` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `kho`
--

INSERT INTO `kho` (`ma_kho`, `ten_kho`, `dia_chi`, `ma_nd`, `ma_loai_kho`, `ma_vung`) VALUES
('K001', 'Kho nguyên liệu Dũng Ly', 'Yên hòa, Hà nội', 'ND007', 'L001', 'VM001'),
('K002', 'Kho nhiên liệu Đạt Hoa', 'Bồ đề, Hà nội', 'ND009', 'L002', 'VM001'),
('K003', 'Kho phụ tùng Hưng Thành', 'Hát môn, Hà nội', 'ND010', 'L003', 'VM001'),
('K004', 'Kho thành phẩm Khánh Minh', 'Từ sơn, Bắc ninh', 'ND008', 'L004', 'VM001'),
('K005', 'Kho nguyên liệu An Hòa', 'Nam đàn, Nghệ an', NULL, 'L001', 'VM003'),
('K006', 'Kho nhiên liệu Hòa Thanh', 'Hà Tĩnh', NULL, 'L002', 'VM003'),
('K007', 'Kho phụ tùng Hòa Phát', 'Thanh Hóa', NULL, 'L003', 'VM003'),
('K008', 'Kho thành phẩm Hòa Thịnh', 'Đà Nẵng', 'ND011', 'L004', 'VM003'),
('K009', 'Kho nguyên liệu Hợp Dũng', 'Tây Ninh', NULL, 'L001', 'VM002'),
('K010', 'Kho nhiên liệu Đạt Tín', 'Bình Dương', NULL, 'L002', 'VM002'),
('K012', 'Kho Nhiên Liệu Tùng Sơn', 'Hà Tĩnh', NULL, 'L002', 'VM003');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `loai_hang`
--

CREATE TABLE `loai_hang` (
  `ma_loai_hang` varchar(20) NOT NULL,
  `ten_loai_hang` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `loai_hang`
--

INSERT INTO `loai_hang` (`ma_loai_hang`, `ten_loai_hang`) VALUES
('M001', 'Nguyên liệu'),
('M002', 'Nhiên liệu'),
('M003', 'Phụ tùng'),
('M004', 'Thành phẩm');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `loai_kho`
--

CREATE TABLE `loai_kho` (
  `ma_loai_kho` varchar(11) NOT NULL,
  `ten_loai_kho` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `loai_kho`
--

INSERT INTO `loai_kho` (`ma_loai_kho`, `ten_loai_kho`) VALUES
('L001', 'Kho nguyên liệu'),
('L002', 'Kho nhiên liệu'),
('L003', 'Kho phụ tùng'),
('L004', 'Kho thành phẩm');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `ma_nd` varchar(20) NOT NULL,
  `ten_nd` varchar(100) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `ma_vai_tro` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `nguoi_dung`
--

INSERT INTO `nguoi_dung` (`ma_nd`, `ten_nd`, `mat_khau`, `ma_vai_tro`) VALUES
('ND001', 'Nguyễn Duy Khánh', '$2y$10$UaCGgJHBo27F8f9FCiv44uq.5Mr0a25MFzlZNh0RVyztKkxf4e8iO', 'VT001'),
('ND002', 'Nguyễn Văn Đạt', '$2y$10$nmDaEU8hD58LAaRlpphpbulpZJSX2T/ybTvKwKeRPWomUXOMOf8bi', 'VT002'),
('ND003', 'Phan Dũng', '$2y$10$wFr.QE8/XD8UoxAc9wdez.NC1bE8PU1O4H57TAGpsP5WYa2vvuCsm', 'VT003'),
('ND004', 'Hoàng Hưng', '$2y$10$U0F/i4FLKeVKM3xTyO9NxOjHgUaQnLY58awwaYjKEBlkH84E2xlYC', 'VT003'),
('ND005', 'Tuấn Anh', '$2y$10$pSRkjHlAXOTR04JfmjEOfuA9/QCFLcfsePdzIRtTLzP9nCODP7h8q', 'VT003'),
('ND006', 'Tiến Đạt', '$2y$10$pw22e3ofee.2AuWUFqoc2eZVcpIBzshP1gxI412Lk9EjJlCxNdC0u', 'VT003'),
('ND007', 'Thế Mạnh', '$2y$10$P4U8yfZaDYk6X0ul0oe0meTNY6LLuUX60V0ueCNjTLEdLKbSf.enS', 'VT004'),
('ND008', 'Trung Kiên', '$2y$10$brJP6wYCsW3zW24oq4gcPu/ytG9mJFK4HEuXEZfGNDZMVXnF.QTtu', 'VT004'),
('ND009', 'Văn An', '$2y$10$x4b27dMIIEHHEAbUZqZ2nu2cYc5l/VD0q0lhjP8zoN8yMovFQ.PKy', 'VT004'),
('ND010', 'Đăng Giáp', '$2y$10$2cH/oK8rQQ2WFlqF2g27bu.RqSj0lST31L3.SvWvpaVjvMpGFcQWm', 'VT004'),
('ND011', 'Nguyễn Minh Toàn', '$2y$10$CB6BF5oSCwS.l/bmlkIkZ.G.pN32a4qU0AN0bLLeLFAoULaVRvmrC', 'VT004');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nha_cung_cap`
--

CREATE TABLE `nha_cung_cap` (
  `ma_ncc` varchar(20) NOT NULL,
  `ten_ncc` varchar(200) NOT NULL,
  `dia_chi` varchar(255) DEFAULT NULL,
  `sdt` varchar(15) DEFAULT NULL,
  `hop_dong` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `nha_cung_cap`
--

INSERT INTO `nha_cung_cap` (`ma_ncc`, `ten_ncc`, `dia_chi`, `sdt`, `hop_dong`) VALUES
('NCC001', 'Công Ty TNHH Numeco', 'Yên Hòa, Hà Nội', '0376883763', 'HD-2000/01'),
('NCC002', 'Công Ty TNHH Sản Xuất Và Thương Mại Ong Vàng', 'Hát Môn, Hồ Chí Minh', '0123456789', 'HD-2000/02'),
('NCC003', 'Công Ty TNHH Tuấn Anh', 'Long Biên, Hà Nội', '0125469853', 'HD-2000/01'),
('NCC004', 'Công Ty TNHH Duy Khánh', 'Sầm Sơn, Thanh Hoá', '0326598745', 'HD-4992/32'),
('NCC005', 'Công Ty TNHH Văn Đạt', 'Phú Quốc, Kiên Giang', '0123659874', 'HD-2003/54'),
('NCC006', 'Công Ty TNHH Đăng Dũng', 'Huế', '0912233364', 'HD-2000/22'),
('NCC007', 'Công ty TNHH Thành Công', 'Hà Tĩnh', '0856301124', 'HD-2000/11'),
('NCC008', 'Công ty TNHH Minh Lý', 'Hà Giang', '0253652201', 'HD-2000/12'),
('NCC009', 'Công ty TNHH Minh Hoàng', 'Hà Giang', '0865412201', 'HD-2000/11'),
('NCC010', 'Công ty TNHH Minh Thành Công', 'Hà Nội', '0569652201', 'HD-2000/10'),
('NCC011', 'Công ty TNHH Minh Lý2', 'hni', '0698774123', 'HD-2000/10');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phan_quyen`
--

CREATE TABLE `phan_quyen` (
  `ma_quyen` varchar(50) NOT NULL,
  `ma_nd` varchar(50) NOT NULL,
  `ma_vung` varchar(50) NOT NULL,
  `ma_loai_kho` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `phan_quyen`
--

INSERT INTO `phan_quyen` (`ma_quyen`, `ma_nd`, `ma_vung`, `ma_loai_kho`) VALUES
('PQ001', 'ND003', 'VM001', 'L001'),
('PQ002', 'ND004', 'VM003', 'L002'),
('PQ003', 'ND005', 'VM002', 'L003'),
('PQ004', 'ND006', 'VM004', 'L004');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieu_nhap`
--

CREATE TABLE `phieu_nhap` (
  `ma_phieu_nhap` varchar(20) NOT NULL,
  `ma_nd` varchar(20) DEFAULT NULL,
  `ngay_nhap` date NOT NULL,
  `nguoi_giao` varchar(100) DEFAULT NULL,
  `don_vi_giao` varchar(100) DEFAULT NULL,
  `loai_nhap` varchar(50) DEFAULT NULL,
  `ma_kho` varchar(20) DEFAULT NULL,
  `ma_ncc` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `phieu_nhap`
--

INSERT INTO `phieu_nhap` (`ma_phieu_nhap`, `ma_nd`, `ngay_nhap`, `nguoi_giao`, `don_vi_giao`, `loai_nhap`, `ma_kho`, `ma_ncc`) VALUES
('PN-TP-001', 'ND001', '2026-01-19', 'Khánh', 'Phân xưởng', 'thanh_pham', 'K004', NULL),
('PN-VT-001', 'ND003', '2026-01-19', 'Đặng Hải Nguyên', 'Công ty TNHH Numeco', 'vat_tu', 'K001', 'NCC001'),
('PN-VT-002', 'ND003', '2026-01-19', 'Đỗ Tiến Đạt', 'Công ty TNHH Sản Xuất Và Thương Mại Ong Vàng', 'vat_tu', 'K001', 'NCC002'),
('PN-VT-003', 'ND001', '2026-01-19', 'Nguyễn Quang Minh', 'Công ty TNHH Tuấn Anh', 'vat_tu', 'K002', 'NCC003'),
('PN-VT-004', 'ND001', '2026-01-19', 'Nguyễn Quốc Khánh', 'Công ty TNHH Văn Đạt', 'vat_tu', 'K003', 'NCC005'),
('PN-VT-005', 'ND001', '2026-01-19', 'Nguyễn Phú Cường', 'DVVC', 'vat_tu', 'K001', 'NCC004'),
('PN-VT-006', 'ND001', '2026-01-19', 'Nguyễn Phú Cường', 'DVVC', 'vat_tu', 'K001', 'NCC006'),
('PN-VT-007', 'ND001', '2026-01-19', 'Nguyễn Phú Cường', 'DVVC', 'vat_tu', 'K007', 'NCC006'),
('PN-VT-008', 'ND001', '2026-01-19', 'Nguyễn Phú Cường', 'DVVC', 'vat_tu', 'K002', 'NCC001'),
('PN-VT-009', 'ND001', '2026-01-19', 'Nguyễn Phú Cường', 'DVVC', 'vat_tu', 'K001', 'NCC004'),
('PN-VT-010', 'ND001', '2026-01-19', 'Nguyễn Phú Cường', 'DVVC', 'vat_tu', 'K010', 'NCC006'),
('PN-VT-011', 'ND001', '2026-01-19', 'Đặng Hải Nguyên', 'DVVC', 'vat_tu', 'K005', 'NCC006'),
('PN-VT-012', 'ND001', '2026-01-19', 'Nguyễn Quang Minh', 'DVVC', 'vat_tu', 'K005', 'NCC008'),
('PN-VT-013', 'ND001', '2026-01-19', 'Đỗ Tiến Đạt', 'DVVC', 'vat_tu', 'K009', 'NCC004'),
('PN-VT-014', 'ND001', '2026-01-19', 'Nguyễn Quang Minh', 'DVVC', 'vat_tu', 'K009', 'NCC010'),
('PN-VT-015', 'ND001', '2026-01-19', 'Đặng Hải Nguyên', 'DVVC', 'vat_tu', 'K001', 'NCC008'),
('PN-VT-016', 'ND001', '2026-01-19', 'Nguyễn Quốc Khánh', 'DVVC', 'vat_tu', 'K001', 'NCC002'),
('PN-VT-017', 'ND001', '2026-01-19', 'Nguyễn Quốc Khánh', 'DVVC', 'vat_tu', 'K001', 'NCC003'),
('PN-VT-018', 'ND001', '2026-01-19', 'Nguyễn Quốc Khánh', 'DVVC', 'vat_tu', 'K009', 'NCC009'),
('PN-VT-019', 'ND001', '2026-01-19', 'Đặng Hải Nguyên', 'DVVC', 'vat_tu', 'K001', 'NCC006'),
('PN-VT-020', 'ND001', '2026-01-19', 'Nguyễn Quang Minh', 'DVVC', 'vat_tu', 'K005', 'NCC002'),
('PN-VT-021', 'ND001', '2026-01-19', 'Nguyễn Quốc Khánh', 'DVVC', 'vat_tu', 'K005', 'NCC006'),
('PN-VT-022', 'ND001', '2026-01-19', 'Nguyễn Quốc Khánh', 'DVVC', 'vat_tu', 'K005', 'NCC006');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phieu_xuat`
--

CREATE TABLE `phieu_xuat` (
  `ma_phieu_xuat` varchar(20) NOT NULL,
  `ma_nd` varchar(20) DEFAULT NULL,
  `ngay_xuat` date NOT NULL,
  `nguoi_nhan` varchar(100) DEFAULT NULL,
  `don_vi_nhan` varchar(100) DEFAULT NULL,
  `loai_xuat` varchar(50) DEFAULT NULL,
  `ma_kho` varchar(20) DEFAULT NULL,
  `ma_dai_ly` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `phieu_xuat`
--

INSERT INTO `phieu_xuat` (`ma_phieu_xuat`, `ma_nd`, `ngay_xuat`, `nguoi_nhan`, `don_vi_nhan`, `loai_xuat`, `ma_kho`, `ma_dai_ly`) VALUES
('PX-TP-001', 'ND001', '2026-01-19', 'Hồng Đức', 'Đại lý Hồng Đức', 'thanh_pham', 'K004', 'DL002'),
('PX-TP-002', 'ND001', '2026-01-19', 'đỗ duy khánh', 'DVVC', 'thanh_pham', 'K004', 'DL005'),
('PX-VT-001', 'ND003', '2026-01-19', 'Thế Mạnh', 'Phân xương', 'vat_tu', 'K001', NULL),
('PX-VT-002', 'ND003', '2026-01-19', 'Thế Mạnh', 'Phân xương', 'vat_tu', 'K001', NULL),
('PX-VT-003', 'ND003', '2026-01-19', 'Thế Mạnh', 'Phân xương', 'vat_tu', 'K001', NULL),
('PX-VT-004', 'ND003', '2026-01-19', 'Thế Mạnh', 'Phân xương', 'vat_tu', 'K001', NULL),
('PX-VT-005', 'ND001', '2026-01-19', 'Văn An', 'Phân xương', 'vat_tu', 'K002', NULL),
('PX-VT-006', 'ND001', '2026-01-19', 'Đăng Giáp', 'Phân xương', 'vat_tu', 'K003', NULL),
('PX-VT-007', 'ND001', '2026-01-19', 'đỗ duy khánh', 'phân xưởng', 'vat_tu', 'K001', NULL),
('PX-VT-008', 'ND001', '2026-01-19', 'hung', 'phân xưởng', 'vat_tu', 'K002', NULL),
('PX-VT-009', 'ND001', '2026-01-19', 'đỗ duy khánh', 'phân xưởng', 'vat_tu', 'K001', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `the_kho`
--

CREATE TABLE `the_kho` (
  `ma_the_kho` varchar(20) NOT NULL,
  `ma_kho` varchar(20) DEFAULT NULL,
  `ma_hang` varchar(20) DEFAULT NULL,
  `ngay` date DEFAULT NULL,
  `so_ct` varchar(20) DEFAULT NULL,
  `loai_phat_sinh` varchar(50) DEFAULT NULL,
  `so_luong_ton` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `the_kho`
--

INSERT INTO `the_kho` (`ma_the_kho`, `ma_kho`, `ma_hang`, `ngay`, `so_ct`, `loai_phat_sinh`, `so_luong_ton`) VALUES
('MTK001-H001-001', 'K001', 'H001', '2026-01-19', 'PN-VT-001', 'vat_tu', 100),
('MTK001-H001-002', 'K001', 'H001', '2026-01-19', 'PX-VT-001', 'vat_tu', 80),
('MTK001-H001-003', 'K001', 'H001', '2026-01-19', 'PX-VT-003', 'vat_tu', 70),
('MTK001-H001-004', 'K001', 'H001', '2026-01-19', 'PN-VT-005', 'vat_tu', 81),
('MTK001-H001-005', 'K001', 'H001', '2026-01-19', 'PN-VT-006', 'vat_tu', 203),
('MTK001-H001-006', 'K001', 'H001', '2026-01-19', 'PX-VT-007', 'vat_tu', 202),
('MTK001-H001-007', 'K001', 'H001', '2026-01-19', 'PN-VT-009', 'vat_tu', 214),
('MTK001-H001-008', 'K001', 'H001', '2026-01-19', 'PX-VT-009', 'vat_tu', 213),
('MTK001-H001-009', 'K005', 'H001', '2026-01-19', 'PN-VT-011', 'vat_tu', 11),
('MTK002-H002-001', 'K001', 'H002', '2026-01-19', 'PN-VT-002', 'vat_tu', 100),
('MTK002-H002-002', 'K001', 'H002', '2026-01-19', 'PX-VT-002', 'vat_tu', 70),
('MTK002-H002-003', 'K001', 'H002', '2026-01-19', 'PX-VT-004', 'vat_tu', 68),
('MTK002-H002-004', 'K001', 'H002', '2026-01-19', 'PN-VT-009', 'vat_tu', 100),
('MTK002-H002-005', 'K009', 'H002', '2026-01-19', 'PN-VT-013', 'vat_tu', 12),
('MTK003-H003-001', 'K004', 'H003', '2026-01-19', 'PN-TP-001', 'thanh_pham', 17),
('MTK003-H003-002', 'K004', 'H003', '2026-01-19', 'PX-TP-001', 'thanh_pham', 7),
('MTK003-H003-003', 'K004', 'H003', '2026-01-19', 'PX-TP-002', 'thanh_pham', 0),
('MTK004-H004-001', 'K002', 'H004', '2026-01-19', 'PN-VT-003', 'vat_tu', 10),
('MTK004-H004-002', 'K002', 'H004', '2026-01-19', 'PX-VT-005', 'vat_tu', 4),
('MTK004-H004-003', 'K002', 'H004', '2026-01-19', 'PX-VT-008', 'vat_tu', 2),
('MTK004-H004-004', 'K002', 'H004', '2026-01-19', 'PN-VT-008', 'vat_tu', 14),
('MTK004-H004-005', 'K010', 'H004', '2026-01-19', 'PN-VT-010', 'vat_tu', 11),
('MTK005-H005-001', 'K003', 'H005', '2026-01-19', 'PN-VT-004', 'vat_tu', 4),
('MTK005-H005-002', 'K003', 'H005', '2026-01-19', 'PX-VT-006', 'vat_tu', 3),
('MTK005-H005-003', 'K007', 'H005', '2026-01-19', 'PN-VT-007', 'vat_tu', 12),
('MTK016-H016-001', 'K001', 'H016', '2026-01-19', 'PN-VT-016', 'vat_tu', 1222),
('MTK017-H017-001', 'K005', 'H017', '2026-01-19', 'PN-VT-012', 'vat_tu', 11),
('MTK017-H017-002', 'K005', 'H017', '2026-01-19', 'PN-VT-021', 'vat_tu', 24),
('MTK018-H018-001', 'K009', 'H018', '2026-01-19', 'PN-VT-014', 'vat_tu', 12),
('MTK019-H019-001', 'K001', 'H019', '2026-01-19', 'PN-VT-015', 'vat_tu', 12),
('MTK019-H019-002', 'K001', 'H019', '2026-01-19', 'PN-VT-019', 'vat_tu', 24),
('MTK019-H019-003', 'K005', 'H019', '2026-01-19', 'PN-VT-020', 'vat_tu', 122),
('MTK020-H020-001', 'K009', 'H020', '2026-01-19', 'PN-VT-018', 'vat_tu', 212),
('MTK022-H022-001', 'K001', 'H022', '2026-01-19', 'PN-VT-017', 'vat_tu', 12),
('MTK023-H023-001', 'K005', 'H023', '2026-01-19', 'PN-VT-022', 'vat_tu', 12);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vai_tro`
--

CREATE TABLE `vai_tro` (
  `ma_vai_tro` varchar(50) NOT NULL,
  `ten_vai_tro` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `vai_tro`
--

INSERT INTO `vai_tro` (`ma_vai_tro`, `ten_vai_tro`) VALUES
('VT001', 'Admin'),
('VT002', 'Ban giám đốc'),
('VT003', 'Quản lý kho'),
('VT004', 'Thủ kho');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vung_mien`
--

CREATE TABLE `vung_mien` (
  `ma_vung` varchar(50) NOT NULL,
  `ten_vung` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `vung_mien`
--

INSERT INTO `vung_mien` (`ma_vung`, `ten_vung`) VALUES
('VM001', 'Miền bắc'),
('VM002', 'Miền nam'),
('VM003', 'Miền trung'),
('VM004', 'Miền tây');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `ct_phieu_nhap`
--
ALTER TABLE `ct_phieu_nhap`
  ADD PRIMARY KEY (`ma_ctpn`),
  ADD KEY `fk_ctpn_pn` (`ma_phieu_nhap`),
  ADD KEY `fk_ctpn_hang` (`ma_hang`);

--
-- Chỉ mục cho bảng `ct_phieu_xuat`
--
ALTER TABLE `ct_phieu_xuat`
  ADD PRIMARY KEY (`ma_ctpx`),
  ADD KEY `fk_ctpx_px` (`ma_phieu_xuat`),
  ADD KEY `fk_ctpx_hang` (`ma_hang`);

--
-- Chỉ mục cho bảng `dai_ly`
--
ALTER TABLE `dai_ly`
  ADD PRIMARY KEY (`ma_dai_ly`);

--
-- Chỉ mục cho bảng `hang_hoa`
--
ALTER TABLE `hang_hoa`
  ADD PRIMARY KEY (`ma_hang`),
  ADD KEY `fk_hang_loai` (`ma_loai_hang`);

--
-- Chỉ mục cho bảng `kho`
--
ALTER TABLE `kho`
  ADD PRIMARY KEY (`ma_kho`),
  ADD KEY `ma_loai_kho` (`ma_loai_kho`),
  ADD KEY `fk_kho_thu_kho` (`ma_nd`),
  ADD KEY `ma_vung` (`ma_vung`);

--
-- Chỉ mục cho bảng `loai_hang`
--
ALTER TABLE `loai_hang`
  ADD PRIMARY KEY (`ma_loai_hang`);

--
-- Chỉ mục cho bảng `loai_kho`
--
ALTER TABLE `loai_kho`
  ADD PRIMARY KEY (`ma_loai_kho`);

--
-- Chỉ mục cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`ma_nd`),
  ADD KEY `ma_vai_tro` (`ma_vai_tro`);

--
-- Chỉ mục cho bảng `nha_cung_cap`
--
ALTER TABLE `nha_cung_cap`
  ADD PRIMARY KEY (`ma_ncc`);

--
-- Chỉ mục cho bảng `phan_quyen`
--
ALTER TABLE `phan_quyen`
  ADD PRIMARY KEY (`ma_quyen`),
  ADD KEY `ma_nd` (`ma_nd`),
  ADD KEY `ma_vung` (`ma_vung`),
  ADD KEY `ma_loai_kho` (`ma_loai_kho`);

--
-- Chỉ mục cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  ADD PRIMARY KEY (`ma_phieu_nhap`),
  ADD KEY `fk_pn_nd` (`ma_nd`),
  ADD KEY `fk_pn_kho` (`ma_kho`),
  ADD KEY `fk_pn_ncc` (`ma_ncc`);

--
-- Chỉ mục cho bảng `phieu_xuat`
--
ALTER TABLE `phieu_xuat`
  ADD PRIMARY KEY (`ma_phieu_xuat`),
  ADD KEY `fk_px_nd` (`ma_nd`),
  ADD KEY `fk_px_kho` (`ma_kho`),
  ADD KEY `fk_px_dl` (`ma_dai_ly`);

--
-- Chỉ mục cho bảng `the_kho`
--
ALTER TABLE `the_kho`
  ADD PRIMARY KEY (`ma_the_kho`),
  ADD KEY `fk_tk_kho` (`ma_kho`),
  ADD KEY `fk_tk_hang` (`ma_hang`);

--
-- Chỉ mục cho bảng `vai_tro`
--
ALTER TABLE `vai_tro`
  ADD PRIMARY KEY (`ma_vai_tro`);

--
-- Chỉ mục cho bảng `vung_mien`
--
ALTER TABLE `vung_mien`
  ADD PRIMARY KEY (`ma_vung`);

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `ct_phieu_nhap`
--
ALTER TABLE `ct_phieu_nhap`
  ADD CONSTRAINT `fk_ctpn_hang` FOREIGN KEY (`ma_hang`) REFERENCES `hang_hoa` (`ma_hang`),
  ADD CONSTRAINT `fk_ctpn_pn` FOREIGN KEY (`ma_phieu_nhap`) REFERENCES `phieu_nhap` (`ma_phieu_nhap`);

--
-- Các ràng buộc cho bảng `ct_phieu_xuat`
--
ALTER TABLE `ct_phieu_xuat`
  ADD CONSTRAINT `fk_ctpx_hang` FOREIGN KEY (`ma_hang`) REFERENCES `hang_hoa` (`ma_hang`),
  ADD CONSTRAINT `fk_ctpx_px` FOREIGN KEY (`ma_phieu_xuat`) REFERENCES `phieu_xuat` (`ma_phieu_xuat`);

--
-- Các ràng buộc cho bảng `hang_hoa`
--
ALTER TABLE `hang_hoa`
  ADD CONSTRAINT `fk_hang_loai` FOREIGN KEY (`ma_loai_hang`) REFERENCES `loai_hang` (`ma_loai_hang`);

--
-- Các ràng buộc cho bảng `kho`
--
ALTER TABLE `kho`
  ADD CONSTRAINT `fk_kho_thu_kho` FOREIGN KEY (`ma_nd`) REFERENCES `nguoi_dung` (`ma_nd`),
  ADD CONSTRAINT `kho_ibfk_1` FOREIGN KEY (`ma_loai_kho`) REFERENCES `loai_kho` (`ma_loai_kho`),
  ADD CONSTRAINT `kho_ibfk_2` FOREIGN KEY (`ma_vung`) REFERENCES `vung_mien` (`ma_vung`);

--
-- Các ràng buộc cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD CONSTRAINT `nguoi_dung_ibfk_1` FOREIGN KEY (`ma_vai_tro`) REFERENCES `vai_tro` (`ma_vai_tro`);

--
-- Các ràng buộc cho bảng `phan_quyen`
--
ALTER TABLE `phan_quyen`
  ADD CONSTRAINT `phan_quyen_ibfk_1` FOREIGN KEY (`ma_nd`) REFERENCES `nguoi_dung` (`ma_nd`),
  ADD CONSTRAINT `phan_quyen_ibfk_2` FOREIGN KEY (`ma_vung`) REFERENCES `vung_mien` (`ma_vung`),
  ADD CONSTRAINT `phan_quyen_ibfk_3` FOREIGN KEY (`ma_loai_kho`) REFERENCES `loai_kho` (`ma_loai_kho`);

--
-- Các ràng buộc cho bảng `phieu_nhap`
--
ALTER TABLE `phieu_nhap`
  ADD CONSTRAINT `fk_pn_kho` FOREIGN KEY (`ma_kho`) REFERENCES `kho` (`ma_kho`),
  ADD CONSTRAINT `fk_pn_ncc` FOREIGN KEY (`ma_ncc`) REFERENCES `nha_cung_cap` (`ma_ncc`),
  ADD CONSTRAINT `fk_pn_nd` FOREIGN KEY (`ma_nd`) REFERENCES `nguoi_dung` (`ma_nd`);

--
-- Các ràng buộc cho bảng `phieu_xuat`
--
ALTER TABLE `phieu_xuat`
  ADD CONSTRAINT `fk_px_dl` FOREIGN KEY (`ma_dai_ly`) REFERENCES `dai_ly` (`ma_dai_ly`),
  ADD CONSTRAINT `fk_px_kho` FOREIGN KEY (`ma_kho`) REFERENCES `kho` (`ma_kho`),
  ADD CONSTRAINT `fk_px_nd` FOREIGN KEY (`ma_nd`) REFERENCES `nguoi_dung` (`ma_nd`);

--
-- Các ràng buộc cho bảng `the_kho`
--
ALTER TABLE `the_kho`
  ADD CONSTRAINT `fk_tk_hang` FOREIGN KEY (`ma_hang`) REFERENCES `hang_hoa` (`ma_hang`),
  ADD CONSTRAINT `fk_tk_kho` FOREIGN KEY (`ma_kho`) REFERENCES `kho` (`ma_kho`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
