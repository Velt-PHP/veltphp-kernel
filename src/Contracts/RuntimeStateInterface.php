<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Contrat portable de la machine d'etat du runtime.
 */
interface RuntimeStateInterface extends ResettableStateInterface
{
    public function isReady(): bool;
    public function isPaused(): bool;
    public function isShutdown(): bool;
    public function isBootstrapped(): bool;
    public function isTerminated(): bool;
    public function markReady(): void;
    public function markBootstrapped(): void;
    public function pause(): void;
    public function resume(): void;
    public function markTerminated(): void;
    public function shutdown(): void;
}
