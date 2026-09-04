<?php
/**
 * Servicio NotificationDispatcher. Encapsula una responsabilidad transversal para que controladores y modelos no dupliquen reglas.
 * Los parámetros se validan antes de usarse; los errores esperables se transforman en respuestas seguras o códigos de salida.
 */

declare(strict_types=1);

final class NotificationDispatcher
{
    private NotificationRepository $repository;
    private array $channels;

    public function __construct(?int $datasetId = null, bool $allDatasets = false)
    {
        $this->repository = new NotificationRepository($datasetId,$allDatasets);
        $this->channels = [
            'internal' => new InternalNotificationChannel(),
            'email' => new EmailNotificationChannel(),
            'whatsapp' => new WhatsAppNotificationChannel(),
        ];
    }

    public function dispatch(int $limit = 100, ?int $promotionId = null): array
    {
        $limit=max(1,min(1000,$limit));$result=['processed'=>0,'sent'=>0,'failed'=>0,'skipped'=>0,'lifecycle'=>$this->repository->refreshPromotionStates(),'recovered'=>$this->repository->recoverStale()];
        while($result['processed']<$limit){$delivery=$this->repository->claimNext($promotionId);if(!$delivery)break;$result['processed']++;
            try{
                if(!$this->repository->consentAllows($delivery)){$this->repository->skipped((int)$delivery['id'],'consent_or_preference_disabled','El canal no está habilitado o falta consentimiento vigente.');$result['skipped']++;continue;}
                $payload=json_decode((string)$delivery['payload_json'],true);if(!is_array($payload))throw new DomainException('payload_invalid');
                if($delivery['canal']==='email'&&$delivery['categoria']==='marketing')$payload['unsubscribe_url']=$this->repository->unsubscribeUrl((int)$delivery['usuario_id'],(string)$delivery['idempotency_key']);
                $channel=$this->channels[$delivery['canal']]??null;if(!$channel)throw new DomainException('channel_not_supported');
                $providerId=$channel->send($delivery,$payload,$this->repository);$this->repository->delivered((int)$delivery['id'],$providerId);$result['sent']++;
            }catch(Throwable$error){
                $code=$error instanceof DomainException?$error->getMessage():'delivery_failed';
                error_log('[GymTrack notification] '.$error->getMessage());
                if(in_array($code,['recipient_email_invalid','payload_invalid','channel_not_supported','whatsapp_feature_disabled','whatsapp_adapter_unavailable'],true)){
                    $this->repository->skipped((int)$delivery['id'],$code,'La entrega no es válida para el canal configurado.');
                    $result['skipped']++;
                    continue;
                }
                $this->repository->failed($delivery,$code,'No se pudo completar el envío. Se reintentará cuando corresponda.');
                $result['failed']++;
            }
        }
        return$result;
    }
}
