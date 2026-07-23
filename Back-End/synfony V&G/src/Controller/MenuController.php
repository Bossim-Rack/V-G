<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Repository\PlatRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/menu', name: 'app_api_menu_')]
class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private SerializerInterface $serializer,
        private MenuRepository $repository,
        private PlatRepository $platRepository,
        private ThemeRepository $themeRepository,
        private RegimeRepository $regimeRepository,
    ) {
    }

    /**
     * Représentation "plate" et sûre du menu (évite les références
     * circulaires Menu <-> Plat <-> Allergene / Menu <-> Theme qu'un
     * $serializer->serialize() direct provoquerait).
     * photoMenu suit le même principe que Plat::photoPlat : stocké/renvoyé
     * en data URI base64, directement utilisable comme src d'une <img>.
     */
    private function menuToArray(Menu $menu): array
    {
        $photo = $menu->getPhotoMenu();
        if (is_resource($photo)) {
            $photo = stream_get_contents($photo);
        }
        if (!is_string($photo) || !str_starts_with($photo, 'data:') || !mb_check_encoding($photo, 'UTF-8')) {
            $photo = null;
        }

        return [
            'id' => $menu->getId(),
            'titleMenu' => $menu->getTitleMenu(),
            'nombrePersonneMinimum' => $menu->getNombrePersonneMinimum(),
            'prixParPersonne' => $menu->getPrixParPersonne(),
            'description' => $menu->getDescription(),
            'quantiteRestante' => $menu->getQuantiteRestante(),
            'photoMenu' => $photo,
            'plats' => array_values(array_map(static fn ($plat) => [
                'id' => $plat->getId(),
                'titlePlat' => $plat->getTitlePlat(),
            ], $menu->getPlats()->toArray())),
            'themes' => array_values(array_map(static fn ($theme) => [
                'id' => $theme->getId(),
                'libelle' => $theme->getLibelle(),
            ], $menu->getThemes()->toArray())),
            'regimesAlimentaires' => array_values(array_map(static fn ($regime) => [
                'id' => $regime->getId(),
                'libelle' => $regime->getLibelle(),
            ], $menu->getRegimesAlimentaires()->toArray())),
            'allergenes' => $this->menuAllergenesArray($menu),
        ];
    }

    /**
     * Union des allergènes présents dans les plats du menu, sous forme
     * {id, libelle} directement exploitable pour un filtre côté client
     * (le champ "regime" reste la version texte lisible du même calcul).
     */
    private function menuAllergenesArray(Menu $menu): array
    {
        $allergenes = [];
        foreach ($menu->getPlats() as $plat) {
            foreach ($plat->getAllergenes() as $allergene) {
                $allergenes[$allergene->getId()] = [
                    'id' => $allergene->getId(),
                    'libelle' => $allergene->getLibelle(),
                ];
            }
        }

        return array_values($allergenes);
    }

    #[Route(name: 'index', methods: 'GET')]
    /** @OA\Get(
     *     path="/api/menu",
     *     summary="Lister tous les menus",
     *     @OA\Response(response=200, description="Liste des menus")
     * )
     */
    public function index(): JsonResponse
    {
        $menus = $this->repository->findAll();
        $data = array_map(fn (Menu $menu) => $this->menuToArray($menu), $menus);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route(methods: 'POST')]
    /** @OA\Post(
     *     path="/api/menu",
     *     summary="Créer un menu",
     *     description="plats est un tableau d'IDs de plats existants.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="titleMenu", type="string", example="Le Homard"),
     *             @OA\Property(property="nombrePersonneMinimum", type="integer", example=2),
     *             @OA\Property(property="prixParPersonne", type="number", format="float", example=38.00),
     *             @OA\Property(property="description", type="string", example="Menu raffiné, haut de gamme."),
     *             @OA\Property(property="quantiteRestante", type="integer", example=10),
     *             @OA\Property(property="photoMenu", type="string", example="data:image/png;base64,iVBORw0KGgo..."),
     *             @OA\Property(property="plats", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="themes", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Menu créé avec succès")
     * )
     */
    public function new(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $menu = new Menu();
        $menu->setTitleMenu($data['titleMenu'] ?? '');
        $menu->setNombrePersonneMinimum((int) ($data['nombrePersonneMinimum'] ?? 1));
        $menu->setPrixParPersonne((string) ($data['prixParPersonne'] ?? '0'));
        $menu->setDescription($data['description'] ?? null);
        $menu->setQuantiteRestante(isset($data['quantiteRestante']) ? (int) $data['quantiteRestante'] : null);

        if (!empty($data['photoMenu'])) {
            $menu->setPhotoMenu($data['photoMenu']);
        }

        if (!empty($data['plats']) && is_array($data['plats'])) {
            foreach ($this->platRepository->findBy(['id' => $data['plats']]) as $plat) {
                $menu->addPlat($plat);
            }
        }

        if (!empty($data['themes']) && is_array($data['themes'])) {
            foreach ($this->themeRepository->findBy(['id' => $data['themes']]) as $theme) {
                $menu->addTheme($theme);
            }
        }

        if (!empty($data['regimesAlimentaires']) && is_array($data['regimesAlimentaires'])) {
            foreach ($this->regimeRepository->findBy(['id' => $data['regimesAlimentaires']]) as $regime) {
                $menu->addRegimeAlimentaire($regime);
            }
        }

        $this->manager->persist($menu);
        $this->manager->flush();

        return new JsonResponse($this->menuToArray($menu), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'edit', methods: 'PUT', requirements: ['id' => '\d+'])]
    /** @OA\Put(
     *     path="/api/menu/{id}",
     *     summary="Modifier un menu par ID",
     *     description="Tous les champs sont optionnels. Si plats est fourni, il remplace entièrement la liste des plats du menu.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Menu modifié avec succès"),
     *     @OA\Response(response=404, description="Menu non trouvé")
     * )
     */
    public function edit(int $id, Request $request): JsonResponse
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if (!$menu) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('titleMenu', $data)) {
            $menu->setTitleMenu($data['titleMenu']);
        }
        if (array_key_exists('nombrePersonneMinimum', $data)) {
            $menu->setNombrePersonneMinimum((int) $data['nombrePersonneMinimum']);
        }
        if (array_key_exists('prixParPersonne', $data)) {
            $menu->setPrixParPersonne((string) $data['prixParPersonne']);
        }
        if (array_key_exists('description', $data)) {
            $menu->setDescription($data['description']);
        }
        if (array_key_exists('quantiteRestante', $data)) {
            $menu->setQuantiteRestante($data['quantiteRestante'] !== null ? (int) $data['quantiteRestante'] : null);
        }
        if (!empty($data['photoMenu'])) {
            $menu->setPhotoMenu($data['photoMenu']);
        }
        if (array_key_exists('plats', $data) && is_array($data['plats'])) {
            foreach ($menu->getPlats()->toArray() as $platActuel) {
                $menu->removePlat($platActuel);
            }
            foreach ($this->platRepository->findBy(['id' => $data['plats']]) as $plat) {
                $menu->addPlat($plat);
            }
        }
        if (array_key_exists('themes', $data) && is_array($data['themes'])) {
            foreach ($menu->getThemes()->toArray() as $themeActuel) {
                $menu->removeTheme($themeActuel);
            }
            foreach ($this->themeRepository->findBy(['id' => $data['themes']]) as $theme) {
                $menu->addTheme($theme);
            }
        }
        if (array_key_exists('regimesAlimentaires', $data) && is_array($data['regimesAlimentaires'])) {
            foreach ($menu->getRegimesAlimentaires()->toArray() as $regimeActuel) {
                $menu->removeRegimeAlimentaire($regimeActuel);
            }
            foreach ($this->regimeRepository->findBy(['id' => $data['regimesAlimentaires']]) as $regime) {
                $menu->addRegimeAlimentaire($regime);
            }
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', name: 'show', methods: 'GET', requirements: ['id' => '\d+'])]
    /** @OA\Get(
     *     path="/api/menu/{id}",
     *     summary="Afficher un menu par ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Menu trouvé avec succès"),
     *     @OA\Response(response=404, description="Menu non trouvé")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if ($menu) {
            return new JsonResponse($this->menuToArray($menu), Response::HTTP_OK);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}', name: 'delete', methods: 'DELETE', requirements: ['id' => '\d+'])]
    /** @OA\Delete(
     *     path="/api/menu/{id}",
     *     summary="Supprimer un menu par ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Menu supprimé avec succès"),
     *     @OA\Response(response=404, description="Menu non trouvé")
     * )
     */
    public function delete(int $id): JsonResponse
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if ($menu) {
            $this->manager->remove($menu);
            $this->manager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
