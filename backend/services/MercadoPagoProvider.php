<?php
/**
 * Servicio MercadoPagoProvider. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class MercadoPagoProvider implements PaymentProviderInterface
{
    private string $token;
    private string $webhookSecret;
    private string $apiBase = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->token=trim((string)(getenv('MERCADO_PAGO_ACCESS_TOKEN')?:''));
        $this->webhookSecret=trim((string)(getenv('MERCADO_PAGO_WEBHOOK_SECRET')?:''));
    }

    public function name(): string { return 'mercado_pago'; }
    public function isConfigured(): bool { return $this->token!==''; }
    public function hasWebhookSecret(): bool { return $this->webhookSecret!==''; }

    public function createCheckout(array $payment): array
    {
        $frontend=rtrim((string)(getenv('FRONTEND_URL')?:'http://localhost:5173'),'/');
        $backend=rtrim((string)(getenv('APP_URL')?:'http://localhost:8080'),'/');
        $payload=[
            'items'=>[ ['id'=>(string)$payment['plan_id'],'title'=>(string)$payment['concepto'],'quantity'=>1,'currency_id'=>(string)$payment['moneda'],'unit_price'=>(float)$payment['monto']] ],
            'payer'=>['email'=>(string)$payment['email']],
            'external_reference'=>(string)$payment['referencia_externa'],
            'notification_url'=>$backend.'/api/webhooks/mercado-pago?source_news=webhooks',
            'back_urls'=>['success'=>$frontend.'/pagos?resultado=procesando','pending'=>$frontend.'/pagos?resultado=pendiente','failure'=>$frontend.'/pagos?resultado=fallido'],
            'auto_return'=>'approved',
            'expires'=>true,
            'expiration_date_to'=>(new DateTimeImmutable((string)$payment['fecha_vencimiento'],new DateTimeZone('UTC')))->format(DATE_ATOM),
        ];
        $response=$this->request('POST','/checkout/preferences',$payload,(string)$payment['idempotency_key']);
        if(empty($response['id'])||empty($response['init_point']))throw new RuntimeException('Mercado Pago no devolvió una preferencia utilizable.');
        return ['preference_id'=>(string)$response['id'],'checkout_url'=>(string)($response['sandbox_init_point']??$response['init_point']),'production_url'=>(string)$response['init_point']];
    }

    public function fetchPayment(string $providerPaymentId): array
    {
        $row=$this->request('GET','/v1/payments/'.rawurlencode($providerPaymentId));
        return [
            'provider_payment_id'=>(string)($row['id']??$providerPaymentId),
            'external_reference'=>(string)($row['external_reference']??''),
            'status'=>(string)($row['status']??''),
            'status_detail'=>(string)($row['status_detail']??''),
            'amount'=>(float)($row['transaction_amount']??0),
            'currency'=>(string)($row['currency_id']??''),
            'method'=>(string)($row['payment_type_id']??$row['payment_method_id']??'mercado_pago'),
            'approved_at'=>$row['date_approved']??null,
            'live_mode'=>(bool)($row['live_mode']??false),
        ];
    }

    public function refund(string $providerPaymentId,float $amount,string $idempotencyKey): array
    {
        $row=$this->request('POST','/v1/payments/'.rawurlencode($providerPaymentId).'/refunds',['amount'=>$amount],$idempotencyKey);
        return ['id'=>(string)($row['id']??''),'status'=>(string)($row['status']??'approved')];
    }

    public function verifyWebhook(string $dataId,string $requestId,string $signature): bool
    {
        if($this->webhookSecret===''||$signature==='')return false;
        $parts=[];foreach(explode(',',$signature) as $part){[$key,$value]=array_pad(explode('=',trim($part),2),2,'');$parts[$key]=$value;}
        if(empty($parts['ts'])||empty($parts['v1']))return false;
        $manifest='id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$parts['ts'].';';
        return hash_equals(hash_hmac('sha256',$manifest,$this->webhookSecret),$parts['v1']);
    }

    private function request(string $method,string $path,?array $payload=null,?string $idempotencyKey=null): array
    {
        if(!$this->isConfigured())throw new RuntimeException('Mercado Pago no está configurado.');
        $headers=['Authorization: Bearer '.$this->token,'Content-Type: application/json'];
        if($idempotencyKey)$headers[]='X-Idempotency-Key: '.$idempotencyKey;
        $curl=curl_init($this->apiBase.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);
        if($payload!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        if($body===false||$error!=='')throw new RuntimeException('No se pudo conectar con Mercado Pago.');
        $decoded=json_decode((string)$body,true);
        if($status<200||$status>=300||!is_array($decoded)){error_log('[GymTrack MercadoPago] HTTP '.$status);throw new RuntimeException('Mercado Pago rechazó la operación.');}
        return $decoded;
    }
}
