<?php

namespace App\Service;

use App\Entity\Commande;
use Psr\Log\LoggerInterface;

/**
 * Statistiques des commandes stockées dans MongoDB (base NoSQL demandée par le sujet).
 *
 * Chaque commande MySQL a son "double" dans la collection `commandes` :
 * il est créé/mis à jour à chaque création, modification ou annulation de commande.
 * Les graphiques et le chiffre d'affaires de l'espace administrateur sont
 * calculés UNIQUEMENT à partir de cette collection (pipelines d'agrégation).
 *
 * On utilise directement le driver de l'extension PHP "mongodb" (pas de librairie
 * supplémentaire à installer avec composer).
 */
class StatsMongoService
{
    private const COLLECTION = 'commandes';

    private ?\MongoDB\Driver\Manager $manager = null;

    public function __construct(
        private LoggerInterface $logger,
        private string $mongoUrl,
        private string $mongoDb,
    ) {
    }

    public function estDisponible(): bool
    {
        return extension_loaded('mongodb');
    }

    /**
     * Enregistre (ou met à jour) la commande dans MongoDB.
     * Ne lève jamais d'exception : une panne MongoDB ne doit pas empêcher de commander.
     */
    public function synchroniser(Commande $commande): void
    {
        if (!$this->estDisponible()) {
            $this->logger->warning('Extension PHP mongodb absente : statistiques non synchronisées.');

            return;
        }

        try {
            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->update(
                ['_id' => $commande->getId()],
                ['$set' => $this->versDocument($commande)],
                ['upsert' => true]
            );
            $this->manager()->executeBulkWrite($this->mongoDb . '.' . self::COLLECTION, $bulk);
        } catch (\Throwable $e) {
            $this->logger->error('Synchronisation MongoDB impossible pour la commande {id} : {erreur}', [
                'id' => $commande->getId(),
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    /** Quand un menu est renommé, ses commandes déjà enregistrées prennent le nouveau titre. */
    public function renommerMenu(int $menuId, string $titre): void
    {
        if (!$this->estDisponible()) {
            return;
        }

        try {
            $bulk = new \MongoDB\Driver\BulkWrite();
            $bulk->update(['menuId' => $menuId], ['$set' => ['menuTitre' => $titre]], ['multi' => true]);
            $this->manager()->executeBulkWrite($this->mongoDb . '.' . self::COLLECTION, $bulk);
        } catch (\Throwable $e) {
            $this->logger->error('Renommage du menu {id} dans MongoDB impossible : {erreur}', ['id' => $menuId, 'erreur' => $e->getMessage()]);
        }
    }

    /**
     * Reconstruit entièrement la collection à partir des commandes MySQL.
     *
     * @param iterable<Commande> $commandes
     */
    public function reconstruire(iterable $commandes): int
    {
        $this->verifierDisponible();

        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->delete([]);
        $nombre = 0;
        foreach ($commandes as $commande) {
            $bulk->insert(['_id' => $commande->getId()] + $this->versDocument($commande));
            ++$nombre;
        }
        $this->manager()->executeBulkWrite($this->mongoDb . '.' . self::COLLECTION, $bulk);

        return $nombre;
    }

    /**
     * Nombre de commandes par menu (hors commandes annulées).
     *
     * @return list<array{menuId: int, menu: string, nombreCommandes: int}>
     */
    public function commandesParMenu(?\DateTimeInterface $debut = null, ?\DateTimeInterface $fin = null): array
    {
        $pipeline = [
            ['$match' => $this->filtre(null, $debut, $fin)],
            ['$group' => [
                '_id' => ['menuId' => '$menuId', 'menu' => '$menuTitre'],
                'nombreCommandes' => ['$sum' => 1],
            ]],
            ['$sort' => ['nombreCommandes' => -1]],
        ];

        return array_map(static fn (array $ligne) => [
            'menuId' => (int) $ligne['_id']['menuId'],
            'menu' => (string) $ligne['_id']['menu'],
            'nombreCommandes' => (int) $ligne['nombreCommandes'],
        ], $this->agreger($pipeline));
    }

    /**
     * Chiffre d'affaires par menu, filtrable par menu et par période (hors commandes annulées).
     *
     * @return array{total: float, nombreCommandes: int, parMenu: list<array{menuId: int, menu: string, chiffreAffaires: float, nombreCommandes: int}>}
     */
    public function chiffreAffaires(?int $menuId = null, ?\DateTimeInterface $debut = null, ?\DateTimeInterface $fin = null): array
    {
        $pipeline = [
            ['$match' => $this->filtre($menuId, $debut, $fin)],
            ['$group' => [
                '_id' => ['menuId' => '$menuId', 'menu' => '$menuTitre'],
                'chiffreAffaires' => ['$sum' => '$montantTotal'],
                'nombreCommandes' => ['$sum' => 1],
            ]],
            ['$sort' => ['chiffreAffaires' => -1]],
        ];

        $parMenu = array_map(static fn (array $ligne) => [
            'menuId' => (int) $ligne['_id']['menuId'],
            'menu' => (string) $ligne['_id']['menu'],
            'chiffreAffaires' => round((float) $ligne['chiffreAffaires'], 2),
            'nombreCommandes' => (int) $ligne['nombreCommandes'],
        ], $this->agreger($pipeline));

        return [
            'total' => round(array_sum(array_column($parMenu, 'chiffreAffaires')), 2),
            'nombreCommandes' => array_sum(array_column($parMenu, 'nombreCommandes')),
            'parMenu' => $parMenu,
        ];
    }

    private function versDocument(Commande $commande): array
    {
        $dateCommande = $commande->getDateCommande() ?? new \DateTimeImmutable();

        return [
            'menuId' => $commande->getMenu()?->getId(),
            'menuTitre' => $commande->getMenu()?->getTitleMenu(),
            'userId' => $commande->getUser()?->getId(),
            'nombrePersonne' => (int) $commande->getNombrePersonne(),
            'montantMenu' => round((float) $commande->getPrixMenu() * (int) $commande->getNombrePersonne(), 2),
            'montantLivraison' => (float) $commande->getPrixLivraison(),
            'montantTotal' => $commande->getMontantTotal(),
            'statut' => $commande->getStatut(),
            'dateCommande' => new \MongoDB\BSON\UTCDateTime(\DateTimeImmutable::createFromInterface($dateCommande)),
            'datePrestation' => $commande->getDatePrestation()?->format('Y-m-d'),
        ];
    }

    private function filtre(?int $menuId, ?\DateTimeInterface $debut, ?\DateTimeInterface $fin): array
    {
        $filtre = ['statut' => ['$ne' => Commande::STATUT_ANNULEE]];
        if ($menuId) {
            $filtre['menuId'] = $menuId;
        }
        if ($debut || $fin) {
            $filtre['dateCommande'] = [];
            if ($debut) {
                $filtre['dateCommande']['$gte'] = new \MongoDB\BSON\UTCDateTime(\DateTimeImmutable::createFromInterface($debut)->setTime(0, 0));
            }
            if ($fin) {
                $filtre['dateCommande']['$lte'] = new \MongoDB\BSON\UTCDateTime(\DateTimeImmutable::createFromInterface($fin)->setTime(23, 59, 59));
            }
        }

        return $filtre;
    }

    private function agreger(array $pipeline): array
    {
        $this->verifierDisponible();

        $commande = new \MongoDB\Driver\Command([
            'aggregate' => self::COLLECTION,
            'pipeline' => $pipeline,
            'cursor' => new \stdClass(),
        ]);
        $curseur = $this->manager()->executeCommand($this->mongoDb, $commande);
        $curseur->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);

        return $curseur->toArray();
    }

    private function verifierDisponible(): void
    {
        if (!$this->estDisponible()) {
            throw new \RuntimeException("L'extension PHP \"mongodb\" n'est pas installée sur le serveur.");
        }
    }

    private function manager(): \MongoDB\Driver\Manager
    {
        return $this->manager ??= new \MongoDB\Driver\Manager($this->mongoUrl);
    }
}
