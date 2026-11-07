<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Authorization;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReversalService;
final class ReversalController
{
    public function __construct(private readonly ReversalService $service, private readonly Authorization $authorization, private readonly Csrf $csrf) {}
    public function receipt(Request $r): Response { $actor=$this->authorization->requirePermission('cobranzas.revertir'); $this->csrf->validate($this->token($r)); return new Response($this->service->receipt((int)$r->params['id'], (string)($r->body['motivo']??''), $actor)); }
    public function payment(Request $r): Response { $actor=$this->authorization->requirePermission('pagos.revertir'); $this->csrf->validate($this->token($r)); return new Response($this->service->payment((int)$r->params['id'], (string)($r->body['motivo']??''), $actor)); }
    private function token(Request $r): ?string { $v=$r->body['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??null); return is_string($v)?$v:null; }
}
