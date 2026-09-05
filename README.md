# Velt Kernel

`velt/kernel` is the runtime-independent foundation of the Velt framework. It owns application bootstrapping, dependency resolution, configuration, environment values, service providers, events, exception handling and lifecycle contracts shared by HTTP, CLI, workers and the future embedded Android runtime.

> Status: alpha. Public contracts are actively being stabilized before Velt 1.0.

## Installation

```bash
composer require velt/kernel:^0.1
```

Requirements: PHP 8.2 or newer. The kernel has no mandatory HTTP, database, UI or mobile dependency.

## Core responsibilities

- Build and own the application container.
- Load configuration and environment repositories.
- Register service providers before booting them in deterministic order.
- Dispatch framework and application events.
- Expose environment helpers such as local, testing and production checks.
- Centralize exception reporting/rendering contracts.
- Model bootstrap, handle and terminate phases for multiple runtimes.
- Supply portable contracts for HTTP, CLI, database and platform adapters.

## Create an application

```php
<?php

use Velt\Kernel\Application;

$app = new Application(
    basePath: dirname(__DIR__),
    config: [
        'app' => [
            'name' => 'Example',
            'env' => 'local',
            'debug' => true,
        ],
    ],
);

$app->bootstrap();
$app->boot();
```

`bootstrap()` prepares the runtime and registered foundation. `boot()` executes provider boot hooks after every service has had an opportunity to register. Calling lifecycle operations repeatedly is guarded by application state.

## Dependency container

The container supports bindings, singletons, existing instances, aliases and constructor autowiring.

```php
interface Clock
{
    public function now(): DateTimeImmutable;
}

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

$container = $app->container();
$container->singleton(Clock::class, SystemClock::class);

$clock = $container->get(Clock::class);
```

Use `instance()` for an object created outside the container and `alias()` when two identifiers should resolve the same binding. Resolution errors are reported as kernel exceptions rather than silently returning null.

## Service providers

Providers package a module's registrations and boot work:

```php
<?php

use Velt\Kernel\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->container()->singleton(
            BillingGateway::class,
            StripeBillingGateway::class,
        );
    }

    public function boot(): void
    {
        // Bind listeners or validate configuration after registration.
    }
}

$app->registerProvider(BillingServiceProvider::class);
$app->boot();
```

Registration should be side-effect-light. Network access, migrations and work tied to a specific request do not belong in `register()`.

## Configuration and environment

The config repository uses dot notation:

```php
$app->config()->get('app.name', 'Velt');
$app->config()->set('features.billing', true);
$app->config()->has('database.default');
$all = $app->config()->all();
```

The environment repository loads `.env`-style values and provides typed application environment helpers:

```php
$app->env()->load($app->basePath() . '/.env');
$debug = $app->env()->get('APP_DEBUG', false);

if ($app->isProduction()) {
    // Disable verbose errors.
}
```

Secrets should remain outside version control and must not be copied into logs or mobile artifacts.

## Events

```php
$app->events()->listen(OrderPaid::class, static function (OrderPaid $event): void {
    // React to the domain event.
});

$app->events()->dispatch(new OrderPaid($orderId));
```

Listeners execute through the event dispatcher contract so runtimes and tests can replace the implementation when required.

## Runtime lifecycle

The kernel models a portable sequence:

```text
construct → bootstrap → boot → handle input → terminate
```

`RuntimeInterface` exposes container, events, boot, ready, bootstrap, handle, termination and fatal recovery. Specialized marker contracts distinguish HTTP, CLI and platform runtimes. Worker lifecycle contracts prepare long-running processes where request state must be reset between interactions.

For Android, the target lifecycle adds pause/resume/reset semantics without placing Android classes inside this package. The actual implementation belongs in `velt/native`, while this kernel remains portable.

## Exception handling

`ExceptionHandlerInterface` separates reporting from rendering. Development environments may produce detailed diagnostics; production renderers must avoid stack traces, paths and secret values.

```php
try {
    $result = $app->handle($input);
} catch (Throwable $exception) {
    $result = $app->exceptions()->handle($exception, $input);
}
```

## Public contracts

Important extension points include:

- `ApplicationInterface`
- `ContainerInterface`
- `ConfigRepositoryInterface`
- `EnvRepositoryInterface`
- `EventDispatcherInterface`
- `ExceptionHandlerInterface`
- `RuntimeStateInterface`, `RuntimeLifecycleEventsInterface`
- `ApplicationScopeInterface`, `RequestScopeInterface`, `PreviewSessionScopeInterface`
- `ExecutionQueueInterface`, `ExecutionTaskInterface`, `CancellationTokenInterface`
- `RuntimeFailureInterface`, `MigrationInterface`, `MigrationRunnerInterface`, `CacheInterface`
- `ServiceProviderInterface`
- `RuntimeInterface`, `HttpRuntimeInterface`, `CliRuntimeInterface`
- `PlatformInterface`, `MobilePlatformInterface`, `DesktopPlatformInterface`
- `DatabaseManagerInterface`, `ConnectionInterface`, `DriverInterface`
- `ArrayableInterface`, `JsonableInterface`, `RenderableInterface`

Applications should depend on contracts where substitution matters and avoid reaching into package internals.

### Runtime scopes

`ApplicationScopeInterface` is reserved for singleton services that live for the lifetime of the Kernel. `RequestScopeInterface` is created for one `handle()` call and is cleared even when execution fails. `PreviewSessionScopeInterface` may survive multiple interactions, but its `destroy()` method must be called when the preview session ends so registered resources are released.

Request and preview state must not be stored in application singletons. Android lifecycle objects such as `Activity` and `Context` are not Kernel services; their adapters belong in `velt/native`.

### Portable execution and recovery

`ExecutionQueueInterface` defines scheduling, cancellation and expiration without assuming a thread or event loop. Each runtime supplies the concrete executor. `Application::fail()` closes the current Kernel instance and dispatches `runtime.rebuild.requested`; the host runtime must create a new instance when appropriate. The Kernel never restarts itself.

### Extension contracts

`ConfigRepositoryInterface`, `EventDispatcherInterface`, `CacheInterface`, `MigrationInterface` and `MigrationRunnerInterface` are platform-neutral extension points. Storage, Android integration and instrumentation remain outside this package.

## Testing and quality

```bash
composer install
composer test
composer analyse
composer cs:check
composer rector:dry-run
```

Tests cover container resolution, lifecycle state, providers, configuration, environment values, events, exception handling and the portable contracts.

## Security and performance

- Do not autowire untrusted class names.
- Avoid retaining request, Activity or user state in process-wide singletons.
- Keep provider boot deterministic and observable.
- Redact exception context before reporting in production.
- Long-running runtimes must reset scoped state and terminate resources cleanly.

## Versioning and contribution

Kernel changes affect every Velt package. Public contract changes require tests, an upgrade note and integration evidence. New runtime-specific behavior should live behind a portable contract and be implemented in the owning adapter repository.

## License

MIT
