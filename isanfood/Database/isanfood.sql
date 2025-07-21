-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 04, 2025 at 10:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `isanfood`
--

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `menu_id` int(11) NOT NULL,
  `type_menu_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `Image_url` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`menu_id`, `type_menu_id`, `name`, `description`, `price`, `status`, `Image_url`) VALUES
(1, 2, 'เฉาก๋วย', '้้่่่่เดเดกเดเะ', 20.00, 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `Order_id` int(11) NOT NULL,
  `User_id` int(11) DEFAULT NULL,
  `table_number` varchar(2) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `order_time` datetime DEFAULT NULL,
  `upload_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_detail`
--

CREATE TABLE `order_detail` (
  `Order_detail_id` int(11) NOT NULL,
  `Order_id` int(11) DEFAULT NULL,
  `menu_id` int(11) DEFAULT NULL,
  `Quantity` varchar(100) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `User_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_item` datetime DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `type_menu`
--

CREATE TABLE `type_menu` (
  `Type_menu_id` int(11) NOT NULL,
  `type_name_menu` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `type_menu`
--

INSERT INTO `type_menu` (`Type_menu_id`, `type_name_menu`) VALUES
(1, 'อาหารคาว'),
(2, 'อาหารหวาน'),
(3, 'เครื่องดื่ม');

-- --------------------------------------------------------

--
-- Table structure for table `type_user`
--

CREATE TABLE `type_user` (
  `type_user_id` int(11) NOT NULL,
  `type_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `type_user`
--

INSERT INTO `type_user` (`type_user_id`, `type_name`) VALUES
(1, 'เจ้าของร้าน (Owner)'),
(2, 'แคชเชียร์ (Cashier)'),
(3, 'ครัว (Chef)'),
(4, 'พนักงานเสิร์ฟ (Waiter)'),
(5, 'ลูกค้า (Customer)');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `User_id` int(11) NOT NULL,
  `type_user_id` int(11) DEFAULT NULL,
  `Username` varchar(50) DEFAULT NULL,
  `Password` varchar(8) DEFAULT NULL,
  `fname` varchar(100) DEFAULT NULL,
  `Sname` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','suspended','pending') DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `subdistrict` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `phone` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`User_id`, `type_user_id`, `Username`, `Password`, `fname`, `Sname`, `status`, `province`, `district`, `subdistrict`, `salary`, `position`, `phone`) VALUES
(101, 1, 'admin_owner', '0000', 'สมชาย', 'ยอดเยี่ยม', 'active', 'กรุงเทพมหานคร', 'บางรัก', 'สีลม', 65000.00, 'เจ้าของร้าน', '0812345678'),
(102, 2, 'cashier_lisa', NULL, 'ลิซ่า', 'สดใส', 'active', 'เชียงใหม่', 'เมืองเชียงใหม่', 'ช้างเผือก', 25000.00, 'แคชเชียร์', '0823456789'),
(103, 3, 'chef_pao', NULL, 'เปา', 'อร่อยเลิศ', 'active', 'ภูเก็ต', 'เมืองภูเก็ต', 'ตลาดใหญ่', 30000.00, 'หัวหน้าเชฟ', '0834567890'),
(104, 3, 'chef_ann', NULL, 'แอน', 'ปรุงสุข', 'active', 'ภูเก็ต', 'เมืองภูเก็ต', 'ตลาดใหญ่', 28000.00, 'เชฟ', '0845678901'),
(105, 4, 'waiter_max', NULL, 'แม็กซ์', 'บริการดี', 'active', 'ชลบุรี', 'บางละมุง', 'หนองปรือ', 22000.00, 'พนักงานเสิร์ฟ', '0856789012'),
(106, 4, 'waiter_minnie', NULL, 'มินนี่', 'ยิ้มสวย', 'active', 'ชลบุรี', 'บางละมุง', 'หนองปรือ', 22000.00, 'พนักงานเสิร์ฟ', '0867890123'),
(107, 5, 'customer_john', NULL, 'จอห์น', 'สมิธ', 'active', 'สงขลา', 'หาดใหญ่', 'คอหงส์', NULL, NULL, '0912345678'),
(108, 5, 'customer_jane', NULL, 'เจน', 'โดว์', 'active', 'ขอนแก่น', 'เมืองขอนแก่น', 'ในเมือง', NULL, NULL, '0923456789'),
(109, 5, 'customer_vip', NULL, 'วีไอพี', 'ลูกค้า', 'active', 'กรุงเทพมหานคร', 'ลาดพร้าว', 'วังทองหลาง', NULL, NULL, '0934567890');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`menu_id`),
  ADD KEY `type_menu_id` (`type_menu_id`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`Order_id`),
  ADD KEY `User_id` (`User_id`);

--
-- Indexes for table `order_detail`
--
ALTER TABLE `order_detail`
  ADD PRIMARY KEY (`Order_detail_id`),
  ADD KEY `Order_id` (`Order_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `User_id` (`User_id`);

--
-- Indexes for table `type_menu`
--
ALTER TABLE `type_menu`
  ADD PRIMARY KEY (`Type_menu_id`);

--
-- Indexes for table `type_user`
--
ALTER TABLE `type_user`
  ADD PRIMARY KEY (`type_user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`User_id`),
  ADD KEY `type_user_id` (`type_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `menu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `menu`
--
ALTER TABLE `menu`
  ADD CONSTRAINT `menu_ibfk_1` FOREIGN KEY (`type_menu_id`) REFERENCES `type_menu` (`Type_menu_id`);

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`User_id`) REFERENCES `user` (`User_id`);

--
-- Constraints for table `order_detail`
--
ALTER TABLE `order_detail`
  ADD CONSTRAINT `order_detail_ibfk_1` FOREIGN KEY (`Order_id`) REFERENCES `order` (`Order_id`),
  ADD CONSTRAINT `order_detail_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`Order_id`),
  ADD CONSTRAINT `payment_ibfk_2` FOREIGN KEY (`User_id`) REFERENCES `user` (`User_id`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`type_user_id`) REFERENCES `type_user` (`type_user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
