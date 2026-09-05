<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * File abstraite d'execution asynchrone.
 *
 * Le Kernel ne definit ni thread, ni event loop, ni strategie de scheduling.
 * Ces details appartiennent au runtime qui implemente ce contrat.
 */
interface ExecutionQueueInterface
{
    /**
     * Planifie une tache.
     *
     * @throws InvalidArgumentException Si l'identifiant est deja planifie.
     */
    public function enqueue(ExecutionTaskInterface $task): void;

    /**
     * Annule une tache planifiee.
     *
     * @throws InvalidArgumentException Si l'identifiant est inconnu.
     */
    public function cancel(string $taskId): void;

    /**
     * Retire la prochaine tache executable a l'instant fourni.
     *
     * Une implementation ne doit pas retourner les taches annulees ou expirees.
     */
    public function dequeue(DateTimeImmutable $now): ?ExecutionTaskInterface;

    public function has(string $taskId): bool;

    /**
     * Annule toutes les taches encore planifiees.
     */
    public function cancelAll(): void;
}
