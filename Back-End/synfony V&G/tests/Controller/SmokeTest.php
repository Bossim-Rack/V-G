<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** @group Functional */
final class SmokeTest extends WebTestCase
{
    public function testApiDocUrlIsSuccessful(): void
    {
        $client = self::createClient();
        $client->followRedirects(false);
        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
    }

    public function testApiAccountUrlIsSecure(): void
    {
        $client = self::createClient();
        $client->followRedirects(false);
        $client->request('GET', '/api/account/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginRouteCanConnectAValidUser(): void
    {
        $client = self::createClient();
        $client->followRedirects(false);

        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            // Compte de démonstration créé par database/02_donnees_demo.sql
            'username' => 'client@vite-gourmand.test',
            'password' => 'Client-Demo2026!',
        ], JSON_THROW_ON_ERROR));

        $statusCode = $client->getResponse()->getStatusCode();
        $content = $client->getResponse()->getContent();

        $this->assertEquals(200, $statusCode);
        $this->assertStringContainsString('user', $content);
        $this->assertStringContainsString('apiToken', $content);
        $this->assertStringContainsString('roles', $content);

        self::assertResponseIsSuccessful();
    }

    public function testLoginRefusesAWrongPassword(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'username' => 'client@vite-gourmand.test',
            'password' => 'mauvais-mot-de-passe',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginRefusesADisabledAccount(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'username' => 'ancien.employe@vite-gourmand.test',
            'password' => 'Inactif-Demo2026!',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('désactivé', json_decode($client->getResponse()->getContent(), true)['error']);
    }

    public function testClientCannotReadTheStatistics(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'username' => 'client@vite-gourmand.test',
            'password' => 'Client-Demo2026!',
        ], JSON_THROW_ON_ERROR));
        $token = json_decode($client->getResponse()->getContent(), true)['apiToken'];

        $client->request('GET', '/api/stats/chiffre-affaires', [], [], ['HTTP_X-AUTH-TOKEN' => $token]);

        self::assertResponseStatusCodeSame(403);
    }
}
