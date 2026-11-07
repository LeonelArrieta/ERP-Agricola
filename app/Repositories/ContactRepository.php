<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ContactRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table,
        private readonly string $idColumn,
    ) {
        if (!in_array($table, ['clientes', 'proveedores'], true)) {
            throw new \InvalidArgumentException('Tabla de contacto no permitida.');
        }
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
            $where[] = '(nombre LIKE :search_nombre OR apellido LIKE :search_apellido OR razon_social LIKE :search_razon_social OR documento LIKE :search_documento)';
            $params['search_nombre'] = '%' . $search . '%';
            $params['search_apellido'] = '%' . $search . '%';
            $params['search_razon_social'] = '%' . $search . '%';
            $params['search_documento'] = '%' . $search . '%';
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $this->table . $condition);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $columns = $this->idColumn . ', nombre, apellido, razon_social, documento, telefono, email, direccion, activo, created_at, updated_at';
        if ($this->hasFiscalFields()) {
            $columns = $this->idColumn . ', nombre, apellido, razon_social, documento, doc_tipo, doc_nro, condicion_iva_receptor_id, telefono, email, direccion, activo, created_at, updated_at';
        }
        $query = $this->pdo->prepare(
            'SELECT ' . $columns . '
             FROM ' . $this->table . $condition . ' ORDER BY ' . $this->idColumn . ' DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' WHERE ' . $this->idColumn . ' = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $result = $query->fetch();
        return is_array($result) ? $result : null;
    }

    public function create(array $data): int
    {
        $fields = $this->fields();
        $query = $this->pdo->prepare(
            'INSERT INTO ' . $this->table . ' (' . implode(', ', $fields) . ')
             VALUES (:' . implode(', :', $fields) . ')'
        );
        $query->execute(array_intersect_key($data, array_flip($fields)));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = $this->fields();
        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, $fields));
        $query = $this->pdo->prepare(
            'UPDATE ' . $this->table . ' SET ' . $assignments . ' WHERE ' . $this->idColumn . ' = :id'
        );
        $query->execute(array_intersect_key($data, array_flip($fields)) + ['id' => $id]);
    }

    private function hasFiscalFields(): bool
    {
        return $this->table === 'clientes';
    }

    private function fields(): array
    {
        return $this->hasFiscalFields()
            ? ['nombre', 'apellido', 'razon_social', 'documento', 'doc_tipo', 'doc_nro', 'condicion_iva_receptor_id', 'telefono', 'email', 'direccion']
            : ['nombre', 'apellido', 'razon_social', 'documento', 'telefono', 'email', 'direccion'];
    }

    public function deactivate(int $id): void
    {
        $query = $this->pdo->prepare('UPDATE ' . $this->table . ' SET activo = 0 WHERE ' . $this->idColumn . ' = :id');
        $query->execute(['id' => $id]);
    }
}
