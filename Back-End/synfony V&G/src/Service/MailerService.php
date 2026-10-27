<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Tous les emails automatiques de l'application sont envoyés depuis ici.
 *
 * Un échec d'envoi (serveur SMTP indisponible...) ne doit jamais faire planter
 * l'action de l'utilisateur (inscription, commande...) : on journalise l'erreur
 * et on renvoie false pour que l'appelant puisse le signaler si besoin.
 */
class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFrom,
        private string $contactEmail,
        private string $frontUrl,
    ) {
    }

    /** Mail de bienvenue + lien de confirmation de l'adresse email (inscription). */
    public function envoyerBienvenue(User $user, string $tokenEnClair): bool
    {
        return $this->envoyer(
            $user->getEmail(),
            'Bienvenue chez Vite & Gourmand - confirmez votre adresse email',
            'emails/bienvenue.html.twig',
            [
                'user' => $user,
                'lien' => $this->lienFront('/verifier-email', $tokenEnClair),
            ]
        );
    }

    /** Nouveau lien de confirmation (demandé depuis la page de connexion). */
    public function envoyerVerification(User $user, string $tokenEnClair): bool
    {
        return $this->envoyer(
            $user->getEmail(),
            'Confirmez votre adresse email',
            'emails/verification.html.twig',
            [
                'user' => $user,
                'lien' => $this->lienFront('/verifier-email', $tokenEnClair),
            ]
        );
    }

    public function envoyerReinitialisationMotDePasse(User $user, string $tokenEnClair): bool
    {
        return $this->envoyer(
            $user->getEmail(),
            'Réinitialisation de votre mot de passe',
            'emails/reinitialisation_mot_de_passe.html.twig',
            [
                'user' => $user,
                'lien' => $this->lienFront('/reinitialiser-mot-de-passe', $tokenEnClair),
            ]
        );
    }

    /** Le mot de passe n'est volontairement PAS envoyé (cf. sujet). */
    public function envoyerCompteEmployeCree(User $employe): bool
    {
        return $this->envoyer(
            $employe->getEmail(),
            'Votre compte employé Vite & Gourmand a été créé',
            'emails/compte_employe.html.twig',
            [
                'user' => $employe,
                'lienConnexion' => $this->lienFront('/connexion'),
            ]
        );
    }

    public function envoyerConfirmationCommande(Commande $commande): bool
    {
        return $this->envoyer(
            $commande->getUser()->getEmail(),
            'Confirmation de votre commande n°' . $commande->getId(),
            'emails/confirmation_commande.html.twig',
            ['commande' => $commande]
        );
    }

    /** Commande "Terminée" : invitation à laisser un avis depuis son espace. */
    public function envoyerInvitationAvis(Commande $commande): bool
    {
        return $this->envoyer(
            $commande->getUser()->getEmail(),
            'Votre avis nous intéresse !',
            'emails/invitation_avis.html.twig',
            [
                'commande' => $commande,
                'lienProfil' => $this->lienFront('/profil'),
            ]
        );
    }

    /** Statut "En attente du retour de matériel" : rappel des 10 jours ouvrés / 600 €. */
    public function envoyerRappelRetourMateriel(Commande $commande): bool
    {
        return $this->envoyer(
            $commande->getUser()->getEmail(),
            'Retour du matériel prêté - commande n°' . $commande->getId(),
            'emails/retour_materiel.html.twig',
            ['commande' => $commande]
        );
    }

    public function envoyerAnnulationCommande(Commande $commande): bool
    {
        return $this->envoyer(
            $commande->getUser()->getEmail(),
            'Annulation de votre commande n°' . $commande->getId(),
            'emails/annulation_commande.html.twig',
            ['commande' => $commande]
        );
    }

    /** Formulaire de contact : le message part vers l'adresse de l'entreprise. */
    public function envoyerMessageContact(string $emailVisiteur, string $titre, string $description): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Vite & Gourmand'))
            ->to($this->contactEmail)
            // "Répondre" dans la messagerie de l'entreprise répond directement au visiteur.
            ->replyTo($emailVisiteur)
            ->subject('[Contact] ' . $titre)
            ->htmlTemplate('emails/contact.html.twig')
            ->context([
                'emailVisiteur' => $emailVisiteur,
                'titre' => $titre,
                'description' => $description,
            ]);

        return $this->envoyerEmail($email);
    }

    private function envoyer(string $destinataire, string $sujet, string $template, array $contexte): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, 'Vite & Gourmand'))
            ->to($destinataire)
            ->subject($sujet)
            ->htmlTemplate($template)
            ->context($contexte);

        return $this->envoyerEmail($email);
    }

    private function envoyerEmail(TemplatedEmail $email): bool
    {
        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error("Échec de l'envoi du mail \"{sujet}\" : {erreur}", [
                'sujet' => $email->getSubject(),
                'erreur' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Lien vers une page du front. On passe par la racine du site (/?page=...) :
     * la racine sert toujours index.html, quel que soit le serveur web (Live Server,
     * hébergement statique...), puis le routeur JS ouvre la bonne page.
     */
    private function lienFront(string $chemin, ?string $token = null): string
    {
        $parametres = ['page' => $chemin];
        if ($token !== null) {
            $parametres['token'] = $token;
        }

        return rtrim($this->frontUrl, '/') . '/?' . http_build_query($parametres);
    }
}
