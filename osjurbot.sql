-- phpMyAdmin SQL Dump
-- version 4.8.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 05 Jul 2018 pada 16.19
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

-- --------------------------------------------------------

--
-- Struktur dari tabel `Log`
--

CREATE TABLE `Log` (
  `uid` int(11) NOT NULL,
  `jam_masuk` datetime NOT NULL,
  `jam_keluar` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `Users`
--

CREATE TABLE `Users` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `mid` varchar(50) NOT NULL,
  `nim` int(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Struktur dari tabel `Users_blacklist`
--

CREATE TABLE `Users_blacklist` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `blacklist` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  ADD PRIMARY KEY (`uid`);

--
-- Indeks untuk tabel `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `Users_blacklist`
--
ALTER TABLE `Users_blacklist`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `Users`
--
ALTER TABLE `Users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `Users_blacklist`
--
ALTER TABLE `Users_blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
