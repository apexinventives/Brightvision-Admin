CREATE TABLE IF NOT EXISTS account_permissions (
    admin_id INT(11) NOT NULL PRIMARY KEY,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    permissions_json TEXT NOT NULL,
    CONSTRAINT fk_account_permissions_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Preserve current login accounts as administrators during the upgrade.
INSERT IGNORE INTO account_permissions (admin_id, role, permissions_json)
SELECT id, 'admin', '{}' FROM admins;
