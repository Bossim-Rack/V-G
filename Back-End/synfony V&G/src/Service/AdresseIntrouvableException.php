<?php

namespace App\Service;

/** Levée quand l'adresse de livraison ne peut pas être localisée (frais de livraison incalculables). */
class AdresseIntrouvableException extends \RuntimeException
{
}
