-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 09-04-2025 a las 22:50:26
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `checkout`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `admin_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `admin_name`, `created_at`) VALUES
(1, '1234', '$2y$10$xWNPB4rDa4bwByrERbuS3eaHPISV0NOKZ2KmHykI/QvDAbxfALoCO', NULL, '2025-02-26 17:33:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `giftcard_redemptions`
--

CREATE TABLE `giftcard_redemptions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `original_amount` decimal(10,2) NOT NULL,
  `balance` decimal(10,2) NOT NULL,
  `redeemed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `giftcard_redemptions`
--

INSERT INTO `giftcard_redemptions` (`id`, `code`, `original_amount`, `balance`, `redeemed`, `created_at`, `updated_at`) VALUES
(1, 'GC-b5cd078', 1000.00, 0.00, 1, '2025-04-09 19:58:50', '2025-04-09 20:14:24'),
(2, 'GC-e918812', 799.00, 0.00, 1, '2025-04-09 20:17:30', '2025-04-09 20:17:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `giftcard_transactions`
--

CREATE TABLE `giftcard_transactions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `giftcard_transactions`
--

INSERT INTO `giftcard_transactions` (`id`, `code`, `order_id`, `amount`, `transaction_date`) VALUES
(1, 'GC-b5cd078', '2', 798.99, '2025-04-09 19:58:57'),
(2, 'GC-b5cd078', '3', 201.01, '2025-04-09 20:14:24'),
(3, 'GC-e918812', '5', 799.00, '2025-04-09 20:17:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `street` varchar(255) NOT NULL,
  `colony` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `zip_code` varchar(10) NOT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending' COMMENT 'Tracks the payment status of an order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`order_id`, `customer_name`, `customer_email`, `phone`, `street`, `colony`, `city`, `state`, `zip_code`, `status`, `payment_status`, `created_at`, `payment_method`, `payment_id`, `payment_notes`, `total_amount`) VALUES
