-- Add size column to order_items table
ALTER TABLE order_items
ADD COLUMN size VARCHAR(10) NOT NULL AFTER quantity;