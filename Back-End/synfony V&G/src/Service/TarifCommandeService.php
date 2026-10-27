<?php

namespace App\Service;

use App\Entity\Menu;
use Psr\Log\LoggerInterface;

/**
 * Calcule le prix d'une commande CÔTÉ SERVEUR (le front n'envoie jamais de prix) :
 *  - prix du menu : prix par personne x nombre de personnes,
 *    avec -10 % si on commande pour au moins 5 personnes de plus que le minimum ;
 *  - livraison : gratuite à Bordeaux, sinon 5 € + 0,59 € par kilomètre.
 *
 * La distance est calculée à partir de l'adresse de livraison, géocodée avec le
 * service public de la Géoplateforme (IGN), à vol d'oiseau depuis le centre de Bordeaux.
 */
class TarifCommandeService
{
    public const REDUCTION_TAUX = 0.10;
    public const REDUCTION_SEUIL_PERSONNES = 5;
    public const LIVRAISON_FORFAIT = 5.0;
    public const LIVRAISON_PRIX_KM = 0.59;

    // Point de départ des livraisons : centre de Bordeaux (place de la Comédie).
    private const BORDEAUX_LAT = 44.8425;
    private const BORDEAUX_LON = -0.5747;

    public function __construct(
        private LoggerInterface $logger,
        private string $geocodageUrl,
    ) {
    }

    /**
     * @return array{prixParPersonne: float, prixParPersonneRemise: float, reductionAppliquee: bool,
     *               nombrePersonne: int, prixMenu: float, livraison: array, total: float}
     *
     * @throws \InvalidArgumentException si le nombre de personnes est insuffisant
     * @throws AdresseIntrouvableException si l'adresse ne peut pas être localisée
     */
    public function calculer(Menu $menu, int $nombrePersonne, string $adresse): array
    {
        $minimum = (int) $menu->getNombrePersonneMinimum();
        if ($nombrePersonne < $minimum) {
            throw new \InvalidArgumentException('Le nombre de personnes minimum pour ce menu est de ' . $minimum . '.');
        }

        $prixParPersonne = (float) $menu->getPrixParPersonne();
        $reduction = $nombrePersonne >= $minimum + self::REDUCTION_SEUIL_PERSONNES;
        $prixParPersonneRemise = $reduction ? round($prixParPersonne * (1 - self::REDUCTION_TAUX), 2) : $prixParPersonne;
        $prixMenu = round($prixParPersonneRemise * $nombrePersonne, 2);

        $livraison = $this->calculerLivraison($adresse);

        return [
            'prixParPersonne' => $prixParPersonne,
            'prixParPersonneRemise' => $prixParPersonneRemise,
            'reductionAppliquee' => $reduction,
            'nombrePersonne' => $nombrePersonne,
            'prixMenu' => $prixMenu,
            'livraison' => $livraison,
            'total' => round($prixMenu + $livraison['montant'], 2),
        ];
    }

    /**
     * @return array{montant: float, ville: string, codePostal: ?string, distanceKm: float, horsBordeaux: bool}
     */
    public function calculerLivraison(string $adresse): array
    {
        $lieu = $this->geocoder($adresse);
        $horsBordeaux = $this->normaliser($lieu['ville']) !== 'bordeaux';

        $distanceKm = $horsBordeaux
            ? round($this->distanceKm(self::BORDEAUX_LAT, self::BORDEAUX_LON, $lieu['lat'], $lieu['lon']), 1)
            : 0.0;

        $montant = $horsBordeaux
            ? round(self::LIVRAISON_FORFAIT + self::LIVRAISON_PRIX_KM * $distanceKm, 2)
            : 0.0;

        return [
            'montant' => $montant,
            'ville' => $lieu['ville'],
            'codePostal' => $lieu['codePostal'],
            'distanceKm' => $distanceKm,
            'horsBordeaux' => $horsBordeaux,
        ];
    }

    /**
     * @return array{ville: string, codePostal: ?string, lat: float, lon: float}
     */
    private function geocoder(string $adresse): array
    {
        $adresse = trim($adresse);
        if (mb_strlen($adresse) < 5) {
            throw new AdresseIntrouvableException("L'adresse de livraison est incomplète.");
        }

        $url = $this->geocodageUrl . '?' . http_build_query(['q' => $adresse, 'limit' => 1, 'index' => 'address']);
        $contexte = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $reponse = @file_get_contents($url, false, $contexte);

        if ($reponse === false) {
            $this->logger->error('Géocodage indisponible pour {url}', ['url' => $url]);
            throw new AdresseIntrouvableException("Le calcul des frais de livraison est momentanément indisponible. Réessayez dans quelques instants.");
        }

        $data = json_decode($reponse, true);
        $feature = $data['features'][0] ?? null;
        $ville = $feature['properties']['city'] ?? null;
        $coordonnees = $feature['geometry']['coordinates'] ?? null;
        $score = (float) ($feature['properties']['score'] ?? 0);

        if (!$ville || !is_array($coordonnees) || count($coordonnees) < 2 || $score < 0.4) {
            throw new AdresseIntrouvableException("Adresse de livraison introuvable : vérifiez le numéro, la rue, le code postal et la ville.");
        }

        return [
            'ville' => $ville,
            'codePostal' => $feature['properties']['postcode'] ?? null,
            'lon' => (float) $coordonnees[0],
            'lat' => (float) $coordonnees[1],
        ];
    }

    /** Distance à vol d'oiseau (formule de haversine). */
    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $rayonTerre = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $rayonTerre * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normaliser(string $ville): string
    {
        $ville = mb_strtolower(trim($ville));

        return strtr($ville, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ç' => 'c']);
    }
}
