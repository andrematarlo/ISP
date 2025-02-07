-- Create subscription_cancellations table
CREATE TABLE IF NOT EXISTS subscription_cancellations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    customer_id INT NOT NULL,
    plan_id INT NOT NULL,
    cancelled_by INT NOT NULL,
    cancel_reason VARCHAR(255) NOT NULL,
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for performance
CREATE INDEX idx_subscription_cancellations_customer ON subscription_cancellations(customer_id);
CREATE INDEX idx_subscription_cancellations_cancelled_at ON subscription_cancellations(cancelled_at);
