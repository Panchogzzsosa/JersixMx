-- Añadir columna para rastrear si la gift card ha sido enviada
ALTER TABLE `order_items` ADD COLUMN `giftcard_sent` TINYINT(1) DEFAULT 0 AFTER `personalization_patch`;

-- Índice para mejorar el rendimiento de las consultas
CREATE INDEX `idx_order_items_giftcard` ON `order_items` (`order_id`, `personalization_name`, `giftcard_sent`);

-- Actualizar la tabla de productos para añadir gift card como producto
INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `stock`, `image_url`, `category`, `created_at`, `status`) 
SELECT MAX(product_id) + 1, 'Tarjeta de Regalo JerSix', 'Tarjeta de regalo para regalar a tus seres queridos', 0, 999, 'img/LogoNav.png', 'Gift Cards', NOW(), 1 
FROM `products`
WHERE NOT EXISTS (
    SELECT 1 FROM `products` WHERE `name` = 'Tarjeta de Regalo JerSix'
) LIMIT 1; 