-- Modify end_date column to DATETIME
ALTER TABLE subscriptions 
MODIFY COLUMN end_date DATETIME;

-- Add columns for subscription cancellation if not exists
ALTER TABLE subscriptions 
ADD COLUMN IF NOT EXISTS cancel_reason VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS cancelled_by INT NULL;

-- Add foreign key for cancelled_by
ALTER TABLE subscriptions
ADD CONSTRAINT fk_cancelled_by 
FOREIGN KEY (cancelled_by) 
REFERENCES users(id) 
ON DELETE SET NULL;
