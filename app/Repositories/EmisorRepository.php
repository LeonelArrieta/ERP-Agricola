<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EmisorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(): ?array
    {
        $query = $this->pdo->query('SELECT * FROM datos_emisor WHERE id_emisor = 1 LIMIT 1');
        $value = $query->fetch();

        return is_array($value) ? $value : null;
    }

    public function save(array $data): void
    {
        $fields = ['razon_social', 'cuit', 'domicilio', 'condicion_iva', 'ingresos_brutos', 'inicio_actividades'];
        $data = array_intersect_key($data, array_flip($fields));

        $this->pdo->exec('INSERT IGNORE INTO datos_emisor (id_emisor) VALUES (1)');

        $assignments = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, array_keys($data)));
        $query = $this->pdo->prepare('UPDATE datos_emisor SET ' . $assignments . ' WHERE id_emisor = 1');
        $query->execute($data);
    }
}
