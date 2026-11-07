-- Usuario de aplicacion con minimo privilegio.
-- Ejecutar como administrador de MySQL/MariaDB una sola vez por entorno.
-- Reemplazar la contrasena antes de ejecutar y nunca versionarla.
--
-- Las migraciones (DDL) deben ejecutarse con un usuario administrador
-- antes de crear este usuario. La aplicacion solo necesita DML.

CREATE DATABASE IF NOT EXISTS erp_agricola
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'erp_app'@'localhost' IDENTIFIED BY 'CAMBIAR_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE ON erp_agricola.* TO 'erp_app'@'localhost';

FLUSH PRIVILEGES;
