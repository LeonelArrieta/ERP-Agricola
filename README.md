# Mini ERP Agricola

Aplicacion web de gestion para una explotacion agricola: clientes y proveedores, inventario, produccion, compras, ventas, finanzas, gastos y facturacion electronica ARCA (WSFEv1). Desarrollada en PHP 8.2 sin framework, con renderizado en servidor y JavaScript vanilla.

## Requisitos

- PHP >= 8.2 con `pdo_mysql`, `mbstring`, `json` y `openssl`.
- MySQL o MariaDB con InnoDB y `utf8mb4`.
- Apache 2.4 con `mod_rewrite`.
- Composer.

## Instalacion

1. Instalar dependencias:

```bash
composer install
```

2. Crear el archivo de entorno:

```bash
cp .env.example .env
```

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

3. Ajustar como minimo en `.env`:

```text
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_agricola
DB_USERNAME=erp_app
DB_PASSWORD=<password>
```

### Base de datos

1. Crear la base, las tablas y los catalogos minimos con un usuario administrador de MySQL:

```bash
mysql -h 127.0.0.1 -u root -p < database/schema.sql
```

El archivo `database/schema.sql` crea la base `erp_agricola`, la estructura completa y los catalogos necesarios (roles, permisos, unidades, tipos de comprobante, punto de venta, caja y datos del emisor). No incluye datos demo ni operativos.

2. Crear el usuario de aplicacion con minimo privilegio. Editar la contrasena en `database/scripts/create_app_user.sql` y ejecutarlo como administrador de MySQL.

3. Crear el usuario administrador inicial:

```bash
php database/scripts/create_admin_user.php admin@example.com "ClaveSegura123" "Admin" "ERP"
```

## Ejecucion

El DocumentRoot debe apuntar a la carpeta `public/`:

```apache
DocumentRoot "/ruta/al/proyecto/erp-agricola/public"
```

Hay un ejemplo de VirtualHost en `deploy/apache-erp-agricola.conf`.

Para desarrollo local con XAMPP tambien se puede acceder desde la raiz del proyecto: el `.htaccess` raiz redirige los assets y usa `index.php` como front controller.

Asegurar permisos de escritura para el usuario web en `storage/`:

```bash
chmod -R 775 storage
```

La aplicacion crea automaticamente `storage/logs`, `storage/cache/dompdf` y `storage/arca` cuando los necesita.

## Estructura

```text
app/                    Codigo de la aplicacion (Controllers, Services, Repositories, Views)
database/
  schema.sql            Estructura + catalogos minimos
  scripts/              Alta de usuario de BD y administrador inicial
deploy/                 Ejemplo de VirtualHost Apache
public/                 DocumentRoot y assets publicos
storage/                Logs, cache de PDF y ticket ARCA (contenido ignorado por git)
index.php               Front controller para acceso desde la raiz
.env.example            Variables de entorno de referencia
```

## Facturacion electronica ARCA

Por defecto `ARCA_MODE=mock`, que no requiere certificados ni conexion con ARCA.

Para usar homologacion o produccion, configurar en `.env`:

```text
ARCA_MODE=homologacion
ARCA_CUIT=<CUIT del emisor>
ARCA_CERT_PATH=/ruta/segura/fuera/del/repositorio/certificado.crt
ARCA_KEY_PATH=/ruta/segura/fuera/del/repositorio/clave.key
ARCA_ALLOW_EMISSION=true
```

Los certificados y claves nunca deben versionarse ni ubicarse dentro del repositorio.

## Seguridad

- No versionar `.env` ni credenciales.
- Usar HTTPS en produccion y `APP_DEBUG=false`.
- Mantener certificados y claves ARCA fuera del repositorio.
- Ejecutar las migraciones DDL con un usuario administrador y la aplicacion con un usuario de solo DML.

## Licencia

Software propietario. Todos los derechos reservados. Ver `LICENSE`.
