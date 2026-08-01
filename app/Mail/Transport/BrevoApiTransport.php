<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 60,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (blank($this->apiKey)) {
            throw new TransportException('Brevo API key is not configured.');
        }

        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('Brevo API transport only supports Symfony email messages.');
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'api-key' => $this->apiKey,
            ])
            ->post(self::ENDPOINT, $this->payloadFromEmail($email));

        if ($response->failed()) {
            throw new TransportException($this->failureMessage($response));
        }
    }

    private function payloadFromEmail(Email $email): array
    {
        $payload = [
            'sender' => $this->addressToBrevo($this->senderFor($email)),
            'to' => $this->addressesToBrevo($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['htmlContent'] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['textContent'] = $text;
        }

        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = $email->getBody()->bodyToString();
        }

        if ($cc = $this->addressesToBrevo($email->getCc())) {
            $payload['cc'] = $cc;
        }

        if ($bcc = $this->addressesToBrevo($email->getBcc())) {
            $payload['bcc'] = $bcc;
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = $this->addressToBrevo($replyTo[0]);
        }

        if ($attachments = $this->attachmentsToBrevo($email)) {
            $payload['attachment'] = $attachments;
        }

        return $payload;
    }

    private function senderFor(Email $email): Address
    {
        $from = $email->getFrom();

        if ($from) {
            return $from[0];
        }

        return new Address(
            (string) config('mail.from.address'),
            (string) config('mail.from.name')
        );
    }

    private function addressToBrevo(Address $address): array
    {
        $brevoAddress = [
            'email' => $address->getAddress(),
        ];

        if ($address->getName() !== '') {
            $brevoAddress['name'] = $address->getName();
        }

        return $brevoAddress;
    }

    /**
     * @param array<int, Address> $addresses
     */
    private function addressesToBrevo(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->addressToBrevo($address), $addresses);
    }

    private function attachmentsToBrevo(Email $email): array
    {
        return array_values(array_filter(array_map(function ($part) {
            $filename = $part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename')
                ?: $part->getFilename();

            if (! $filename) {
                return null;
            }

            return [
                'name' => $filename,
                'content' => base64_encode($part->bodyToString()),
            ];
        }, $email->getAttachments())));
    }

    private function failureMessage(Response $response): string
    {
        $message = $response->json('message') ?: $response->json('code') ?: $response->body();
        $message = str($message)->limit(300)->toString();

        return sprintf('Brevo API email send failed with HTTP %s: %s', $response->status(), $message);
    }

    public function __toString(): string
    {
        return 'brevo_api';
    }
}
