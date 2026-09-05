<?php

declare(strict_types=1);

namespace Velt\Kernel\Scope;

use RuntimeException;
use Throwable;
use Velt\Kernel\Contracts\PreviewSessionScopeInterface;
use Velt\Kernel\Exceptions\ServiceNotFoundException;

/**
 * Scope de session preview partage entre plusieurs interactions.
 */
final class PreviewSessionScope implements PreviewSessionScopeInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @var array<string, callable(): void>
     */
    private array $resources = [];

    private bool $destroyed = false;

    public function set(string $key, mixed $value): void
    {
        $this->assertActive();
        $this->values[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertActive();

        if (! $this->has($key)) {
            if ($default !== null) {
                return $default;
            }

            throw new ServiceNotFoundException(
                "Preview session value not found: {$key}"
            );
        }

        return $this->values[$key];
    }

    public function has(string $key): bool
    {
        $this->assertActive();

        return array_key_exists($key, $this->values);
    }

    public function registerResource(string $id, callable $release): void
    {
        $this->assertActive();
        $this->resources[$id] = $release;
    }

    public function reset(): void
    {
        $this->assertActive();
        $this->values = [];
    }

    public function destroy(): void
    {
        if ($this->destroyed) {
            return;
        }

        $failure = null;

        foreach (array_reverse($this->resources) as $release) {
            try {
                $release();
            } catch (Throwable $exception) {
                $failure ??= $exception;
            }
        }

        $this->values = [];
        $this->resources = [];
        $this->destroyed = true;

        if ($failure !== null) {
            throw $failure;
        }
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    public function all(): array
    {
        $this->assertActive();

        return $this->values;
    }

    private function assertActive(): void
    {
        if ($this->destroyed) {
            throw new RuntimeException(
                'Preview session has been destroyed.'
            );
        }
    }
}
