INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'JoJeTech Solutions'),
('company_address', 'Consolacion,Dalaguete,Cebu'),
('company_phone', '+639195700051'),
('company_email', 'tamarloandre@gmail.com'),
('due_date_days', '15'),
('late_fee_percentage', '5'),
('enable_email_notifications', '1'),
('enable_sms_notifications', '0'),
('notification_days_before', '3'),
('smtp_username', 'tamarloandre@gmail.com'),
('smtp_password', 'eidt jjzk ooxa zrrr')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
