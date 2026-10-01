-- Optional deployment migration; payments.php also creates this table automatically.
CREATE TABLE IF NOT EXISTS payment_amount_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    full_amount DECIMAL(12,2) NOT NULL,
    half_first DECIMAL(12,2) NOT NULL,
    half_second DECIMAL(12,2) NOT NULL,
    quarter_each DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB;
