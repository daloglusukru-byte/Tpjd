-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:3306
-- Üretim Zamanı: 17 Şub 2026, 22:33:53
-- Sunucu sürümü: 8.0.44-cll-lve
-- PHP Sürümü: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `tpjdorgt_uyelik`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `members`
--

CREATE TABLE `members` (
  `id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `memberNo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tcNo` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthDate` date DEFAULT NULL,
  `bloodType` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phoneHome` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maritalStatus` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `neighborhood` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fatherName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motherName` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graduation` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graduationYear` int DEFAULT NULL,
  `employmentStatus` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workplace` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthCity` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthDistrict` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrationDate` date DEFAULT NULL,
  `volumeNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `familyNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lineNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `membershipType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aidatAktif` tinyint(1) DEFAULT '1',
  `membershipStatus` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `exitDate` date DEFAULT NULL,
  `exitReasonType` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exitReason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `members`
--

INSERT INTO `members` (`id`, `memberNo`, `firstName`, `lastName`, `tcNo`, `gender`, `birthDate`, `bloodType`, `email`, `phone`, `phoneHome`, `maritalStatus`, `city`, `district`, `neighborhood`, `address`, `fatherName`, `motherName`, `graduation`, `graduationYear`, `employmentStatus`, `workplace`, `position`, `birthCity`, `birthDistrict`, `registrationDate`, `volumeNo`, `familyNo`, `lineNo`, `notes`, `membershipType`, `aidatAktif`, `membershipStatus`, `exitDate`, `exitReasonType`, `exitReason`, `createdAt`, `updatedAt`) VALUES
('1770222755062eip42gfu1', 'TPJD0001', 'Mehmet', 'Kılıçay', '123456789', 'Erkek', '1980-02-05', 'A Rh+', 'mkilicay@gmail.com', '+905552508438', '', 'Evli', 'Ankara', 'Çankaya', '', 'TPAO', '', '', '', 0, 'Çalışıyor', 'TPAO', '', '', '', '2024-03-04', '', '', '', '', 'Asil Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-04 19:32:35', '2026-02-16 01:33:57'),
('17702231370750vju68vq2', 'TPJD0038', 'Muharrem', 'Emeklioğlu', '10931643426', 'Erkek', '1982-03-01', 'A Rh+', 'emeklioglu@tpao.gov.tr', '+905552508438', '', '', 'Kayseri', 'Etimesgut', '', 'TPAO', '', '', '', 0, '', '', '', '', '', '2022-01-21', '', '', '', '', 'Öğrenci Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-04 19:38:58', '2026-02-16 20:46:57'),
('17702240585434jobr6hqf', 'TPJD0066', 'Aytekin', 'Murathan', '35624466', 'Erkek', '2000-02-05', '', 'ggædd.com', '+905552503895', '', '', 'Antalya', '', '', 'MTA', '', '', '', 0, '', '', '', '', '', '2019-07-07', '', '', '', '', 'Asil Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-04 19:54:19', '2026-02-16 01:33:21'),
('1771189319279805mbwz5v', 'TPJD2026002', 'Ahmet', 'Gedik', '12345678901', 'Erkek', '1900-01-01', 'A Rh+', 'aagedik@gmail.com', '+905438880177', '', '', 'KONYA', 'BOZKIR', '', '212.Sokak No.: 2/2 D2-1 Blok Kat 5 Daire 18', '', '', '', 0, '', '', '', '', '', '2022-07-18', '', '', '', '', 'Fahri Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-16 00:01:59', '2026-02-16 20:47:17'),
('1771264609488jxlumz737', '3695', 'Ali', 'Emeklioğlu', '3654993', 'Erkek', '2000-02-13', 'A Rh+', 'mmemekli@gmail.com', '+905552508438', '', 'Bekar', 'Ankara', 'çankaya', '', 'ankara', '', '', '', 0, 'Çalışıyor', '', '', 'ankara', 'çankaya', '2026-02-03', '', '', '', '', 'Asil Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-16 20:56:49', '2026-02-17 20:25:00'),
('1771264754412if8tfezhz', '43344', 'dad', 'dads', '54563644', 'Erkek', '1990-01-30', 'A Rh+', 'ff@dd.com', '+90535626448', '', 'Bekar', 'Ardahan', '', '', 'sfsd', '', '', '', 0, '', '', '', '', '', '2026-02-16', '', '', '', '', 'Asil Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-16 20:59:14', '2026-02-17 20:26:01'),
('1771349010847b0bo41vgx', 'tpjd02', 'Ahmet', 'Emeklioğlu', '12169784521', 'Erkek', '1985-04-15', 'AB Rh+', 'aemeklioglu@gmail.com', '+905534797547', NULL, 'Evli', 'Kayseri', 'Serik', 'Yukarı kocayataj', 'Yukarıkocayatak Mh. Gazal Küme Evleri Toki Konutları d2-7 blok Kat:6 Daire:39', 'asdaf', 'kilili', 'ffpfpş', 2019, 'Çalışıyor', 'ıkuytdd', 'ofo', 'Serik', 'Serik', '2025-01-17', '3563', '12312', '15646', 'dıdıdı', 'Asil Üye', 1, 'aktif', NULL, NULL, NULL, '2026-02-17 20:23:30', '2026-02-17 20:23:30');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `notifications`
--

CREATE TABLE `notifications` (
  `id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipients` json DEFAULT NULL,
  `priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `sentAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `recipients`, `priority`, `sentAt`, `createdAt`) VALUES
('1770223267100r2awwx9mj', 'deneme (E-posta)', 'deneme', '[\"17702231370750vju68vq2\"]', 'normal', '2026-02-04 16:41:07', '2026-02-04 19:41:07'),
('1771195290776oeak82uqb', 'merhaba (E-posta)', 'Bildirim Mesajı *', '[\"1771189319279805mbwz5v\"]', 'normal', '2026-02-15 22:41:31', '2026-02-16 01:41:31'),
('1771195295396ln7wlhqvb', 'merhaba (Telegram)', 'Bildirim Mesajı *', '[\"1771189319279805mbwz5v\"]', 'normal', '2026-02-15 22:41:35', '2026-02-16 01:41:35'),
('17711964676742b8srj7pq', 'TPJD2026002 - Ahmet Gedik (E-posta)', 'TPJD2026002 - Ahmet Gedik', '[\"1771189319279805mbwz5v\"]', 'normal', '2026-02-15 23:01:08', '2026-02-16 02:01:07'),
('notif_69925033d38a5', 'Toplu E-posta Gönderimi', '1 kişiye e-posta gönderildi', '[\"aagedik@gmail.com\"]', 'normal', '2026-02-16 02:01:07', '2026-02-16 02:01:07'),
('notif_6992513652bff', 'Toplu E-posta Gönderimi', '4 kişiye e-posta gönderildi', '[\"aagedik@gmail.com\", \"ggædd.com\", \"emeklioglu@tpao.gov.tr\", \"mkilicay@gmail.com\"]', 'normal', '2026-02-16 02:05:26', '2026-02-16 02:05:26');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `payments`
--

CREATE TABLE `payments` (
  `id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `memberId` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` int NOT NULL,
  `date` date NOT NULL,
  `receiptNo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` datetime DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `payments`
--

INSERT INTO `payments` (`id`, `memberId`, `amount`, `type`, `year`, `date`, `receiptNo`, `description`, `status`, `createdAt`, `updatedAt`) VALUES
('1770222899228hk75elrb1', '1770222755062eip42gfu1', 100.00, '0', 2026, '2026-02-04', '', '', 'tamamlandı', '2026-02-04 16:34:59', '2026-02-04 16:34:59'),
('1770223647641yzxuxwq06', '17702231370750vju68vq2', 90.00, '0', 2026, '2026-02-04', '', '', 'tamamlandı', '2026-02-04 16:47:27', '2026-02-04 16:47:27'),
('17702236656486e0tg7jfm', '17702231370750vju68vq2', 90.00, '0', 2025, '2026-02-04', '', '', 'tamamlandı', '2026-02-04 16:47:45', '2026-02-04 16:47:45'),
('1770227282100gmyq4poxa', '17702240585434jobr6hqf', 100.00, '0', 2010, '2026-02-04', '9090', 'hbdfh', 'tamamlandı', '2026-02-04 17:48:02', '2026-02-04 17:48:02'),
('1771349399546lzzmebxl4', '1771264754412if8tfezhz', 100.00, '0', 2021, '2026-02-17', '', '', 'tamamlandı', '2026-02-17 17:29:59', '2026-02-17 17:29:59'),
('p_6992328f238bb', '17702231370750vju68vq2', 90.00, 'aidat', 2023, '2026-02-15', '1000241', '2023 yılı Asil Onursal aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:54:39', '2026-02-15 23:55:26'),
('p_6992328f23f0d', '17702231370750vju68vq2', 90.00, 'aidat', 2024, '2024-01-01', '3212312', '2024 yılı Asil Onursal aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:54:39', '2026-02-16 20:46:28'),
('p_6992328f23ffe', '17702231370750vju68vq2', 90.00, 'aidat', 2025, '2026-02-15', '2025', '2025 yılı Asil Onursal aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:54:39', '2026-02-15 23:57:34'),
('p_699233052bf45', '17702240585434jobr6hqf', 100.00, 'aidat', 2020, '2026-02-15', 'tpjd001', '2020 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:56:37', '2026-02-16 02:32:13'),
('p_699233052c263', '17702240585434jobr6hqf', 100.00, 'aidat', 2021, '2026-02-15', '100022', '2021 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:56:37', '2026-02-16 02:33:09'),
('p_699233052c34b', '17702240585434jobr6hqf', 100.00, 'aidat', 2022, '2026-02-15', 'FIS002', '2022 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:56:37', '2026-02-16 02:46:20'),
('p_699233052c5f7', '17702240585434jobr6hqf', 100.00, 'aidat', 2023, '2023-01-01', NULL, '2023 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-15 23:56:37', '2026-02-15 23:56:37'),
('p_699233052c732', '17702240585434jobr6hqf', 100.00, 'aidat', 2024, '2024-01-01', NULL, '2024 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-15 23:56:37', '2026-02-15 23:56:37'),
('p_699233052c840', '17702240585434jobr6hqf', 100.00, 'aidat', 2025, '2026-02-15', '282', '2025 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:56:37', '2026-02-16 02:33:32'),
('p_699233185fb37', '1770222755062eip42gfu1', 100.00, 'aidat', 2021, '2026-02-15', '1000241', '2021 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:56:56', '2026-02-16 02:39:33'),
('p_699233185fdd8', '1770222755062eip42gfu1', 100.00, 'aidat', 2022, '2022-01-01', NULL, '2022 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-15 23:56:56', '2026-02-15 23:56:56'),
('p_699233185ff09', '1770222755062eip42gfu1', 100.00, 'aidat', 2023, '2023-01-01', NULL, '2023 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-15 23:56:56', '2026-02-15 23:56:56'),
('p_699233186002c', '1770222755062eip42gfu1', 100.00, 'aidat', 2024, '2024-01-01', NULL, '2024 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-15 23:56:56', '2026-02-15 23:56:56'),
('p_699233186013d', '1770222755062eip42gfu1', 100.00, 'aidat', 2025, '2026-02-15', '100024', '2025 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-15 23:56:56', '2026-02-16 02:53:25'),
('p_699234478ff91', '1771189319279805mbwz5v', 100.00, 'aidat', 2020, '2026-02-15', '100022', '2020 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:01:59', '2026-02-16 01:39:28'),
('p_699234479058f', '1771189319279805mbwz5v', 100.00, 'aidat', 2021, '2026-02-15', '100022', '2021 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:01:59', '2026-02-16 01:39:41'),
('p_6992344790671', '1771189319279805mbwz5v', 100.00, 'aidat', 2022, '2026-02-15', '100024', '2022 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:01:59', '2026-02-16 01:40:02'),
('p_6992344790716', '1771189319279805mbwz5v', 100.00, 'aidat', 2023, '2026-02-15', '100022', '2023 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:01:59', '2026-02-16 01:40:23'),
('p_69923447907d8', '1771189319279805mbwz5v', 100.00, 'aidat', 2024, '2026-02-15', '100024', '2024 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:01:59', '2026-02-16 02:40:07'),
('p_699234479090d', '1771189319279805mbwz5v', 100.00, 'aidat', 2025, '2026-02-15', '100022', '2025 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:01:59', '2026-02-16 01:40:43'),
('p_699235516ee07', '1771189319279805mbwz5v', 100.00, 'aidat', 2022, '2026-02-15', 'FIS002', '2022 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:06:25', '2026-02-16 01:39:52'),
('p_699235516f5fd', '1771189319279805mbwz5v', 100.00, 'aidat', 2023, '2026-02-15', '100022', '2023 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:06:25', '2026-02-16 01:40:15'),
('p_699235516f755', '1771189319279805mbwz5v', 100.00, 'aidat', 2024, '2026-02-15', '1000241', '2024 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:06:25', '2026-02-16 01:40:33'),
('p_699235516f82c', '1771189319279805mbwz5v', 100.00, 'aidat', 2025, '2026-02-15', '100024', '2025 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 00:06:25', '2026-02-16 02:50:53'),
('p_69924c1073c03', '1771189319279805mbwz5v', 100.00, 'aidat', 2010, '2026-02-15', '1000241', '2010 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 01:43:28', '2026-02-16 02:46:54'),
('p_6992f6d5dd41e', '17702240585434jobr6hqf', 100.00, 'aidat', 2018, '2026-02-16', '5421', '2018 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 13:52:05', '2026-02-16 13:52:25'),
('p_699358690e103', '17702231370750vju68vq2', 80.00, 'aidat', 2003, '2026-02-16', '', '2003 yılı Öğrenci Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-16 20:48:25', '2026-02-16 20:51:01'),
('p_69935a61c12b9', '1771264609488jxlumz737', 100.00, 'aidat', 2021, '2021-01-01', NULL, '2021 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-16 20:56:49', '2026-02-16 20:56:49'),
('p_6994a46c1809d', '1771264609488jxlumz737', 100.00, 'aidat', 2024, '2024-01-01', NULL, '2024 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-17 20:25:00', '2026-02-17 20:25:00'),
('p_6994a4a969c5e', '1771264754412if8tfezhz', 100.00, 'aidat', 2001, '2001-01-01', NULL, '2001 yılı Asil Üye aidatı (geçmiş dönem)', 'bekliyor', '2026-02-17 20:26:01', '2026-02-17 20:26:01'),
('p_6994a4a96a105', '1771264754412if8tfezhz', 100.00, 'aidat', 2002, '2026-02-17', '', '2002 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-17 20:26:01', '2026-02-17 20:30:44'),
('p_6994a4a96a41e', '1771264754412if8tfezhz', 100.00, 'aidat', 2003, '2026-02-17', '', '2003 yılı Asil Üye aidatı (geçmiş dönem)', 'tamamlandı', '2026-02-17 20:26:01', '2026-02-17 20:30:30');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` json DEFAULT NULL,
  `updatedAt` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updatedAt`) VALUES
(1, 'aidat_settings', '{\"Asil Üye\": 100, \"Fahri Üye\": 70, \"Asil Onursal\": 90, \"Fahri Onursal\": 60, \"Öğrenci Üye\": 80}', '2026-01-28 23:22:41'),
(2, 'admin_username', '\"admin\"', '2026-01-28 23:18:01'),
(3, 'admin_password', '\"$2y$10$6htiQDI.e6HZCuKijj9tdeFfuyS51lc8rCdgwlzFlFRjB9vRWbU6a\"', '2026-02-16 02:09:00');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sms_history`
--

CREATE TABLE `sms_history` (
  `id` int NOT NULL,
  `recipients` text NOT NULL,
  `message` text NOT NULL,
  `job_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `error_message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `memberNo` (`memberNo`),
  ADD UNIQUE KEY `tcNo` (`tcNo`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_memberNo` (`memberNo`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_tcNo` (`tcNo`),
  ADD KEY `idx_membershipStatus` (`membershipStatus`),
  ADD KEY `idx_aidatAktif` (`aidatAktif`);

--
-- Tablo için indeksler `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sentAt` (`sentAt`);

--
-- Tablo için indeksler `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_memberId` (`memberId`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`date`);

--
-- Tablo için indeksler `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Tablo için indeksler `sms_history`
--
ALTER TABLE `sms_history`
  ADD PRIMARY KEY (`id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Tablo için AUTO_INCREMENT değeri `sms_history`
--
ALTER TABLE `sms_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`memberId`) REFERENCES `members` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
