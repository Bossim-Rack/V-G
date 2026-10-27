<?php

namespace App\Mailer;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envoi des mails via l'API HTTP de Brevo (et non en SMTP).
 * Render bloque le SMTP sur son offre gratuite : on passe donc par une simple requête HTTPS.
 * DSN à mettre dans MAILER_DSN : brevo+api://CLE_API@default
 */
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(
        private string $apiKey,
        private string $endpoint = 'https://api.brevo.com/v3/smtp/email',
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        // Le dispatcher est indispensable : c'est lui qui transforme les templates Twig en HTML avant l'envoi.
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $corps = [
            'sender' => $this->adresse($envelope->getSender()),
            'to' => array_map(fn (Address $a) => $this->adresse($a), $this->destinataires($email, $envelope)),
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() ?: nl2br(htmlspecialchars((string) $email->getTextBody())),
        ];
        if ($email->getTextBody()) {
            $corps['textContent'] = (string) $email->getTextBody();
        }
        if ($email->getReplyTo()) {
            $corps['replyTo'] = $this->adresse($email->getReplyTo()[0]);
        }

        $contexte = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "api-key: {$this->apiKey}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($corps),
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);

        $reponse = @file_get_contents($this->endpoint, false, $contexte);
        $statut = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m) ? (int) $m[1] : 0;

        if ($reponse === false || $statut < 200 || $statut >= 300) {
            throw new TransportException(sprintf('Brevo a refusé le mail (HTTP %d) : %s', $statut, (string) $reponse));
        }
    }

    /** @return Address[] */
    private function destinataires(Email $email, Envelope $envelope): array
    {
        return $envelope->getRecipients();
    }

    private function adresse(Address $adresse): array
    {
        $donnees = ['email' => $adresse->getAddress()];
        if ($adresse->getName() !== '') {
            $donnees['name'] = $adresse->getName();
        }

        return $donnees;
    }
}
