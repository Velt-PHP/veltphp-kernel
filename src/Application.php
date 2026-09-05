<?php

declare(strict_types=1);

namespace Velt\Kernel;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Velt\Kernel\Config\ConfigRepository;
use Velt\Kernel\Contracts\ApplicationInterface;
use Velt\Kernel\Contracts\ApplicationScopeInterface;
use Velt\Kernel\Contracts\ConfigRepositoryInterface;
use Velt\Kernel\Contracts\ContainerInterface;
use Velt\Kernel\Contracts\EnvRepositoryInterface;
use Velt\Kernel\Contracts\EventDispatcherInterface;
use Velt\Kernel\Contracts\ExceptionHandlerInterface;
use Velt\Kernel\Contracts\PreviewSessionScopeInterface;
use Velt\Kernel\Contracts\RuntimeLifecycleEventsInterface;
use Velt\Kernel\Contracts\RequestScopeInterface;
use Velt\Kernel\Contracts\RuntimeStateInterface;
use Velt\Kernel\Contracts\ServiceProviderInterface;
use Velt\Kernel\Env\EnvRepository;
use Velt\Kernel\Exceptions\DefaultExceptionHandler;
use Velt\Kernel\Runtime\RuntimeState;
use Velt\Kernel\Runtime\RuntimeFailure;
use Velt\Kernel\Scope\ApplicationScope;
use Velt\Kernel\Scope\RequestScope;
use Velt\Kernel\Scope\PreviewSessionScope;

final class Application implements ApplicationInterface
{
    public const VERSION = '0.1.0';

    private string $basePath;

    public function version(): string
    {
        return self::VERSION;
    }

    private ContainerInterface $container;

    private ConfigRepositoryInterface $config;

    private EventDispatcherInterface $events;

    private EnvRepositoryInterface $env;

    private ExceptionHandlerInterface $exceptions;

    /**
     * @var array<class-string, ServiceProviderInterface>
     */
    private array $providers = [];

    private bool $booted = false;

    private RuntimeStateInterface $runtimeState;

    private ApplicationScopeInterface $applicationScope;

    private ?RequestScopeInterface $requestScope = null;

    private PreviewSessionScopeInterface $previewSession;

    private bool $rebuildRequested = false;

