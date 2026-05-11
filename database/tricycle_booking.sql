-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 19, 2026 at 05:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tricycle_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_code` varchar(20) NOT NULL,
  `passenger_id` int(11) NOT NULL,
  `pickup_landmark` varchar(100) NOT NULL,
  `dropoff_landmark` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `status` enum('PENDING','ASSIGNED','PASSENGER PICKED UP','IN TRANSIT','COMPLETED','CANCELLED') DEFAULT 'PENDING',
  `pickup_time` datetime DEFAULT NULL,
  `dropoff_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_pax` int(11) NOT NULL DEFAULT 0,
  `trike_units` int(11) NOT NULL DEFAULT 0,
  `distance` decimal(10,2) DEFAULT NULL,
  `fare` decimal(10,2) DEFAULT NULL,
  `preferred_time` datetime DEFAULT current_timestamp(),
  `driver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_code`, `passenger_id`, `pickup_landmark`, `dropoff_landmark`, `notes`, `driver_name`, `status`, `pickup_time`, `dropoff_time`, `created_at`, `total_pax`, `trike_units`, `distance`, `fare`, `preferred_time`, `driver_id`) VALUES
(1, 'BK26030811084412', 1, 'Monumento', 'Bagong Barrio', 'sadada', 'Sia Santiago', 'ASSIGNED', '2026-03-09 11:08:00', NULL, '2026-03-08 03:08:44', 1, 1, NULL, NULL, '0000-00-00 00:00:00', NULL),
(3, 'BK26030910042912', 1, 'Eulogio Rodriguez Elementary School, Jasmin Street, Caloocan, 1403 Metro Manila, Philippines', 'Diocesan Shrine and Parish of Our Lady of Grace, M. H. del Pilar Street, Caloocan, 1403 Metro Manila', 'dsada\nCancelled: No reason provided', NULL, 'CANCELLED', '2026-03-10 00:03:00', NULL, '2026-03-09 02:04:29', 34, 1, 2.12, 30.61, NULL, NULL),
(4, 'BK26030910253663', 1, 'Systems Plus Computer College, 141-143 10th Avenue, Caloocan, 1400 Metro Manila, Philippines', 'Diocesan Shrine and Parish of Our Lady of Grace, M. H. del Pilar Street, Caloocan, 1403 Metro Manila', 'saktong 10:30 po sana umuulan po kasi', 'Sia Santiago', 'IN TRANSIT', '2026-03-09 11:25:13', NULL, '2026-03-09 02:25:36', 4, 1, 0.53, 25.00, '2026-03-09 10:25:36', NULL),
(5, 'BK26030911362042', 1, 'LRT 5th Avenue, Obrero, Caloocan, National Capital District, Philippines', 'WCC Aeronautical and Technological College, William Shaw Street, Grace Park East, 1470 Metro Manila,', 'hello', 'Sia Santiago', 'IN TRANSIT', '2026-03-09 11:37:27', NULL, '2026-03-09 03:36:20', 4, 1, 1.50, 27.51, '2026-03-09 11:36:20', NULL),
(11, 'BK26030912571746', 5, 'SM City Grand Central, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 'WCC Aeronautical and Technological College, William Shaw Street, Grace Park East, 1470 Metro Manila,', 'hahaha', 'Richmond', 'COMPLETED', '2026-03-09 12:57:52', '2026-03-09 12:58:02', '2026-03-09 04:57:17', 3, 1, 1.16, 25.78, '2026-03-09 12:57:17', NULL),
(12, 'BK26030913084339', 8, 'Diocesan Shrine and Parish of Our Lady of Grace, M. H. del Pilar Street, Caloocan, 1403 Metro Manila', 'SM City Grand Central, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 'sdada', 'David', 'COMPLETED', '2026-03-09 13:17:37', '2026-03-09 13:17:43', '2026-03-09 05:08:43', 12, 3, 0.45, 75.00, '2026-03-09 13:08:43', 9),
(13, 'BK26030913592697', 6, 'Diocesan Shrine and Parish of Our Lady of Grace, M. H. del Pilar Street, Caloocan, 1403 Metro Manila', 'WCC Aeronautical and Technological College, William Shaw Street, Grace Park East, 1470 Metro Manila,', 'sdada', 'Libb', 'COMPLETED', '2026-03-09 14:01:10', '2026-03-09 14:01:41', '2026-03-09 05:59:26', 2, 1, 0.81, 25.00, '2026-03-09 13:59:26', 10),
(14, 'BK26030914024422', 4, 'Hotel Sogo - LRT Monumento, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 'WCC Aeronautical and Technological College, William Shaw Street, Grace Park East, 1470 Metro Manila,', 'umaga na', 'Richmond', 'COMPLETED', '2026-03-09 14:03:24', '2026-03-09 14:03:43', '2026-03-09 06:02:44', 3, 1, 1.16, 25.78, '2026-03-09 14:02:44', 7),
(15, 'BK26030914081738', 4, 'LRT 5th Avenue, Obrero, Caloocan, National Capital District, Philippines', 'Systems Plus Computer College, 141-143 10th Avenue, Caloocan, 1400 Metro Manila, Philippines', 'malalate ako ha', 'Richmond', 'COMPLETED', '2026-03-09 14:09:15', '2026-03-09 14:09:25', '2026-03-09 06:08:17', 1, 1, 0.93, 25.00, '2026-03-09 14:08:17', 7),
(16, 'BK26031011085048', 1, 'Eulogio Rodriguez Elementary School, Jasmin Street, Caloocan, 1403 Metro Manila, Philippines', 'Systems Plus Computer College, 141-143 10th Avenue, Caloocan, 1400 Metro Manila, Philippines', 'basta medyo maaga sana sa 1:00', 'Richmond', 'COMPLETED', '2026-03-10 11:10:41', '2026-03-10 11:10:55', '2026-03-10 03:08:50', 3, 1, 1.99, 29.97, '2026-03-10 11:08:50', 7),
(17, 'BK26031016522816', 11, 'Diocesan Shrine and Parish of Our Lady of Grace, M. H. del Pilar Street, Caloocan, 1403 Metro Manila', 'SM City Grand Central, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 'basta maaga makadating', 'Sia Santiago', 'COMPLETED', '2026-03-10 16:55:01', '2026-03-10 16:55:21', '2026-03-10 08:52:28', 3, 1, 0.45, 25.00, '2026-03-10 16:52:28', 3),
(18, 'BK26031815523912', 5, 'Hotel Sogo - LRT Monumento, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 'Systems Plus Computer College, 141-143 10th Avenue, Caloocan, 1400 Metro Manila, Philippines', 'haha', 'Sia Santiago', 'COMPLETED', '2026-03-18 15:54:41', '2026-03-18 15:55:55', '2026-03-18 07:52:39', 4, 1, 0.66, 25.00, '2026-03-18 15:52:39', 3);

-- --------------------------------------------------------

--
-- Table structure for table `driver_stats`
--

CREATE TABLE `driver_stats` (
  `driver_id` int(11) NOT NULL,
  `lifetime_earnings` decimal(10,2) DEFAULT 0.00,
  `today_earnings` decimal(10,2) DEFAULT 0.00,
  `total_completed_trips` int(11) DEFAULT 0,
  `last_update` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_stats`
