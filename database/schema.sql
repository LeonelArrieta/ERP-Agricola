-- Mini ERP Agricola - esquema y catalogos base
-- Archivo unico para una base nueva. Solo estructura y catalogos minimos:
-- roles/permisos, unidades, tipos de comprobante, punto de venta, caja y emisor.
-- No contiene datos demo ni operativos.

CREATE DATABASE IF NOT EXISTS erp_agricola CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_agricola;

-- ===== 001_initial_schema.sql =====

-- Mini ERP Agricola - esquema inicial SDD v1.1.1
-- Requiere MySQL/MariaDB con soporte InnoDB y CHECK.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE roles (
    id_rol BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
    id_permiso BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol_permiso (
    id_rol BIGINT UNSIGNED NOT NULL,
    id_permiso BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_rol_permiso_rol FOREIGN KEY (id_rol) REFERENCES roles (id_rol),
    CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (id_permiso) REFERENCES permisos (id_permiso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_rol BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles (id_rol),
    INDEX idx_usuarios_rol (id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registros_auditoria (
    id_registro_auditoria BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario BIGINT UNSIGNED NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(100) NOT NULL,
    id_entidad BIGINT UNSIGNED NOT NULL,
    valores_anteriores JSON NULL,
    valores_nuevos JSON NULL,
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    INDEX idx_auditoria_usuario_fecha (id_usuario, fecha_hora),
    INDEX idx_auditoria_entidad (entidad, id_entidad),
    INDEX idx_auditoria_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes (
    id_cliente BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NULL,
    apellido VARCHAR(100) NULL,
    razon_social VARCHAR(200) NULL,
    documento VARCHAR(50) NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    direccion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clientes_documento (documento),
    INDEX idx_clientes_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proveedores (
    id_proveedor BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NULL,
    apellido VARCHAR(100) NULL,
    razon_social VARCHAR(200) NULL,
    documento VARCHAR(50) NULL,
    telefono VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    direccion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_proveedores_documento (documento),
    INDEX idx_proveedores_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trabajadores (
    id_trabajador BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    documento VARCHAR(50) NULL,
    telefono VARCHAR(50) NULL,
    costo_hora_actual DECIMAL(15,2) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_trabajadores_costo CHECK (costo_hora_actual >= 0),
    INDEX idx_trabajadores_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cultivos (
    id_cultivo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parcelas (
    id_parcela BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    superficie DECIMAL(15,3) NOT NULL,
    unidad_superficie VARCHAR(30) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_parcelas_superficie CHECK (superficie > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tareas (
    id_tarea BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE productos (
    id_producto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    porcentaje_alicuota DECIMAL(5,2) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_productos_alicuota CHECK (porcentaje_alicuota IN (10.50, 21.00, 27.00))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE insumos (
    id_insumo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    stock_actual DECIMAL(15,3) NOT NULL DEFAULT 0,
    costo_unitario_actual DECIMAL(15,2) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_insumos_stock CHECK (stock_actual >= 0),
    CONSTRAINT chk_insumos_costo CHECK (costo_unitario_actual >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias_gastos (
    id_categoria_gasto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ciclos_cultivo (
    id_ciclo_cultivo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cultivo BIGINT UNSIGNED NOT NULL,
    id_parcela BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    estado ENUM('Planificado','EnCurso','Cosechado','Cancelado') NOT NULL DEFAULT 'Planificado',
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ciclos_cultivo FOREIGN KEY (id_cultivo) REFERENCES cultivos (id_cultivo),
    CONSTRAINT fk_ciclos_parcela FOREIGN KEY (id_parcela) REFERENCES parcelas (id_parcela),
    CONSTRAINT chk_ciclos_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
    INDEX idx_ciclos_cultivo (id_cultivo),
    INDEX idx_ciclos_parcela_estado (id_parcela, estado),
    INDEX idx_ciclos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tareas_cultivo (
    id_tarea_cultivo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ciclo_cultivo BIGINT UNSIGNED NOT NULL,
    id_tarea BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('Planificada','EnCurso','Completada','Cancelada') NOT NULL DEFAULT 'Planificada',
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tareas_cultivo_ciclo FOREIGN KEY (id_ciclo_cultivo) REFERENCES ciclos_cultivo (id_ciclo_cultivo),
    CONSTRAINT fk_tareas_cultivo_tarea FOREIGN KEY (id_tarea) REFERENCES tareas (id_tarea),
    INDEX idx_tareas_cultivo_ciclo (id_ciclo_cultivo),
    INDEX idx_tareas_cultivo_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trabajos_humanos (
    id_trabajo_humano BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_tarea_cultivo BIGINT UNSIGNED NOT NULL,
    id_trabajador BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    horas_aplicadas DECIMAL(15,3) NOT NULL,
    costo_hora DECIMAL(15,2) NOT NULL,
    costo_total DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_trabajos_tarea FOREIGN KEY (id_tarea_cultivo) REFERENCES tareas_cultivo (id_tarea_cultivo),
    CONSTRAINT fk_trabajos_trabajador FOREIGN KEY (id_trabajador) REFERENCES trabajadores (id_trabajador),
    CONSTRAINT chk_trabajos_horas CHECK (horas_aplicadas > 0),
    CONSTRAINT chk_trabajos_costo_hora CHECK (costo_hora >= 0),
    CONSTRAINT chk_trabajos_total CHECK (costo_total >= 0),
    INDEX idx_trabajos_tarea (id_tarea_cultivo),
    INDEX idx_trabajos_trabajador (id_trabajador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE consumos_insumos (
    id_consumo_insumo BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_tarea_cultivo BIGINT UNSIGNED NOT NULL,
    id_insumo BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    cantidad DECIMAL(15,3) NOT NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    costo_unitario DECIMAL(15,2) NOT NULL,
    costo_total DECIMAL(15,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_consumos_tarea FOREIGN KEY (id_tarea_cultivo) REFERENCES tareas_cultivo (id_tarea_cultivo),
    CONSTRAINT fk_consumos_insumo FOREIGN KEY (id_insumo) REFERENCES insumos (id_insumo),
    CONSTRAINT chk_consumos_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_consumos_costo CHECK (costo_unitario >= 0 AND costo_total >= 0),
    INDEX idx_consumos_tarea (id_tarea_cultivo),
    INDEX idx_consumos_insumo (id_insumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cosechas (
    id_cosecha BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ciclo_cultivo BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    cantidad DECIMAL(15,3) NOT NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cosechas_ciclo FOREIGN KEY (id_ciclo_cultivo) REFERENCES ciclos_cultivo (id_ciclo_cultivo),
    CONSTRAINT chk_cosechas_cantidad CHECK (cantidad > 0),
    INDEX idx_cosechas_ciclo_fecha (id_ciclo_cultivo, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lotes_produccion (
    id_lote_produccion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_producto BIGINT UNSIGNED NOT NULL,
    id_cosecha BIGINT UNSIGNED NOT NULL,
    fecha_produccion DATE NOT NULL,
    cantidad_inicial DECIMAL(15,3) NOT NULL,
    cantidad_disponible DECIMAL(15,3) NOT NULL,
    costo_unitario DECIMAL(15,2) NOT NULL,
    es_costo_provisional TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lotes_producto FOREIGN KEY (id_producto) REFERENCES productos (id_producto),
    CONSTRAINT fk_lotes_cosecha FOREIGN KEY (id_cosecha) REFERENCES cosechas (id_cosecha),
    CONSTRAINT chk_lotes_cantidades CHECK (cantidad_inicial > 0 AND cantidad_disponible >= 0 AND cantidad_disponible <= cantidad_inicial),
    CONSTRAINT chk_lotes_costo CHECK (costo_unitario >= 0),
    INDEX idx_lotes_producto (id_producto),
    INDEX idx_lotes_cosecha (id_cosecha),
    INDEX idx_lotes_disponible (cantidad_disponible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caja (
    id_caja TINYINT UNSIGNED PRIMARY KEY,
    saldo_actual DECIMAL(15,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_caja_saldo CHECK (saldo_actual >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movimientos_inventario (
    id_movimiento_inventario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_insumo BIGINT UNSIGNED NULL,
    id_producto BIGINT UNSIGNED NULL,
    id_lote_produccion BIGINT UNSIGNED NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('EntradaCompra','EntradaCosecha','SalidaVenta','SalidaConsumo','SalidaPorMerma','ReversionEntrada','ReversionSalida') NOT NULL,
    cantidad DECIMAL(15,3) NOT NULL,
    stock_anterior DECIMAL(15,3) NOT NULL,
    stock_resultante DECIMAL(15,3) NOT NULL,
    origen_tipo VARCHAR(50) NOT NULL,
    origen_id BIGINT UNSIGNED NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    observaciones TEXT NULL,
    CONSTRAINT fk_mov_inv_insumo FOREIGN KEY (id_insumo) REFERENCES insumos (id_insumo),
    CONSTRAINT fk_mov_inv_producto FOREIGN KEY (id_producto) REFERENCES productos (id_producto),
    CONSTRAINT fk_mov_inv_lote FOREIGN KEY (id_lote_produccion) REFERENCES lotes_produccion (id_lote_produccion),
    CONSTRAINT fk_mov_inv_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT chk_mov_inv_recurso CHECK ((id_insumo IS NOT NULL AND id_producto IS NULL) OR (id_insumo IS NULL AND id_producto IS NOT NULL)),
    CONSTRAINT chk_mov_inv_cantidad CHECK (cantidad > 0 AND stock_anterior >= 0 AND stock_resultante >= 0),
    INDEX idx_mov_inv_fecha_tipo (fecha_hora, tipo),
    INDEX idx_mov_inv_insumo (id_insumo),
    INDEX idx_mov_inv_lote (id_lote_produccion),
    INDEX idx_mov_inv_origen (origen_tipo, origen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ventas (
    id_venta BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cliente BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    tipo_pago ENUM('Contado','Credito') NOT NULL,
    estado ENUM('Presupuesto','Pendiente','Facturado','Cobrado','Cancelado') NOT NULL DEFAULT 'Pendiente',
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    importe_alicuota DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ventas_cliente FOREIGN KEY (id_cliente) REFERENCES clientes (id_cliente),
    CONSTRAINT chk_ventas_importes CHECK (subtotal >= 0 AND importe_alicuota >= 0 AND total >= 0),
    INDEX idx_ventas_cliente (id_cliente),
    INDEX idx_ventas_fecha_estado (fecha, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalles_ventas (
    id_detalle_venta BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta BIGINT UNSIGNED NOT NULL,
    id_producto BIGINT UNSIGNED NOT NULL,
    id_lote_produccion BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(15,3) NOT NULL,
    precio_costo DECIMAL(15,2) NOT NULL,
    porcentaje_rentabilidad DECIMAL(7,6) NOT NULL,
    precio_bruto DECIMAL(15,2) NOT NULL,
    porcentaje_alicuota DECIMAL(5,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    importe_alicuota DECIMAL(15,2) NOT NULL,
    precio_neto_final DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_det_ventas_venta FOREIGN KEY (id_venta) REFERENCES ventas (id_venta),
    CONSTRAINT fk_det_ventas_producto FOREIGN KEY (id_producto) REFERENCES productos (id_producto),
    CONSTRAINT fk_det_ventas_lote FOREIGN KEY (id_lote_produccion) REFERENCES lotes_produccion (id_lote_produccion),
    CONSTRAINT chk_det_ventas_valores CHECK (cantidad > 0 AND precio_costo >= 0 AND porcentaje_rentabilidad >= 0 AND porcentaje_rentabilidad < 1 AND precio_bruto >= 0 AND porcentaje_alicuota IN (10.50, 21.00, 27.00) AND subtotal >= 0 AND importe_alicuota >= 0 AND precio_neto_final >= 0),
    INDEX idx_det_ventas_venta (id_venta),
    INDEX idx_det_ventas_lote (id_lote_produccion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE compras (
    id_compra BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_proveedor BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    tipo_pago ENUM('Contado','Credito') NOT NULL,
    estado ENUM('Presupuesto','Pendiente','Facturado','Pagado','Cancelado') NOT NULL DEFAULT 'Pendiente',
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    importe_alicuota DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_compras_proveedor FOREIGN KEY (id_proveedor) REFERENCES proveedores (id_proveedor),
    CONSTRAINT chk_compras_importes CHECK (subtotal >= 0 AND importe_alicuota >= 0 AND total >= 0),
    INDEX idx_compras_proveedor (id_proveedor),
    INDEX idx_compras_fecha_estado (fecha, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detalles_compras (
    id_detalle_compra BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_compra BIGINT UNSIGNED NOT NULL,
    id_insumo BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(15,3) NOT NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    costo_unitario DECIMAL(15,2) NOT NULL,
    importe_linea DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_det_compras_compra FOREIGN KEY (id_compra) REFERENCES compras (id_compra),
    CONSTRAINT fk_det_compras_insumo FOREIGN KEY (id_insumo) REFERENCES insumos (id_insumo),
    CONSTRAINT chk_det_compras_valores CHECK (cantidad > 0 AND costo_unitario >= 0 AND importe_linea >= 0),
    INDEX idx_det_compras_compra (id_compra),
    INDEX idx_det_compras_insumo (id_insumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cuentas_por_cobrar (
    id_cuenta_por_cobrar BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta BIGINT UNSIGNED NOT NULL UNIQUE,
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE NULL,
    importe_original DECIMAL(15,2) NOT NULL,
    saldo_actual DECIMAL(15,2) NOT NULL,
    estado ENUM('Pendiente','Parcialmente_Cobrada','Cobrada','Cancelada') NOT NULL DEFAULT 'Pendiente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cxc_venta FOREIGN KEY (id_venta) REFERENCES ventas (id_venta),
    CONSTRAINT chk_cxc_importes CHECK (importe_original >= 0 AND saldo_actual >= 0 AND saldo_actual <= importe_original),
    INDEX idx_cxc_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cuentas_por_pagar (
    id_cuenta_por_pagar BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_compra BIGINT UNSIGNED NULL,
    id_gasto BIGINT UNSIGNED NULL,
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE NULL,
    importe_original DECIMAL(15,2) NOT NULL,
    saldo_actual DECIMAL(15,2) NOT NULL,
    estado ENUM('Pendiente','Parcialmente_Pagada','Pagada','Cancelada') NOT NULL DEFAULT 'Pendiente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_cxp_origen CHECK ((id_compra IS NOT NULL AND id_gasto IS NULL) OR (id_compra IS NULL AND id_gasto IS NOT NULL)),
    CONSTRAINT chk_cxp_importes CHECK (importe_original >= 0 AND saldo_actual >= 0 AND saldo_actual <= importe_original),
    UNIQUE KEY uq_cxp_compra (id_compra),
    UNIQUE KEY uq_cxp_gasto (id_gasto),
    INDEX idx_cxp_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gastos (
    id_gasto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_categoria_gasto BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    importe DECIMAL(15,2) NOT NULL,
    tipo_pago ENUM('Contado','Diferido') NOT NULL,
    estado ENUM('Pendiente','Pagado','Cancelado') NOT NULL DEFAULT 'Pendiente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gastos_categoria FOREIGN KEY (id_categoria_gasto) REFERENCES categorias_gastos (id_categoria_gasto),
    CONSTRAINT chk_gastos_importe CHECK (importe > 0),
    INDEX idx_gastos_categoria (id_categoria_gasto),
    INDEX idx_gastos_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cuentas_por_pagar
    ADD CONSTRAINT fk_cxp_compra FOREIGN KEY (id_compra) REFERENCES compras (id_compra),
    ADD CONSTRAINT fk_cxp_gasto FOREIGN KEY (id_gasto) REFERENCES gastos (id_gasto);

CREATE TABLE movimientos_caja (
    id_movimiento_caja BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('Ingreso','Egreso','ReversionIngreso','ReversionEgreso') NOT NULL,
    importe DECIMAL(15,2) NOT NULL,
    origen_tipo VARCHAR(50) NOT NULL,
    origen_id BIGINT UNSIGNED NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    observaciones TEXT NULL,
    CONSTRAINT fk_mov_caja_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT chk_mov_caja_importe CHECK (importe > 0),
    INDEX idx_mov_caja_fecha_tipo (fecha_hora, tipo),
    INDEX idx_mov_caja_origen (origen_tipo, origen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cobros (
    id_cobro BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cuenta_por_cobrar BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    importe DECIMAL(15,2) NOT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cobros_cxc FOREIGN KEY (id_cuenta_por_cobrar) REFERENCES cuentas_por_cobrar (id_cuenta_por_cobrar),
    CONSTRAINT fk_cobros_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT chk_cobros_importe CHECK (importe > 0),
    INDEX idx_cobros_cxc_fecha (id_cuenta_por_cobrar, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagos (
    id_pago BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cuenta_por_pagar BIGINT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    importe DECIMAL(15,2) NOT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagos_cxp FOREIGN KEY (id_cuenta_por_pagar) REFERENCES cuentas_por_pagar (id_cuenta_por_pagar),
    CONSTRAINT fk_pagos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT chk_pagos_importe CHECK (importe > 0),
    INDEX idx_pagos_cxp_fecha (id_cuenta_por_pagar, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reversiones_cobros (
    id_reversion_cobro BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cobro BIGINT UNSIGNED NOT NULL UNIQUE,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario BIGINT UNSIGNED NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    id_movimiento_caja BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rev_cobro_cobro FOREIGN KEY (id_cobro) REFERENCES cobros (id_cobro),
    CONSTRAINT fk_rev_cobro_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_rev_cobro_mov FOREIGN KEY (id_movimiento_caja) REFERENCES movimientos_caja (id_movimiento_caja)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reversiones_pagos (
    id_reversion_pago BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pago BIGINT UNSIGNED NOT NULL UNIQUE,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario BIGINT UNSIGNED NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    id_movimiento_caja BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rev_pago_pago FOREIGN KEY (id_pago) REFERENCES pagos (id_pago),
    CONSTRAINT fk_rev_pago_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_rev_pago_mov FOREIGN KEY (id_movimiento_caja) REFERENCES movimientos_caja (id_movimiento_caja)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;



-- ===== 002_seed_base.sql =====

INSERT IGNORE INTO roles (nombre, descripcion) VALUES ('Administrador', 'Acceso administrativo del sistema');

INSERT IGNORE INTO permisos (nombre, descripcion) VALUES
    ('sistema.administrar', 'Administrar la configuracion y seguridad del sistema'),
    ('auditoria.ver', 'Consultar registros de auditoria'),
    ('usuarios.ver', 'Consultar usuarios'),
    ('usuarios.crear', 'Crear usuarios'),
    ('usuarios.editar', 'Modificar y desactivar usuarios'),
    ('clientes.ver', 'Consultar clientes'),
    ('clientes.crear', 'Crear clientes'),
    ('clientes.editar', 'Modificar y desactivar clientes'),
    ('proveedores.ver', 'Consultar proveedores'),
    ('proveedores.crear', 'Crear proveedores'),
    ('proveedores.editar', 'Modificar y desactivar proveedores'),
    ('productos.ver', 'Consultar productos'),
    ('productos.crear', 'Crear productos'),
    ('productos.editar', 'Modificar y desactivar productos'),
    ('insumos.ver', 'Consultar insumos'),
    ('insumos.crear', 'Crear insumos'),
    ('insumos.editar', 'Modificar y desactivar insumos'),
    ('produccion.ver', 'Consultar catalogos de produccion'),
    ('produccion.crear', 'Gestionar catalogos y operaciones de produccion'),
    ('inventario.ver', 'Consultar inventario'),
    ('inventario.merma', 'Registrar mermas de inventario'),
    ('produccion.cancelar', 'Cancelar ciclos de cultivo'),
    ('compras.ver', 'Consultar compras'),
    ('compras.crear', 'Registrar compras'),
    ('ventas.ver', 'Consultar ventas'),
    ('ventas.crear', 'Registrar ventas'),
    ('cobranzas.ver', 'Consultar cuentas por cobrar'),
    ('cobranzas.crear', 'Registrar cobros'),
    ('pagos.ver', 'Consultar cuentas por pagar'),
    ('pagos.crear', 'Registrar pagos'),
    ('caja.ver', 'Consultar Caja'),
    ('gastos.ver', 'Consultar gastos'),
    ('gastos.crear', 'Registrar gastos'),
    ('ventas.cancelar', 'Cancelar ventas'),
    ('cobranzas.revertir', 'Revertir cobros'),
    ('pagos.revertir', 'Revertir pagos'),
    ('compras.cancelar', 'Cancelar compras');

INSERT IGNORE INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r
JOIN permisos p ON p.nombre IN ('sistema.administrar', 'auditoria.ver', 'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'clientes.ver', 'clientes.crear', 'clientes.editar', 'proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'productos.ver', 'productos.crear', 'productos.editar', 'insumos.ver', 'insumos.crear', 'insumos.editar', 'produccion.ver', 'produccion.crear', 'inventario.ver', 'inventario.merma', 'produccion.cancelar', 'compras.ver', 'compras.crear', 'compras.cancelar', 'ventas.ver', 'ventas.crear', 'ventas.cancelar', 'cobranzas.ver', 'cobranzas.crear', 'cobranzas.revertir', 'pagos.ver', 'pagos.crear', 'pagos.revertir', 'caja.ver', 'gastos.ver', 'gastos.crear')
WHERE r.nombre = 'Administrador';

INSERT IGNORE INTO caja (id_caja, saldo_actual) VALUES (1, 0);



-- ===== 003_create_unidades.sql =====

-- Mini ERP Agricola - maestro de unidades de medida
-- Migracion aditiva solicitada durante la implementacion. No modifica el SDD.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS unidades (
    id_unidad BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO unidades (nombre, descripcion) VALUES
    ('kg', 'Kilogramo'),
    ('litros', 'Litro'),
    ('unidades', 'Unidad'),
    ('toneladas', 'Tonelada'),
    ('ha', 'Hectarea');

INSERT IGNORE INTO permisos (nombre, descripcion) VALUES
    ('unidades.ver', 'Consultar unidades de medida'),
    ('unidades.crear', 'Crear unidades de medida'),
    ('unidades.editar', 'Modificar y desactivar unidades de medida');

INSERT IGNORE INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r
JOIN permisos p ON p.nombre IN ('unidades.ver', 'unidades.crear', 'unidades.editar')
WHERE r.nombre = 'Administrador';



-- ===== 004_facturacion_electronica.sql =====

-- Mini ERP Agricola - facturacion electronica ARCA (WSFEv1)
-- Migracion aditiva. No modifica reglas comerciales ni financieras existentes.

SET NAMES utf8mb4;

ALTER TABLE clientes
    ADD COLUMN doc_tipo SMALLINT UNSIGNED NULL AFTER documento,
    ADD COLUMN doc_nro VARCHAR(20) NULL AFTER doc_tipo,
    ADD COLUMN condicion_iva_receptor_id SMALLINT UNSIGNED NULL AFTER doc_nro;

CREATE TABLE IF NOT EXISTS tipos_comprobante (
    id_tipo_comprobante SMALLINT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    clase ENUM('A','B','C') NOT NULL,
    discrimina_iva TINYINT(1) NOT NULL DEFAULT 1,
    signo ENUM('D','C') NOT NULL DEFAULT 'D',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_tipo_comprobante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tipos_comprobante (id_tipo_comprobante, nombre, clase, discrimina_iva, signo) VALUES
    (1, 'Factura A', 'A', 1, 'D'),
    (2, 'Nota de Credito A', 'A', 1, 'C'),
    (3, 'Nota de Debito A', 'A', 1, 'D'),
    (6, 'Factura B', 'B', 1, 'D'),
    (7, 'Nota de Credito B', 'B', 1, 'C'),
    (8, 'Nota de Debito B', 'B', 1, 'D'),
    (11, 'Factura C', 'C', 0, 'D'),
    (12, 'Nota de Credito C', 'C', 0, 'C'),
    (13, 'Nota de Debito C', 'C', 0, 'D');

CREATE TABLE IF NOT EXISTS puntos_venta (
    id_punto_venta BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero SMALLINT UNSIGNED NOT NULL UNIQUE,
    descripcion VARCHAR(150) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO puntos_venta (numero, descripcion) VALUES (1, 'Punto de venta principal');

CREATE TABLE IF NOT EXISTS comprobantes_fiscales (
    id_comprobante_fiscal BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta BIGINT UNSIGNED NOT NULL,
    id_punto_venta BIGINT UNSIGNED NOT NULL,
    id_tipo_comprobante SMALLINT UNSIGNED NOT NULL,
    numero BIGINT UNSIGNED NULL,
    fecha DATE NOT NULL,
    concepto TINYINT UNSIGNED NOT NULL DEFAULT 1,
    doc_tipo SMALLINT UNSIGNED NOT NULL,
    doc_nro VARCHAR(20) NOT NULL,
    condicion_iva_receptor_id SMALLINT UNSIGNED NOT NULL,
    imp_total DECIMAL(15,2) NOT NULL,
    imp_tot_conc DECIMAL(15,2) NOT NULL DEFAULT 0,
    imp_neto DECIMAL(15,2) NOT NULL,
    imp_op_ex DECIMAL(15,2) NOT NULL DEFAULT 0,
    imp_iva DECIMAL(15,2) NOT NULL DEFAULT 0,
    imp_trib DECIMAL(15,2) NOT NULL DEFAULT 0,
    moneda CHAR(3) NOT NULL DEFAULT 'PES',
    cotizacion DECIMAL(15,6) NOT NULL DEFAULT 1,
    paga_misma_moneda TINYINT(1) NULL,
    fch_serv_desde DATE NULL,
    fch_serv_hasta DATE NULL,
    fch_vto_pago DATE NULL,
    estado_fiscal ENUM('Pendiente','Enviando','Autorizado','Rechazado','ErrorComunicacion') NOT NULL DEFAULT 'Pendiente',
    cae VARCHAR(20) NULL,
    cae_fch_vto DATE NULL,
    resultado_arca CHAR(1) NULL,
    observaciones JSON NULL,
    errores JSON NULL,
    id_comprobante_asociado BIGINT UNSIGNED NULL,
    asoc_tipo SMALLINT UNSIGNED NULL,
    asoc_pto_vta SMALLINT UNSIGNED NULL,
    asoc_nro BIGINT UNSIGNED NULL,
    asoc_fecha DATE NULL,
    asoc_cuit VARCHAR(11) NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_comprobantes_venta FOREIGN KEY (id_venta) REFERENCES ventas (id_venta),
    CONSTRAINT fk_comprobantes_punto FOREIGN KEY (id_punto_venta) REFERENCES puntos_venta (id_punto_venta),
    CONSTRAINT fk_comprobantes_tipo FOREIGN KEY (id_tipo_comprobante) REFERENCES tipos_comprobante (id_tipo_comprobante),
    CONSTRAINT fk_comprobantes_asociado FOREIGN KEY (id_comprobante_asociado) REFERENCES comprobantes_fiscales (id_comprobante_fiscal),
    CONSTRAINT fk_comprobantes_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
    CONSTRAINT chk_comprobantes_importes CHECK (imp_total >= 0 AND imp_neto >= 0 AND imp_iva >= 0 AND imp_tot_conc >= 0 AND imp_op_ex >= 0 AND imp_trib >= 0),
    UNIQUE KEY uq_comprobante_numero (id_punto_venta, id_tipo_comprobante, numero),
    INDEX idx_comprobantes_venta (id_venta),
    INDEX idx_comprobantes_estado (estado_fiscal),
    INDEX idx_comprobantes_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comprobante_iva (
    id_comprobante_iva BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_comprobante_fiscal BIGINT UNSIGNED NOT NULL,
    alic_iva_id SMALLINT UNSIGNED NOT NULL,
    base_imp DECIMAL(15,2) NOT NULL,
    importe DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_comprobante_iva FOREIGN KEY (id_comprobante_fiscal) REFERENCES comprobantes_fiscales (id_comprobante_fiscal),
    CONSTRAINT chk_comprobante_iva_importes CHECK (base_imp >= 0 AND importe >= 0),
    INDEX idx_comprobante_iva (id_comprobante_fiscal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permisos (nombre, descripcion) VALUES
    ('facturacion.ver', 'Consultar comprobantes fiscales'),
    ('facturacion.emitir', 'Emitir comprobantes fiscales'),
    ('facturacion.notas', 'Emitir notas de credito y debito');

INSERT IGNORE INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r
JOIN permisos p ON p.nombre IN ('facturacion.ver', 'facturacion.emitir', 'facturacion.notas')
WHERE r.nombre = 'Administrador';



-- ===== 005_produccion_estimada.sql =====

-- Mini ERP Agricola - produccion estimada del ciclo de cultivo
-- Migracion aditiva: permite calcular el costo provisional de los lotes.

SET NAMES utf8mb4;

ALTER TABLE ciclos_cultivo
    ADD COLUMN cantidad_estimada DECIMAL(15,3) NULL AFTER fecha_fin,
    ADD COLUMN unidad_estimada VARCHAR(30) NULL AFTER cantidad_estimada,
    ADD CONSTRAINT chk_ciclos_cantidad_estimada CHECK (cantidad_estimada IS NULL OR cantidad_estimada > 0);



-- ===== 006_detalle_venta_unidad.sql =====

-- Mini ERP Agricola - unidad de medida historica en el detalle de venta
-- Migracion aditiva: conserva la unidad usada al confirmar la venta.

SET NAMES utf8mb4;

ALTER TABLE detalles_ventas
    ADD COLUMN unidad_medida VARCHAR(30) NULL AFTER cantidad;

UPDATE detalles_ventas d
INNER JOIN productos p ON p.id_producto = d.id_producto
SET d.unidad_medida = p.unidad_medida
WHERE d.unidad_medida IS NULL;



-- ===== 007_facturacion_diagnostico_e_intentos.sql =====

-- Mini ERP Agricola - facturacion electronica: diagnostico, intentos y estados
-- Migracion aditiva. No modifica comprobantes historicos.

SET NAMES utf8mb4;

ALTER TABLE comprobantes_fiscales
    MODIFY COLUMN estado_fiscal ENUM('Pendiente','Enviando','Autorizado','Rechazado','ErrorComunicacion','ResultadoIncierto','RequiereRevision') NOT NULL DEFAULT 'Pendiente';

CREATE TABLE IF NOT EXISTS facturacion_solicitudes (
    id_solicitud BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_comprobante_fiscal BIGINT UNSIGNED NOT NULL,
    numero_intento SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    operacion ENUM('ultimo_autorizado','solicitar_cae','consultar') NOT NULL,
    ambiente ENUM('mock','homologacion','produccion') NOT NULL,
    adaptador_version VARCHAR(30) NOT NULL,
    estado ENUM('iniciado','completado','error') NOT NULL DEFAULT 'iniciado',
    codigo_resultado VARCHAR(60) NULL,
    correlacion CHAR(32) NOT NULL,
    observaciones JSON NULL,
    errores JSON NULL,
    detalle_tecnico JSON NULL,
    inicio DATETIME NOT NULL,
    fin DATETIME NULL,
    duracion_ms INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_solicitudes_comprobante FOREIGN KEY (id_comprobante_fiscal) REFERENCES comprobantes_fiscales (id_comprobante_fiscal),
    INDEX idx_solicitudes_comprobante (id_comprobante_fiscal),
    INDEX idx_solicitudes_correlacion (correlacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permisos (nombre, descripcion) VALUES
    ('facturacion.administrar', 'Administrar la configuracion y el diagnostico de facturacion electronica');

INSERT IGNORE INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r
JOIN permisos p ON p.nombre = 'facturacion.administrar'
WHERE r.nombre = 'Administrador';



-- ===== 008_datos_emisor.sql =====

-- Mini ERP Agricola - datos del emisor y snapshot historico en comprobantes
-- Migracion aditiva. No modifica reglas comerciales ni financieras existentes.

SET NAMES utf8mb4;

-- Datos del emisor: fila unica garantizada en la base (id_emisor = 1 + CHECK).
CREATE TABLE IF NOT EXISTS datos_emisor (
    id_emisor TINYINT UNSIGNED NOT NULL DEFAULT 1,
    razon_social VARCHAR(200) NULL,
    cuit VARCHAR(11) NULL,
    domicilio VARCHAR(255) NULL,
    condicion_iva SMALLINT UNSIGNED NULL,
    ingresos_brutos VARCHAR(50) NULL,
    inicio_actividades DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_emisor),
    CONSTRAINT chk_datos_emisor_unico CHECK (id_emisor = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO datos_emisor (id_emisor) VALUES (1);

-- Snapshot del emisor por comprobante (inmutable). NULL en comprobantes previos.
ALTER TABLE comprobantes_fiscales
    ADD COLUMN emisor_razon_social VARCHAR(200) NULL AFTER asoc_cuit,
    ADD COLUMN emisor_cuit VARCHAR(11) NULL AFTER emisor_razon_social,
    ADD COLUMN emisor_domicilio VARCHAR(255) NULL AFTER emisor_cuit,
    ADD COLUMN emisor_condicion_iva SMALLINT UNSIGNED NULL AFTER emisor_domicilio,
    ADD COLUMN emisor_ingresos_brutos VARCHAR(50) NULL AFTER emisor_condicion_iva,
    ADD COLUMN emisor_inicio_actividades DATE NULL AFTER emisor_ingresos_brutos;


