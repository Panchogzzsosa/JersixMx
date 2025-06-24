-- Tabla para gestionar el inventario por tallas
CREATE TABLE `product_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `size` varchar(10) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_size_unique` (`product_id`, `size`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insertar tallas comunes para productos existentes
INSERT INTO `product_inventory` (`product_id`, `size`, `stock`) 
SELECT 
    p.product_id,
    'S',
    CASE WHEN p.stock > 0 THEN FLOOR(p.stock / 4) ELSE 0 END
FROM products p
WHERE p.category != 'Gift Card';

INSERT INTO `product_inventory` (`product_id`, `size`, `stock`) 
SELECT 
    p.product_id,
    'M',
    CASE WHEN p.stock > 0 THEN FLOOR(p.stock / 4) ELSE 0 END
FROM products p
WHERE p.category != 'Gift Card';

INSERT INTO `product_inventory` (`product_id`, `size`, `stock`) 
SELECT 
    p.product_id,
    'L',
    CASE WHEN p.stock > 0 THEN FLOOR(p.stock / 4) ELSE 0 END
FROM products p
WHERE p.category != 'Gift Card';

INSERT INTO `product_inventory` (`product_id`, `size`, `stock`) 
SELECT 
    p.product_id,
    'XL',
    CASE WHEN p.stock > 0 THEN FLOOR(p.stock / 4) ELSE 0 END
FROM products p
WHERE p.category != 'Gift Card'; 