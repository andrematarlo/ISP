-- Create upgrade_requests table
CREATE TABLE IF NOT EXISTS upgrade_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    plan_id INT NOT NULL,
    reason TEXT NOT NULL,
    preferred_date DATE,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX idx_upgrade_requests_customer ON upgrade_requests(customer_id);
CREATE INDEX idx_upgrade_requests_status ON upgrade_requests(status);
