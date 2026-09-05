<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

use DateTimeImmutable;
use Throwable;

/**
 * Tache portable pouvant etre executee par un runtime.
 */
interface ExecutionTaskInterface
{
    /**
     * Identifiant stable de la tache dans sa file.
     */
    public function id(): string;

    /**
     * Execute la tache avec le signal d'annulation du runtime.
     *
     * @return mixed
     */
    public function execute(CancellationTokenInterface $token): mixed;

    /**
     * Annule la tache avant ou pendant son execution.
     */
    public function cancel(): void;

    public function isCancelled(): bool;

    /**
     * Retourne l'echeance absolue de la tache, si elle en possede une.
     */
    public function expiresAt(): ?DateTimeImmutable;

    /**
     * Indique si la tache est expiree a l'instant fourni.
     */
    public function isExpired(DateTimeImmutable $now): bool;

    public function status(): ExecutionTaskStatus;

    public function markExpired(): void;

    public function markCompleted(): void;

    public function markFailed(Throwable $exception): void;

    public function error(): ?Throwable;
}
