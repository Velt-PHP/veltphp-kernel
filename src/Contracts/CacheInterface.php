<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Contrat de cache portable, sans hypothese de stockage.
 */
interface CacheInterface
{
    /**
     * Retourne une valeur ou la valeur par defaut si elle est absente.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stocke une valeur avec une duree de vie optionnelle en secondes.
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void;

    public function has(string $key): bool;

    public function delete(string $key): void;

    /**
     * Vide le cache gere par cette implementation.
     */
    public function clear(): void;
}
