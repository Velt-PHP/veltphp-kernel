<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Registre des services applicatifs persistants du Kernel.
 *
 * Les services inscrits ici vivent pendant toute la vie de l'instance
 * Application et ne font pas partie des etats geres par reset().
 */
interface ApplicationScopeInterface
{
    /**
     * Enregistre une instance singleton explicitement persistante.
     */
    public function instance(string $id, object $service): void;

    /**
     * Indique si un service persistant est enregistre.
     */
    public function has(string $id): bool;

    /**
     * Retourne un service persistant.
     *
     * @throws \Velt\Kernel\Exceptions\ServiceNotFoundException
     */
    public function get(string $id): object;

    /**
     * Retourne tous les services persistants.
     *
     * @return array<string, object>
     */
    public function all(): array;
}
