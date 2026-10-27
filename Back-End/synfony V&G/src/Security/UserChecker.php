<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Appelé par Symfony à chaque authentification (login ET jeton X-AUTH-TOKEN).
 *
 * Les contrôles sont faits en "post-auth", c'est-à-dire APRÈS la vérification du mot de passe :
 * quelqu'un qui ne connaît pas le mot de passe ne peut pas savoir si un compte existe,
 * est désactivé ou n'est pas encore confirmé.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException("Ce compte a été désactivé. Contactez l'administrateur.");
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Veuillez confirmer votre adresse email avant de vous connecter (lien reçu par mail).');
        }
    }
}
