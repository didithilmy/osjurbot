-- phpMyAdmin SQL Dump
-- version 4.8.1
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Waktu pembuatan: 08 Jul 2018 pada 13.03
-- Versi server: 8.0.11
-- Versi PHP: 7.2.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `osjurbot`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `Current`
--

CREATE TABLE `Current` (
  `id` bigint(11) NOT NULL,
  `nim` text NOT NULL,
  `jam_masuk` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `Log`
--

CREATE TABLE `Log` (
  `id` int(11) NOT NULL,
  `nim` text NOT NULL,
  `jam_masuk` datetime NOT NULL,
  `jam_keluar` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `Log`
--

INSERT INTO `Log` (`id`, `nim`, `jam_masuk`, `jam_keluar`) VALUES
(1, '1', '2018-07-05 19:24:40', '2018-07-05 19:39:23'),
(2, '1', '2018-07-05 19:39:47', '2018-07-05 19:41:33'),
(3, '4', '2018-07-05 19:44:34', '2018-07-05 20:09:32'),
(4, '4', '2018-07-05 20:09:47', '2018-07-05 20:09:51'),
(5, '16517292', '2018-07-08 10:58:11', '2018-07-08 16:58:21'),
(6, '16517297', '2018-07-08 10:59:18', '2018-07-08 17:59:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peserta`
--

CREATE TABLE `peserta` (
  `name` varchar(36) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `nim` int(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Struktur dari tabel `Users`
--

CREATE TABLE `Users` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `mid` varchar(50) NOT NULL,
  `nim` int(8) NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `Current`
--
ALTER TABLE `Current`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `Log`
--
ALTER TABLE `Log`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- Indeks untuk tabel `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nim` (`nim`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `Current`
--
ALTER TABLE `Current`
  MODIFY `id` bigint(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `Log`
--
ALTER TABLE `Log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `Users`
--
ALTER TABLE `Users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
