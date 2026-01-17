-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th1 14, 2026 lúc 03:25 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

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
('DL002', 'Đại lý Hồng Đức', 'Hồ Chí Minh', '0987654321', '023456789123', 'Đỗ Văn Hùng', 'DL_AB_02', '2026-01-05');

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
('H001', 'Bột', 25000, 'Kg', 5, 10, 'M001'),
('H002', 'Dầu', 30000, 'Lít', 5, 10, 'M002'),
('H003', 'Bánh quy', 50000, 'Hộp', 20, 100, 'M004');

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
('K001', 'Kho Đạt Family', 'Long Biên, Hà Nội', NULL, 'L001', 'VM001'),
('K002', 'Kho Dũng Phan', 'Yên Hòa, Hà Nội', NULL, 'L002', 'VM002');

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
('NCC002', 'Công Ty TNHH Sản Xuất Và Thương Mại Ong Vàng', 'Hát Môn, Hồ Chí Minh', '0123456789', 'HD-2000/02');

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
('VM002', 'Miền nam');

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
