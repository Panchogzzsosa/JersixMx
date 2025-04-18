-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 10-04-2025 a las 19:32:59
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
(1, 'GC-3099066', 1000.00, 0.00, 1, '2025-04-09 21:20:48', '2025-04-09 21:48:27'),
(2, 'GC-b774ba8', 1000.00, 0.00, 1, '2025-04-09 21:34:36', '2025-04-09 21:39:22'),
(3, 'GC-34108bc', 799.00, 0.00, 1, '2025-04-09 21:52:51', '2025-04-09 21:53:04'),
(4, 'GC-48ae788', 799.00, 0.00, 1, '2025-04-09 22:00:38', '2025-04-09 22:01:39'),
(5, 'GC-d7d11e2', 799.00, 799.00, 0, '2025-04-09 22:09:44', '2025-04-09 22:09:44'),
(6, 'GC-3d537c5', 1000.00, 0.00, 1, '2025-04-09 22:12:14', '2025-04-09 22:19:04'),
(7, 'GC-61c0947', 799.00, 799.00, 0, '2025-04-09 22:23:23', '2025-04-09 22:23:23');

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
(1, 'GC-b774ba8', '15', 798.99, '2025-04-09 21:38:13'),
(2, 'GC-b774ba8', '16', 201.01, '2025-04-09 21:39:22'),
(3, 'GC-3099066', '17', 798.99, '2025-04-09 21:43:15'),
(4, 'GC-3099066', '18', 201.01, '2025-04-09 21:48:27'),
(5, 'GC-34108bc', '20', 799.00, '2025-04-09 21:53:04'),
(6, 'GC-48ae788', '2', 799.00, '2025-04-09 22:01:39'),
(7, 'GC-3d537c5', '8', 798.99, '2025-04-09 22:17:35'),
(8, 'GC-3d537c5', '9', 201.01, '2025-04-09 22:19:04');

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
(1, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 04:53:28', 'paypal', '09B50610AP292302M', NULL, 1598.00),
(2, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:06:58', 'paypal', '99X17831J30774534', NULL, 1598.00),
(3, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:22:40', 'paypal', '7CB01784AT007192C', NULL, 1598.00),
(4, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:26:45', 'paypal', '74T852996J496414F', NULL, 1598.00),
(5, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:33:28', 'paypal', '4AS134432N769461L', NULL, 1598.00),
(6, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:39:03', 'paypal', '28K84517YM3204204', NULL, 1598.00),
(7, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:43:01', 'paypal', '6K59265795390923N', NULL, 1598.00),
(8, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:45:27', 'paypal', '7AL98366YF4893946', NULL, 1598.00),
(9, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 05:49:59', 'paypal', '75B42833FB0423503', NULL, 1598.00),
(10, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:11:58', 'paypal', '30819512D5892322V', NULL, 1598.00),
(11, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8119084453', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:16:54', 'paypal', '16P817820F4665849', NULL, 1598.00),
(12, 'Hugo Vazquez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:22:56', 'paypal', '3G208711S0668705R', NULL, 1598.00),
(14, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:33:22', 'paypal', '617168928H184902W', NULL, 1598.00),
(15, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:34:13', 'paypal', '61G12679Y48668633', NULL, 1598.00),
(16, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:44:37', 'paypal', '008610914B876483X', NULL, 1598.00),
(17, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 06:56:05', 'paypal', '2AL73270FF455272P', NULL, 1598.00),
(18, 'Francisco Gonzalez', 'jersixmx@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 07:01:53', 'paypal', '23V51473FV387091J', NULL, 1598.00),
(19, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-10 07:19:34', 'paypal', '1LD58090KX8711428', NULL, 1598.00),
(20, 'ddasdadasd', 'fracasdasd@dsad.dsad', '3233231313', 'dsadadad', 'dadas', 'Santiago', 'Nuevo Leon', '67032', 'pending', 'paid', '2025-04-10 07:21:46', 'paypal', '88U645388E061280H', NULL, 1598.00),
(21, 'Usuario de Prueba', 'test@example.com', '5512345678', 'Calle Prueba 123', 'Col. Test', 'Ciudad Test', 'Estado de Prueba', '12345', 'pending', 'paid', '2025-04-10 07:37:41', 'test', 'TEST-1744270661855', NULL, 3196.00),
(22, 'Pancho', 'sdasdasda@dsad.dada', '2313123131', 'Felicitos Guajardo , 1008', 'Yerbaniz', 'Santiago', 'Nuevo Leon', '67032', 'pending', 'paid', '2025-04-10 07:39:19', 'paypal', '1LU97011UU262952B', NULL, 1598.00),
(23, 'Pancho', 'ddsdasd@dasdsa.dsad', '3432423432', 'Felicitos Guajardo , 1008', 'ddasdasds', 'Santiago', 'Nuevo Leon', '67032', 'pending', 'paid', '2025-04-10 07:41:36', 'paypal', '9XX92274N42131628', NULL, 1598.00),
(24, 'Pancho', 'sdasdasd@dsadas.dasdas', '3423131313', 'Felicitos Guajardo , 1008', 'sdasdasdasd', 'Santiago', 'Nuevo Leon', '67032', 'pending', 'paid', '2025-04-10 07:45:25', 'paypal', '83802094A59651449', NULL, 1598.00),
(25, 'Pancho', 'sadsadsa@dsdadad.dsadsa', '3231231312', 'Felicitos Guajardo , 1008', 'sadasdsd', 'Santiago', 'Nuevo Leon', '67032', 'pending', 'paid', '2025-04-10 07:58:15', 'paypal', '7B087204YE6456722', NULL, 1598.00),
(26, 'Pancho', 'sdsasdasds@dsadas.dsad', '3233231231', 'Felicitos Guajardo , 1008', 'dadsadasdad', 'Santiago', 'Nuevo Leon', '67032', 'pending', 'paid', '2025-04-10 08:05:17', 'paypal', '1P532674R0366553P', NULL, 1598.00);

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
(1, 1, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(2, 1, 7, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(3, 2, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(4, 2, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(5, 3, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(6, 3, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(7, 4, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(8, 4, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(9, 5, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(10, 5, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(11, 6, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(12, 6, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(13, 7, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(14, 7, 7, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(15, 8, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(16, 8, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(17, 9, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(18, 9, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(19, 10, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(20, 10, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(21, 11, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(22, 11, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(23, 12, 9, 1, 799.00, 799.00, 'XL', NULL, NULL, NULL, 0, 'pendiente'),
(24, 12, 9, 1, 799.00, 799.00, 'XL', NULL, NULL, NULL, 0, 'pendiente'),
(26, 14, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(27, 15, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(28, 16, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(29, 16, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(30, 17, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(31, 17, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(32, 18, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(33, 19, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(34, 19, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(35, 20, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(36, 20, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(37, 21, 3, 1, 799.00, 799.00, 'S', NULL, NULL, NULL, 0, 'pendiente'),
(38, 21, 5, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(39, 21, 5, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(40, 21, 7, 1, 799.00, 799.00, 'XL', NULL, NULL, NULL, 0, 'pendiente'),
(41, 22, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(42, 22, 9, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(43, 23, 8, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(44, 23, 8, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(45, 24, 9, 1, 799.00, 799.00, 'XL', NULL, NULL, NULL, 0, 'pendiente'),
(46, 24, 9, 1, 799.00, 799.00, 'XL', NULL, NULL, NULL, 0, 'pendiente'),
(47, 25, 3, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(48, 25, 3, 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(49, 26, 9, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(50, 26, 7, 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente');

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
(2, 'Seleccion Mexicana 24/25', 'Jersey Seleccion Mexicana 24/25', 799.00, 0, 'uploads/products/67ed9ec010a81.jpg', 'Selecciones', '2025-03-31 18:46:01', '2025-04-09 16:53:52', NULL, 0),
(3, 'Barcelona Local 24/25', 'Jersey oficial Local de Barcelona para la temporada 2023-2024.', 799.00, 4, 'uploads/products/67f5b6645a365.jpg', 'Equipos', '2025-03-31 18:53:13', '2025-04-08 23:51:04', NULL, 1),
(4, 'Real Madrid Local 24/25', 'Jersey oficial Local de Real Madrid para la temporada 2024-2025. 100% poliï¿½ster, ajuste regular.', 799.00, 4, 'uploads/products/67ec76fb94cc3.jpg', 'Equipos', '2025-03-31 19:01:36', '2025-04-09 16:53:46', NULL, 0),
(7, 'Manchester City Local 24/25', 'Jersey de Manchester City Local temporada 24/25	', 799.00, 3, 'img/products/1743533045_67a795c9030e8.png', 'Equipos', '2025-04-01 18:44:05', '2025-04-10 05:44:14', NULL, 1),
(8, 'Rayados Local 24/25', 'Jersey de Rayados Local temporada 24/25', 799.00, 6, 'img/products/1743537813_67a54ccd76ae6.jpg', 'Equipos', '2025-04-01 20:03:33', '2025-04-02 20:23:50', NULL, 1),
(9, 'Bayern Múnich Local 24/25', 'Jersey del Bayern MÃºnich Local 24/25 ', 799.00, 4, 'img/products/1743546271_BayerMunchenLocal.jpg', 'Equipos', '2025-04-01 22:24:31', '2025-04-09 07:04:38', NULL, 1),
(10, 'AC Milan Local 24/25', 'Jersey AC Milan Local 24/25 ', 799.00, 2, 'uploads/products/67f61b5a9fb60.png', 'Equipos', '2025-04-01 22:26:31', '2025-04-09 16:53:33', NULL, 0),
(11, 'Tigres Local 24/25', 'Jersey Tigres local 24/25', 799.00, 5, 'img/products/1743547962_TigresLocal.jpg', 'Equipos', '2025-04-01 22:52:42', '2025-04-02 20:24:23', NULL, 1),
(13, 'America Local 24/25', 'Jersey America Local 24/25', 799.00, 7, 'img/products/1743549317_AmericaLocal.jpg', 'Equipos', '2025-04-01 23:15:17', '2025-04-02 20:24:37', NULL, 1),
(14, 'Cruz Azul Local 24/25', 'Jersey Cruz Azul Local 24/25', 799.00, 5, 'img/products/1743550458_CruzAzulLocal.jpg', 'Equipos', '2025-04-01 23:34:18', '2025-04-02 20:25:02', NULL, 1),
(15, 'PSG Local 24/25', 'Jersey PSG Local 24/25', 799.00, 4, 'img/products/1743611276_PSGLocal.jpg', 'Equipos', '2025-04-02 16:27:56', '2025-04-02 20:25:34', NULL, 1),
(16, 'Chivas Local 24/25', 'Jersey Chivas Local 24/25', 799.00, 3, 'uploads/products/67f00bc716e3f.jpg', 'Equipos', '2025-04-02 16:30:29', '2025-04-09 16:53:41', NULL, 0),
(17, 'Barcelona Visitante 24/25', 'Jersey Barcelona Visitante 24/25', 799.00, 0, 'img/products/1743624740_JerseyBarcelonaV.jpg', 'Equipos', '2025-04-02 20:12:20', '2025-04-08 23:53:59', NULL, 1),
(18, 'Atletico de Madrid Local 24/25', 'Jersey Atletico de Madrid 24/25', 799.00, 0, 'img/products/1743625389_AtleticoM.jpeg', 'Equipos', '2025-04-02 20:23:09', '2025-04-03 02:52:27', NULL, 1),
(19, 'Real Madrid Visitante 24/25', 'Jersey Real Madrid Visitante 24/25', 799.00, 0, 'img/products/1743626203_RealMV-Photoroom.png', 'Equipos', '2025-04-02 20:36:46', '2025-04-08 23:53:59', NULL, 1),
(23, 'Atletico de Madrid Visitante 24/25', 'Jersey Atletico de Madrid Visitante 24/25', 799.00, 0, 'img/products/1743626724_AtleticoMV.jpg', 'Equipos', '2025-04-02 20:45:24', '2025-04-08 23:53:59', NULL, 1),
(24, 'Athletic Bilbao Local 24/25', 'Jersey Athletic Bilbao Local 24/25\r\n', 799.00, 0, 'img/products/1743626959_athetic1.jpg', 'Equipos', '2025-04-02 20:49:19', '2025-04-09 07:04:38', NULL, 1),
(25, 'Athletic Bilbao Visitante 24/25', 'Jersey Athletic Bilbao Visitante 24/25\r\n', 799.00, 0, 'img/products/1743627060_1.jpg', 'Equipos', '2025-04-02 20:51:00', '2025-04-09 07:04:38', NULL, 1),
(26, 'PSG Tercera 24/25', 'Jersey PSG Tercera 24/25', 799.00, 0, 'img/products/1743627364_paris1.png', 'Equipos', '2025-04-02 20:56:04', '2025-04-08 23:53:59', NULL, 1),
(27, 'Tottenham Local 24/25', 'Jersey tottenham local 24/25', 799.00, 0, 'img/products/1743627491_1.jpg', 'Equipos', '2025-04-02 20:58:11', '2025-04-08 23:53:59', NULL, 1),
(28, 'Chivas Alternativa 24/25', 'Jersey Chivas Alternativa 24/25', 799.00, 0, 'img/products/1743627735_1.jpg', 'Equipos', '2025-04-02 21:02:16', '2025-04-08 23:53:59', NULL, 1),
(29, 'Borussia Dortmund Visitante 24/25', 'Jersey borussia dortmund visita 24/25', 799.00, 0, 'img/products/1743628037_1.jpg', 'Equipos', '2025-04-02 21:07:17', '2025-04-08 23:53:59', NULL, 1),
(30, 'Borussia Dortmund Tercera 24/25', 'Jersey Borussia Dortmund Tercera 24/25\r\n', 799.00, 0, 'img/products/1743628208_1.jpg', 'Equipos', '2025-04-02 21:10:08', '2025-04-08 23:53:59', NULL, 1),
(31, 'AC Milan Visitante 24/25', 'Jersey AC Milan Visitante 24/25', 799.00, 0, 'uploads/products/67eda89a4ac80.jpeg', 'Equipos', '2025-04-02 21:12:57', '2025-04-09 16:53:34', NULL, 0),
(32, 'Bayern Múnich Wiesn 24/25', 'Jersey Bayern Múnich Wiesn 24/25', 799.00, 0, 'img/products/1743628691_1.jpg', 'Equipos', '2025-04-02 21:18:11', '2025-04-09 07:04:38', NULL, 1),
(33, 'Argentina Aniversario', 'Jersey Argentina Aniversario', 799.00, 0, 'uploads/products/67edaaf7a88cb.png', 'Selecciones', '2025-04-02 21:23:02', '2025-04-09 16:53:36', NULL, 0),
(34, 'Barcelona Tercera 24/25', 'Jersey Barcelona Tercera 24/25', 799.00, 0, 'img/products/1743629218_1.jpg', 'Equipos', '2025-04-02 21:26:58', '2025-04-03 02:51:41', NULL, 1),
(35, 'Bayern Múnich Tercera 24/25', 'Jersey Bayer Munich Tercera 24/25', 799.00, 0, 'img/products/1743629792_1.jpg', 'Equipos', '2025-04-02 21:36:32', '2025-04-08 23:56:28', NULL, 1),
(36, 'Inter de Milan Local 24/25', 'Jersey Inter de Milan Local 24/25', 799.00, 0, 'img/products/1743631095_1.jpg', 'Equipos', '2025-04-02 21:58:16', '2025-04-08 23:53:59', NULL, 1),
(37, 'Juventus Local 24/25', 'Jersey Juventus Local 24/25', 799.00, 0, 'img/products/1743631458_1.png', 'Equipos', '2025-04-02 22:04:18', '2025-04-08 23:53:59', NULL, 1),
(38, 'Chelsea Visitante 24/25', 'Jersey Chelsea Visitante 24/25', 799.00, 0, 'img/products/1743631710_1.jpg', 'Equipos', '2025-04-02 22:08:30', '2025-04-08 23:59:31', NULL, 1),
(39, 'Chelsea Local 24/25', 'Jersey Chelsea Local 24/25', 799.00, 0, 'img/products/1743631990_1.jpg', 'Equipos', '2025-04-02 22:13:11', '2025-04-08 23:53:59', NULL, 1),
(40, 'Seleccion BrasileÃ±a 24/25', 'Jersey Seleccion BrasileÃ±a 24/25', 799.00, 0, 'img/products/1743632160_1.jpg', 'Selecciones', '2025-04-02 22:16:00', '2025-04-08 23:53:59', NULL, 1),
(41, 'Necaxa Local 24/25', 'Jersey Necaxa Local 24/25', 799.00, 0, 'img/products/1743632290_1.jpg', 'Equipos', '2025-04-02 22:18:10', '2025-04-08 23:53:59', NULL, 1),
(42, 'Ajax Visitante 24/25', 'Jersey Ajax Visitante 24/25', 799.00, 0, 'img/products/1743632492_1.png', 'Equipos', '2025-04-02 22:21:33', '2025-04-08 23:53:59', NULL, 1),
(43, 'Manchester United Local 24/25', 'Jersey Manchester United Local 24/25', 799.00, 0, 'img/products/1743632702_1.png', 'Equipos', '2025-04-02 22:25:03', '2025-04-08 23:53:59', NULL, 1),
(44, 'Arsenal Local 24/25', 'Jersey Arsenal Local 24/25', 799.00, 0, 'img/products/1743632791_1.jpg', 'Equipos', '2025-04-02 22:26:31', '2025-04-08 23:53:59', NULL, 1),
(45, 'Seleccion Española 24/25', 'Jersey Seleccion Española 24/25', 799.00, 0, 'img/products/1743647655_1.jpg', 'Selecciones', '2025-04-03 02:34:15', '2025-04-08 23:56:54', NULL, 1),
(46, 'Seleccion Alemania 24/25', 'Jersey Seleccion Alemania 24/25', 799.00, 0, 'img/products/1743647823_1-Photoroom.png', 'Selecciones', '2025-04-03 02:37:05', '2025-04-08 23:53:59', NULL, 1),
(47, 'Seleccion Francia 24/25', 'Jersey Seleccion Francia 24/25', 799.00, 0, 'img/products/1743647961_1.jpg', 'Selecciones', '2025-04-03 02:39:21', '2025-04-08 23:53:59', NULL, 1),
(48, 'Seleccion Francia Visitante 24/25', 'Jersey Seleccion Francia Visitante 24/25', 799.00, 0, 'img/products/1743648068_1.jpg', 'Selecciones', '2025-04-03 02:41:08', '2025-04-08 23:53:59', NULL, 1),
(49, 'Seleccion Portugal 24/25', 'Jersey Seleccion Portugal 24/25', 799.00, 0, 'img/products/1743648433_1.jpg', 'Selecciones', '2025-04-03 02:47:13', '2025-04-08 23:53:59', NULL, 1),
(51, 'Seleccion Argentina 24/25', 'Jersey Seleccion Argentina 24/25', 799.00, 0, 'uploads/products/67eec6cc97e34.jpg', 'Selecciones', '2025-04-03 17:32:21', '2025-04-09 16:53:47', NULL, 0),
(52, 'Seleccion Italiana 24/25', 'Jersey Seleccion Italiana 24/25', 799.00, 0, 'img/products/1743701819_1.jpg', 'Selecciones', '2025-04-03 17:36:59', '2025-04-08 23:53:59', NULL, 1),
(53, 'Seleccion Italiana Visitante 24/25', 'Jersey Seleccion Italiana Visitante 24/25', 799.00, 0, 'img/products/1743701996_1.jpg', 'Selecciones', '2025-04-03 17:39:56', '2025-04-08 23:53:59', NULL, 1),
(54, 'Seleccion Inglaterra 24/25', 'Jersey Seleccion Inglaterra 24/25', 799.00, 0, 'img/products/1743702111_1.jpg', 'Selecciones', '2025-04-03 17:41:51', '2025-04-08 23:53:59', NULL, 1),
(55, 'Liverpool Local 24/25', 'Jersey Liverpool Local 24/25', 799.00, 0, 'img/products/1743702883_1.jpg', 'Equipos', '2025-04-03 17:54:43', '2025-04-08 23:53:59', NULL, 1),
(56, 'Liverpool Tercer 24/25', 'Jersey Liverpool Tercer 24/25', 799.00, 0, 'img/products/1743702952_4.jpg', 'Equipos', '2025-04-03 17:55:53', '2025-04-08 23:53:59', NULL, 1),
(57, 'Liverpool Local 06/07', 'Jersey Liverpool Local 2006', 899.00, 0, 'img/products/1743703198_1-Photoroom.png', 'Retro', '2025-04-03 18:00:00', '2025-04-08 23:53:59', NULL, 1),
(58, 'Chelsea Local 06/07', 'Jersey Chelsea Local 06/07', 899.00, 0, 'img/products/1743703467_1-Photoroom.png', 'Retro', '2025-04-03 18:04:29', '2025-04-08 23:53:59', NULL, 1),
(59, 'Seleccion Española 08', 'Jersey Seleccion EspaÃ±ola 08', 899.00, 0, 'img/products/1743703816_1-Photoroom.png', 'Retro', '2025-04-03 18:10:18', '2025-04-08 23:56:46', NULL, 1),
(61, 'Bayern Múnich Local 01/02', 'Jersey Bayern MÃºnich Local 01/02', 899.00, 0, 'img/products/1743704490_1-Photoroom.png', 'Retro', '2025-04-03 18:21:33', '2025-04-08 23:55:35', NULL, 1),
(62, 'Manchester United Local 13/14', 'Jersey Manchester United Visitante 13/14', 899.00, 0, 'img/products/1743704768_1-Photoroom.png', 'Retro', '2025-04-03 18:26:11', '2025-04-08 23:53:59', NULL, 1),
(63, 'Milan Local 10/11', 'Jersey Milan Locan 2010', 899.00, 0, 'uploads/products/67f154bb63c06.png', 'Retro', '2025-04-05 15:30:39', '2025-04-09 16:53:43', NULL, 0),
(64, 'Tigres Visitante 24/25', 'Jersey Tigres Visitante 24/25', 799.00, 0, 'img/products/1743869290_1.jpg', 'Equipos', '2025-04-05 16:08:10', '2025-04-05 16:08:10', NULL, 1),
(65, 'Mystery Box', 'Mystery Box', 799.00, 0, 'img/products/1744146869_1.jpg', 'nueva', '2025-04-08 21:14:29', '2025-04-09 16:53:44', NULL, 0),
(66, 'Tarjeta de Regalo JerSix', '	\r\nTarjeta de Regalo JerSix', 0.00, 0, 'uploads/products/67f6e48d8a22c.jpg', 'Gift Card', '2025-04-08 21:15:24', '2025-04-09 21:20:13', NULL, 0);

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
(1, 1598.00, 1),
(2, 1598.00, 1),
(3, 1598.00, 1),
(4, 1598.00, 1),
(5, 1598.00, 1),
(6, 1598.00, 1),
(7, 1598.00, 1),
(8, 1598.00, 1),
(9, 1598.00, 1),
(10, 1598.00, 1),
(11, 1598.00, 1),
(12, 1598.00, 1),
(13, 1598.00, 1),
(14, 1598.00, 1),
(15, 1598.00, 1),
(16, 1598.00, 1),
(17, 1598.00, 1),
(18, 1598.00, 1),
(19, 1598.00, 1),
(20, 1598.00, 1),
(21, 1598.00, 1),
(22, 1598.00, 1),
(23, 1598.00, 1),
(24, 1598.00, 1);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `giftcard_transactions`
--
ALTER TABLE `giftcard_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
