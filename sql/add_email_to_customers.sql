-- Add email column to customers table
ALTER TABLE customers
ADD COLUMN email VARCHAR(255) AFTER full_name;