    private bool $scopesClosed = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        string $basePath,
        array $config = [],
        ?RuntimeStateInterface $runtimeState = null,
        ?ApplicationScopeInterface $applicationScope = null,
        ?PreviewSessionScopeInterface $previewSession = null
    ) {
        $this->basePath = rtrim(
            $basePath,
            DIRECTORY_SEPARATOR
        );

        $this->runtimeState = $runtimeState ?? new RuntimeState();
        $this->applicationScope = $applicationScope ?? new ApplicationScope();
        $this->previewSession = $previewSession ?? new PreviewSessionScope();

        $this->container = new Container();

        $this->env = new EnvRepository();

        $this->loadEnvironment();

        $configuration = $this->loadConfigurationFiles();

        $this->config = new ConfigRepository(
            $this->mergeConfiguration(
                $configuration,
                $config
            ),
            $this->env
        );

        $this->events = new EventDispatcher();

        $this->exceptions = new DefaultExceptionHandler(
            $this->isDebug()
        );

        $this->registerBaseBindings();
    }

    public function basePath(): string
    {
        return $this->basePath;
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
        return (string) $this->env->get(
            'APP_ENV',
            'production'
        );
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
        return (bool) $this->env->get(
            'APP_DEBUG',
            false
        );
    }

    public function registerProvider(
        string|ServiceProviderInterface $provider
    ): ServiceProviderInterface {
        if ($this->booted) {
            throw new InvalidArgumentException(
                'Cannot register provider after application boot.'
            );
        }

        if (is_string($provider)) {
            if (isset($this->providers[$provider])) {
                return $this->providers[$provider];
            }

            if (! class_exists($provider)) {
                throw new InvalidArgumentException(
                    "Provider class [$provider] does not exist."
                );
            }

            $instance = $this->instantiateProvider(
                $provider
            );

            if (! $instance instanceof ServiceProviderInterface) {
                throw new InvalidArgumentException(
                    "Provider class [$provider] must implement ServiceProviderInterface."
                );
            }

            $provider = $instance;
        }

        if (isset($this->providers[$provider::class])) {
            return $this->providers[$provider::class];
        }

        $provider->register();

        $this->providers[$provider::class] = $provider;

        $this->events->dispatch(
            'provider.registered',
            $provider
        );

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

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Indique si l'application est prête à accepter des interactions.
     */
    public function isReady(): bool
    {
        return $this->runtimeState->isReady();
    }

    /**
     * Indique si l'application est actuellement en pause.
     */
    public function isPaused(): bool
    {
        return $this->runtimeState->isPaused();
    }

    /**
     * Indique si l'instance de l'application a été définitivement arrêtée.
     */
    public function isShutdown(): bool
    {
        return $this->runtimeState->isShutdown();
    }

    public function isBootstrapped(): bool
    {
        return $this->runtimeState->isBootstrapped();
    }

    public function isTerminated(): bool
    {
        return $this->runtimeState->isTerminated();
    }

    /**
     * Prepare l'application avant execution.
     */
    public function bootstrap(): void
    {
        if ($this->runtimeState->isBootstrapped()) {
            return;
        }

        $this->events->dispatch(RuntimeLifecycleEventsInterface::BOOTSTRAPPING);

        $this->boot();
        $this->ready();
        $this->runtimeState->markBootstrapped();

        $this->events->dispatch(
            RuntimeLifecycleEventsInterface::BOOTSTRAPPED
        );
    }

    public function requestScope(): RequestScopeInterface
    {
        if ($this->requestScope === null) {
            throw new RuntimeException(
                'No active request scope.'
            );
        }

        return $this->requestScope;
    }

    public function ready(): void
    {
        if (! $this->booted) {
            throw new RuntimeException(
                'Cannot mark application as ready before boot.'
            );
        }

        if ($this->runtimeState->isReady()) {
            return;
        }

        $this->runtimeState->markReady();

        $this->events->dispatch(RuntimeLifecycleEventsInterface::READY);
    }

    /**
     * Met l'application en pause sans détruire son instance.
     *
     * @throws RuntimeException Si l'application n'est pas prête.
     */
    public function pause(): void
    {
        if (! $this->runtimeState->isReady()) {
            throw new RuntimeException(
                'Cannot pause application before it is ready.'
            );
        }

        if ($this->runtimeState->isShutdown()) {
            throw new RuntimeException(
                'Cannot pause a shut down application.'
            );
        }

        if ($this->runtimeState->isPaused()) {
            throw new RuntimeException(
                'Application is already paused.'
            );
        }

        $this->runtimeState->pause();

        $this->events->dispatch(
            RuntimeLifecycleEventsInterface::PAUSED
        );
    }

    /**
     * Reprend l'application après une mise en pause.
     *
     * @throws RuntimeException Si l'application n'est pas en pause.
     */
    public function resume(): void
    {
        if ($this->runtimeState->isShutdown()) {
            throw new RuntimeException(
                'Cannot resume a shut down application.'
            );
        }

        if (! $this->runtimeState->isPaused()) {
            throw new RuntimeException(
                'Cannot resume an application that is not paused.'
            );
        }

        $this->runtimeState->resume();

        $this->events->dispatch(
            RuntimeLifecycleEventsInterface::RESUMED
        );
    }

    /**
     * Réinitialise l'état temporaire du runtime.
     *
     * Les services applicatifs persistants et le conteneur
     * sont conservés.
     *
     * @throws RuntimeException Si l'application n'est pas dans
     *                          un état permettant sa réinitialisation.
     */
    public function reset(): void
    {
        if ($this->runtimeState->isShutdown()) {
            throw new RuntimeException(
                'Cannot reset a shut down application.'
            );
        }

        if (! $this->runtimeState->isReady()) {
            throw new RuntimeException(
                'Cannot reset an application that is not ready.'
            );
        }

        $this->runtimeState->reset();

        $this->events->dispatch(
            RuntimeLifecycleEventsInterface::RESET
        );
    }

    /**
     * Arrête définitivement l'instance courante de l'application.
     *
     * Après cette opération, l'instance ne peut plus être reprise.
     * Une nouvelle instance doit être créée pour démarrer un nouveau
     * cycle de vie.
     */
    public function shutdown(): void
    {
        $alreadyShutdown = $this->runtimeState->isShutdown();

        if ($alreadyShutdown && $this->scopesClosed) {
            return;
        }

        if (! $alreadyShutdown) {
            $this->events->dispatch(
                RuntimeLifecycleEventsInterface::SHUTTING_DOWN
            );

            $this->runtimeState->shutdown();
        }

        try {
            if ($this->requestScope !== null) {
                $this->requestScope->clear();
                $this->requestScope = null;
            }

            $this->previewSession->destroy();
            $this->scopesClosed = true;
        } finally {
            if (! $alreadyShutdown) {
                $this->events->dispatch(
                    RuntimeLifecycleEventsInterface::SHUTDOWN
                );
            }
        }
    }

    public function boot(): void
    {
        if ($this->runtimeState->isShutdown()) {
            throw new RuntimeException(
                'Cannot boot a shut down application.'
            );
        }

        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;

        $this->events->dispatch(RuntimeLifecycleEventsInterface::BOOTED);
    }

    /**
     * Point d'entree runtime.
     */
    public function handle(
        mixed $input = null
    ): mixed {
        if ($this->runtimeState->isShutdown()) {
            throw new RuntimeException(
                'Cannot handle a shut down application.'
            );
        }

        if ($this->runtimeState->isPaused()) {
            throw new RuntimeException(
                'Cannot handle a paused application.'
            );
        }

        if ($this->runtimeState->isTerminated()) {
            throw new RuntimeException(
                'Cannot handle a terminated application.'
            );
        }

        $this->bootstrap();

        $requestScope = new RequestScope();
        $this->requestScope = $requestScope;

        try {
            $this->events->dispatch(
                'application.handling',
                $input
            );

            $output = $input;

            $this->events->dispatch(
                'application.handled',
                $output
            );

            return $output;
        } finally {
            $requestScope->clear();

            if ($this->requestScope === $requestScope) {
                $this->requestScope = null;
            }
        }
    }

    /**
     * Termine proprement l'execution.
     */
    public function terminate(
        mixed $input = null,
        mixed $output = null
    ): void {
        if ($this->runtimeState->isTerminated()) {
            return;
        }

        $this->events->dispatch(
            'application.terminating',
            [
                'input' => $input,
                'output' => $output,
            ]
        );

        $this->runtimeState->markTerminated();

        $this->events->dispatch(
            'application.terminated',
            [
                'input' => $input,
                'output' => $output,
            ]
        );
    }

    private function registerBaseBindings(): void
    {
        $this->container->instance(
            'app',
            $this
        );

        $this->container->instance(
            'config',
            $this->config
        );

        $this->container->instance(
            'events',
            $this->events
        );

        $this->container->instance(
            'env',
            $this->env
        );

        $this->container->instance(
            'exceptions',
            $this->exceptions
        );

        $this->container->instance(
            ApplicationInterface::class,
            $this
        );

        $this->container->instance(
            ContainerInterface::class,
            $this->container
        );

        $this->container->instance(
            ConfigRepositoryInterface::class,
            $this->config
        );

        $this->container->instance(
            EnvRepositoryInterface::class,
            $this->env
        );

        $this->container->instance(
            EventDispatcherInterface::class,
            $this->events
        );

        $this->container->instance(
            ExceptionHandlerInterface::class,
            $this->exceptions
        );

        $this->container->instance(
            RuntimeStateInterface::class,
            $this->runtimeState
        );

        $this->container->instance(
            ApplicationScopeInterface::class,
            $this->applicationScope
        );

        $this->container->instance(
            PreviewSessionScopeInterface::class,
            $this->previewSession
        );
    }

    public function fail(Throwable $exception, string $phase = 'runtime'): void
    {
        if ($this->rebuildRequested) {
            return;
        }

        $cleanupException = null;

        try {
            $this->shutdown();
        } catch (Throwable $exceptionDuringCleanup) {
            $cleanupException = $exceptionDuringCleanup;
        }

        $this->rebuildRequested = true;

        $failure = new RuntimeFailure(
            $exception,
            $phase,
            $cleanupException === null,
            $cleanupException
        );

        $this->events->dispatch(
            RuntimeLifecycleEventsInterface::RUNTIME_FAILED,
            $failure
        );

        $this->events->dispatch(
            RuntimeLifecycleEventsInterface::REBUILD_REQUESTED,
            $failure
        );
    }

    /**
     * Charge config/*.php.
     *
     * @return array<string, mixed>
     */
    private function loadConfigurationFiles(): array
    {
        $configPath = $this->basePath
            . DIRECTORY_SEPARATOR
            . 'config';

        if (! is_dir($configPath)) {
            return [];
        }

        $configuration = [];

        $files = glob(
            $configPath
            . DIRECTORY_SEPARATOR
            . '*.php'
        ) ?: [];

        sort($files, SORT_NATURAL);

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $key = basename(
                $file,
                '.php'
            );

            $data = require $file;

            if (! is_array($data)) {
                throw new RuntimeException(
                    sprintf(
                        'Configuration file [%s] must return an array.',
                        $file
                    )
                );
            }

            $configuration[$key] = $this->mergeConfiguration(
                $configuration[$key] ?? [],
                $data
            );
        }

        return $configuration;
    }

    /**
     * @param array<int|string, mixed> $base
     * @param array<int|string, mixed> $overrides
     *
     * @return array<int|string, mixed>
     */
    private function mergeConfiguration(
        array $base,
        array $overrides
    ): array {
        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $base) &&
                is_array($base[$key]) &&
                is_array($value) &&
                $this->canMergeConfigurationArrays(
                    $base[$key],
                    $value
                )
            ) {
                $base[$key] = $this->mergeConfiguration(
                    $base[$key],
                    $value
                );

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<int|string, mixed> $left
     * @param array<int|string, mixed> $right
     */
    private function canMergeConfigurationArrays(
        array $left,
        array $right
    ): bool {
        return (
            (
                $left === [] ||
                ! array_is_list($left)
            ) &&
            (
                $right === [] ||
                ! array_is_list($right)
            )
        );
    }

    private function loadEnvironment(): void
    {
        $envPath = $this->basePath
            . DIRECTORY_SEPARATOR
            . '.env';

        if (! file_exists($envPath)) {
            return;
        }

        $this->env->load($envPath);
    }

    private function instantiateProvider(
        string $provider
    ): object {
        try {
            return new $provider($this);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                "Provider class [$provider] could not be instantiated.",
                0,
                $exception
            );
        }
    }
}
