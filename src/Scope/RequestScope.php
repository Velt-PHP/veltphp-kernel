<?php

declare(strict_types=1);

namespace Velt\Kernel\Scope;

use Velt\Kernel\Contracts\RequestScopeInterface;
use Velt\Kernel\Exceptions\ServiceNotFoundException;

/**
 * Scope jetable pour une interaction unique.
 */
final class RequestScope implements RequestScopeInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->has($key)) {
            if ($default !== null) {
                return $default;
            }

            throw new ServiceNotFoundException(
                "Request scope value not found: {$key}"
            );
        }

        return $this->values[$key];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function all(): array
    {
        return $this->values;
    }
}