(1, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-09 20:48:49', 'paypal', '8LK31692TA111523V', NULL, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `size` varchar(10) NOT NULL DEFAULT '',
  `personalization_name` varchar(100) DEFAULT NULL,
  `personalization_number` varchar(10) DEFAULT NULL,
  `personalization_patch` text DEFAULT NULL,
  `giftcard_sent` tinyint(1) DEFAULT 0,
  `giftcard_status` varchar(20) DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price`, `subtotal`, `size`, `personalization_name`, `personalization_number`, `personalization_patch`, `giftcard_sent`, `giftcard_status`) VALUES
(1, 1, 1, 1, 799.00, 799.00, 'L', 'Pancho', '10', '1', 0, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `stock`, `image_url`, `category`, `created_at`, `updated_at`, `image_path`, `status`) VALUES
(1, 'Manchester City Local 24/25', 'Jersey Manchester City Local 24/25', 799.00, 0, 'img/products/1744231566_67a54cae7727c.png', 'nueva', '2025-04-09 20:46:06', '2025-04-09 20:46:06', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_url`, `sort_order`, `created_at`) VALUES
(7, 3, 'uploads/products/67ec7477ab999_0.webp', 0, '2025-04-01 23:19:19'),
(8, 3, 'uploads/products/67ec7477aba75_1.png', 1, '2025-04-01 23:19:19'),
(9, 9, 'uploads/products/67ec7506731e2_0.jpg', 0, '2025-04-01 23:21:42'),
(10, 9, 'uploads/products/67ec750673346_1.jpg', 1, '2025-04-01 23:21:42'),
(11, 7, 'uploads/products/67ec75207b7e2_0.png', 0, '2025-04-01 23:22:08'),
(15, 7, 'uploads/products/67ec757fd94ea_0.jpg', 1, '2025-04-01 23:23:43'),
(16, 8, 'uploads/products/67ec76e47c106_0.jpg', 0, '2025-04-01 23:29:40'),
(17, 8, 'uploads/products/67ec76e47c283_1.jpg', 1, '2025-04-01 23:29:40'),
(18, 4, 'uploads/products/67ec76fb951c4_0.png', 0, '2025-04-01 23:30:03'),
(19, 4, 'uploads/products/67ec76fb95265_1.png', 1, '2025-04-01 23:30:03'),
(20, 11, 'uploads/products/67ec77123ef58_0.jpg', 0, '2025-04-01 23:30:26'),
(21, 11, 'uploads/products/67ec77123f0a4_1.jpg', 1, '2025-04-01 23:30:26'),
(22, 10, 'uploads/products/67ec77329b7e3_0.jpg', 0, '2025-04-01 23:30:59'),
(23, 10, 'uploads/products/67ec7732aac86_1.png', 1, '2025-04-01 23:30:59'),
(24, 14, 'uploads/products/67ec780bc716d_0.png', 0, '2025-04-01 23:34:37'),
(25, 14, 'uploads/products/67ec780ca1d70_1.png', 1, '2025-04-01 23:34:37'),
(26, 15, 'uploads/products/67ed659f04bb1_0.jpg', 0, '2025-04-02 16:28:15'),
(27, 15, 'uploads/products/67ed659f076ec_1.png', 1, '2025-04-02 16:28:15'),
(28, 16, 'uploads/products/67ed66300eb03_0.jpg', 0, '2025-04-02 16:30:40'),
(29, 16, 'uploads/products/67ed66300ec98_1.jpg', 1, '2025-04-02 16:30:40'),
(30, 17, 'img/products/additional/1743624740_0_JerseyBarcelonaV2.jpg', 0, '2025-04-02 20:12:20'),
(31, 17, 'img/products/additional/1743624740_1_JerseyBarcelonaV3.jpg', 0, '2025-04-02 20:12:20'),
(32, 18, 'img/products/additional/1743625389_0_AtleticoM2.jpg', 0, '2025-04-02 20:23:09'),
(33, 18, 'img/products/additional/1743625389_1_AtleticoM3.jpg', 0, '2025-04-02 20:23:09'),
(34, 2, 'uploads/products/67ed9e9c4bd83_0.jpg', 0, '2025-04-02 20:31:24'),
(35, 2, 'uploads/products/67ed9e9c4bf1d_1.jpg', 1, '2025-04-02 20:31:24'),
(36, 19, 'img/products/additional/1743626204_0_RealM2-Photoroom.png', 0, '2025-04-02 20:36:46'),
(37, 19, 'img/products/additional/1743626205_1_RealM3-Photoroom.png', 0, '2025-04-02 20:36:46'),
(44, 23, 'img/products/additional/1743626724_0_AtleticoMV2.jpg', 0, '2025-04-02 20:45:24'),
(45, 23, 'img/products/additional/1743626724_1_AtleticoMV3.jpg', 0, '2025-04-02 20:45:24'),
(46, 24, 'img/products/additional/1743626959_0_athletic2.jpg', 0, '2025-04-02 20:49:19'),
(47, 25, 'img/products/additional/1743627060_0_2.jpg', 0, '2025-04-02 20:51:00'),
(48, 25, 'uploads/products/67eda34b31b55_0.webp', 1, '2025-04-02 20:51:23'),
(50, 26, 'img/products/additional/1743627364_0_Paris2.jpg', 0, '2025-04-02 20:56:04'),
(51, 26, 'img/products/additional/1743627364_1_Paris3.jpg', 0, '2025-04-02 20:56:04'),
(52, 27, 'img/products/additional/1743627491_0_2.jpg', 0, '2025-04-02 20:58:11'),
(53, 27, 'img/products/additional/1743627491_1_3.jpg', 0, '2025-04-02 20:58:11'),
(54, 28, 'img/products/additional/1743627735_0_2.jpg', 0, '2025-04-02 21:02:16'),
(55, 28, 'img/products/additional/1743627736_1_3-Photoroom.png', 0, '2025-04-02 21:02:16'),
(56, 29, 'img/products/additional/1743628037_0_2.jpg', 0, '2025-04-02 21:07:17'),
(57, 29, 'img/products/additional/1743628037_1_3-Photoroom.png', 0, '2025-04-02 21:07:17'),
(58, 30, 'img/products/additional/1743628208_0_2.jpg', 0, '2025-04-02 21:10:08'),
(59, 30, 'img/products/additional/1743628208_1_3.jpg', 0, '2025-04-02 21:10:08'),
(62, 31, 'uploads/products/67eda8d80f177_0.webp', 0, '2025-04-02 21:15:04'),
(63, 31, 'uploads/products/67eda8d80f2b5_1.webp', 1, '2025-04-02 21:15:04'),
(64, 32, 'uploads/products/67eda9ae4e561_0.webp', 0, '2025-04-02 21:18:38'),
(65, 32, 'uploads/products/67eda9ae4e6bd_1.webp', 1, '2025-04-02 21:18:38'),
(70, 33, 'uploads/products/67edab07eb2e8_0.png', 0, '2025-04-02 21:24:28'),
(71, 33, 'uploads/products/67edab093d805_1.png', 1, '2025-04-02 21:24:28'),
(72, 34, 'img/products/additional/1743629218_0_2.jpg', 0, '2025-04-02 21:26:58'),
(74, 34, 'uploads/products/67edabcb8f86d_0.png', 1, '2025-04-02 21:27:39'),
(75, 35, 'img/products/additional/1743629792_0_2.jpg', 0, '2025-04-02 21:36:32'),
(76, 35, 'img/products/additional/1743629792_1_3.jpg', 0, '2025-04-02 21:36:32'),
(77, 36, 'img/products/additional/1743631095_0_2.jpg', 0, '2025-04-02 21:58:16'),
(78, 36, 'img/products/additional/1743631096_1_3-Photoroom.png', 0, '2025-04-02 21:58:16'),
(79, 37, 'img/products/additional/1743631458_0_2.png', 0, '2025-04-02 22:04:18'),
(80, 37, 'img/products/additional/1743631458_1_3.jpg', 0, '2025-04-02 22:04:18'),
(81, 38, 'img/products/additional/1743631710_0_2.jpg', 0, '2025-04-02 22:08:30'),
(82, 38, 'img/products/additional/1743631710_1_3-Photoroom.png', 0, '2025-04-02 22:08:30'),
(83, 39, 'img/products/additional/1743631990_0_2.jpg', 0, '2025-04-02 22:13:11'),
(84, 39, 'img/products/additional/1743631990_1_3-Photoroom (1).png', 0, '2025-04-02 22:13:11'),
(85, 40, 'img/products/additional/1743632160_0_2.jpg', 0, '2025-04-02 22:16:00'),
(86, 40, 'img/products/additional/1743632160_1_3.jpg', 0, '2025-04-02 22:16:00'),
(87, 41, 'img/products/additional/1743632290_0_2.jpg', 0, '2025-04-02 22:18:10'),
(88, 41, 'img/products/additional/1743632290_1_3.jpg', 0, '2025-04-02 22:18:10'),
(89, 42, 'img/products/additional/1743632492_0_2.png', 0, '2025-04-02 22:21:33'),
(90, 42, 'img/products/additional/1743632492_1_3-Photoroom.png', 0, '2025-04-02 22:21:33'),
(91, 43, 'img/products/additional/1743632702_0_2.png', 0, '2025-04-02 22:25:03'),
(92, 43, 'img/products/additional/1743632702_1_3.png', 0, '2025-04-02 22:25:03'),
(93, 44, 'img/products/additional/1743632791_0_2.jpg', 0, '2025-04-02 22:26:31'),
(94, 44, 'img/products/additional/1743632791_1_3.jpg', 0, '2025-04-02 22:26:31'),
(95, 45, 'img/products/additional/1743647655_0_2.jpg', 0, '2025-04-03 02:34:15'),
(96, 45, 'img/products/additional/1743647655_1_3.jpg', 0, '2025-04-03 02:34:15'),
(97, 46, 'img/products/additional/1743647824_0_2-Photoroom.png', 0, '2025-04-03 02:37:05'),
(98, 46, 'img/products/additional/1743647824_1_3-Photoroom.png', 0, '2025-04-03 02:37:05'),
(99, 47, 'img/products/additional/1743647961_0_2.jpg', 0, '2025-04-03 02:39:21'),
(100, 47, 'img/products/additional/1743647961_1_3.jpg', 0, '2025-04-03 02:39:21'),
(101, 48, 'img/products/additional/1743648068_0_2.jpg', 0, '2025-04-03 02:41:08'),
(102, 48, 'img/products/additional/1743648068_1_3.jpg', 0, '2025-04-03 02:41:08'),
(103, 49, 'img/products/additional/1743648433_0_2.jpg', 0, '2025-04-03 02:47:13'),
(104, 49, 'uploads/products/67edf6e0263fc_0.webp', 1, '2025-04-03 02:48:00'),
(109, 51, 'uploads/products/67eec6d45ad56_0.jpg', 0, '2025-04-03 17:35:16'),
(110, 51, 'uploads/products/67eec6d45ae99_1.png', 1, '2025-04-03 17:35:16'),
(111, 52, 'img/products/additional/1743701819_0_2.jpg', 0, '2025-04-03 17:36:59'),
(112, 52, 'img/products/additional/1743701819_1_3.jpg', 0, '2025-04-03 17:36:59'),
(113, 53, 'img/products/additional/1743701996_0_2.jpg', 0, '2025-04-03 17:39:56'),
(114, 53, 'img/products/additional/1743701996_1_3.jpg', 0, '2025-04-03 17:39:56'),
(115, 54, 'img/products/additional/1743702111_0_2.jpg', 0, '2025-04-03 17:41:51'),
(116, 54, 'img/products/additional/1743702111_1_3.jpg', 0, '2025-04-03 17:41:51'),
(117, 55, 'img/products/additional/1743702883_0_2.jpg', 0, '2025-04-03 17:54:43'),
(118, 55, 'img/products/additional/1743702883_1_3.jpg', 0, '2025-04-03 17:54:43'),
(119, 56, 'img/products/additional/1743702952_0_5.jpg', 0, '2025-04-03 17:55:53'),
(120, 56, 'img/products/additional/1743702953_1_6-Photoroom.png', 0, '2025-04-03 17:55:53'),
(121, 57, 'img/products/additional/1743703199_0_2-Photoroom.png', 0, '2025-04-03 18:00:00'),
(122, 57, 'img/products/additional/1743703200_1_3-Photoroom.png', 0, '2025-04-03 18:00:00'),
(123, 58, 'img/products/additional/1743703468_0_2-Photoroom.png', 0, '2025-04-03 18:04:29'),
(124, 58, 'img/products/additional/1743703468_1_3-Photoroom.png', 0, '2025-04-03 18:04:29'),
(125, 59, 'img/products/additional/1743703817_0_2-Photoroom.png', 0, '2025-04-03 18:10:18'),
(126, 59, 'img/products/additional/1743703818_1_4-Photoroom.png', 0, '2025-04-03 18:10:18'),
(129, 61, 'img/products/additional/1743704491_0_2-Photoroom.png', 0, '2025-04-03 18:21:33'),
(130, 61, 'img/products/additional/1743704493_1_3-Photoroom.png', 0, '2025-04-03 18:21:33'),
(131, 62, 'img/products/additional/1743704770_0_2-Photoroom.png', 0, '2025-04-03 18:26:11'),
(133, 62, 'uploads/products/67eed30550851_0.png', 2, '2025-04-03 18:27:17'),
(134, 13, 'uploads/products/67f00aef6b1a0_0.jpg', 2, '2025-04-04 16:38:07'),
(135, 13, 'uploads/products/67f00aef6b2ef_1.jpg', 3, '2025-04-04 16:38:07'),
(137, 63, 'uploads/products/67f154c067f25_0.png', 1, '2025-04-05 16:05:28'),
(138, 63, 'uploads/products/67f154c584b4f_1.png', 2, '2025-04-05 16:05:28'),
(139, 64, 'img/products/additional/1743869290_0_2.jpg', 0, '2025-04-05 16:08:10'),
(141, 64, 'uploads/products/67f15b1b3bbd0_0.jpg', 2, '2025-04-05 16:32:27'),
(142, 1, 'img/products/additional/1744231566_0_ManchsterC2.png', 0, '2025-04-09 20:46:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resenas`
--

CREATE TABLE `resenas` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `calificacion` decimal(2,1) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `recomienda` enum('si','no') DEFAULT 'si',
  `imagen_path` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sales_stats`
--

CREATE TABLE `sales_stats` (
  `id` int(11) NOT NULL,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `total_orders` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sales_stats`
--

INSERT INTO `sales_stats` (`id`, `total_sales`, `total_orders`) VALUES
(1, 0.00, 0),
(2, 0.00, 0),
(3, 0.00, 0),
(4, 0.00, 0),
(5, 0.00, 0),
(6, 0.00, 0),
(7, 0.00, 0),
(8, 0.00, 0),
(9, 0.00, 0),
(10, 799.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subscribers`
--

CREATE TABLE `subscribers` (
  `subscriber_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subscription_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `giftcard_redemptions`
--
ALTER TABLE `giftcard_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indices de la tabla `giftcard_transactions`
--
ALTER TABLE `giftcard_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_newsletter_email` (`email`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`);

--
-- Indices de la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order_items_giftcard` (`order_id`,`personalization_name`,`giftcard_sent`);

--
-- Indices de la tabla `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_product_category` (`category`);

--
-- Indices de la tabla `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indices de la tabla `resenas`
--
ALTER TABLE `resenas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `sales_stats`
--
ALTER TABLE `sales_stats`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`subscriber_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `giftcard_redemptions`
--
ALTER TABLE `giftcard_redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `giftcard_transactions`
--
ALTER TABLE `giftcard_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT de la tabla `resenas`
--
ALTER TABLE `resenas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sales_stats`
--
ALTER TABLE `sales_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