--

INSERT INTO `driver_stats` (`driver_id`, `lifetime_earnings`, `today_earnings`, `total_completed_trips`, `last_update`) VALUES
(3, 75.78, 25.00, 3, '2026-03-18'),
(7, 80.75, 29.97, 3, '2026-03-10'),
(9, 75.00, 75.00, 1, '2026-03-09'),
(10, 25.00, 25.00, 1, '2026-03-09');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lon` decimal(10,7) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `lat`, `lon`, `added_at`) VALUES
(1, 'SM City Grand Central, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 14.6487403, 120.9837453, '2026-03-08 07:26:00'),
(2, 'Systems Plus Computer College, 141-143 10th Avenue, Caloocan, 1400 Metro Manila, Philippines', 14.6512772, 120.9893223, '2026-03-08 07:27:42'),
(3, 'WCC Aeronautical and Technological College, William Shaw Street, Grace Park East, 1470 Metro Manila, Philippines', 14.6562513, 120.9911822, '2026-03-08 07:28:04'),
(5, 'Maria Clara High School, Maria Clara Street, Grace Park East, 1405 Metro Manila, Philippines', 14.6484619, 120.9855359, '2026-03-08 23:52:41'),
(6, 'Hotel Sogo - LRT Monumento, Rizal Avenue Extension, Caloocan, 1403 Metro Manila, Philippines', 14.6487403, 120.9837453, '2026-03-08 23:54:45'),
(7, 'LRT 5th Avenue, Obrero, Caloocan, National Capital District, Philippines', 14.6443740, 120.9845190, '2026-03-08 23:55:37'),
(8, 'New Caloocan City Hall, Caloocan, 1400 Metro Manila, Philippines', 14.6488341, 120.9905916, '2026-03-09 00:10:51'),
(10, 'Diocesan Shrine and Parish of Our Lady of Grace, M. H. del Pilar Street, Caloocan, 1403 Metro Manila, Philippines', 14.6526741, 120.9846054, '2026-03-09 00:45:39'),
(11, 'Eulogio Rodriguez Elementary School, Jasmin Street, Caloocan, 1403 Metro Manila, Philippines', 14.6676909, 120.9967754, '2026-03-09 00:52:11');

-- --------------------------------------------------------

--
-- Table structure for table `tricycle_locations`
--

CREATE TABLE `tricycle_locations` (
  `id` int(11) NOT NULL,
  `pickup` varchar(250) NOT NULL,
  `dropoff` varchar(250) NOT NULL,
  `distance_km` decimal(8,2) NOT NULL,
  `fare` decimal(8,2) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tricycle_routes`
