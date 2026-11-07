<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BillingController;
use App\Controllers\ContactController;
use App\Controllers\InventoryCatalogController;
use App\Controllers\InventoryController;
use App\Controllers\CropCycleController;
use App\Controllers\CropTaskController;
use App\Controllers\HarvestController;
use App\Controllers\PurchaseController;
use App\Controllers\SaleController;
use App\Controllers\FinanceController;
use App\Controllers\ExpenseController;
use App\Controllers\ExpenseCategoryController;
use App\Controllers\CancellationController;
use App\Controllers\ReversalController;
use App\Controllers\PurchaseCancellationController;
use App\Controllers\WebController;
use App\Controllers\Web\ArcaDiagnosticWebController;
use App\Controllers\Web\ContactWebController;
use App\Controllers\Web\BillingWebController;
use App\Controllers\Web\EmisorWebController;
use App\Controllers\Web\ExpenseWebController;
use App\Controllers\Web\ExpenseCategoryWebController;
use App\Controllers\Web\FinanceWebController;
use App\Controllers\Web\InventoryCatalogWebController;
use App\Controllers\Web\InventoryWebController;
use App\Controllers\Web\ProductionCatalogWebController;
use App\Controllers\Web\ProductionWebController;
use App\Controllers\Web\PointOfSaleWebController;
use App\Controllers\Web\PurchaseWebController;
use App\Controllers\Web\SaleWebController;
use App\Controllers\Web\UnitWebController;
use App\Controllers\Web\WorkerWebController;
use App\Controllers\ProductionCatalogController;
use App\Controllers\HealthController;
use App\Controllers\PointOfSaleController;
use App\Controllers\UnitController;
use App\Controllers\UserController;
use App\Core\Authorization;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\HtmlResponse;
use App\Core\FileResponse;
use App\Core\RedirectResponse;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Infrastructure\Arca\ArcaConfigurationException;
use App\Infrastructure\Arca\ArcaFactory;
use App\Infrastructure\Pdf\ComprobantePdfRenderer;
use App\Infrastructure\Qr\QrSvgRenderer;
use App\Domain\Facturacion\QrFiscal;
use App\Repositories\UserRepository;
use App\Repositories\ContactRepository;
use App\Repositories\InventoryCatalogRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\CropCycleRepository;
use App\Repositories\CropTaskRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\HarvestRepository;
use App\Repositories\PurchaseRepository;
use App\Repositories\SaleRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\ExpenseCategoryRepository;
use App\Repositories\FacturacionSolicitudRepository;
use App\Repositories\FiscalRepository;
use App\Repositories\EmisorRepository;
use App\Repositories\CancellationRepository;
use App\Repositories\ReversalRepository;
use App\Repositories\PurchaseCancellationRepository;
use App\Repositories\ProductionCatalogRepository;
use App\Repositories\UnitRepository;
use App\Repositories\WorkerRepository;
use App\Services\ArcaDiagnosticsService;
use App\Services\AuthService;
use App\Services\UserService;
use App\Services\ContactService;
use App\Services\InventoryCatalogService;
use App\Services\InventoryService;
use App\Services\CropCycleService;
use App\Services\CropTaskService;
use App\Services\DashboardService;
use App\Services\HarvestService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\FinanceService;
use App\Services\ExpenseService;
use App\Services\ExpenseCategoryService;
use App\Services\FacturacionService;
use App\Services\EmisorService;
use App\Services\ComprobanteRepresentacionService;
use App\Services\PointOfSaleService;
use App\Services\CancellationService;
use App\Services\ReversalService;
use App\Services\PurchaseCancellationService;
use App\Services\ProductionCatalogService;
use App\Services\UnitService;
use App\Services\WorkerService;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; frame-ancestors \'none\'; base-uri \'self\'; form-action \'self\'');

