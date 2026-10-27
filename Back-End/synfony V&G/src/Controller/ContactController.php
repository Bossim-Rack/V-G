<?php

namespace App\Controller;

use App\Service\MailerService;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/contact', name: 'app_api_contact_')]
class ContactController extends AbstractController
{
    #[Route(name: 'send', methods: 'POST')]
    /** @OA\Post(
     *     path="/api/contact",
     *     summary="Formulaire de contact (public) : la demande est envoyée par mail à l'entreprise",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="titre", type="string", example="Devis pour un mariage"),
     *             @OA\Property(property="description", type="string", example="Bonjour, je souhaiterais..."),
     *             @OA\Property(property="email", type="string", example="visiteur@email.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Message envoyé"),
     *     @OA\Response(response=400, description="Données invalides"),
     *     @OA\Response(response=503, description="Le message n'a pas pu être envoyé")
     * )
     */
    public function send(Request $request, ValidatorInterface $validator, MailerService $mailerService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];

        // Champ piège invisible pour les humains : s'il est rempli, c'est un robot. Méthode trouvée sur un forum (honeypot).
        if (!empty($data['site'])) {
            return new JsonResponse(['message' => 'Votre message a bien été envoyé.']);
        }

        $champs = [
            'titre' => trim((string) ($data['titre'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
        ];

        $erreurs = $validator->validate($champs, new Assert\Collection([
            'titre' => [new Assert\NotBlank(message: 'Le titre est obligatoire.'), new Assert\Length(max: 150, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')],
            'description' => [new Assert\NotBlank(message: 'La description est obligatoire.'), new Assert\Length(min: 10, max: 3000, minMessage: 'La description doit contenir au moins {{ limit }} caractères.', maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')],
            'email' => [new Assert\NotBlank(message: "L'email est obligatoire pour pouvoir vous répondre."), new Assert\Email(message: "L'email n'est pas valide.")],
        ]));

        if (count($erreurs) > 0) {
            $messages = [];
            foreach ($erreurs as $erreur) {
                $champ = trim($erreur->getPropertyPath(), '[]');
                $messages[$champ] ??= $erreur->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_BAD_REQUEST);
        }

        if (!$mailerService->envoyerMessageContact($champs['email'], $champs['titre'], $champs['description'])) {
            return new JsonResponse(
                ['message' => "Votre message n'a pas pu être envoyé pour le moment. Réessayez plus tard ou appelez-nous."],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new JsonResponse(['message' => 'Votre message a bien été envoyé. Nous vous répondrons par email dans les meilleurs délais.']);
    }
}
