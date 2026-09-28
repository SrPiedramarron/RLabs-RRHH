<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envía correo vía la API HTTP de Brevo (antes Sendinblue), usando solo el
 * API key — no requiere credenciales SMTP separadas (que Brevo genera
 * aparte y esta empresa no tenía). Se registra como mailer 'brevo' en
 * config/mail.php.
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom()[0] ?? throw new TransportException('Brevo: el correo no tiene remitente (from).');

        $payload = array_filter([
            'sender'      => $this->formatearDireccion($from),
            'to'          => array_map($this->formatearDireccion(...), $email->getTo()),
            'cc'          => array_map($this->formatearDireccion(...), $email->getCc()) ?: null,
            'bcc'         => array_map($this->formatearDireccion(...), $email->getBcc()) ?: null,
            'replyTo'     => $email->getReplyTo() ? $this->formatearDireccion($email->getReplyTo()[0]) : null,
            'subject'     => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
        ]);

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept'  => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new TransportException('Error al enviar correo vía Brevo: ' . $response->body());
        }
    }

    private function formatearDireccion(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name'  => $address->getName() ?: null,
        ]);
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
