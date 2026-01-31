-- Vouchers Table
CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    minutes INT NOT NULL,
    status ENUM('UNUSED','USED','VOID','EXPIRED') DEFAULT 'UNUSED',
    date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_used DATETIME NULL,
    expiry_date DATETIME NULL,
    qr_image VARCHAR(128) NULL
);

-- Redemption Log Table
CREATE TABLE IF NOT EXISTS redemption_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_code VARCHAR(64) NOT NULL,
    minutes INT NOT NULL,
    date_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(32) NOT NULL,
    status ENUM('SUCCESS','FAILED') NOT NULL,
    FOREIGN KEY (voucher_code) REFERENCES vouchers(code)
);