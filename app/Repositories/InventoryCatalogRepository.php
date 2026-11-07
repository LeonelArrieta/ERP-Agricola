<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InventoryCatalogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(string $type, int $offset, int $limit, ?bool $active, string $search): array
    {
        $table = $this->table($type);
        $id = $this->id($type);
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
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . $condition);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $query = $this->pdo->prepare('SELECT * FROM ' . $table . $condition . ' ORDER BY ' . $id . ' DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public function find(string $type, int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM ' . $this->table($type) . ' WHERE ' . $this->id($type) . ' = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function create(string $type, array $data): int
    {
        $table = $this->table($type);
        $fields = $type === 'producto'
            ? ['nombre', 'descripcion', 'unidad_medida', 'porcentaje_alicuota']
            : ['nombre', 'descripcion', 'unidad_medida'];
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $query = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $placeholders . ')');
        $query->execute(array_intersect_key($data, array_flip($fields)));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $type, int $id, array $data): void
    {
        $fields = $type === 'producto'
            ? ['nombre', 'descripcion', 'unidad_medida', 'porcentaje_alicuota']
            : ['nombre', 'descripcion', 'unidad_medida'];
        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, $fields));
        $query = $this->pdo->prepare('UPDATE ' . $this->table($type) . ' SET ' . $assignments . ' WHERE ' . $this->id($type) . ' = :id');
        $query->execute(array_intersect_key($data, array_flip($fields)) + ['id' => $id]);
    }

    public function deactivate(string $type, int $id): void
    {
        $query = $this->pdo->prepare('UPDATE ' . $this->table($type) . ' SET activo = 0 WHERE ' . $this->id($type) . ' = :id');
        $query->execute(['id' => $id]);
    }

    private function table(string $type): string
    {
        return match ($type) {
            'producto' => 'productos',
            'insumo' => 'insumos',
            default => throw new \InvalidArgumentException('Catalogo no permitido.'),
        };
    }

    private function id(string $type): string
    {
        return $type === 'producto' ? 'id_producto' : 'id_insumo';
    }
}
