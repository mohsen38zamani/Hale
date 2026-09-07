-- Hale local development database bootstrap
CREATE DATABASE IF NOT EXISTS hale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS hale_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON hale.* TO 'hale'@'%';
GRANT ALL PRIVILEGES ON hale_testing.* TO 'hale'@'%';
FLUSH PRIVILEGES;
