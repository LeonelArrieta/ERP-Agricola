<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(int $offset, int $limit, ?bool $active, string $search): array
    {
        $where = [];
        $params = [];
        if ($active !== null) {
            $where[] = 'activo = :active';
            $params['active'] = $active ? 1 : 0;
        }
        if ($search !== '') {
            $where[] = '(nombre LIKE :search_name OR apellido LIKE :search_lastname OR documento LIKE :search_document)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_lastname'] = '%' . $search . '%';
            $params['search_document'] = '%' . $search . '%';
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM trabajadores' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare('SELECT * FROM trabajadores' . $condition . ' ORDER BY apellido, nombre LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM trabajadores WHERE id_trabajador = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $worker = $query->fetch();

        return is_array($worker) ? $worker : null;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($id === null) {
            $query = $this->pdo->prepare('INSERT INTO trabajadores (nombre, apellido, documento, telefono, costo_hora_actual) VALUES (:nombre, :apellido, :documento, :telefono, :costo_hora)');
            $query->execute($data);

            return (int) $this->pdo->lastInsertId();
        }

        $query = $this->pdo->prepare('UPDATE trabajadores SET nombre = :nombre, apellido = :apellido, documento = :documento, telefono = :telefono, costo_hora_actual = :costo_hora WHERE id_trabajador = :id');
        $query->execute($data + ['id' => $id]);

        return $id;
    }

    public function deactivate(int $id): void
    {
        $query = $this->pdo->prepare('UPDATE trabajadores SET activo = 0 WHERE id_trabajador = :id');
        $query->execute(['id' => $id]);
    }

    public function activate(int $id): void
    {
        $query = $this->pdo->prepare('UPDATE trabajadores SET activo = 1 WHERE id_trabajador = :id');
        $query->execute(['id' => $id]);
    }

    public function activeOptions(): array
    {
        return $this->pdo->query('SELECT id_trabajador, nombre, apellido, costo_hora_actual FROM trabajadores WHERE activo = 1 ORDER BY apellido, nombre')->fetchAll();
    }
}
