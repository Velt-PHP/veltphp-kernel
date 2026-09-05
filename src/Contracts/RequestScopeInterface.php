<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Etat temporaire associe a une seule interaction du runtime.
 */
interface RequestScopeInterface
{
    /**
     * Stocke une valeur dans le scope courant.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retourne une valeur du scope courant.
     *
     * @throws \Velt\Kernel\Exceptions\ServiceNotFoundException
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Indique si une valeur existe dans le scope courant.
     */
    public function has(string $key): bool;

    /**
     * Supprime explicitement tout l'etat de la requete.
     */
    public function clear(): void;

    /**
     * Retourne l'etat courant du scope.
     *
     * @return array<string, mixed>
     */
    public function all(): array;
}
