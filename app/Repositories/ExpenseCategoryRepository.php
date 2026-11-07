<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ExpenseCategoryRepository
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
            $where[] = '(nombre LIKE :search_nombre OR descripcion LIKE :search_descripcion)';
            $params['search_nombre'] = '%' . $search . '%';
            $params['search_descripcion'] = '%' . $search . '%';
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM categorias_gastos' . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare('SELECT * FROM categorias_gastos' . $condition . ' ORDER BY id_categoria_gasto DESC LIMIT :limit OFFSET :offset');
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
        $query = $this->pdo->prepare('SELECT * FROM categorias_gastos WHERE id_categoria_gasto = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $category = $query->fetch();

        return is_array($category) ? $category : null;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($id === null) {
            $query = $this->pdo->prepare('INSERT INTO categorias_gastos (nombre, descripcion) VALUES (:nombre, :descripcion)');
            $query->execute(['nombre' => $data['nombre'], 'descripcion' => $data['descripcion']]);

            return (int) $this->pdo->lastInsertId();
        }

        $query = $this->pdo->prepare('UPDATE categorias_gastos SET nombre = :nombre, descripcion = :descripcion WHERE id_categoria_gasto = :id');
        $query->execute(['nombre' => $data['nombre'], 'descripcion' => $data['descripcion'], 'id' => $id]);

        return $id;
    }

    public function deactivate(int $id): void
    {
        $query = $this->pdo->prepare('UPDATE categorias_gastos SET activo = 0 WHERE id_categoria_gasto = :id');
        $query->execute(['id' => $id]);
    }

    public function activate(int $id): void
    {
        $query = $this->pdo->prepare('UPDATE categorias_gastos SET activo = 1 WHERE id_categoria_gasto = :id');
        $query->execute(['id' => $id]);
    }

    public function activeOptions(): array
    {
        return $this->pdo->query('SELECT id_categoria_gasto, nombre FROM categorias_gastos WHERE activo = 1 ORDER BY nombre')->fetchAll();
    }
}
