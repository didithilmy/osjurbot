-- phpMyAdmin SQL Dump
-- version 4.8.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 05 Jul 2018 pada 21.03
-- Versi server: 5.7.22-0ubuntu18.04.1
-- Versi PHP: 7.2.5-0ubuntu0.18.04.1

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
  `uid` int(11) NOT NULL,
  `jam_masuk` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `Current`
--

INSERT INTO `Current` (`uid`, `jam_masuk`) VALUES
(1, '2018-07-05 13:09:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `Log`
--

CREATE TABLE `Log` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `jam_masuk` datetime NOT NULL,
  `jam_keluar` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `Log`
--

INSERT INTO `Log` (`id`, `uid`, `jam_masuk`, `jam_keluar`) VALUES
(1, 1, '2018-07-05 19:24:40', '2018-07-05 19:39:23'),
(2, 1, '2018-07-05 19:39:47', '2018-07-05 19:41:33'),
(3, 4, '2018-07-05 19:44:34', '2018-07-05 20:09:32'),
(4, 4, '2018-07-05 20:09:47', '2018-07-05 20:09:51');

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
-- Dumping data untuk tabel `Users`
--

INSERT INTO `Users` (`id`, `name`, `mid`, `nim`, `count`) VALUES
(4, 'Josh', 'example', 16517133, 1),
(7, 'Tony', 'example', 16517136, 0),
(8, 'Jony', 'example', 16517199, 0),
(9, 'Monica', 'example', 16517019, 0),
(10, 'Patrice', 'example', 16517999, 0),
(11, 'Emil', 'example', 16517201, 0),
(12, 'John', 'example', 16517908, 0),
(13, 'Koni', 'example', 16517888, 0),
(14, 'Ron', 'example', 16517111, 0),
(15, 'Patrick', 'example', 16517222, 0),
(16, 'Bob', 'example', 16517333, 0),
(17, 'Jerry', 'example', 16517444, 0),
(18, 'Dona', 'example', 16517555, 0),
(19, 'Goby', 'example', 16517777, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `Users_blacklist`
--

CREATE TABLE `Users_blacklist` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `reason` text NOT NULL,
  `type` int(1) NOT NULL,
  `blacklist` datetime NOT NULL,
  `length` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `Users_blacklist`
--

INSERT INTO `Users_blacklist` (`id`, `uid`, `reason`, `type`, `blacklist`, `length`) VALUES
(1, 4, 'Testing', 1, '2018-07-05 09:00:00', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `Users_shuffled`
--

CREATE TABLE `Users_shuffled` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data untuk tabel `Users_shuffled`
--

INSERT INTO `Users_shuffled` (`id`, `uid`) VALUES
(1, 9),
(2, 4),
(3, 10),
(4, 8),
(6, 7),
(7, 8),
(8, 18),
(9, 10),
(10, 19),
(11, 8),
(12, 9),
(13, 14),
(14, 7),
(15, 16),
(16, 17),
(17, 15),
(18, 4),
(19, 12),
(20, 11),
(21, 13);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `Current`
--
ALTER TABLE `Current`
  ADD PRIMARY KEY (`uid`);

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
-- Indeks untuk tabel `Users_blacklist`
--
ALTER TABLE `Users_blacklist`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `Users_shuffled`
--
ALTER TABLE `Users_shuffled`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `Log`
--
ALTER TABLE `Log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `Users`
--
ALTER TABLE `Users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `Users_blacklist`
--
ALTER TABLE `Users_blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `Users_shuffled`
--
ALTER TABLE `Users_shuffled`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
