<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

use Throwable;

/**
 * Signal portable d'un echec fatal du runtime.
 */
interface RuntimeFailureInterface
{
    /**
     * Retourne l'exception a l'origine de l'echec.
     */
    public function exception(): Throwable;

    /**
     * Retourne la phase pendant laquelle l'echec est survenu.
     */
    public function phase(): string;

    /**
     * Indique si le nettoyage du runtime a termine sans erreur.
     */
    public function cleanupCompleted(): bool;

    /**
     * Retourne l'erreur de nettoyage, si elle existe.
     */
    public function cleanupException(): ?Throwable;
}