$config = new Config($basePath);
$logger = new Logger($basePath . '/storage/logs/app.log');
$session = new Session($config);
$session->start();
$csrf = new Csrf($session);
$views = new ViewRenderer($basePath . '/app');
$database = new Database($config);
$request = Request::fromGlobals();
$router = new Router();
$router->get('/health', [HealthController::class, 'show']);
$router->get('/', static fn (Request $request): RedirectResponse => new RedirectResponse(url($session->userId() !== null ? 'dashboard' : 'login')));
$webController = new WebController(
    $session,
    $csrf,
    $views,
    fn (): Authorization => new Authorization($database->connection(), $session),
    fn (): DashboardService => new DashboardService(new DashboardRepository($database->connection())),
);
$router->get('/login', [$webController, 'login']);
$router->get('/dashboard', [$webController, 'dashboard']);
$router->get('/csrf', fn (Request $request): Response => new Response(['token' => $csrf->token()]));
$router->post('/login', fn (Request $request): Response|RedirectResponse => (new AuthController(
    new AuthService($database->connection(), $session),
    new Authorization($database->connection(), $session),
    $session,
    $csrf,
))->login($request));
$router->post('/logout', function (Request $request) use ($database, $session, $csrf): Response|RedirectResponse {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }

    return (new AuthController(
        new AuthService($database->connection(), $session),
        new Authorization($database->connection(), $session),
        $session,
        $csrf,
    ))->logout($request);
});
$router->get('/me', function (Request $request) use ($database, $session, $csrf): Response {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }

    return (new AuthController(
        new AuthService($database->connection(), $session),
        new Authorization($database->connection(), $session),
        $session,
        $csrf,
    ))->me($request);
});
$userController = function () use ($database, $session, $csrf): UserController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }

    $pdo = $database->connection();
    return new UserController(
        new UserService($pdo, new UserRepository($pdo)),
        new Authorization($pdo, $session),
        $csrf,
    );
};
$router->get('/usuarios', fn (Request $request): Response => $userController()->index($request));
$router->get('/usuarios/{id}', fn (Request $request): Response => $userController()->show($request));
$router->post('/usuarios', fn (Request $request): Response => $userController()->store($request));
$router->patch('/usuarios/{id}', fn (Request $request): Response => $userController()->update($request));
$router->post('/usuarios/{id}/desactivar', fn (Request $request): Response => $userController()->deactivate($request));
$contactController = function (string $table, string $idColumn, string $entity, string $permission) use ($database, $session, $csrf): ContactController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new ContactController(
        new ContactService($pdo, new ContactRepository($pdo, $table, $idColumn), $entity),
        new Authorization($pdo, $session),
        $csrf,
        $permission,
    );
};
$router->get('/clientes', fn (Request $request): Response => $contactController('clientes', 'id_cliente', 'clientes', 'clientes')->index($request));
$router->get('/clientes/{id}', fn (Request $request): Response => $contactController('clientes', 'id_cliente', 'clientes', 'clientes')->show($request));
$router->post('/clientes', fn (Request $request): Response => $contactController('clientes', 'id_cliente', 'clientes', 'clientes')->store($request));
$router->patch('/clientes/{id}', fn (Request $request): Response => $contactController('clientes', 'id_cliente', 'clientes', 'clientes')->update($request));
$router->post('/clientes/{id}/desactivar', fn (Request $request): Response => $contactController('clientes', 'id_cliente', 'clientes', 'clientes')->deactivate($request));
$router->get('/proveedores', fn (Request $request): Response => $contactController('proveedores', 'id_proveedor', 'proveedores', 'proveedores')->index($request));
$router->get('/proveedores/{id}', fn (Request $request): Response => $contactController('proveedores', 'id_proveedor', 'proveedores', 'proveedores')->show($request));
$router->post('/proveedores', fn (Request $request): Response => $contactController('proveedores', 'id_proveedor', 'proveedores', 'proveedores')->store($request));
$router->patch('/proveedores/{id}', fn (Request $request): Response => $contactController('proveedores', 'id_proveedor', 'proveedores', 'proveedores')->update($request));
$router->post('/proveedores/{id}/desactivar', fn (Request $request): Response => $contactController('proveedores', 'id_proveedor', 'proveedores', 'proveedores')->deactivate($request));
$inventoryCatalogController = function () use ($database, $session, $csrf): InventoryCatalogController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new InventoryCatalogController(new InventoryCatalogService($pdo, new InventoryCatalogRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/productos', fn (Request $request): Response => $inventoryCatalogController()->index($request, 'producto'));
$router->get('/productos/{id}', fn (Request $request): Response => $inventoryCatalogController()->show($request, 'producto'));
$router->post('/productos', fn (Request $request): Response => $inventoryCatalogController()->store($request, 'producto'));
$router->patch('/productos/{id}', fn (Request $request): Response => $inventoryCatalogController()->update($request, 'producto'));
$router->post('/productos/{id}/desactivar', fn (Request $request): Response => $inventoryCatalogController()->deactivate($request, 'producto'));
$router->get('/insumos', fn (Request $request): Response => $inventoryCatalogController()->index($request, 'insumo'));
$router->get('/insumos/{id}', fn (Request $request): Response => $inventoryCatalogController()->show($request, 'insumo'));
$router->post('/insumos', fn (Request $request): Response => $inventoryCatalogController()->store($request, 'insumo'));
$router->patch('/insumos/{id}', fn (Request $request): Response => $inventoryCatalogController()->update($request, 'insumo'));
$router->post('/insumos/{id}/desactivar', fn (Request $request): Response => $inventoryCatalogController()->deactivate($request, 'insumo'));
$unitController = function () use ($database, $session, $csrf): UnitController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new UnitController(new UnitService($pdo, new UnitRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/unidades', fn (Request $request): Response => $unitController()->index($request));
$router->get('/unidades/{id}', fn (Request $request): Response => $unitController()->show($request));
$router->post('/unidades', fn (Request $request): Response => $unitController()->store($request));
$router->patch('/unidades/{id}', fn (Request $request): Response => $unitController()->update($request));
$router->post('/unidades/{id}/desactivar', fn (Request $request): Response => $unitController()->deactivate($request));
$router->post('/unidades/{id}/activar', fn (Request $request): Response => $unitController()->activate($request));
$expenseCategoryController = function () use ($database, $session, $csrf): ExpenseCategoryController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new ExpenseCategoryController(new ExpenseCategoryService($pdo, new ExpenseCategoryRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/categorias-gastos', fn (Request $request): Response => $expenseCategoryController()->index($request));
$router->get('/categorias-gastos/{id}', fn (Request $request): Response => $expenseCategoryController()->show($request));
$router->post('/categorias-gastos', fn (Request $request): Response => $expenseCategoryController()->store($request));
$router->patch('/categorias-gastos/{id}', fn (Request $request): Response => $expenseCategoryController()->update($request));
$router->post('/categorias-gastos/{id}/desactivar', fn (Request $request): Response => $expenseCategoryController()->deactivate($request));
$router->post('/categorias-gastos/{id}/activar', fn (Request $request): Response => $expenseCategoryController()->activate($request));
$productionCatalogController = function () use ($database, $session, $csrf): ProductionCatalogController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new ProductionCatalogController(new ProductionCatalogService($pdo, new ProductionCatalogRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
foreach ([
    'trabajadores' => 'trabajador',
    'cultivos' => 'cultivo',
    'parcelas' => 'parcela',
    'tareas' => 'tarea',
] as $path => $type) {
    $router->get('/' . $path, fn (Request $request): Response => $productionCatalogController()->index($request, $type));
    $router->get('/' . $path . '/{id}', fn (Request $request): Response => $productionCatalogController()->show($request, $type));
    $router->post('/' . $path, fn (Request $request): Response => $productionCatalogController()->store($request, $type));
    $router->patch('/' . $path . '/{id}', fn (Request $request): Response => $productionCatalogController()->update($request, $type));
    $router->post('/' . $path . '/{id}/desactivar', fn (Request $request): Response => $productionCatalogController()->deactivate($request, $type));
    if ($type === 'parcela') {
        $router->post('/' . $path . '/{id}/activar', fn (Request $request): Response => $productionCatalogController()->activate($request, $type));
    }
}
$inventoryController = function () use ($database, $session, $csrf): InventoryController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new InventoryController(new InventoryService($pdo, new InventoryRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/inventario', fn (Request $request): Response => $inventoryController()->movements($request));
$router->get('/inventario/movimientos', fn (Request $request): Response => $inventoryController()->movements($request));
$router->post('/inventario/merma', fn (Request $request): Response => $inventoryController()->merma($request));
$cropCycleController = function () use ($database, $session, $csrf): CropCycleController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new CropCycleController(new CropCycleService($pdo, new CropCycleRepository($pdo), new HarvestRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/ciclos-cultivo', fn (Request $request): Response => $cropCycleController()->index($request));
$router->get('/ciclos-cultivo/{id}', fn (Request $request): Response => $cropCycleController()->show($request));
$router->post('/ciclos-cultivo', fn (Request $request): Response => $cropCycleController()->store($request));
$router->post('/ciclos-cultivo/{id}/iniciar', fn (Request $request): Response => $cropCycleController()->transition($request, 'EnCurso'));
$router->post('/ciclos-cultivo/{id}/cancelar', fn (Request $request): Response => $cropCycleController()->transition($request, 'Cancelado', 'produccion.cancelar'));
$router->patch('/ciclos-cultivo/{id}/estimacion', fn (Request $request): Response => $cropCycleController()->updateEstimate($request));
$cropTaskController = function () use ($database, $session, $csrf): CropTaskController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new CropTaskController(new CropTaskService($pdo, new CropTaskRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/ciclos-cultivo/{cycle}/tareas', fn (Request $request): Response => $cropTaskController()->list($request));
$router->post('/ciclos-cultivo/{cycle}/tareas', fn (Request $request): Response => $cropTaskController()->create($request));
$router->post('/tareas-cultivo/{id}/iniciar', fn (Request $request): Response => $cropTaskController()->transition($request, 'EnCurso'));
$router->post('/tareas-cultivo/{id}/completar', fn (Request $request): Response => $cropTaskController()->transition($request, 'Completada'));
$router->post('/tareas-cultivo/{id}/cancelar', fn (Request $request): Response => $cropTaskController()->transition($request, 'Cancelada'));
$router->post('/tareas-cultivo/{id}/consumos', fn (Request $request): Response => $cropTaskController()->consume($request));
$router->get('/tareas-cultivo/{id}/trabajos', fn (Request $request): Response => $cropTaskController()->works($request));
$router->post('/tareas-cultivo/{id}/trabajos', fn (Request $request): Response => $cropTaskController()->storeWork($request));
$harvestController = function () use ($database, $session, $csrf): HarvestController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new HarvestController(new HarvestService($pdo, new HarvestRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/ciclos-cultivo/{id}/cosechas', fn (Request $request): Response => $harvestController()->create($request));
$router->post('/ciclos-cultivo/{id}/consolidar', fn (Request $request): Response => $harvestController()->consolidate($request));
$purchaseController = function () use ($database, $session, $csrf): PurchaseController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new PurchaseController(new PurchaseService($pdo, new PurchaseRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/compras', fn (Request $request): Response => $purchaseController()->store($request));
$saleController = function () use ($database, $session, $csrf): SaleController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new SaleController(new SaleService($pdo, new SaleRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/ventas', fn (Request $request): Response => $saleController()->store($request));
$billingController = function () use ($database, $session, $csrf, $config, $logger): BillingController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    $fiscal = new FiscalRepository($pdo);
    return new BillingController(
        new FacturacionService(
            $pdo,
            $fiscal,
            new SaleRepository($pdo),
            new ContactRepository($pdo, 'clientes', 'id_cliente'),
            ArcaFactory::create($config, $fiscal),
            (int) $config->get('arca_cuit'),
            (int) $config->get('arca_condicion_emisor'),
            (bool) $config->get('arca_allow_emission'),
            new \App\Domain\Facturacion\ValidadorFiscal(),
            new \App\Domain\Facturacion\CalculadorFiscal(),
            (string) $config->get('arca_mode'),
            new FacturacionSolicitudRepository($pdo),
            $logger,
            new EmisorService($pdo, new EmisorRepository($pdo)),
            (int) $config->get('arca_punto_venta_default'),
        ),
        new Authorization($pdo, $session),
        $csrf,
    );
};
$router->get('/comprobantes', fn (Request $request): Response => $billingController()->index($request));
$router->get('/comprobantes/{id}', fn (Request $request): Response => $billingController()->show($request));
$router->post('/ventas/{id}/facturar', fn (Request $request): Response => $billingController()->invoice($request));
$router->post('/comprobantes/{id}/notas', fn (Request $request): Response => $billingController()->note($request));
$router->post('/comprobantes/{id}/reconciliar', fn (Request $request): Response => $billingController()->reconcile($request));
$pointOfSaleController = function () use ($database, $session, $csrf): PointOfSaleController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new PointOfSaleController(new PointOfSaleService($pdo, new FiscalRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/puntos-venta', fn (Request $request): Response => $pointOfSaleController()->index($request));
$router->get('/puntos-venta/{id}', fn (Request $request): Response => $pointOfSaleController()->show($request));
$router->post('/puntos-venta', fn (Request $request): Response => $pointOfSaleController()->store($request));
$router->patch('/puntos-venta/{id}', fn (Request $request): Response => $pointOfSaleController()->update($request));
$router->post('/puntos-venta/{id}/desactivar', fn (Request $request): Response => $pointOfSaleController()->deactivate($request));
$router->post('/puntos-venta/{id}/activar', fn (Request $request): Response => $pointOfSaleController()->activate($request));
$financeController = function () use ($database, $session, $csrf): FinanceController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new FinanceController(new FinanceService($pdo, new FinanceRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->get('/cxc', fn (Request $request): Response => $financeController()->receivables($request));
$router->get('/cxp', fn (Request $request): Response => $financeController()->payables($request));
$router->get('/caja/movimientos', fn (Request $request): Response => $financeController()->cash($request));
$router->post('/ventas/{id}/cobro', fn (Request $request): Response => $financeController()->receive($request));
$router->post('/compras/{id}/pago', fn (Request $request): Response => $financeController()->pay($request));
$expenseController = function () use ($database, $session, $csrf): ExpenseController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new ExpenseController(new ExpenseService($pdo, new ExpenseRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/gastos', fn (Request $request): Response => $expenseController()->store($request));
$cancellationController = function () use ($database, $session, $csrf): CancellationController {
    if ($session->userId() === null) {
        throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    }
    $pdo = $database->connection();
    return new CancellationController(new CancellationService($pdo, new CancellationRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/ventas/{id}/cancelar', fn (Request $request): Response => $cancellationController()->sale($request));
$reversalController = function () use ($database, $session, $csrf): ReversalController {
    if ($session->userId() === null) throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    $pdo = $database->connection();
    return new ReversalController(new ReversalService($pdo, new ReversalRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/cobros/{id}/revertir', fn (Request $request): Response => $reversalController()->receipt($request));
$router->post('/pagos/{id}/revertir', fn (Request $request): Response => $reversalController()->payment($request));
$purchaseCancellationController = function () use ($database, $session, $csrf): PurchaseCancellationController {
    if ($session->userId() === null) throw new HttpException('AUTHENTICATION_REQUIRED', 'Debe iniciar sesion para continuar.', 401);
    $pdo = $database->connection();
    return new PurchaseCancellationController(new PurchaseCancellationService($pdo, new PurchaseCancellationRepository($pdo)), new Authorization($pdo, $session), $csrf);
};
$router->post('/compras/{id}/cancelar', fn (Request $request): Response => $purchaseCancellationController()->cancel($request));

$contactWebController = function (string $type, string $singular, string $plural, string $permission, string $idColumn) use ($database, $session, $csrf, $views): ContactWebController {
    $pdo = $database->connection();
    return new ContactWebController(
        new ContactService($pdo, new ContactRepository($pdo, $type, $idColumn), $type),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
        $type,
        $singular,
        $plural,
        $permission,
        $idColumn,
    );
};
foreach ([
    ['clientes', 'Cliente', 'Clientes', 'clientes', 'id_cliente'],
    ['proveedores', 'Proveedor', 'Proveedores', 'proveedores', 'id_proveedor'],
] as [$type, $singular, $plural, $permission, $idColumn]) {
    $router->get('/app/' . $type, fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->index($request));
    $router->get('/app/' . $type . '/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->create($request));
    $router->post('/app/' . $type, fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->store($request));
    $router->get('/app/' . $type . '/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->show($request));
    $router->get('/app/' . $type . '/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->edit($request));
    $router->post('/app/' . $type . '/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->update($request));
    $router->post('/app/' . $type . '/{id}/desactivar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $contactWebController($type, $singular, $plural, $permission, $idColumn)->deactivate($request));
}

$inventoryCatalogWebController = function (string $type, string $singular, string $plural, string $permission, string $idColumn) use ($database, $session, $csrf, $views): InventoryCatalogWebController {
    $pdo = $database->connection();
    return new InventoryCatalogWebController(
        new InventoryCatalogService($pdo, new InventoryCatalogRepository($pdo)),
        new UnitRepository($pdo),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
        $type,
        $singular,
        $plural,
        $permission,
        $idColumn,
    );
};
foreach ([
    ['producto', 'Producto', 'Productos', 'productos', 'id_producto'],
    ['insumo', 'Insumo', 'Insumos', 'insumos', 'id_insumo'],
] as [$type, $singular, $plural, $permission, $idColumn]) {
    $router->get('/app/' . $type . 's', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->index($request));
    $router->get('/app/' . $type . 's/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->create($request));
    $router->post('/app/' . $type . 's', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->store($request));
    $router->get('/app/' . $type . 's/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->show($request));
    $router->get('/app/' . $type . 's/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->edit($request));
    $router->post('/app/' . $type . 's/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->update($request));
    $router->post('/app/' . $type . 's/{id}/desactivar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryCatalogWebController($type, $singular, $plural, $permission, $idColumn)->deactivate($request));
}

$inventoryWebController = function () use ($database, $session, $csrf, $views): InventoryWebController {
    $pdo = $database->connection();
    return new InventoryWebController(
        new InventoryService($pdo, new InventoryRepository($pdo)),
        new InventoryCatalogService($pdo, new InventoryCatalogRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/inventario', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryWebController()->index($request));
$router->get('/app/inventario/mermas/nueva', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryWebController()->mermaForm($request));
$router->post('/app/inventario/mermas', fn (Request $request): Response|RedirectResponse|HtmlResponse => $inventoryWebController()->mermaStore($request));

$productionWebController = function () use ($database, $session, $csrf, $views): ProductionWebController {
    $pdo = $database->connection();
    return new ProductionWebController(
        new CropCycleService($pdo, new CropCycleRepository($pdo), new HarvestRepository($pdo)),
        new CropTaskService($pdo, new CropTaskRepository($pdo)),
        new HarvestService($pdo, new HarvestRepository($pdo)),
        new ProductionCatalogService($pdo, new ProductionCatalogRepository($pdo)),
        new InventoryCatalogService($pdo, new InventoryCatalogRepository($pdo)),
        new UnitRepository($pdo),
        new WorkerRepository($pdo),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/ciclos-cultivo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionWebController()->index($request));
$router->get('/app/ciclos-cultivo/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionWebController()->create($request));
$router->post('/app/ciclos-cultivo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionWebController()->store($request));
$router->get('/app/ciclos-cultivo/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionWebController()->show($request));
$router->post('/app/ciclos-cultivo/{id}/iniciar', fn (Request $request): Response|RedirectResponse => $productionWebController()->start($request));
$router->post('/app/ciclos-cultivo/{id}/cancelar', fn (Request $request): Response|RedirectResponse => $productionWebController()->cancel($request));
$router->post('/app/ciclos-cultivo/{id}/estimacion', fn (Request $request): Response|RedirectResponse => $productionWebController()->updateEstimate($request));
$router->post('/app/ciclos-cultivo/{id}/cosechas', fn (Request $request): Response|RedirectResponse => $productionWebController()->harvest($request));
$router->post('/app/ciclos-cultivo/{id}/consolidar', fn (Request $request): Response|RedirectResponse => $productionWebController()->consolidate($request));
$router->post('/app/ciclos-cultivo/{id}/tareas', fn (Request $request): Response|RedirectResponse => $productionWebController()->taskCreate($request));
$router->get('/app/tareas-cultivo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionWebController()->tasksIndex($request));
$router->get('/app/tareas-cultivo/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionWebController()->taskShow($request));
$router->post('/app/tareas-cultivo/{id}/iniciar', fn (Request $request): Response|RedirectResponse => $productionWebController()->taskTransition($request, 'EnCurso'));
$router->post('/app/tareas-cultivo/{id}/completar', fn (Request $request): Response|RedirectResponse => $productionWebController()->taskTransition($request, 'Completada'));
$router->post('/app/tareas-cultivo/{id}/cancelar', fn (Request $request): Response|RedirectResponse => $productionWebController()->taskTransition($request, 'Cancelada'));
$router->post('/app/tareas-cultivo/{id}/consumos', fn (Request $request): Response|RedirectResponse => $productionWebController()->consume($request));
$router->post('/app/tareas-cultivo/{id}/trabajos', fn (Request $request): Response|RedirectResponse => $productionWebController()->taskWork($request));

$productionCatalogWebController = function (string $type, string $singular, string $plural, string $idColumn) use ($database, $session, $csrf, $views): ProductionCatalogWebController {
    $pdo = $database->connection();
    return new ProductionCatalogWebController(
        new ProductionCatalogService($pdo, new ProductionCatalogRepository($pdo)),
        new UnitRepository($pdo),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
        $type,
        $singular,
        $plural,
        $idColumn,
    );
};
foreach ([
    ['cultivo', 'Cultivo', 'Cultivos', 'id_cultivo'],
    ['parcela', 'Parcela', 'Parcelas', 'id_parcela'],
    ['tarea', 'Tarea', 'Tareas', 'id_tarea'],
] as [$type, $singular, $plural, $idColumn]) {
    $router->get('/app/' . $type . 's', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->index($request));
    $router->get('/app/' . $type . 's/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->create($request));
    $router->post('/app/' . $type . 's', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->store($request));
    $router->get('/app/' . $type . 's/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->show($request));
    $router->get('/app/' . $type . 's/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->edit($request));
    $router->post('/app/' . $type . 's/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->update($request));
    $router->post('/app/' . $type . 's/{id}/desactivar', fn (Request $request): Response|RedirectResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->deactivate($request));
    if ($type === 'parcela') {
        $router->post('/app/' . $type . 's/{id}/activar', fn (Request $request): Response|RedirectResponse => $productionCatalogWebController($type, $singular, $plural, $idColumn)->activate($request));
    }
}

$unitWebController = function () use ($database, $session, $csrf, $views): UnitWebController {
    $pdo = $database->connection();
    return new UnitWebController(
        new UnitService($pdo, new UnitRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/unidades', fn (Request $request): Response|RedirectResponse|HtmlResponse => $unitWebController()->index($request));
$router->get('/app/unidades/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $unitWebController()->create($request));
$router->post('/app/unidades', fn (Request $request): Response|RedirectResponse|HtmlResponse => $unitWebController()->store($request));
$router->get('/app/unidades/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $unitWebController()->show($request));
$router->get('/app/unidades/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $unitWebController()->edit($request));
$router->post('/app/unidades/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $unitWebController()->update($request));
$router->post('/app/unidades/{id}/desactivar', fn (Request $request): Response|RedirectResponse => $unitWebController()->deactivate($request));
$router->post('/app/unidades/{id}/activar', fn (Request $request): Response|RedirectResponse => $unitWebController()->activate($request));

$expenseCategoryWebController = function () use ($database, $session, $csrf, $views): ExpenseCategoryWebController {
    $pdo = $database->connection();
    return new ExpenseCategoryWebController(
        new ExpenseCategoryService($pdo, new ExpenseCategoryRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/categorias-gastos', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseCategoryWebController()->index($request));
$router->get('/app/categorias-gastos/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseCategoryWebController()->create($request));
$router->post('/app/categorias-gastos', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseCategoryWebController()->store($request));
$router->get('/app/categorias-gastos/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseCategoryWebController()->show($request));
$router->get('/app/categorias-gastos/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseCategoryWebController()->edit($request));
$router->post('/app/categorias-gastos/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseCategoryWebController()->update($request));
$router->post('/app/categorias-gastos/{id}/desactivar', fn (Request $request): Response|RedirectResponse => $expenseCategoryWebController()->deactivate($request));
$router->post('/app/categorias-gastos/{id}/activar', fn (Request $request): Response|RedirectResponse => $expenseCategoryWebController()->activate($request));

$workerWebController = function () use ($database, $session, $csrf, $views): WorkerWebController {
    $pdo = $database->connection();
    return new WorkerWebController(
        new WorkerService($pdo, new WorkerRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/trabajadores', fn (Request $request): Response|RedirectResponse|HtmlResponse => $workerWebController()->index($request));
$router->get('/app/trabajadores/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $workerWebController()->create($request));
$router->post('/app/trabajadores', fn (Request $request): Response|RedirectResponse|HtmlResponse => $workerWebController()->store($request));
$router->get('/app/trabajadores/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $workerWebController()->show($request));
$router->get('/app/trabajadores/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $workerWebController()->edit($request));
$router->post('/app/trabajadores/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $workerWebController()->update($request));
$router->post('/app/trabajadores/{id}/desactivar', fn (Request $request): Response|RedirectResponse => $workerWebController()->deactivate($request));
$router->post('/app/trabajadores/{id}/activar', fn (Request $request): Response|RedirectResponse => $workerWebController()->activate($request));

$purchaseWebController = function () use ($database, $session, $csrf, $views): PurchaseWebController {
    $pdo = $database->connection();
    return new PurchaseWebController(
        new PurchaseService($pdo, new PurchaseRepository($pdo)),
        new PurchaseCancellationService($pdo, new PurchaseCancellationRepository($pdo)),
        new FinanceService($pdo, new FinanceRepository($pdo)),
        new ContactService($pdo, new ContactRepository($pdo, 'proveedores', 'id_proveedor'), 'proveedores'),
        new InventoryCatalogService($pdo, new InventoryCatalogRepository($pdo)),
        new UnitRepository($pdo),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/compras', fn (Request $request): Response|RedirectResponse|HtmlResponse => $purchaseWebController()->index($request));
$router->get('/app/compras/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $purchaseWebController()->create($request));
$router->post('/app/compras', fn (Request $request): Response|RedirectResponse|HtmlResponse => $purchaseWebController()->store($request));
$router->get('/app/compras/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $purchaseWebController()->show($request));
$router->post('/app/compras/{id}/cancelar', fn (Request $request): Response|RedirectResponse => $purchaseWebController()->cancel($request));
$router->post('/app/compras/{id}/pago', fn (Request $request): Response|RedirectResponse => $purchaseWebController()->pay($request));

$saleWebController = function () use ($database, $session, $csrf, $views): SaleWebController {
    $pdo = $database->connection();
    return new SaleWebController(
        new SaleService($pdo, new SaleRepository($pdo)),
        new CancellationService($pdo, new CancellationRepository($pdo)),
        new FinanceService($pdo, new FinanceRepository($pdo)),
        new ContactService($pdo, new ContactRepository($pdo, 'clientes', 'id_cliente'), 'clientes'),
        new FiscalRepository($pdo),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/ventas', fn (Request $request): Response|RedirectResponse|HtmlResponse => $saleWebController()->index($request));
$router->get('/app/ventas/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $saleWebController()->create($request));
$router->post('/app/ventas', fn (Request $request): Response|RedirectResponse|HtmlResponse => $saleWebController()->store($request));
$router->get('/app/ventas/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $saleWebController()->show($request));
$router->post('/app/ventas/{id}/cancelar', fn (Request $request): Response|RedirectResponse => $saleWebController()->cancel($request));
$router->post('/app/ventas/{id}/cobro', fn (Request $request): Response|RedirectResponse => $saleWebController()->receive($request));

$billingWebController = function () use ($database, $session, $csrf, $views, $config, $logger, $basePath): BillingWebController {
    $pdo = $database->connection();
    $fiscal = new FiscalRepository($pdo);
    $representation = new ComprobanteRepresentacionService(
        $fiscal,
        new SaleRepository($pdo),
        new ContactRepository($pdo, 'clientes', 'id_cliente'),
        new QrFiscal(),
        new QrSvgRenderer(),
        new ComprobantePdfRenderer($basePath . '/storage/cache/dompdf'),
        $views,
        $basePath . '/public/assets/css/invoice.css',
    );
    return new BillingWebController(
        new FacturacionService(
            $pdo,
            $fiscal,
            new SaleRepository($pdo),
            new ContactRepository($pdo, 'clientes', 'id_cliente'),
            ArcaFactory::create($config, $fiscal),
            (int) $config->get('arca_cuit'),
            (int) $config->get('arca_condicion_emisor'),
            (bool) $config->get('arca_allow_emission'),
            new \App\Domain\Facturacion\ValidadorFiscal(),
            new \App\Domain\Facturacion\CalculadorFiscal(),
            (string) $config->get('arca_mode'),
            new FacturacionSolicitudRepository($pdo),
            $logger,
            new EmisorService($pdo, new EmisorRepository($pdo)),
            (int) $config->get('arca_punto_venta_default'),
        ),
        new SaleService($pdo, new SaleRepository($pdo)),
        new PointOfSaleService($pdo, $fiscal),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
        $representation,
    );
};
$router->get('/app/comprobantes', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->index($request));$router->get('/app/comprobantes/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->show($request));
$router->get('/app/comprobantes/{id}/imprimir', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->print($request));
$router->get('/app/comprobantes/{id}/pdf', fn (Request $request): Response|RedirectResponse|FileResponse => $billingWebController()->pdf($request));
$router->get('/app/ventas/{id}/facturar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->invoiceForm($request));
$router->post('/app/ventas/{id}/facturar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->invoiceStore($request));
$router->get('/app/comprobantes/{id}/notas/nueva', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->noteForm($request));
$router->post('/app/comprobantes/{id}/notas', fn (Request $request): Response|RedirectResponse|HtmlResponse => $billingWebController()->noteStore($request));
$router->post('/app/comprobantes/{id}/reconciliar', fn (Request $request): Response|RedirectResponse => $billingWebController()->reconcile($request));
$router->post('/app/comprobantes/{id}/reintentar', fn (Request $request): Response|RedirectResponse => $billingWebController()->retry($request));

$diagnosticWebController = function () use ($database, $session, $csrf, $views, $config): ArcaDiagnosticWebController {
    $pdo = $database->connection();
    $fiscal = new FiscalRepository($pdo);

    return new ArcaDiagnosticWebController(
        new ArcaDiagnosticsService(
            $config,
            static fn (): \App\Infrastructure\Arca\ArcaGateway => ArcaFactory::create($config, $fiscal),
            $fiscal,
        ),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/facturacion/diagnostico', fn (Request $request): Response|RedirectResponse|HtmlResponse => $diagnosticWebController()->index($request));

$pointOfSaleWebController = function () use ($database, $session, $csrf, $views): PointOfSaleWebController {
    $pdo = $database->connection();
    return new PointOfSaleWebController(
        new PointOfSaleService($pdo, new FiscalRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/puntos-venta', fn (Request $request): Response|RedirectResponse|HtmlResponse => $pointOfSaleWebController()->index($request));
$router->get('/app/puntos-venta/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $pointOfSaleWebController()->create($request));
$router->post('/app/puntos-venta', fn (Request $request): Response|RedirectResponse|HtmlResponse => $pointOfSaleWebController()->store($request));
$router->get('/app/puntos-venta/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $pointOfSaleWebController()->show($request));
$router->get('/app/puntos-venta/{id}/editar', fn (Request $request): Response|RedirectResponse|HtmlResponse => $pointOfSaleWebController()->edit($request));
$router->post('/app/puntos-venta/{id}', fn (Request $request): Response|RedirectResponse|HtmlResponse => $pointOfSaleWebController()->update($request));
$router->post('/app/puntos-venta/{id}/desactivar', fn (Request $request): Response|RedirectResponse => $pointOfSaleWebController()->deactivate($request));
$router->post('/app/puntos-venta/{id}/activar', fn (Request $request): Response|RedirectResponse => $pointOfSaleWebController()->activate($request));

$emisorWebController = function () use ($database, $session, $csrf, $views): EmisorWebController {
    $pdo = $database->connection();
    return new EmisorWebController(
        new EmisorService($pdo, new EmisorRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/emisor', fn (Request $request): Response|RedirectResponse|HtmlResponse => $emisorWebController()->edit($request));
$router->post('/app/emisor', fn (Request $request): Response|RedirectResponse|HtmlResponse => $emisorWebController()->update($request));

$financeWebController = function () use ($database, $session, $csrf, $views): FinanceWebController {
    $pdo = $database->connection();
    return new FinanceWebController(
        new FinanceService($pdo, new FinanceRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/cxc', fn (Request $request): Response|RedirectResponse|HtmlResponse => $financeWebController()->receivables($request));
$router->get('/app/cxp', fn (Request $request): Response|RedirectResponse|HtmlResponse => $financeWebController()->payables($request));
$router->get('/app/caja/movimientos', fn (Request $request): Response|RedirectResponse|HtmlResponse => $financeWebController()->cash($request));

$expenseWebController = function () use ($database, $session, $csrf, $views): ExpenseWebController {
    $pdo = $database->connection();
    return new ExpenseWebController(
        new ExpenseService($pdo, new ExpenseRepository($pdo)),
        new Authorization($pdo, $session),
        $session,
        $csrf,
        $views,
    );
};
$router->get('/app/gastos', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseWebController()->index($request));
$router->get('/app/gastos/nuevo', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseWebController()->create($request));
$router->post('/app/gastos', fn (Request $request): Response|RedirectResponse|HtmlResponse => $expenseWebController()->store($request));

$format = $request->body['format'] ?? $request->query['format'] ?? null;
$wantsHtml = str_starts_with($request->path, '/app/')
    || $format === 'html'
    || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html');

try {
    $router->dispatch($request)->send();
} catch (ArcaConfigurationException $exception) {
    if ($wantsHtml) {
        (new HtmlResponse($views->partial('errors/page', [
            'status' => 500,
            'message' => 'Facturacion electronica no configurada: ' . $exception->getMessage(),
        ]), 500))->send();
    }
    Response::error('ARCA_CONFIGURATION_ERROR', $exception->getMessage(), 500);
} catch (HttpException $exception) {
    if ($wantsHtml) {
        (new HtmlResponse($views->partial('errors/page', [
            'status' => $exception->status,
            'message' => $exception->getMessage(),
        ]), $exception->status))->send();
    }
    Response::error($exception->errorCode, $exception->getMessage(), $exception->status);
} catch (Throwable $exception) {
    $logger->error('Unhandled application error.', [
        'path' => $request->path,
        'request_id' => $request->requestId,
    ], $exception);

    if ($config->isDebug()) {
        throw $exception;
    }

    if ($wantsHtml) {
        (new HtmlResponse($views->partial('errors/page', [
            'status' => 500,
            'message' => 'Ocurrio un error interno.',
        ]), 500))->send();
    }

    Response::error('INTERNAL_ERROR', 'Ocurrio un error interno.', 500);
}
