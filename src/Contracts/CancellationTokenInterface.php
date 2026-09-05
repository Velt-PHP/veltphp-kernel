<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Signal portable d'annulation d'une execution.
 */
interface CancellationTokenInterface
{
    /**
     * Demande l'annulation de l'execution associee.
     */
    public function cancel(): void;

    /**
     * Indique si l'annulation a ete demandee.
     */
    public function isCancellationRequested(): bool;

    /**
     * Echoue si l'execution doit etre interrompue.
     *
     * @throws \RuntimeException
     */
    public function throwIfCancellationRequested(): void;
}
