<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Authorization; use App\Core\Csrf; use App\Core\Request; use App\Core\Response; use App\Services\PurchaseCancellationService;
final class PurchaseCancellationController
{
    public function __construct(private readonly PurchaseCancellationService $service,private readonly Authorization $authorization,private readonly Csrf $csrf){}
    public function cancel(Request $r):Response{$actor=$this->authorization->requirePermission('compras.cancelar');$token=$r->body['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??null);$this->csrf->validate(is_string($token)?$token:null);return new Response($this->service->cancel((int)$r->params['id'],$actor));}
}
