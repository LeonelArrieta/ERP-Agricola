<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\HtmlResponse;
use App\Core\HttpException;
use App\Core\Navigation;
use App\Core\QueryFilters;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Repositories\UnitRepository;
use App\Repositories\WorkerRepository;
use App\Services\CropCycleService;
use App\Services\CropTaskService;
use App\Services\HarvestService;
use App\Services\InventoryCatalogService;
use App\Services\ProductionCatalogService;

final class ProductionWebController
{
    public function __construct(
        private readonly CropCycleService $cycles,
        private readonly CropTaskService $tasks,
        private readonly HarvestService $harvests,
        private readonly ProductionCatalogService $productionCatalog,
        private readonly InventoryCatalogService $inventoryCatalog,
        private readonly UnitRepository $units,
        private readonly WorkerRepository $workers,
        private readonly Authorization $authorization,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly ViewRenderer $views,
    ) {
    }

    public function index(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $filters = [
            'search' => QueryFilters::text($request->query['search'] ?? null),
            'estado' => QueryFilters::enum($request->query['estado'] ?? null, ['Planificado', 'EnCurso', 'Cosechado', 'Cancelado'], 'estado'),
        ] + QueryFilters::dateRange($request->query);
        $result = $this->cycles->list($page, $perPage, $filters);

        return $this->render('production/index', [
            'title' => 'Ciclos de cultivo | ERP Agricola',
            'active' => 'ciclos-cultivo',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.crear');

        return $this->form([], null);
    }

    public function store(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));

        try {
            $cycle = $this->cycles->create($request->body, $actor);
        } catch (HttpException $exception) {
            if (!in_array($exception->status, [409, 422], true)) {
                throw $exception;
            }

            return $this->form($request->body, $exception->getMessage(), $this->errors($exception));
        }

        $this->session->flash('success', 'Ciclo de cultivo creado correctamente.');

        return new RedirectResponse(url('app/ciclos-cultivo/' . (int) $cycle['id_ciclo_cultivo']));
    }

    public function show(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.ver');
        $id = $this->id($request);

        return $this->render('production/show', [
            'title' => 'Ciclo de cultivo | ERP Agricola',
            'active' => 'ciclos-cultivo',
            'cycle' => $this->cycles->get($id),
            'tasks' => $this->tasks->list($id),
            'products' => $this->inventoryCatalog->list('producto', 1, 100, true, '')['items'],
            'insumos' => $this->inventoryCatalog->list('insumo', 1, 100, true, '')['items'],
            'taskTypes' => $this->productionCatalog->list('tarea', 1, 100, true, '')['items'],
            'units' => $this->units->activeOptions(),
            'workers' => $this->workers->activeOptions(),
            'taskCosts' => $this->tasks->costsByCycle($id),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        return $this->transition($id, 'EnCurso', $actor);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.cancelar');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        return $this->transition($id, 'Cancelado', $actor);
    }

    public function harvest(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->harvests->create($id, $request->body, $actor);
            $this->session->flash('success', 'Cosecha registrada y lote provisional generado.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ciclos-cultivo/' . $id));
    }

    public function consolidate(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->harvests->consolidate($id, $actor);
            $this->session->flash('success', 'Ciclo consolidado. Costo unitario: ' . number_format((float) $result['costo_unitario'], 2, ',', '.') . '. Los lotes quedaron con costo definitivo.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ciclos-cultivo/' . $id));
    }

