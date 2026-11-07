<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Infrastructure\Arca\ArcaCatalogs;
use App\Repositories\EmisorRepository;
use PDO;

final class EmisorService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EmisorRepository $repository,
    ) {
    }

    public function get(): array
    {
        return $this->repository->find() ?? [
            'id_emisor' => 1,
            'razon_social' => null,
            'cuit' => null,
            'domicilio' => null,
            'condicion_iva' => null,
            'ingresos_brutos' => null,
            'inicio_actividades' => null,
        ];
    }

    public function update(array $input, int $actor): array
    {
        $before = $this->get();
        $data = $this->validate($input);

        $this->pdo->beginTransaction();
        try {
            $this->repository->save($data);
            $this->audit($actor, $before, $data);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        return $this->get();
    }

    public function isComplete(?array $emisor): bool
    {
        if ($emisor === null) {
            return false;
        }

        return trim((string) ($emisor['razon_social'] ?? '')) !== ''
            && preg_match('/^\d{11}$/', (string) ($emisor['cuit'] ?? '')) === 1
            && trim((string) ($emisor['domicilio'] ?? '')) !== ''
            && isset(ArcaCatalogs::CONDICIONES_IVA[(int) ($emisor['condicion_iva'] ?? 0)]);
    }

    private function validate(array $input): array
    {
        $razon = trim((string) ($input['razon_social'] ?? ''));
        if ($razon === '' || mb_strlen($razon) > 200) {
            throw new HttpException('VALIDATION_ERROR', 'La razon social es obligatoria (hasta 200 caracteres).', 422, ['field' => 'razon_social']);
        }

        $cuit = preg_replace('/\D/', '', (string) ($input['cuit'] ?? ''));
        if (!is_string($cuit) || strlen($cuit) !== 11) {
            throw new HttpException('VALIDATION_ERROR', 'El CUIT debe tener 11 digitos.', 422, ['field' => 'cuit']);
        }

        $domicilio = trim((string) ($input['domicilio'] ?? ''));
        if ($domicilio === '' || mb_strlen($domicilio) > 255) {
            throw new HttpException('VALIDATION_ERROR', 'El domicilio es obligatorio (hasta 255 caracteres).', 422, ['field' => 'domicilio']);
        }

        $condicion = (int) ($input['condicion_iva'] ?? 0);
        if (!isset(ArcaCatalogs::CONDICIONES_IVA[$condicion])) {
            throw new HttpException('VALIDATION_ERROR', 'La condicion frente al IVA no es valida.', 422, ['field' => 'condicion_iva']);
        }

        $ingresosBrutos = trim((string) ($input['ingresos_brutos'] ?? ''));
        if (mb_strlen($ingresosBrutos) > 50) {
            throw new HttpException('VALIDATION_ERROR', 'Los ingresos brutos no pueden superar los 50 caracteres.', 422, ['field' => 'ingresos_brutos']);
        }

        $inicio = trim((string) ($input['inicio_actividades'] ?? ''));
        if ($inicio !== '' && date_create_from_format('Y-m-d', $inicio) === false) {
            throw new HttpException('VALIDATION_ERROR', 'La fecha de inicio de actividades no es valida.', 422, ['field' => 'inicio_actividades']);
        }

        return [
            'razon_social' => $razon,
            'cuit' => $cuit,
            'domicilio' => $domicilio,
            'condicion_iva' => $condicion,
            'ingresos_brutos' => $ingresosBrutos === '' ? null : $ingresosBrutos,
            'inicio_actividades' => $inicio === '' ? null : $inicio,
        ];
    }

    private function audit(int $actor, array $before, array $after): void
    {
        $query = $this->pdo->prepare(
            'INSERT INTO registros_auditoria (id_usuario, accion, entidad, id_entidad, valores_anteriores, valores_nuevos)
             VALUES (:user_id, :action, :entity, :entity_id, :before, :after)'
        );
        $query->execute([
            'user_id' => $actor,
            'action' => 'Modificar',
            'entity' => 'datos_emisor',
            'entity_id' => 1,
            'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
        ]);
    }

    private function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
