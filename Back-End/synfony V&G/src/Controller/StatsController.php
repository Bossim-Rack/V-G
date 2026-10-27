<?php

namespace App\Controller;

use App\Service\StatsMongoService;
use DateTimeImmutable;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

/**
 * Statistiques de l'espace administrateur. Les données viennent de MongoDB
 * (base non relationnelle), alimentée à chaque commande (voir StatsMongoService).
 * Accès réservé à ROLE_ADMIN (voir access_control dans security.yaml).
 */
#[Route('/api/stats', name: 'app_api_stats_')]
class StatsController extends AbstractController
{
    public function __construct(private StatsMongoService $statsMongo)
    {
    }

    #[Route('/commandes-par-menu', name: 'commandes_par_menu', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/stats/commandes-par-menu",
     *     summary="Nombre de commandes par menu, hors annulations (admin, données MongoDB)",
     *     @OA\Parameter(name="debut", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="fin", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Liste {menuId, menu, nombreCommandes}"),
     *     @OA\Response(response=503, description="MongoDB indisponible")
     * )
     */
    public function commandesParMenu(Request $request): JsonResponse
    {
        [$debut, $fin] = $this->lirePeriode($request);
        if ($debut instanceof JsonResponse) {
            return $debut;
        }

        try {
            return new JsonResponse($this->statsMongo->commandesParMenu($debut, $fin));
        } catch (\Throwable $e) {
            return $this->indisponible($e);
        }
    }

    #[Route('/chiffre-affaires', name: 'chiffre_affaires', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/stats/chiffre-affaires",
     *     summary="Chiffre d'affaires par menu, filtrable par menu et par période (admin, données MongoDB)",
     *     @OA\Parameter(name="menuId", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="debut", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="fin", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="{total, nombreCommandes, parMenu[]}"),
     *     @OA\Response(response=503, description="MongoDB indisponible")
     * )
     */
    public function chiffreAffaires(Request $request): JsonResponse
    {
        [$debut, $fin] = $this->lirePeriode($request);
        if ($debut instanceof JsonResponse) {
            return $debut;
        }
        $menuId = $request->query->getInt('menuId') ?: null;

        try {
            return new JsonResponse($this->statsMongo->chiffreAffaires($menuId, $debut, $fin));
        } catch (\Throwable $e) {
            return $this->indisponible($e);
        }
    }

    /** @return array{0: DateTimeImmutable|JsonResponse|null, 1: ?DateTimeImmutable} */
    private function lirePeriode(Request $request): array
    {
        try {
            $debut = $request->query->get('debut') ? new DateTimeImmutable((string) $request->query->get('debut')) : null;
            $fin = $request->query->get('fin') ? new DateTimeImmutable((string) $request->query->get('fin')) : null;
        } catch (\Exception) {
            return [new JsonResponse(['message' => 'Période invalide (format attendu : AAAA-MM-JJ).'], Response::HTTP_BAD_REQUEST), null];
        }

        if ($debut && $fin && $debut > $fin) {
            return [new JsonResponse(['message' => 'La date de début doit être avant la date de fin.'], Response::HTTP_BAD_REQUEST), null];
        }

        return [$debut, $fin];
    }

    private function indisponible(\Throwable $e): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'Statistiques indisponibles : la base MongoDB ne répond pas (' . $e->getMessage() . ').'],
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }
}
