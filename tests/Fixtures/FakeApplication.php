<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Fixtures;

use Velt\Kernel\Contracts\ApplicationInterface;
use Velt\Kernel\Contracts\ConfigRepositoryInterface;
use Velt\Kernel\Contracts\ContainerInterface;
use Velt\Kernel\Contracts\EnvRepositoryInterface;
use Velt\Kernel\Contracts\EventDispatcherInterface;
use Velt\Kernel\Contracts\ExceptionHandlerInterface;
use Velt\Kernel\Contracts\RequestScopeInterface;
use Throwable;
use Velt\Kernel\Contracts\ServiceProviderInterface;

final class FakeApplication implements ApplicationInterface
{
    /**
     * @var array<class-string, ServiceProviderInterface>
     */
    private array $providers = [];

    /**
     * État de préparation du runtime.
     */
    private bool $ready = false;

    /**
     * État de pause du runtime.
     */
    private bool $paused = false;

    /**
     * Indique si le runtime est définitivement arrêté.
     */
    private bool $shutdown = false;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ConfigRepositoryInterface $config,
        private readonly EventDispatcherInterface $events,
        private readonly EnvRepositoryInterface $env,
        private readonly ExceptionHandlerInterface $exceptions,
    ) {}

    public function basePath(): string
    {
        return '/velt';
    }

    public function version(): string
    {
        return 'test';
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function config(): ConfigRepositoryInterface
    {
        return $this->config;
    }

    public function events(): EventDispatcherInterface
    {
        return $this->events;
    }

    public function requestScope(): RequestScopeInterface
    {
        throw new \RuntimeException('No active request scope.');
    }

    public function env(): EnvRepositoryInterface
    {
        return $this->env;
    }

    public function exceptions(): ExceptionHandlerInterface
    {
        return $this->exceptions;
    }

    public function environment(): string
    {
        return 'local';
    }

    public function isLocal(): bool
    {
        return $this->environment() === 'local';
    }

    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    public function isTesting(): bool
    {
        return $this->environment() === 'testing';
    }

    public function isDebug(): bool
    {
        return true;
    }

    public function registerProvider(
        string|ServiceProviderInterface $provider
    ): ServiceProviderInterface {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $this->providers[$provider::class] = $provider;

        return $provider;
    }

    public function hasProvider(
        string $provider
    ): bool {
        return isset(
            $this->providers[$provider]
        );
    }

    public function getProvider(
        string $provider
    ): ?ServiceProviderInterface {
        return $this->providers[$provider]
            ?? null;
    }

    /**
     * @return array<int, ServiceProviderInterface>
     */
    public function providers(): array
    {
        return array_values(
            $this->providers
        );
    }

    public function bootstrap(): void {}

    public function boot(): void {}

    public function ready(): void {}

    public function handle(
        mixed $input = null
    ): mixed {
        return $input;
    }

    public function terminate(
        mixed $input = null,
        mixed $output = null
    ): void {}

    public function fail(Throwable $exception, string $phase = 'runtime'): void {}

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }

    public function reset(): void
    {
        $this->paused = false;
    }

    public function shutdown(): void
    {
        $this->ready = false;
        $this->paused = false;
        $this->shutdown = true;
    }

    public function isBooted(): bool
    {
        return false;
    }

    public function isBootstrapped(): bool
    {
        return false;
    }

    public function isTerminated(): bool
    {
        return false;
    }

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
}
