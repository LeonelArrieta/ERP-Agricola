<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

$basePath = dirname(__DIR__, 2);
require $basePath . '/vendor/autoload.php';

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$firstName = $argv[3] ?? 'Admin';
$lastName = $argv[4] ?? 'ERP';

if (!is_string($email) || !is_string($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php database/scripts/create_admin_user.php <email> <password> [nombre] [apellido]\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "La contrasena debe tener al menos 8 caracteres.\n");
    exit(1);
}

$config = new Config($basePath);
$pdo = (new Database($config))->connection();

$roleId = (int) $pdo->query("SELECT id_rol FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
if ($roleId < 1) {
    fwrite(STDERR, "No existe el rol Administrador. Ejecute database/schema.sql.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$normalizedEmail = strtolower($email);

$existing = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email');
$existing->execute(['email' => $normalizedEmail]);
$userId = (int) $existing->fetchColumn();

if ($userId > 0) {
    $update = $pdo->prepare('UPDATE usuarios SET id_rol = :role, nombre = :first, apellido = :last, password_hash = :hash, activo = 1 WHERE id_usuario = :id');
    $update->execute(['role' => $roleId, 'first' => $firstName, 'last' => $lastName, 'hash' => $hash, 'id' => $userId]);
    fwrite(STDOUT, "Usuario actualizado: {$normalizedEmail} (id {$userId})\n");
    exit(0);
}

$insert = $pdo->prepare('INSERT INTO usuarios (id_rol, nombre, apellido, email, password_hash, activo) VALUES (:role, :first, :last, :email, :hash, 1)');
$insert->execute(['role' => $roleId, 'first' => $firstName, 'last' => $lastName, 'email' => $normalizedEmail, 'hash' => $hash]);
fwrite(STDOUT, 'Usuario administrador creado: ' . $normalizedEmail . ' (id ' . $pdo->lastInsertId() . ")\n");
