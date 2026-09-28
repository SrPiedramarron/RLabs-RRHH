<?php

namespace App\Services;

use App\Models\Employee;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    /**
     * Envía una notificación push a todos los dispositivos suscritos del
     * trabajador. Si una suscripción ya no es válida (el navegador la
     * eliminó, el trabajador desinstaló la PWA, etc.), se borra sola.
     */
    public function enviarATrabajador(Employee $empleado, string $titulo, string $cuerpo, ?string $url = null): void
    {
        $suscripciones = $empleado->pushSubscriptions;

        if ($suscripciones->isEmpty()) {
            return;
        }

        if (! config('services.vapid.public_key') || ! config('services.vapid.private_key')) {
            \Log::warning('Push notification no enviada: VAPID keys no configuradas.');
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $payload = json_encode([
            'title' => $titulo,
            'body'  => $cuerpo,
            'url'   => $url ?? '/checkin/solicitudes',
        ]);

        foreach ($suscripciones as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $reporte) {
            if (! $reporte->isSuccess() && $reporte->isSubscriptionExpired()) {
                \App\Models\PushSubscription::where('endpoint', $reporte->getEndpoint())->delete();
            }
        }
    }
}
