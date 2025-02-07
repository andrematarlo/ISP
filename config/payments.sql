-- Create payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('gcash', 'cash', 'bank_transfer') NOT NULL,
    reference_number VARCHAR(50),
    gcash_number VARCHAR(20),
    payment_date DATETIME NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- Add payment-related columns to bills table if they don't exist
ALTER TABLE bills
ADD COLUMN IF NOT EXISTS paid_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS payment_id INT,
ADD FOREIGN KEY (payment_id) REFERENCES payments(id);

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_bill_payment ON payments(bill_id, payment_method, status);
CREATE INDEX IF NOT EXISTS idx_gcash_reference ON payments(reference_number);
