<?php

declare(strict_types=1);

namespace Velt\Kernel\Runtime;

use RuntimeException;
use Velt\Kernel\Contracts\RuntimeStateInterface;

/**
 * Machine d'etat portable du runtime du Kernel.
 */
final class RuntimeState implements RuntimeStateInterface
{
    private bool $ready = false;
    private bool $paused = false;
    private bool $shutdown = false;
    private bool $bootstrapped = false;
    private bool $terminated = false;

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function isShutdown(): bool
    {
        return $this->shutdown;
    }

    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }

    public function isTerminated(): bool
    {
        return $this->terminated;
    }

    public function markReady(): void
    {
        if ($this->shutdown) {
            throw new RuntimeException('Cannot make a shut down runtime ready.');
        }

        $this->ready = true;
    }

    public function markBootstrapped(): void
    {
        if ($this->shutdown) {
            throw new RuntimeException('Cannot bootstrap a shut down runtime.');
        }

        if (! $this->ready) {
            throw new RuntimeException(
                'Cannot mark a runtime as bootstrapped before it is ready.'
            );
        }

        $this->bootstrapped = true;
    }

    public function pause(): void
    {
        if ($this->shutdown) {
            throw new RuntimeException('Cannot pause a shut down runtime.');
        }

        if (! $this->ready) {
            throw new RuntimeException('Cannot pause a runtime before it is ready.');
        }

        if ($this->paused) {
            throw new RuntimeException('Runtime is already paused.');
        }

        $this->paused = true;
    }

    public function resume(): void
    {
        if ($this->shutdown) {
            throw new RuntimeException('Cannot resume a shut down runtime.');
        }

        if (! $this->paused) {
            throw new RuntimeException('Cannot resume a runtime that is not paused.');
        }

        $this->paused = false;
    }

    public function markTerminated(): void
    {
        $this->terminated = true;
    }

    public function shutdown(): void
    {
        if ($this->shutdown) {
            return;
        }

        $this->ready = false;
        $this->paused = false;
        $this->shutdown = true;
    }

    public function reset(): void
    {
        if ($this->shutdown) {
            throw new RuntimeException('Cannot reset a shut down runtime.');
        }

        if (! $this->ready) {
            throw new RuntimeException('Cannot reset a runtime that is not ready.');
        }

        $this->paused = false;
        $this->terminated = false;
    }
}
