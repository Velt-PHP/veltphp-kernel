<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Orchestrateur abstrait des migrations.
 */
interface MigrationRunnerInterface
{
    /**
     * Enregistre une migration dans l'orchestrateur.
     */
    public function register(MigrationInterface $migration): void;

    /**
     * Applique les migrations en attente.
     */
    public function migrate(): void;

    /**
     * Annule la derniere migration appliquee.
     */
    public function rollback(): void;

    /**
     * @return array<string, bool>
     */
    public function status(): array;
}
