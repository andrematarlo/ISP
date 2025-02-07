-- Update existing customers' email addresses from users table
UPDATE customers c
JOIN users u ON c.user_id = u.id
SET c.email = u.email
WHERE c.email IS NULL;
