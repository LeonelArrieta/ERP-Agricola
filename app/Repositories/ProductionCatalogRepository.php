<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProductionCatalogRepository
{
    private const TABLES = [
        'trabajador' => ['trabajadores', 'id_trabajador'],
        'cultivo' => ['cultivos', 'id_cultivo'],
        'parcela' => ['parcelas', 'id_parcela'],
        'tarea' => ['tareas', 'id_tarea'],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(string $type, int $offset, int $limit, ?bool $active, string $search): array
    {
        [$table, $id] = $this->definition($type);
        $where = [];
        $params = [];
        if ($active !== null) {
            $where[] = 'activo = :active';
            $params['active'] = $active ? 1 : 0;
        }
        if ($search !== '') {
            if ($type === 'trabajador') {
                $where[] = '(nombre LIKE :search_nombre OR apellido LIKE :search_apellido OR documento LIKE :search_documento)';
                $params['search_nombre'] = '%' . $search . '%';
                $params['search_apellido'] = '%' . $search . '%';
                $params['search_documento'] = '%' . $search . '%';
            } else {
                $where[] = '(nombre LIKE :search_nombre OR descripcion LIKE :search_descripcion)';
                $params['search_nombre'] = '%' . $search . '%';
                $params['search_descripcion'] = '%' . $search . '%';
            }
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . $condition);
        $count->execute($params);
        $query = $this->pdo->prepare('SELECT * FROM ' . $table . $condition . ' ORDER BY ' . $id . ' DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function find(string $type, int $id): ?array
    {
        [$table, $idColumn] = $this->definition($type);
        $query = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE ' . $idColumn . ' = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $value = $query->fetch();
        return is_array($value) ? $value : null;
    }

    public function save(string $type, array $data, ?int $id = null): int
    {
        [$table, $idColumn] = $this->definition($type);
        $fields = $this->fields($type);
        if ($id === null) {
            $query = $this->pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (:' . implode(', :', $fields) . ')');
            $query->execute(array_intersect_key($data, array_flip($fields)));
            return (int) $this->pdo->lastInsertId();
        }
        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, $fields));
        $query = $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $assignments . ' WHERE ' . $idColumn . ' = :id');
        $query->execute(array_intersect_key($data, array_flip($fields)) + ['id' => $id]);
        return $id;
    }

    public function deactivate(string $type, int $id): void
    {
        [$table, $idColumn] = $this->definition($type);
        $query = $this->pdo->prepare('UPDATE ' . $table . ' SET activo = 0 WHERE ' . $idColumn . ' = :id');
        $query->execute(['id' => $id]);
    }

    public function activate(string $type, int $id): void
    {
        [$table, $idColumn] = $this->definition($type);
        $query = $this->pdo->prepare('UPDATE ' . $table . ' SET activo = 1 WHERE ' . $idColumn . ' = :id');
        $query->execute(['id' => $id]);
    }

    public function hasActiveCycle(int $plotId): bool
    {
        $query = $this->pdo->prepare("SELECT 1 FROM ciclos_cultivo WHERE id_parcela = :id AND estado = 'EnCurso' LIMIT 1");
        $query->execute(['id' => $plotId]);
        return $query->fetchColumn() !== false;
    }

    private function definition(string $type): array
    {
        if (!isset(self::TABLES[$type])) {
            throw new \InvalidArgumentException('Catalogo de produccion no permitido.');
        }
        return self::TABLES[$type];
    }

    private function fields(string $type): array
    {
        return match ($type) {
            'trabajador' => ['nombre', 'apellido', 'documento', 'telefono', 'costo_hora_actual'],
            'parcela' => ['nombre', 'descripcion', 'superficie', 'unidad_superficie'],
            default => ['nombre', 'descripcion'],
        };
    }
}
