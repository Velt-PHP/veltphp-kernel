<?php

declare(strict_types=1);

namespace Velt\Kernel\Runtime;

use Throwable;
use Velt\Kernel\Contracts\RuntimeFailureInterface;

/**
 * Valeur immuable de signalement d'un echec fatal.
 */
final class RuntimeFailure implements RuntimeFailureInterface
{
    public function __construct(
        private readonly Throwable $exception,
        private readonly string $phase,
        private readonly bool $cleanupCompleted = true,
        private readonly ?Throwable $cleanupException = null
    ) {
    }

    public function exception(): Throwable
    {
        return $this->exception;
    }

    public function phase(): string
    {
        return $this->phase;
    }

    public function cleanupCompleted(): bool
    {
        return $this->cleanupCompleted;
    }

    public function cleanupException(): ?Throwable
    {
        return $this->cleanupException;
    }
}
