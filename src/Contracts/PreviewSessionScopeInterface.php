<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Etat partage entre plusieurs interactions d'une session de preview.
 */
interface PreviewSessionScopeInterface
{
    /**
     * Stocke une valeur liee a la session de preview.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Retourne une valeur de session.
     *
     * @throws \Velt\Kernel\Exceptions\ServiceNotFoundException
     */
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    /**
     * Enregistre le nettoyage explicite d'une ressource de session.
     */
    public function registerResource(string $id, callable $release): void;

    /**
     * Reinitialise les valeurs de session sans liberer les ressources.
     */
    public function reset(): void;

    /**
     * Libere les ressources et ferme definitivement la session.
     */
    public function destroy(): void;

    public function isDestroyed(): bool;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;
}