--

CREATE TABLE `tricycle_routes` (
  `id` int(11) NOT NULL,
  `pickup` varchar(250) NOT NULL,
  `dropoff` varchar(250) NOT NULL,
  `distance_km` decimal(8,2) NOT NULL,
  `fare` decimal(8,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `profile` varchar(255) DEFAULT NULL,
  `role` enum('passenger','admin','driver') DEFAULT 'passenger',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'offline',
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `contact`, `profile`, `role`, `created_at`, `status`, `otp_code`, `otp_expiry`) VALUES
(1, 'Son Gieric Sajoca', 'stell@gmail.com', '$2y$10$hVp0dV1aO.TinZUiIN.MUu/KQ5e0sgF9tn9gwK9EI7kNGK7g6O3i.', '09928697153', 'profile_1_1772691102.png', 'passenger', '2026-03-05 04:57:05', 'offline', NULL, NULL),
(2, 'Admin', 'admin@gmail.com', '$2y$10$ZIliI5xWzXlEchlwsYGaaOpr9HpHzmux2jqCMQO7xxml2HUcjxS7e', '09171234567', NULL, 'admin', '2026-03-05 05:13:55', 'offline', NULL, NULL),
(3, 'Sia Santiago', 'sia@gmail.com', '$2y$10$6tbCV7F6hYnnY/Ut3PqBxeylJps9XwrBtkCAqIynumKy7EoyaAcUW', '09928634153', 'driver_3_1772777217.jpg', 'driver', '2026-03-05 08:08:31', 'online', NULL, NULL),
(4, 'Son Jerick', 'jerickchua58@gmail.com', '$2y$10$jKNmBbGTzbvg5b2nM5dLT.dAFJM70UwxfOPMG3DRQZBvMOoYAs4fG', '09928697153', NULL, 'passenger', '2026-03-06 05:28:28', 'offline', '298106', '2026-03-09 13:43:40'),
(5, 'Freaky Brown', 'freakybrown066@gmail.com', '$2y$10$AyFcxIUCRUTYulTxgQE2Wu2ITzNCMkRpNvrmCO4Iul9R2yXna3iRC', '09928627153', 'profile_5_1772775832.png', 'passenger', '2026-03-06 05:43:27', 'offline', '974641', '2026-03-09 13:39:38'),
(6, 'joshua dequina', 'joshua.dequina@my.jru.edu', '$2y$10$yuuiYltMeWFfGgDZTOUQc.3PQNNM4WV0ZByBeB.1EYKH/fRpBZqNW', '09928697153', 'profile_6_1772777597.png', 'passenger', '2026-03-06 06:12:37', 'offline', NULL, NULL),
(7, 'Richmond', 'rich@gmail.com', '$2y$10$mEAAkUwwJn/IZmvjl.BkjuzYet3zB74UipmpxrEdUV9tULrF1r2aq', '09928697134', 'driver_7_1772941296.png', 'driver', '2026-03-06 07:59:10', 'online', NULL, NULL),
(8, 'Ken Chiang', 'bernlinga05@gmail.com', '$2y$10$.KP7hwpODR6BCctgMJQyYO9W/UtaORXXvFz664JGPhpRN/lfqZqK2', '09928634189', 'profile_8_1772784843.png', 'passenger', '2026-03-06 08:13:42', 'offline', NULL, NULL),
(9, 'David', 'david@gmail.com', '$2y$10$h9gnmKIsyk2LABGPWACD/eKskASKDNbGM3ZNmjMSdw1pQxB314fxu', '09928697145', 'default.png', 'driver', '2026-03-06 13:16:53', 'online', NULL, NULL),
(10, 'Libb', 'libb@gmail.com', '$2y$10$Tl6uyC6SESeIQkgt2jK/sOPWyO8Q7rRHo3xfJm2Lr0VqMCDYNKL4u', '09928697134', 'default.png', 'driver', '2026-03-08 05:26:14', 'online', NULL, NULL),
(11, 'Cristina Mabini', 'mmariasanchezz156@gmail.com', '$2y$10$CigHpDVcAiDrSyFfSG/jaukVG/ARXmYjd7W8i5vxdOkrPDes.XLmy', '09928627153', 'profile_11_1773132627.png', 'passenger', '2026-03-10 08:49:55', 'offline', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `passenger_id` (`passenger_id`);

--
-- Indexes for table `driver_stats`
--
ALTER TABLE `driver_stats`
  ADD PRIMARY KEY (`driver_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tricycle_locations`
--
ALTER TABLE `tricycle_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tricycle_routes`
--
ALTER TABLE `tricycle_routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tricycle_locations`
--
ALTER TABLE `tricycle_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tricycle_routes`
--
ALTER TABLE `tricycle_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`passenger_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_stats`
--
ALTER TABLE `driver_stats`
  ADD CONSTRAINT `driver_stats_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
