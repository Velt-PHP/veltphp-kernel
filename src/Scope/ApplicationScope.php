<?php

declare(strict_types=1);

namespace Velt\Kernel\Scope;

use Velt\Kernel\Contracts\ApplicationScopeInterface;
use Velt\Kernel\Exceptions\ServiceNotFoundException;

/**
 * Registre explicite des services applicatifs persistants.
 */
final class ApplicationScope implements ApplicationScopeInterface
{
    /**
     * @var array<string, object>
     */
    private array $services = [];

    public function instance(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function get(string $id): object
    {
        if (! $this->has($id)) {
            throw new ServiceNotFoundException(
                "Persistent application service not found: {$id}"
            );
        }

        return $this->services[$id];
    }

    public function all(): array
    {
        return $this->services;
    }
}