    public function updateEstimate(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $this->cycles->updateEstimate($id, $request->body, $actor);
            $this->session->flash('success', 'Produccion estimada actualizada.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ciclos-cultivo/' . $id));
    }

    public function taskCreate(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $id = $this->id($request);

        try {
            $result = $this->tasks->createWithResources($id, $request->body, $actor);
            $summary = $result['resumen'];
            $message = 'Tarea ' . $result['estado'] . ' creada.';
            if ($summary['consumos'] > 0 || $summary['trabajos'] > 0) {
                $message .= ' Insumos: ' . $summary['consumos'] . ', mano de obra: ' . $summary['trabajos']
                    . '. Costo: $' . number_format((float) ($summary['costos']['costo_total'] ?? 0), 2, ',', '.') . '.';
            }
            $this->session->flash('success', $message);
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ciclos-cultivo/' . $id));
    }

    public function taskTransition(Request $request, string $target): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $taskId = $this->id($request);

        try {
            $this->tasks->transition($taskId, $target, $actor);
            $this->session->flash('success', 'Estado de la tarea actualizado.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/tareas-cultivo/' . $taskId));
    }

    public function consume(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $taskId = $this->id($request);

        try {
            $result = $this->tasks->consume($taskId, $request->body, $actor);
            $this->session->flash('success', 'Consumo registrado. Costo histórico: $' . number_format((float) $result['costo_total'], 2, ',', '.') . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/tareas-cultivo/' . $taskId));
    }

    public function taskWork(Request $request): RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $actor = $this->authorization->requirePermission('produccion.crear');
        $this->csrf->validate($this->token($request));
        $taskId = $this->id($request);

        try {
            $result = $this->tasks->registerWork($taskId, $request->body, $actor);
            $this->session->flash('success', 'Trabajo registrado. Costo de mano de obra: $' . number_format((float) $result['costo_total'], 2, ',', '.') . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/tareas-cultivo/' . $taskId));
    }

    public function tasksIndex(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.ver');

        $page = max(1, (int) ($request->query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($request->query['per_page'] ?? 20)));
        $state = null;
        if (isset($request->query['estado']) && in_array($request->query['estado'], ['Planificada', 'EnCurso', 'Completada', 'Cancelada'], true)) {
            $state = (string) $request->query['estado'];
        }
        $search = is_string($request->query['search'] ?? null) ? trim($request->query['search']) : '';
        $result = $this->tasks->listAll($page, $perPage, $state, $search);

        return $this->render('production/tasks-index', [
            'title' => 'Tareas de cultivo | ERP Agricola',
            'active' => 'tareas-cultivo',
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'filters' => ['estado' => (string) ($request->query['estado'] ?? ''), 'search' => $search],
        ]);
    }

    public function taskShow(Request $request): HtmlResponse|RedirectResponse
    {
        $redirect = $this->redirectIfAnonymous();
        if ($redirect !== null) {
            return $redirect;
        }
        $this->authorization->requirePermission('produccion.ver');
        $data = $this->tasks->detail($this->id($request));

        return $this->render('production/task-show', [
            'title' => 'Tarea de cultivo | ERP Agricola',
            'active' => 'tareas-cultivo',
            'task' => $data['tarea'],
            'consumptions' => $data['consumos'],
            'trabajos' => $data['trabajos'],
            'costos' => $data['costos'],
            'insumos' => $this->inventoryCatalog->list('insumo', 1, 100, true, '')['items'],
            'units' => $this->units->activeOptions(),
            'workers' => $this->workers->activeOptions(),
        ]);
    }

    private function transition(int $id, string $target, int $actor): RedirectResponse
    {
        try {
            $this->cycles->transition($id, $target, $actor);
            $this->session->flash('success', 'Estado del ciclo actualizado a ' . $target . '.');
        } catch (HttpException $exception) {
            $this->flashException($exception);
        }

        return new RedirectResponse(url('app/ciclos-cultivo/' . $id));
    }

    private function flashException(HttpException $exception): void
    {
        if (!in_array($exception->status, [404, 409, 422], true)) {
            throw $exception;
        }
        $this->session->flash('error', $exception->getMessage());
    }

    private function form(array $values, ?string $error, array $errors = []): HtmlResponse
    {
        return $this->render('production/form', [
            'title' => 'Nuevo ciclo de cultivo | ERP Agricola',
            'active' => 'ciclos-cultivo',
            'values' => $values,
            'error' => $error,
            'errors' => $errors,
            'cultivos' => $this->productionCatalog->list('cultivo', 1, 100, true, '')['items'],
            'parcelas' => $this->productionCatalog->list('parcela', 1, 100, true, '')['items'],
            'units' => $this->units->activeOptions(),
        ]);
    }

    private function errors(HttpException $exception): array
    {
        $field = $exception->details['field'] ?? null;

        return is_string($field) ? [$field => $exception->getMessage()] : ['_form' => $exception->getMessage()];
    }

    private function render(string $view, array $data): HtmlResponse
    {
        $permissions = $this->authorization->permissions();

        return new HtmlResponse($this->views->render($view, $data + [
            'user' => $this->authorization->user(),
            'permissions' => $permissions,
            'navigation' => Navigation::groups($permissions),
            'csrfToken' => $this->csrf->token(),
            'flashes' => $this->session->pullFlashes(),
        ]));
    }

    private function redirectIfAnonymous(): ?RedirectResponse
    {
        return $this->session->userId() === null ? new RedirectResponse(url('login')) : null;
    }

    private function id(Request $request): int
    {
        $id = $request->params['id'] ?? null;
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            throw new HttpException('VALIDATION_ERROR', 'El identificador no es valido.', 422);
        }

        return (int) $id;
    }

    private function token(Request $request): ?string
    {
        $token = $request->body['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        return is_string($token) ? $token : null;
    }
}
