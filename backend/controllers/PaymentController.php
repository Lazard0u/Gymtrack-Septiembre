<?php
/**
 * Controlador HTTP PaymentController. Recibe la ruta, valida entrada y autorización, llama modelos o servicios y devuelve JSON sin exponer detalles internos.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class PaymentController
{
    private const ADMIN_ROLES=['empleado','dueño','admin_general'];
    public function adminList(): void{[, $gym,$repo]=$this->admin('payments.read');$result=$repo->adminPayments($gym,$_GET);ApiResponder::success(['items'=>$result['items']],200,['pagination'=>$result['pagination']]);}
    public function adminOptions(): void{[, $gym,$repo]=$this->admin('payments.read');ApiResponder::success($repo->options($gym));}
    public function finance(): void{[, $gym,$repo]=$this->admin('finance.read');ApiResponder::success($repo->finance($gym));}
    public function createManual(): void
    {
        [$actor,$gym,$repo]=$this->admin('payments.manual');$body=$this->body();$membership=(int)($body['membership_id']??0);$amount=round((float)($body['amount']??0),2);$currency=strtoupper(trim((string)($body['currency']??'UYU')));$method=(string)($body['method']??'');$paidAt=(string)($body['paid_at']??'');$concept=trim((string)($body['concept']??'Pago de membresía'));$notes=trim((string)($body['notes']??''));$fields=[];
        if($membership<1)$fields['membership_id']='Seleccioná una membresía.';if($amount<=0||$amount>9999999999.99)$fields['amount']='Ingresá un importe válido.';if(!preg_match('/^[A-Z]{3}$/',$currency))$fields['currency']='Usá un código de moneda de tres letras.';if(!in_array($method,['efectivo','transferencia','tarjeta','debito'],true))$fields['method']='Seleccioná un método válido.';if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$paidAt))$fields['paid_at']='Indicá una fecha válida.';if(mb_strlen($concept)<3||mb_strlen($concept)>180)$fields['concept']='El concepto debe tener entre 3 y 180 caracteres.';if(mb_strlen($notes)>500)$fields['notes']='Las notas no pueden superar 500 caracteres.';if($fields)ApiResponder::error(422,'validation_error','Revisá los campos indicados.',$fields);
        $payment=$repo->createManual($gym,$actor,['membership_id'=>$membership,'amount'=>$amount,'currency'=>$currency,'method'=>$method,'paid_at'=>$paidAt,'concept'=>$concept,'notes'=>$notes],$this->key());AdminAuditLogger::record('payment.manual.created','pago','success',$actor,$gym,(string)$payment['id'],'Pago manual aprobado',null,['amount'=>$amount,'currency'=>$currency]);ApiResponder::success($payment,201);
    }
    public function refund(string $id): void
    {
        [$actor,$gym,$repo]=$this->admin('payments.manual');$reason=trim((string)($this->body()['reason']??''));if(mb_strlen($reason)<8||mb_strlen($reason)>255)ApiResponder::error(422,'validation_error','Indicá el motivo del reembolso.',['reason'=>'Escribí entre 8 y 255 caracteres.']);$payment=$repo->refund($gym,(int)$id,$actor,$reason,$this->key(),PaymentProviderFactory::configured());AdminAuditLogger::record('payment.refunded','pago','success',$actor,$gym,$id,$reason);ApiResponder::success($payment);
    }
    public function memberList(): void{AuthMiddleware::verificarRol('socio');AuthMiddleware::verificarPermiso('payments.read');$gym=AuthMiddleware::requerirContextoGimnasio();ApiResponder::success((new PaymentRepository(AuthMiddleware::obtenerDemoDatasetId()))->memberPayments($gym,AuthMiddleware::obtenerUsuarioId()));}
    public function checkout(): void{AuthMiddleware::verificarRol('socio');AuthMiddleware::verificarPermiso('payments.read');$gym=AuthMiddleware::requerirContextoGimnasio();$plan=(int)($this->body()['plan_id']??0);if($plan<1)ApiResponder::error(422,'validation_error','Seleccioná un plan.',['plan_id'=>'El plan es obligatorio.']);$result=(new PaymentRepository(AuthMiddleware::obtenerDemoDatasetId()))->createCheckout($gym,AuthMiddleware::obtenerUsuarioId(),$plan,$this->key(),PaymentProviderFactory::configured());ApiResponder::success($result,201);}
    public function mercadoPagoWebhook(): void
    {
        $body=$this->body(false);$dataId=trim((string)($body['data']['id']??$_GET['data_id']??$_GET['id']??''));$type=(string)($body['type']??$_GET['type']??'');if($type!=='payment'||$dataId==='')ApiResponder::success(['ignored'=>true]);$provider=new MercadoPagoProvider();$signature=(string)($_SERVER['HTTP_X_SIGNATURE']??'');$providerRequestId=(string)($_SERVER['HTTP_X_REQUEST_ID']??'');if(!$provider->verifyWebhook($dataId,$providerRequestId,$signature))ApiResponder::error(401,'invalid_webhook_signature','La firma del webhook no es válida.');$providerData=$provider->fetchPayment($dataId);$eventId=trim((string)($body['id']??''));if($eventId==='')$eventId=$dataId.'-'.($body['action']??'payment.updated');$result=(new PaymentRepository(null))->applyProviderUpdate($providerData,$eventId,$body);ApiResponder::success($result);
    }
    private function admin(string $permission): array{AuthMiddleware::verificarRoles(self::ADMIN_ROLES);AuthMiddleware::verificarPermiso($permission);$gym=AuthMiddleware::requerirContextoGimnasio();return [AuthMiddleware::obtenerUsuarioId(),$gym,new PaymentRepository(AuthMiddleware::obtenerDemoDatasetId())];}
    private function key(): string{$key=strtolower(trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??'')));if(!preg_match('/^[a-f0-9-]{36}$/',$key))ApiResponder::error(422,'invalid_idempotency_key','Idempotency-Key debe ser un UUID válido.');return $key;}
    private function body(bool $required=true): array{$raw=(string)file_get_contents('php://input');if($raw===''&&!$required)return [];$decoded=json_decode($raw,true);if(!is_array($decoded))ApiResponder::error(400,'invalid_json','El cuerpo de la solicitud no es válido.');return $decoded;}
}
