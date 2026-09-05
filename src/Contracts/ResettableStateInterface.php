<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Représente un état temporaire pouvant être réinitialisé.
 *
 * Les implémentations permettent au Kernel de réinitialiser
 * les états liés à l'exécution sans affecter les services
 * applicatifs persistants du conteneur.
 */
interface ResettableStateInterface
{
    /**
     * Réinitialise l'état temporaire.
     */
    public function reset(): void;
}