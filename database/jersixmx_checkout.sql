-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 24, 2025 at 11:42 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jersixmx_checkout`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `admin_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `admin_name`, `created_at`) VALUES
(1, '1234', '$2y$10$xWNPB4rDa4bwByrERbuS3eaHPISV0NOKZ2KmHykI/QvDAbxfALoCO', NULL, '2025-02-26 17:33:23');

-- --------------------------------------------------------

--
-- Table structure for table `banner_config`
--

CREATE TABLE `banner_config` (
  `id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `color_fondo` varchar(20) DEFAULT '#ff0000',
  `color_texto` varchar(20) DEFAULT '#ffffff',
  `velocidad_animacion` int(11) DEFAULT 500,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banner_config`
--

INSERT INTO `banner_config` (`id`, `mensaje`, `activo`, `color_fondo`, `color_texto`, `velocidad_animacion`, `created_at`, `updated_at`) VALUES
(8, '20% DE DESCUENTO CON CODIGO \"JERSIX20\"', 1, '#ff0000', '#ffffff', 500, '2025-04-22 21:24:24', '2025-05-02 16:37:22');

-- --------------------------------------------------------

--
-- Table structure for table `banner_images`
--

CREATE TABLE `banner_images` (
  `id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `position` enum('imagen1','imagen2','imagen3') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banner_images`
--

INSERT INTO `banner_images` (`id`, `image_url`, `orden`, `activo`, `created_at`, `position`) VALUES
(4, 'img/banner/banner_imagen1_1747853037.jpeg', 0, 1, '2025-05-21 18:43:57', 'imagen1'),
(5, 'img/banner/banner_imagen1_1747853092.jpeg', 0, 1, '2025-05-21 18:44:52', 'imagen1'),
(6, 'img/banner/banner_imagen1_1747853161.png', 0, 1, '2025-05-21 18:46:01', 'imagen1');

-- --------------------------------------------------------

--
-- Table structure for table `codigos_promocionales`
--

CREATE TABLE `codigos_promocionales` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `descuento` decimal(10,2) NOT NULL,
  `tipo_descuento` enum('porcentaje','fijo','paquete','auto') NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `usos_maximos` int(11) NOT NULL,
  `usos_actuales` int(11) DEFAULT 0,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `codigos_promocionales`
--

INSERT INTO `codigos_promocionales` (`id`, `codigo`, `descuento`, `tipo_descuento`, `fecha_inicio`, `fecha_fin`, `usos_maximos`, `usos_actuales`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(2, 'HOLA', 20.00, 'porcentaje', '2010-11-11 11:11:00', '2222-11-11 11:11:00', 100000000, 0, 'activo', '2025-04-28 19:05:09', '2025-04-28 19:05:09'),
(3, 'AUTO2XJERSEY', 598.00, 'auto', '2025-05-24 21:39:28', '2099-12-31 23:59:59', 0, 0, 'activo', '2025-05-24 21:39:28', '2025-05-24 21:39:28');

-- --------------------------------------------------------

--
-- Table structure for table `featured_products`
--

CREATE TABLE `featured_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `position` enum('producto1','producto2','producto3') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `featured_products`
--

INSERT INTO `featured_products` (`id`, `product_id`, `orden`, `activo`, `created_at`, `position`) VALUES
(459, 8, 0, 1, '2025-04-24 16:15:39', 'producto3'),
(460, 80, 0, 1, '2025-04-24 16:15:45', 'producto2'),
(461, 33, 0, 1, '2025-04-24 16:15:51', 'producto1');

-- --------------------------------------------------------

--
-- Table structure for table `giftcard_redemptions`
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
-- Dumping data for table `giftcard_redemptions`
--

INSERT INTO `giftcard_redemptions` (`id`, `code`, `original_amount`, `balance`, `redeemed`, `created_at`, `updated_at`) VALUES
(1, 'GC-f40976a', 1000.00, 0.00, 1, '2025-04-21 18:50:25', '2025-04-21 18:51:53');

-- --------------------------------------------------------

--
-- Table structure for table `giftcard_transactions`
--

CREATE TABLE `giftcard_transactions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `giftcard_transactions`
--

INSERT INTO `giftcard_transactions` (`id`, `code`, `order_id`, `amount`, `transaction_date`) VALUES
(1, 'GC-f40976a', '2', 799.00, '2025-04-21 18:50:25'),
(2, 'GC-f40976a', '3', 201.00, '2025-04-21 18:51:53');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `newsletter`
--

INSERT INTO `newsletter` (`id`, `email`, `subscribed_at`) VALUES
(1, 'HOLA@GMAIL.COM', '2025-04-21 21:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
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
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_name`, `customer_email`, `phone`, `street`, `colony`, `city`, `state`, `zip_code`, `status`, `payment_status`, `created_at`, `payment_method`, `payment_id`, `payment_notes`, `total_amount`) VALUES
(1, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'completed', 'paid', '2025-04-22 00:49:42', 'paypal', '8E999582DY763045G', NULL, 1000.00),
(2, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'completed', 'paid', '2025-04-22 00:50:25', 'giftcard', 'GIFTCARD-FULL-PAYMENT-1745261425925', 'Gift Card aplicada: GC-f40976a - Monto: $799.00', 0.00),
(3, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'completed', 'paid', '2025-04-22 00:51:53', 'paypal', '3UG38915890571215', 'Gift Card aplicada: GC-f40976a - Monto: $201.00', 598.00),
(4, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 03:21:34', 'paypal', '0K30524178412794X', NULL, 799.00),
(5, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 04:15:36', 'paypal', '11R427440M744191R', NULL, 3000.00),
(6, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 07:48:45', 'paypal', '2JD982894A576750A', NULL, 799.00),
(7, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 07:52:44', 'paypal', '41G76250T6176661C', NULL, 799.00),
(8, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 07:53:22', 'paypal', '1S796496WY402103S', NULL, 799.00),
(9, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 07:54:00', 'paypal', '2B082918LF949874H', NULL, 799.00),
(10, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 07:59:33', 'paypal', '6GK281538A203761D [PROMO:HOLA:$639.20] [PROMO:HOLA:$639.20]', 'Código promocional aplicado: HOLA - Descuento: $639.20 - Total original: $3,196.00 - Total con descuento: $2,556.80', 2556.80),
(13, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 11:33:02', 'paypal', '1NM36095LV921581H', NULL, 799.00),
(14, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 11:37:13', 'paypal', '3VU172971W532760Y', NULL, 799.00),
(15, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 11:37:56', 'paypal', '2DS66047AS8876716 [PROMO:HOLA:$159.80] [PROMO:HOLA:$159.80]', 'Código promocional aplicado: HOLA - Descuento: $159.80 - Total original: $799.00 - Total con descuento: $639.20', 639.20),
(16, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-22 11:39:30', 'paypal', '2EN989168C2550731', NULL, 1000.00),
(17, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-23 10:42:32', 'paypal', '0UR502600D838571N [PROMO:HOLA:$159.80] [PROMO:HOLA:$159.80]', 'Código promocional aplicado: HOLA - Descuento: $159.80 - Total original: $799.00 - Total con descuento: $639.20', 639.20),
(18, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-23 11:00:02', 'paypal', '9WW67609FE742311T [PROMO:HOLA:$189.80] [PROMO:HOLA:$159.80]', 'Código promocional aplicado: HOLA - Descuento: $159.80 - Total original: $799.00 - Total con descuento: $639.20', 639.20),
(19, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-24 07:12:09', 'paypal', '9SA40292GL634162G [PROMO:HOLA:$189.80] [PROMO:HOLA:$159.80]', 'Código promocional aplicado: HOLA - Descuento: $159.80 - Total original: $799.00 - Total con descuento: $639.20', 639.20),
(20, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-24 13:50:23', 'paypal', '38E36714B3830133N', NULL, 799.00),
(21, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-24 13:52:22', 'paypal', '63S35471816472039', NULL, 799.00),
(22, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-24 13:54:26', 'paypal', '3HB10045KM815781L', NULL, 799.00),
(23, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-24 13:54:58', 'paypal', '21P47757AH6341139', NULL, 799.00),
(24, 'Francisco Gonzalez', 'franciscogzz03@gmail.com', '8123584236', 'Felicitos Guajardo #1008', 'El yerbaniz', 'Monterrey', 'Nuevo Leon', '67302', 'pending', 'paid', '2025-04-24 14:12:52', 'paypal', '5DK31782VT045000W', NULL, 799.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `cart_item_id` varchar(50) DEFAULT NULL,
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
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `cart_item_id`, `quantity`, `price`, `subtotal`, `size`, `personalization_name`, `personalization_number`, `personalization_patch`, `giftcard_sent`, `giftcard_status`) VALUES
(1, 1, 66, 'giftcard-1745260762169', 1, 1000.00, 1000.00, 'N/A', 'franciscogzz03@gmail.com', 'GC-f40976a', 'RCP:RnJhbmNpc2NvIEdvbnphbGV6IFNvc2E=|MSG:|SND:UGFuY2hv', 1, 'enviada'),
(2, 2, 31, '1745261406774-rz5t7', 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(3, 3, 31, '1745261493544-6xref', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(4, 4, 65, '1745270472932-g39zt', 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(5, 5, 66, 'giftcard-1745272984315', 3, 1000.00, 3000.00, 'N/A', 'franciscogzz03@gmail.com', 'GC-cd0be42', 'RCP:RnJhbmNpc2NvIEdvbnphbGV6IFNvc2E=|MSG:|SND:UGFuY2hv', 0, 'pendiente'),
(6, 6, 65, '1745286499512-4kzmq', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(7, 7, 65, '1745286751836-e9s1l', 1, 799.00, 799.00, 'M', NULL, NULL, 'TIPO:TGlnYSBFdXJvcGVh', 0, 'pendiente'),
(8, 8, 65, '1745286790335-vsb9k', 1, 799.00, 799.00, 'L', NULL, NULL, 'TIPO:TGlnYSBFdXJvcGVh', 0, 'pendiente'),
(9, 9, 65, '1745286828104-kelr8', 1, 799.00, 799.00, 'L', NULL, NULL, 'TIPO:Q2hhbXBpb25zIExlYWd1ZQ==', 0, 'pendiente'),
(10, 10, 65, '1745287139265-hicds', 1, 799.00, 799.00, 'S', NULL, NULL, 'TIPO:Q2hhbXBpb25zIExlYWd1ZQ==', 0, 'pendiente'),
(11, 10, 65, '1745287142746-2px59', 1, 799.00, 799.00, 'M', NULL, NULL, 'TIPO:TGlnYSBNWA==', 0, 'pendiente'),
(12, 10, 65, '1745287145063-u64cy', 1, 799.00, 799.00, 'L', NULL, NULL, 'TIPO:TGlnYSBFdXJvcGVh', 0, 'pendiente'),
(13, 10, 65, '1745287146965-t42x0', 1, 799.00, 799.00, 'XL', NULL, NULL, 'TIPO:UmV0cm8=', 0, 'pendiente'),
(16, 13, 65, '1745299954992-xye08', 1, 799.00, 799.00, 'M', NULL, NULL, 'TIPO:TGlnYSBNWA==|UNWANTED:Q3J1eiBBenVs', 0, 'pendiente'),
(17, 14, 10, '1745300218052-mu25i', 1, 799.00, 799.00, 'M', NULL, NULL, NULL, 0, 'pendiente'),
(18, 15, 65, '1745300257803-2t8od', 1, 799.00, 799.00, 'M', NULL, NULL, 'TIPO:TGlnYSBNWA==|UNWANTED:UmF5YWRvcw==', 0, 'pendiente'),
(19, 16, 66, 'giftcard-1745300352957', 1, 1000.00, 1000.00, 'N/A', 'franciscogzz03@gmail.com', 'GC-0636bbd', 'RCP:RnJhbmNpc2NvIEdvbnphbGV6IFNvc2E=|MSG:|SND:UGFuY2hv', 0, 'pendiente'),
(20, 17, 42, '1745383305941-ygp9j', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(21, 18, 10, '1745384344066-oaul1', 1, 799.00, 799.00, 'M', 'Pancho', '10', '1', 0, 'pendiente'),
(22, 19, 10, '1745457068398-hcxgy', 1, 799.00, 799.00, 'L', 'Pancho', '10', '1', 0, 'pendiente'),
(23, 20, 10, '1745480993574-se03h', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(24, 21, 10, '1745481127495-8uln5', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(25, 22, 42, '1745481252085-jtswu', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(26, 23, 10, '1745481284034-naw9f', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente'),
(27, 24, 33, '1745482344923-znnhx', 1, 799.00, 799.00, 'L', NULL, NULL, NULL, 0, 'pendiente');

-- --------------------------------------------------------

--
-- Table structure for table `products`
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
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `stock`, `image_url`, `category`, `created_at`, `updated_at`, `image_path`, `status`) VALUES
(2, 'Seleccion Mexicana 25', 'Jersey Seleccion Mexicana 24/25', 799.00, 0, 'uploads/products/67fd8c40d0f38.png', 'Selecciones', '2025-03-31 18:46:01', '2025-04-20 09:37:10', NULL, 0),
(3, 'Barcelona Local 24/25', 'Jersey oficial Local de Barcelona para la temporada 2023-2024.', 799.00, 4, 'uploads/products/6806b4549d2eb.jpg', 'Equipos', '2025-03-31 18:53:13', '2025-04-21 21:10:46', NULL, 1),
(4, 'Real Madrid Local 24/25', 'Jersey oficial Local de Real Madrid para la temporada 2024-2025. 100% poliï¿½ster, ajuste regular.', 799.00, 4, 'uploads/products/67fd859e5fa89.jpg', 'Equipos', '2025-03-31 19:01:36', '2025-04-20 09:37:10', NULL, 0),
(7, 'Manchester City Local 24/25', 'Jersey de Rayados Local temporada 24/25	', 799.00, 3, 'uploads/products/67fd7626aef51.png', 'Equipos', '2025-04-01 18:44:05', '2025-04-20 09:37:10', NULL, 0),
(8, 'Rayados Local 24/25', 'Jersey de Rayados Local temporada 24/25', 799.00, 6, 'uploads/products/680741bd1a5aa.jpg', 'Equipos', '2025-04-01 20:03:33', '2025-04-22 07:14:05', NULL, 1),
(9, 'Bayern Múnich Local 24/25', 'Jersey del Bayern MÃºnich Local 24/25 ', 799.00, 4, 'uploads/products/67fd664c82863.jpg', 'Equipos', '2025-04-01 22:24:31', '2025-04-20 09:37:10', NULL, 0),
(10, 'AC Milan Local 24/25', 'Jersey AC Milan Local 24/25 ', 799.00, 1, 'uploads/products/6806b2e11434c.jpg', 'Equipos', '2025-04-01 22:26:31', '2025-05-12 02:29:21', NULL, 1),
(11, 'Tigres Local 24/25', 'Jersey Tigres local 24/25', 799.00, 5, 'uploads/products/67fd8db78e4f1.jpg', 'Equipos', '2025-04-01 22:52:42', '2025-04-20 09:37:10', NULL, 0),
(13, 'America Local 24/25', 'Jersey America Local 24/25', 799.00, 7, 'uploads/products/6806b33590bdc.jpg', 'Equipos', '2025-04-01 23:15:17', '2025-04-21 21:05:57', NULL, 1),
(14, 'Cruz Azul Local 24/25', 'Jersey Cruz Azul Local 24/25', 799.00, 5, 'uploads/products/67fd743a588e2.jpg', 'Equipos', '2025-04-01 23:34:18', '2025-04-20 09:37:10', NULL, 0),
(15, 'PSG Local 24/25', 'Jersey PSG Local 24/25', 799.00, 4, 'uploads/products/67fd83b87d2c3.jpg', 'Equipos', '2025-04-02 16:27:56', '2025-04-20 09:37:10', NULL, 0),
(16, 'Chivas Local 24/25', 'Jersey Chivas Local 24/25', 799.00, 3, 'uploads/products/67fd739127099.jpg', 'Equipos', '2025-04-02 16:30:29', '2025-04-20 09:37:10', NULL, 0),
(17, 'Barcelona Visitante 24/25', 'Jersey Barcelona Visitante 24/25', 799.00, 0, 'uploads/products/67fd5c71149d1.jpg', 'Equipos', '2025-04-02 20:12:20', '2025-04-20 09:37:10', NULL, 0),
(18, 'Atletico de Madrid Local 24/25', 'Jersey Atletico de Madrid 24/25', 799.00, 0, 'uploads/products/6806b41a42edc.jpg', 'Equipos', '2025-04-02 20:23:09', '2025-04-21 21:09:46', NULL, 1),
(19, 'Real Madrid Visitante 24/25', 'Jersey Real Madrid Visitante 24/25', 799.00, 0, 'uploads/products/67fd8625430e3.jpg', 'Equipos', '2025-04-02 20:36:46', '2025-04-20 09:37:10', NULL, 0),
(23, 'Atletico de Madrid Visitante 24/25', 'Jersey Atletico de Madrid Visitante 24/25', 799.00, 0, 'uploads/products/67fd5a9b2587a.jpg', 'Equipos', '2025-04-02 20:45:24', '2025-04-20 09:37:10', NULL, 0),
(24, 'Athletic Bilbao Local 24/25', 'Jersey Athletic Bilbao Local 24/25\r\n', 799.00, 0, 'uploads/products/6806b38d1467b.jpg', 'Equipos', '2025-04-02 20:49:19', '2025-04-21 21:07:25', NULL, 1),
(25, 'Athletic Bilbao Visitante 24/25', 'Jersey Athletic Bilbao Visitante 24/25\r\n', 799.00, 0, 'uploads/products/6806b3d5f13dc.png', 'Equipos', '2025-04-02 20:51:00', '2025-04-21 21:08:40', NULL, 1),
(26, 'PSG Cuarta 24/25', 'Jersey PSG Tercera 24/25', 799.00, 0, 'uploads/products/67fd839156cf5.png', 'Equipos', '2025-04-02 20:56:04', '2025-04-20 09:37:10', NULL, 0),
(27, 'Tottenham Local 24/25', 'Jersey tottenham local 24/25', 799.00, 0, 'uploads/products/67fd8df492d38.jpg', 'Equipos', '2025-04-02 20:58:11', '2025-04-20 09:37:10', NULL, 0),
(28, 'Chivas Alternativa 24/25', 'Jersey Chivas Alternativa 24/25', 799.00, 0, 'uploads/products/67fd68a0929d6.png', 'Equipos', '2025-04-02 21:02:16', '2025-04-20 09:37:10', NULL, 0),
(29, 'Borussia Dortmund Visitante 24/25', 'Jersey borussia dortmund visita 24/25', 799.00, 0, 'uploads/products/67fd670f81fe8.png', 'Equipos', '2025-04-02 21:07:17', '2025-04-20 09:37:10', NULL, 0),
(30, 'Borussia Dortmund Tercera 24/25', 'Jersey Borussia Dortmund Tercera 24/25\r\n', 799.00, 0, 'uploads/products/67fd66f94739d.png', 'Equipos', '2025-04-02 21:10:08', '2025-04-20 09:37:10', NULL, 0),
(31, 'AC Milan Visitante 24/25', 'Jersey AC Milan Visitante 24/25', 799.00, 0, 'uploads/products/6804c136eba03.png', 'Equipos', '2025-04-02 21:12:57', '2025-04-20 09:41:15', NULL, 1),
(32, 'Bayern Múnich Wiesn 24/25', 'Jersey Bayern Múnich Wiesn 24/25', 799.00, 0, 'uploads/products/67fd6694e53f7.png', 'Equipos', '2025-04-02 21:18:11', '2025-04-20 09:37:10', NULL, 0),
(33, 'Argentina Aniversario', 'Jersey Argentina Aniversario', 799.00, 0, 'uploads/products/6806b362463f8.jpg', 'Selecciones', '2025-04-02 21:23:02', '2025-04-21 21:06:42', NULL, 1),
(34, 'Barcelona Tercera 24/25', 'Jersey Barcelona Tercera 24/25', 799.00, 0, 'uploads/products/67fd5c4795f93.png', 'Equipos', '2025-04-02 21:26:58', '2025-04-20 09:37:10', NULL, 0),
(35, 'Bayern Múnich Tercera 24/25', 'Jersey Bayer Munich Tercera 24/25', 799.00, 0, 'uploads/products/67fd6679c8c7d.jpg', 'Equipos', '2025-04-02 21:36:32', '2025-04-20 09:37:10', NULL, 0),
(36, 'Inter de Milan Local 24/25', 'Jersey Inter de Milan Local 24/25', 799.00, 0, 'uploads/products/67fd745e7a184.png', 'Equipos', '2025-04-02 21:58:16', '2025-04-20 09:37:10', NULL, 0),
(37, 'Juventus Local 24/25', 'Jersey Juventus Local 24/25', 799.00, 0, 'uploads/products/67fd748c446f9.png', 'Equipos', '2025-04-02 22:04:18', '2025-04-20 09:37:10', NULL, 0),
(38, 'Chelsea Visitante 24/25', 'Jersey Chelsea Visitante 24/25', 799.00, 0, 'uploads/products/67fd6884e6eb9.png', 'Equipos', '2025-04-02 22:08:30', '2025-04-20 09:37:10', NULL, 0),
(39, 'Chelsea Local 24/25', 'Jersey Chelsea Local 24/25', 799.00, 0, 'uploads/products/67fd686f606d7.png', 'Equipos', '2025-04-02 22:13:11', '2025-04-20 09:37:10', NULL, 0),
(40, 'Seleccion Brasileña Local 24', 'Jersey Seleccion Brasileña 24/25', 799.00, 0, 'uploads/products/67fd86c79c7c3.jpg', 'Selecciones', '2025-04-02 22:16:00', '2025-04-20 09:37:10', NULL, 0),
(41, 'Necaxa Local 24/25', 'Jersey Necaxa Local 24/25', 799.00, 0, 'uploads/products/67fd831eb5dc7.png', 'Equipos', '2025-04-02 22:18:10', '2025-04-20 09:37:10', NULL, 0),
(42, 'Ajax Visitante 24/25', 'Jersey Ajax Visitante 24/25', 799.00, 0, 'uploads/products/6806b32171ac7.png', 'Equipos', '2025-04-02 22:21:33', '2025-04-21 21:09:16', NULL, 1),
(43, 'Manchester United Local 24/25', 'Jersey Manchester United Local 24/25', 799.00, 0, 'uploads/products/67fd769570e23.jpg', 'Equipos', '2025-04-02 22:25:03', '2025-04-20 09:37:10', NULL, 0),
(44, 'Arsenal Local 24/25', 'Jersey Arsenal Local 24/25', 799.00, 0, 'uploads/products/6806b3741404b.jpg', 'Equipos', '2025-04-02 22:26:31', '2025-04-21 21:07:00', NULL, 1),
(45, 'Seleccion Española Local 24', 'Jersey Seleccion Española 24/25', 799.00, 0, 'uploads/products/67fd8b5f31893.jpg', 'Selecciones', '2025-04-03 02:34:15', '2025-04-20 09:37:10', NULL, 0),
(46, 'Seleccion Alemana Local 24', 'Jersey Seleccion Alemania 24/25', 799.00, 0, 'uploads/products/67fd864099416.png', 'Selecciones', '2025-04-03 02:37:05', '2025-04-20 09:37:10', NULL, 0),
(47, 'Seleccion Francesa Local 24', 'Jersey Seleccion Francia 24/25', 799.00, 0, 'uploads/products/67fd8b87e34fe.jpg', 'Selecciones', '2025-04-03 02:39:21', '2025-04-20 09:37:10', NULL, 0),
(48, 'Seleccion Francesca Visitante 24', 'Jersey Seleccion Francia Visitante 24/25', 799.00, 0, 'uploads/products/67fd8ba1b57e5.jpg', 'Selecciones', '2025-04-03 02:41:08', '2025-04-20 09:37:10', NULL, 0),
(49, 'Seleccion Portugueda Local 24', 'Jersey Seleccion Portugal 24/25', 799.00, 0, 'img/products/1743648433_1.jpg', 'Selecciones', '2025-04-03 02:47:13', '2025-04-20 09:37:10', NULL, 0),
(51, 'Seleccion Argentina Local 24', 'Jersey Seleccion Argentina 24/25', 799.00, 0, 'uploads/products/67fd866b581fa.png', 'Selecciones', '2025-04-03 17:32:21', '2025-04-20 09:37:10', NULL, 0),
(52, 'Seleccion Italiana Local 24', 'Jersey Seleccion Italiana 24/25', 799.00, 0, 'uploads/products/67fd8bde2be15.jpg', 'Selecciones', '2025-04-03 17:36:59', '2025-04-20 09:37:10', NULL, 0),
(53, 'Seleccion Italiana Visitante 24', 'Jersey Seleccion Italiana Visitante 24/25', 799.00, 0, 'uploads/products/67fd8c072b3bc.jpg', 'Selecciones', '2025-04-03 17:39:56', '2025-04-20 09:37:10', NULL, 0),
(54, 'Seleccion Inglaterra Local 24', 'Jersey Seleccion Inglaterra 24/25', 799.00, 0, 'uploads/products/67fd8bc6c5560.jpg', 'Selecciones', '2025-04-03 17:41:51', '2025-04-20 09:37:10', NULL, 0),
(55, 'Liverpool Local 24/25', 'Jersey Liverpool Local 24/25', 799.00, 0, 'uploads/products/67fd75c42231a.jpg', 'Equipos', '2025-04-03 17:54:43', '2025-04-20 09:37:10', NULL, 0),
(56, 'Liverpool Tercer 24/25', 'Jersey Liverpool Tercer 24/25', 799.00, 0, 'uploads/products/67fd75f3ba86d.jpg', 'Equipos', '2025-04-03 17:55:53', '2025-04-20 09:37:10', NULL, 0),
(57, 'Liverpool Local 06/07', 'Jersey Liverpool Local 2006', 899.00, 0, 'uploads/products/67fff8f9cd692.png', 'Retro', '2025-04-03 18:00:00', '2025-04-20 09:37:10', NULL, 0),
(58, 'Chelsea Local 06/07', 'Jersey Chelsea Local 06/07', 899.00, 0, 'uploads/products/67fd68458b6cc.png', 'Retro', '2025-04-03 18:04:29', '2025-04-20 09:37:10', NULL, 0),
(59, 'Seleccion Española 08', 'Jersey Seleccion Española 08', 899.00, 0, 'uploads/products/67fd86fb7afd8.png', 'Retro', '2025-04-03 18:10:18', '2025-04-20 09:37:10', NULL, 0),
(61, 'Bayern Múnich Local 01/02', 'Jersey Bayern Múnich Local 01/02', 899.00, 0, 'uploads/products/67fd5c9067825.png', 'Retro', '2025-04-03 18:21:33', '2025-04-20 09:37:10', NULL, 0),
(62, 'Manchester United Local 13/14', 'Jersey Manchester United Visitante 13/14', 899.00, 0, 'uploads/products/67fd7761c7b8d.png', 'Retro', '2025-04-03 18:26:11', '2025-04-20 09:37:10', NULL, 0),
(64, 'Tigres Visitante 24/25', 'Jersey Tigres Visitante 24/25', 799.00, 0, 'uploads/products/67fd8dd0af499.jpg', 'Equipos', '2025-04-05 16:08:10', '2025-04-20 09:37:10', NULL, 0),
(65, 'Mystery Box', 'Mystery Box', 799.00, 0, 'uploads/products/6806b72e71956.png', 'Mystery Box', '2025-04-08 21:14:29', '2025-04-21 21:22:54', NULL, 0),
(66, 'Tarjeta de Regalo JersixMx', '	\r\nTarjeta de Regalo JerSix', 0.00, 0, 'uploads/products/68068d27db5ae.jpg', 'Gift Card', '2025-04-08 21:15:24', '2025-04-21 18:23:35', NULL, 0),
(67, 'Borussia Dortmund Local 24/25', 'Borussia Dortmund Local 24/25', 799.00, 0, 'img/products/1744660160_Borussia DortmundL1.jpg', 'Equipos', '2025-04-14 19:49:20', '2025-04-20 09:37:10', NULL, 0),
(68, 'AC Milan Local 03/04', 'Jersey Milan Local 03/04', 899.00, 3, 'uploads/products/6806b2cd09e3d.png', 'Retro', '2025-04-14 23:45:05', '2025-05-12 02:52:04', NULL, 1),
(69, 'Santos de Brasil Local 12/13', 'Jersey Santos de Brasil Local 12/13', 899.00, 0, 'img/products/1744674627_SantosB12:13_1.png', 'Retro', '2025-04-14 23:50:27', '2025-04-20 09:37:10', NULL, 0),
(70, 'PSG Local 01/02', 'Jersey PSG Local 01/02', 899.00, 0, 'img/products/1744674724_PSG01:02.png', 'Retro', '2025-04-14 23:52:04', '2025-04-20 09:37:10', NULL, 0),
(71, 'AC Milan Visitante 11/12', 'Jerse AC Milan Visitante 11/12', 899.00, 0, 'uploads/products/6806b3107b63b.png', 'Retro', '2025-04-14 23:55:08', '2025-04-21 21:06:10', NULL, 1),
(72, 'Manchester United 98/99', 'Jersey Manchester United 98/99', 899.00, 0, 'img/products/1744674990_ManchesterUnited98V_1.png', 'Retro', '2025-04-14 23:56:30', '2025-04-20 09:37:10', NULL, 0),
(73, 'Manchester United Visitante 98/99', 'Jersey Manchester United Visitante 98/99', 899.00, 0, 'img/products/1744675076_ManchesterUnited98_1.png', 'Retro', '2025-04-14 23:57:56', '2025-04-20 09:37:10', NULL, 0),
(74, 'Betis Local 00/01', 'Jersey Betis 00/01', 899.00, 0, 'img/products/1744675148_BetisL00_1.png', 'Retro', '2025-04-14 23:59:08', '2025-04-20 09:37:10', NULL, 0),
(75, 'Barcelona Local 89', 'Jersey Barcelona Local 89', 899.00, 0, 'img/products/1744675240_Barcelona89.png', 'Retro', '2025-04-15 00:00:40', '2025-04-20 09:37:10', NULL, 0),
(76, 'Barcelona Visitante 08/09', 'Jersey Barcelona Visitante 08/09', 899.00, 0, 'uploads/products/6804c06637555.jpg', 'Retro', '2025-04-15 00:01:52', '2025-04-20 09:41:18', NULL, 1),
(77, 'Atletico de Madrid Visitante 04/05', 'Jersey Atletico de Madrid Visitante 04/05', 899.00, 0, 'uploads/products/6806b42ded7d1.png', 'Retro', '2025-04-15 00:03:05', '2025-04-22 02:17:32', NULL, 1),
(78, 'Barcelona Local 99', 'Jersey Barcelona Local 1899-1999', 899.00, 0, 'img/products/1744828192_1.png', 'Retro', '2025-04-16 18:29:52', '2025-04-20 09:37:10', NULL, 0),
(80, 'Real Madrid Local 99/00', 'Jersey Real Madrid Local 99/00', 899.00, 0, 'img/products/1745289080_Real Madrid Local 99:00_1.png', 'Retro', '2025-04-22 02:31:20', '2025-04-22 02:31:20', NULL, 1),
(81, 'Inter de Miami Local 24/25', 'Jersey Inter Miami Local 24/25', 799.00, 0, 'img/products/1745346744_InterM1.jpg', 'Equipos', '2025-04-22 18:32:24', '2025-04-22 18:34:41', NULL, 1),
(82, 'Selección Francesa 98', 'Jersey Selección Francesa 98', 899.00, 0, 'img/products/1745519041_francia1998_1.png', 'Retro', '2025-04-24 18:24:01', '2025-04-24 18:24:01', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_url`, `sort_order`, `created_at`) VALUES
(103, 49, 'img/products/additional/1743648433_0_2.jpg', 0, '2025-04-03 02:47:13'),
(160, 23, 'uploads/products/67fd5a9b25ec0_0.jpg', 2, '2025-04-14 18:57:31'),
(161, 23, 'uploads/products/67fd5a9b25fa7_1.jpg', 3, '2025-04-14 18:57:31'),
(166, 34, 'uploads/products/67fd5af08b205_0.png', 2, '2025-04-14 18:58:56'),
(167, 34, 'uploads/products/67fd5af08b3cb_1.png', 3, '2025-04-14 18:58:56'),
(168, 17, 'uploads/products/67fd5c71150ed_0.jpg', 2, '2025-04-14 19:05:21'),
(169, 17, 'uploads/products/67fd5c71151b6_1.png', 3, '2025-04-14 19:05:21'),
(170, 61, 'uploads/products/67fd5c906d908_0.png', 2, '2025-04-14 19:05:53'),
(171, 61, 'uploads/products/67fd5c90c4d31_1.png', 3, '2025-04-14 19:05:53'),
(174, 9, 'uploads/products/67fd664c82fde_0.jpg', 2, '2025-04-14 19:47:24'),
(175, 9, 'uploads/products/67fd664c830c2_1.jpg', 3, '2025-04-14 19:47:24'),
(176, 35, 'uploads/products/67fd6679c91d2_0.jpg', 2, '2025-04-14 19:48:09'),
(177, 35, 'uploads/products/67fd6679c92a2_1.jpg', 3, '2025-04-14 19:48:09'),
(178, 32, 'uploads/products/67fd6694e5a8b_0.png', 2, '2025-04-14 19:48:36'),
(179, 32, 'uploads/products/67fd6694e5bf6_1.png', 3, '2025-04-14 19:48:36'),
(180, 67, 'img/products/additional/1744660160_0_Borussia DortmundL2.jpg', 0, '2025-04-14 19:49:20'),
(181, 67, 'img/products/additional/1744660160_1_Borussia DortmundL3.jpg', 0, '2025-04-14 19:49:20'),
(182, 30, 'uploads/products/67fd66f9479b9_0.png', 2, '2025-04-14 19:50:17'),
(183, 30, 'uploads/products/67fd66f947a93_1.png', 3, '2025-04-14 19:50:17'),
(184, 29, 'uploads/products/67fd670f8259c_0.png', 2, '2025-04-14 19:50:39'),
(185, 29, 'uploads/products/67fd670f8266c_1.png', 3, '2025-04-14 19:50:39'),
(194, 58, 'uploads/products/67fd68463114b_0.png', 2, '2025-04-14 19:55:51'),
(195, 58, 'uploads/products/67fd6846a798a_1.png', 3, '2025-04-14 19:55:51'),
(198, 38, 'uploads/products/67fd6884e7501_0.png', 2, '2025-04-14 19:56:52'),
(199, 38, 'uploads/products/67fd6884e7603_1.png', 3, '2025-04-14 19:56:52'),
(200, 28, 'uploads/products/67fd68a092f1b_0.png', 2, '2025-04-14 19:57:20'),
(201, 28, 'uploads/products/67fd68a092ff8_1.png', 3, '2025-04-14 19:57:20'),
(206, 39, 'uploads/products/67fd6a925fa7f_0.png', 2, '2025-04-14 20:05:38'),
(207, 39, 'uploads/products/67fd6a925fc22_1.png', 3, '2025-04-14 20:05:38'),
(208, 16, 'uploads/products/67fd7319e1898_0.jpg', 2, '2025-04-14 20:42:01'),
(212, 16, 'uploads/products/67fd73f9111f8_0.png', 3, '2025-04-14 20:45:45'),
(213, 14, 'uploads/products/67fd743a58f00_0.png', 2, '2025-04-14 20:46:50'),
(214, 14, 'uploads/products/67fd743a5902f_1.png', 3, '2025-04-14 20:46:50'),
(215, 36, 'uploads/products/67fd745e7a789_0.png', 2, '2025-04-14 20:47:26'),
(216, 36, 'uploads/products/67fd745e901a4_1.png', 3, '2025-04-14 20:47:26'),
(217, 37, 'uploads/products/67fd748c44d12_0.png', 2, '2025-04-14 20:48:12'),
(218, 37, 'uploads/products/67fd748c44e20_1.jpg', 3, '2025-04-14 20:48:12'),
(221, 55, 'uploads/products/67fd75c4229bf_0.jpg', 2, '2025-04-14 20:53:24'),
(222, 55, 'uploads/products/67fd75c422ac1_1.jpg', 3, '2025-04-14 20:53:24'),
(223, 56, 'uploads/products/67fd75f3bae0a_0.jpg', 2, '2025-04-14 20:54:11'),
(224, 56, 'uploads/products/67fd75f3baee7_1.jpg', 3, '2025-04-14 20:54:11'),
(225, 7, 'uploads/products/67fd7626af4ae_0.png', 2, '2025-04-14 20:55:02'),
(226, 7, 'uploads/products/67fd7626af57b_1.jpg', 3, '2025-04-14 20:55:02'),
(227, 43, 'uploads/products/67fd76957136d_0.jpg', 2, '2025-04-14 20:56:53'),
(228, 43, 'uploads/products/67fd76957143f_1.jpg', 3, '2025-04-14 20:56:53'),
(229, 62, 'uploads/products/67fd7761daad8_0.png', 2, '2025-04-14 21:00:18'),
(230, 62, 'uploads/products/67fd776228993_1.png', 3, '2025-04-14 21:00:18'),
(231, 41, 'uploads/products/67fd831eb65b4_0.png', 2, '2025-04-14 21:50:22'),
(232, 41, 'uploads/products/67fd831eb66d6_1.jpg', 3, '2025-04-14 21:50:22'),
(233, 26, 'uploads/products/67fd839157362_0.png', 2, '2025-04-14 21:52:17'),
(234, 26, 'uploads/products/67fd839157464_1.jpg', 3, '2025-04-14 21:52:17'),
(237, 15, 'uploads/products/67fd84d13b141_0.jpg', 2, '2025-04-14 21:57:37'),
(238, 15, 'uploads/products/67fd84d13b272_1.jpg', 3, '2025-04-14 21:57:37'),
(243, 4, 'uploads/products/67fd859e6040c_0.png', 2, '2025-04-14 22:01:02'),
(244, 4, 'uploads/products/67fd859e6052a_1.png', 3, '2025-04-14 22:01:02'),
(245, 19, 'uploads/products/67fd86254376a_0.jpg', 2, '2025-04-14 22:03:17'),
(246, 19, 'uploads/products/67fd862543849_1.jpg', 3, '2025-04-14 22:03:17'),
(247, 46, 'uploads/products/67fd864099a5e_0.png', 2, '2025-04-14 22:03:44'),
(248, 46, 'uploads/products/67fd864099b75_1.png', 3, '2025-04-14 22:03:44'),
(249, 51, 'uploads/products/67fd866b58801_0.png', 2, '2025-04-14 22:04:27'),
(250, 51, 'uploads/products/67fd866b588ec_1.png', 3, '2025-04-14 22:04:27'),
(251, 40, 'uploads/products/67fd86c79cdfa_0.jpg', 2, '2025-04-14 22:05:59'),
(252, 40, 'uploads/products/67fd86c79ced5_1.jpg', 3, '2025-04-14 22:05:59'),
(253, 59, 'uploads/products/67fd86fb7b713_0.png', 2, '2025-04-14 22:06:51'),
(254, 59, 'uploads/products/67fd86fb7b867_1.png', 3, '2025-04-14 22:06:51'),
(255, 45, 'uploads/products/67fd8b5f31dfc_0.jpg', 2, '2025-04-14 22:25:35'),
(256, 45, 'uploads/products/67fd8b5f31f1d_1.jpg', 3, '2025-04-14 22:25:35'),
(257, 47, 'uploads/products/67fd8b87e3b55_0.jpg', 2, '2025-04-14 22:26:15'),
(258, 47, 'uploads/products/67fd8b87e3c46_1.jpg', 3, '2025-04-14 22:26:15'),
(259, 48, 'uploads/products/67fd8ba1b6031_0.jpg', 2, '2025-04-14 22:26:41'),
(260, 48, 'uploads/products/67fd8ba1b6146_1.jpg', 3, '2025-04-14 22:26:41'),
(261, 54, 'uploads/products/67fd8bc6c5a4c_0.jpg', 2, '2025-04-14 22:27:18'),
(262, 54, 'uploads/products/67fd8bc6c5b02_1.jpg', 3, '2025-04-14 22:27:18'),
(263, 52, 'uploads/products/67fd8bde2c3b9_0.jpg', 2, '2025-04-14 22:27:42'),
(264, 52, 'uploads/products/67fd8bde2c4ba_1.jpg', 3, '2025-04-14 22:27:42'),
(265, 53, 'uploads/products/67fd8c072b908_0.jpg', 2, '2025-04-14 22:28:23'),
(266, 53, 'uploads/products/67fd8c072b9d1_1.png', 3, '2025-04-14 22:28:23'),
(267, 2, 'uploads/products/67fd8c413e65c_0.png', 2, '2025-04-14 22:29:23'),
(268, 2, 'uploads/products/67fd8c42e420a_1.png', 3, '2025-04-14 22:29:23'),
(269, 49, 'uploads/products/67fd8d602a4d9_0.png', 2, '2025-04-14 22:34:09'),
(270, 11, 'uploads/products/67fd8db78eb98_0.jpg', 2, '2025-04-14 22:35:35'),
(271, 11, 'uploads/products/67fd8db78ec74_1.jpg', 3, '2025-04-14 22:35:35'),
(273, 64, 'uploads/products/67fd8dd0afb71_1.jpg', 3, '2025-04-14 22:36:00'),
(274, 64, 'uploads/products/67fd8dd0afc3f_2.jpg', 4, '2025-04-14 22:36:00'),
(275, 27, 'uploads/products/67fd8df4935e8_0.jpg', 2, '2025-04-14 22:36:36'),
(276, 27, 'uploads/products/67fd8df4937ab_1.jpg', 3, '2025-04-14 22:36:36'),
(281, 69, 'img/products/additional/1744674627_0_SantosB12:13_2.png', 0, '2025-04-14 23:50:27'),
(282, 69, 'img/products/additional/1744674627_1_SantosB12:13_3.png', 0, '2025-04-14 23:50:27'),
(283, 70, 'img/products/additional/1744674724_0_PSG01:02_2.png', 0, '2025-04-14 23:52:04'),
(284, 70, 'img/products/additional/1744674724_1_PSG01:02_3.png', 0, '2025-04-14 23:52:04'),
(287, 72, 'img/products/additional/1744674990_0_ManchesterUnited98V_2.png', 0, '2025-04-14 23:56:30'),
(288, 73, 'img/products/additional/1744675076_0_ManchesterUnited98_2.png', 0, '2025-04-14 23:57:56'),
(289, 74, 'img/products/additional/1744675148_0_BetisL00_2.png', 0, '2025-04-14 23:59:08'),
(290, 74, 'img/products/additional/1744675148_1_Betis00_3.png', 0, '2025-04-14 23:59:08'),
(291, 75, 'img/products/additional/1744675240_0_Barcelona89_2.png', 0, '2025-04-15 00:00:40'),
(292, 75, 'img/products/additional/1744675240_1_Barcelona89_3.png', 0, '2025-04-15 00:00:40'),
(299, 69, 'uploads/products/67fdc292ae1cb_0.png', 3, '2025-04-15 02:21:06'),
(301, 78, 'img/products/additional/1744828192_0_2.png', 0, '2025-04-16 18:29:52'),
(302, 78, 'img/products/additional/1744828192_1_3.png', 0, '2025-04-16 18:29:52'),
(303, 57, 'uploads/products/67fff8f9ce074_0.png', 2, '2025-04-16 18:37:45'),
(304, 57, 'uploads/products/67fff8f9ce270_1.png', 3, '2025-04-16 18:37:45'),
(305, 76, 'uploads/products/6804c06639d3f_0.jpg', 2, '2025-04-20 09:37:42'),
(306, 76, 'uploads/products/6804c06639e2b_1.png', 3, '2025-04-20 09:37:42'),
(307, 31, 'uploads/products/6804c136ed8c1_0.png', 2, '2025-04-20 09:41:10'),
(308, 31, 'uploads/products/6804c136eda64_1.png', 3, '2025-04-20 09:41:10'),
(311, 68, 'uploads/products/6806b2cd0b403_0.png', 2, '2025-04-21 21:04:13'),
(312, 68, 'uploads/products/6806b2cd0b4e4_1.png', 3, '2025-04-21 21:04:13'),
(314, 10, 'uploads/products/6806b2e1157fc_1.jpg', 3, '2025-04-21 21:04:33'),
(315, 10, 'uploads/products/6806b2e1158c0_2.jpg', 4, '2025-04-21 21:04:33'),
(317, 71, 'uploads/products/6806b3107c95d_1.png', 3, '2025-04-21 21:05:20'),
(318, 71, 'uploads/products/6806b3107ca1e_2.png', 4, '2025-04-21 21:05:20'),
(319, 42, 'uploads/products/6806b3217276a_0.png', 2, '2025-04-21 21:05:37'),
(320, 42, 'uploads/products/6806b32172835_1.jpg', 3, '2025-04-21 21:05:37'),
(321, 13, 'uploads/products/6806b33591dd1_0.jpg', 2, '2025-04-21 21:05:57'),
(322, 13, 'uploads/products/6806b33591ee0_1.jpg', 3, '2025-04-21 21:05:57'),
(323, 33, 'uploads/products/6806b36248094_0.jpg', 2, '2025-04-21 21:06:42'),
(324, 33, 'uploads/products/6806b3624821b_1.jpg', 3, '2025-04-21 21:06:42'),
(325, 44, 'uploads/products/6806b3741511d_0.jpg', 2, '2025-04-21 21:07:00'),
(326, 44, 'uploads/products/6806b37415288_1.jpg', 3, '2025-04-21 21:07:00'),
(327, 24, 'uploads/products/6806b38d157cc_0.jpg', 2, '2025-04-21 21:07:25'),
(328, 24, 'uploads/products/6806b38d1593f_1.png', 3, '2025-04-21 21:07:25'),
(329, 25, 'uploads/products/6806b3d5f2751_0.png', 2, '2025-04-21 21:08:37'),
(330, 25, 'uploads/products/6806b3d5f28d0_1.png', 3, '2025-04-21 21:08:37'),
(331, 18, 'uploads/products/6806b41a447fd_0.jpg', 2, '2025-04-21 21:09:46'),
(332, 18, 'uploads/products/6806b41a4497f_1.jpg', 3, '2025-04-21 21:09:46'),
(333, 77, 'uploads/products/6806b42dee85d_0.png', 2, '2025-04-21 21:10:05'),
(334, 77, 'uploads/products/6806b42dee9d0_1.png', 3, '2025-04-21 21:10:05'),
(335, 3, 'uploads/products/6806b4549e4bd_0.jpg', 2, '2025-04-21 21:10:44'),
(336, 3, 'uploads/products/6806b4549e580_1.jpg', 3, '2025-04-21 21:10:44'),
(337, 80, 'img/products/additional/1745289080_0_Real Madrid Local 99:00_2.png', 0, '2025-04-22 02:31:20'),
(338, 80, 'img/products/additional/1745289080_1_Real Madrid Local 99:00_3.png', 0, '2025-04-22 02:31:20'),
(339, 8, 'uploads/products/680741bd1d84b_0.jpg', 2, '2025-04-22 07:14:05'),
(340, 8, 'uploads/products/680741bd1d96d_1.jpg', 3, '2025-04-22 07:14:05'),
(341, 81, 'img/products/additional/1745346744_0_InterM2.jpg', 0, '2025-04-22 18:32:24'),
(342, 81, 'img/products/additional/1745346744_1_InterM3.jpg', 0, '2025-04-22 18:32:24'),
(343, 82, 'img/products/additional/1745519041_0_francia1998_2.png', 0, '2025-04-24 18:24:01'),
(344, 82, 'img/products/additional/1745519041_1_francia1998_3.png', 0, '2025-04-24 18:24:01');

-- --------------------------------------------------------

--
-- Table structure for table `resenas`
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

--
-- Dumping data for table `resenas`
--

INSERT INTO `resenas` (`id`, `producto_id`, `nombre`, `calificacion`, `titulo`, `contenido`, `recomienda`, `imagen_path`, `fecha_creacion`) VALUES
(1, 34, 'Diego Hutt', 5.0, 'Calidad del jersey', 'Me llego en tiempo y forma. Excelente calidad, recomendado!! 👌🏼👌🏼👌🏼', 'si', NULL, '2025-04-15 00:37:28'),
(2, 8, 'Jose Palacios', 5.0, 'Calidad', 'Excelenteee calidad!!', 'si', '[\"uploads\\/reviews\\/review_8_1745306146_68074222f418b_0.jpeg\"]', '2025-04-22 07:15:47'),
(14, 10, 'Prueba', 5.0, 'Hola', 'hOLA', 'si', NULL, '2025-05-04 19:29:38'),
(15, 10, 'Prueba 2', 5.0, 'Calidad', 'saxsadsa', 'si', NULL, '2025-05-04 19:30:58'),
(16, 10, 'Prueba 2', 5.0, 'Calidad', 'saxsadsa', 'si', NULL, '2025-05-04 19:31:02'),
(17, 10, 'Prueba 2', 5.0, 'Calidad', 'saxsadsa', 'si', NULL, '2025-05-04 19:31:40'),
(18, 10, 'Francisco', 5.0, 'Calidad', 'asdasdasdsadsadasdsadas', 'si', NULL, '2025-05-04 19:31:58');

-- --------------------------------------------------------

--
-- Table structure for table `sales_stats`
--

CREATE TABLE `sales_stats` (
  `id` int(11) NOT NULL,
  `total_sales` decimal(10,2) DEFAULT 0.00,
  `total_orders` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_stats`
--

INSERT INTO `sales_stats` (`id`, `total_sales`, `total_orders`) VALUES
(1, 1598.00, 3),
(2, 1598.00, 3),
(3, 1598.00, 3),
(4, 799.00, 1),
(5, 3000.00, 1),
(6, 799.00, 1),
(7, 799.00, 1),
(8, 799.00, 1),
(9, 799.00, 1),
(10, 3196.00, 1),
(11, 799.00, 1),
(12, 799.00, 1),
(13, 799.00, 1),
(14, 799.00, 1),
(15, 799.00, 1),
(16, 1000.00, 1),
(17, 799.00, 1),
(18, 799.00, 1),
(19, 799.00, 1),
(20, 799.00, 1),
(21, 799.00, 1),
(22, 799.00, 1),
(23, 799.00, 1),
(24, 799.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `subscribers`
--

CREATE TABLE `subscribers` (
  `subscriber_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subscription_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitas`
--

CREATE TABLE `visitas` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fecha_visita` datetime DEFAULT current_timestamp(),
  `pagina_visitada` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitas`
--

INSERT INTO `visitas` (`id`, `ip`, `fecha_visita`, `pagina_visitada`, `user_agent`) VALUES
(1, '::1', '2025-04-25 19:31:50', '/Tienda/admin2/dashboard.php', NULL),
(2, '::1', '2025-04-25 19:32:11', '/Tienda/admin2/dashboard.php', NULL),
(3, '::1', '2025-04-25 19:32:12', '/Tienda/admin2/dashboard.php', NULL),
(4, '::1', '2025-04-25 19:32:19', '/Tienda/admin2/dashboard.php', NULL),
(5, '::1', '2025-04-25 19:32:20', '/Tienda/admin2/dashboard.php', NULL),
(6, '::1', '2025-04-25 19:32:49', '/Tienda/admin2/dashboard.php', NULL),
(7, '::1', '2025-04-25 19:32:52', '/Tienda/admin2/dashboard.php', NULL),
(8, '::1', '2025-04-25 19:33:12', '/Tienda/admin2/dashboard.php', NULL),
(9, '::1', '2025-04-25 19:33:14', '/Tienda/admin2/dashboard.php', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `banner_config`
--
ALTER TABLE `banner_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `banner_images`
--
ALTER TABLE `banner_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `codigos_promocionales`
--
ALTER TABLE `codigos_promocionales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indexes for table `featured_products`
--
ALTER TABLE `featured_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `giftcard_redemptions`
--
ALTER TABLE `giftcard_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `giftcard_transactions`
--
ALTER TABLE `giftcard_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_newsletter_email` (`email`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order_items_giftcard` (`order_id`,`personalization_name`,`giftcard_sent`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_product_category` (`category`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `resenas`
--
ALTER TABLE `resenas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indexes for table `sales_stats`
--
ALTER TABLE `sales_stats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`subscriber_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `visitas`
--
ALTER TABLE `visitas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `banner_config`
--
ALTER TABLE `banner_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `banner_images`
--
ALTER TABLE `banner_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `codigos_promocionales`
--
ALTER TABLE `codigos_promocionales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `featured_products`
--
ALTER TABLE `featured_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=462;

--
-- AUTO_INCREMENT for table `giftcard_redemptions`
--
ALTER TABLE `giftcard_redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `giftcard_transactions`
--
ALTER TABLE `giftcard_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=345;

--
-- AUTO_INCREMENT for table `resenas`
--
ALTER TABLE `resenas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `sales_stats`
--
ALTER TABLE `sales_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visitas`
--
ALTER TABLE `visitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `featured_products`
--
ALTER TABLE `featured_products`
  ADD CONSTRAINT `featured_products_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
