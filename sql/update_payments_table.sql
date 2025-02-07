-- Add new columns to payments table for better tracking
ALTER TABLE payments
ADD COLUMN processed_at DATETIME NULL AFTER status,
ADD COLUMN processed_by INT NULL AFTER processed_at,
ADD FOREIGN KEY (processed_by) REFERENCES users(id);

-- Update existing records to have processed_at if they are completed
UPDATE payments 
SET processed_at = payment_date,
    processed_by = (SELECT id FROM users WHERE role = 'admin' LIMIT 1)
WHERE status = 'completed';
