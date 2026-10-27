<?php

namespace App\Mailer;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** Permet d'écrire MAILER_DSN=brevo+api://CLE_API@default dans les variables d'environnement. */
class BrevoTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ($dsn->getScheme() !== 'brevo+api') {
            throw new UnsupportedSchemeException($dsn, 'brevo', $this->getSupportedSchemes());
        }

        $endpoint = $dsn->getHost() === 'default'
            ? 'https://api.brevo.com/v3/smtp/email'
            : 'http://' . $dsn->getHost() . ($dsn->getPort() ? ':' . $dsn->getPort() : '') . '/v3/smtp/email';

        return new BrevoApiTransport($this->getUser($dsn), $endpoint, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['brevo+api'];
    }
}
