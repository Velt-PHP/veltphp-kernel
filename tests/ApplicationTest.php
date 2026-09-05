<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests;

use PHPUnit\Framework\TestCase;
use Velt\Kernel\Application;
use Velt\Kernel\Config\ConfigRepository;
use Velt\Kernel\Contracts\ApplicationScopeInterface;
use Velt\Kernel\Contracts\RequestScopeInterface;
use Velt\Kernel\Contracts\PreviewSessionScopeInterface;
use Velt\Kernel\Contracts\ConfigRepositoryInterface;
use Velt\Kernel\Contracts\ContainerInterface;
use Velt\Kernel\Contracts\EnvRepositoryInterface;
use Velt\Kernel\Contracts\EventDispatcherInterface;
use Velt\Kernel\Contracts\ApplicationInterface;
use Velt\Kernel\Contracts\ExceptionHandlerInterface;
use Velt\Kernel\Contracts\RuntimeStateInterface;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    /**
     * BLOC 1 : CE BLOQUE CONTIENT LES FONCTIONS DE TEST D'INFORMATIONS GENERALES
     */
    public function test_application_exposes_version(): void
    {
        $this->assertSame(
            '0.1.0',
            Application::VERSION
        );
    }

    public function test_application_can_be_instantiated(): void
    {
        $app = new Application(__DIR__);

        $this->assertInstanceOf(
            Application::class,
            $app
        );
    }

    public function test_application_returns_base_path(): void
    {
        $app = new Application(__DIR__);

        $this->assertSame(
            __DIR__,
            $app->basePath()
        );
    }

    /**
     * BLOC 2 : CE BLOQUE CONTIENT LES FONCTIONS DE TEST DU LYFECYCLE
     */

    public function test_application_starts_in_initial_lifecycle_state(): void
    {
        $app = new Application(__DIR__);

        $this->assertFalse(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isPaused()
        );

        $this->assertFalse(
            $app->isShutdown()
        );
    }

    public function test_application_becomes_ready_after_bootstrap(): void
    {
        $app = new Application(__DIR__);

        $this->assertFalse(
            $app->isReady()
        );

        $app->bootstrap();

        $this->assertTrue(
            $app->isBootstrapped()
        );

        $this->assertTrue(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isPaused()
        );

        $this->assertFalse(
            $app->isShutdown()
        );
    }

    public function test_application_delegates_runtime_state_transitions(): void
    {
        $runtimeState = $this->createMock(RuntimeStateInterface::class);
        $runtimeState->expects($this->once())
            ->method('isBootstrapped')
            ->willReturn(false);
        $runtimeState->expects($this->once())
            ->method('markReady');
        $runtimeState->expects($this->once())
            ->method('markBootstrapped');

        $app = new Application(__DIR__, [], $runtimeState);

        $app->bootstrap();
    }

    public function test_application_ready_hook_is_idempotent(): void
    {
        $app = new Application(__DIR__);
        $readyEvents = 0;

        $app->events()->listen(
            'application.ready',
            static function () use (&$readyEvents): void {
                $readyEvents++;
            }
        );

        $app->boot();
        $app->ready();
        $app->ready();

        $this->assertTrue($app->isReady());
        $this->assertSame(1, $readyEvents);
    }

    public function test_application_cannot_be_ready_before_boot(): void
    {
        $app = new Application(__DIR__);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot mark application as ready before boot.'
        );

        $app->ready();
    }

    public function test_application_cannot_handle_while_paused(): void
    {
        $app = new Application(__DIR__);
        $app->bootstrap();
        $app->pause();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot handle a paused application.'
        );

        $app->handle('input');
    }

    public function test_application_cannot_handle_after_shutdown(): void
    {
        $app = new Application(__DIR__);
        $app->bootstrap();
        $app->shutdown();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot handle a shut down application.'
        );

        $app->handle('input');
    }

    public function test_application_cannot_handle_after_termination_until_reset(): void
    {
        $app = new Application(__DIR__);
        $app->bootstrap();
        $app->terminate();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot handle a terminated application.'
        );

        $app->handle('input');
    }

    public function test_fatal_failure_inside_interaction_still_cleans_request_scope(): void
    {
        $app = new Application(__DIR__);
        $app->events()->listen(
            'application.handling',
            static function () use ($app): void {
                $app->requestScope()->set('temporary', true);
                $app->fail(new RuntimeException('fatal'), 'handle');
            }
        );

        $app->handle('input');

        $this->assertTrue($app->isShutdown());
        $this->expectException(RuntimeException::class);
        $app->requestScope();
    }

    public function test_application_registers_runtime_state_contract(): void
    {
        $runtimeState = $this->createMock(RuntimeStateInterface::class);
        $app = new Application(__DIR__, [], $runtimeState);

        $this->assertSame(
            $runtimeState,
            $app->container()->get(RuntimeStateInterface::class)
        );
    }

    public function test_persistent_application_service_survives_reset(): void
    {
        $app = new Application(__DIR__);
        $scope = $app->container()->get(ApplicationScopeInterface::class);
        $service = new \stdClass();

        $this->assertInstanceOf(ApplicationScopeInterface::class, $scope);

        $scope->instance('persistent.service', $service);
        $app->bootstrap();
        $app->reset();

        $this->assertSame($service, $scope->get('persistent.service'));
    }

    public function test_request_scope_is_recreated_between_interactions(): void
    {
        $app = new Application(__DIR__);
        $scopes = [];
        $requestIds = [];

        $this->assertFalse(
            $app->container()->has(RequestScopeInterface::class)
        );

        $app->events()->listen(
            'application.handling',
            function () use ($app, &$scopes, &$requestIds): void {
                $scope = $app->requestScope();
                $requestId = count($scopes) + 1;
                $scope->set('request.id', $requestId);
                $requestIds[] = $scope->get('request.id');
                $scopes[] = $scope;
            }
        );

        $app->handle('first');
        $app->handle('second');

        $this->assertCount(2, $scopes);
        $this->assertNotSame($scopes[0], $scopes[1]);
        $this->assertSame([1, 2], $requestIds);
        $this->assertSame([], $scopes[0]->all());
        $this->assertSame([], $scopes[1]->all());
        $this->assertFalse(
            $app->container()->has(RequestScopeInterface::class)
        );
        $this->expectException(RuntimeException::class);
        $app->requestScope();
    }

    public function test_fatal_failure_shuts_down_and_requests_host_rebuild(): void
    {
        $app = new Application(__DIR__);
        $failure = new RuntimeException('fatal');
        $events = [];

        $app->events()->listen(
            'runtime.failed',
            static function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = [$event, $payload];
            }
        );
        $app->events()->listen(
            'runtime.rebuild.requested',
            static function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = [$event, $payload];
            }
        );

        $app->bootstrap();
        $app->fail($failure, 'handle');
        $app->fail($failure, 'handle');

        $this->assertTrue($app->isShutdown());
        $this->assertSame(
            ['runtime.failed', 'runtime.rebuild.requested'],
            array_column($events, 0)
        );
        $this->assertSame($events[0][1], $events[1][1]);
        $this->assertSame($failure, $events[0][1]->exception());
        $this->assertSame('handle', $events[0][1]->phase());
        $this->assertTrue($events[0][1]->cleanupCompleted());
        $this->assertNull($events[0][1]->cleanupException());
    }

    public function test_preview_session_survives_runtime_reset_without_being_a_request_scope(): void
    {
        $app = new Application(__DIR__);
        $preview = $app->container()->get(PreviewSessionScopeInterface::class);
        $service = new \stdClass();

        $preview->set('preview.state', 'active');
        $app->container()->get(ApplicationScopeInterface::class)
            ->instance('persistent.service', $service);

        $app->bootstrap();
        $app->reset();

        $this->assertSame('active', $preview->get('preview.state'));
        $this->assertSame(
            $service,
            $app->container()->get(ApplicationScopeInterface::class)
                ->get('persistent.service')
        );
        $this->assertFalse(
            $app->container()->has(RequestScopeInterface::class)
        );
    }

    public function test_preview_session_destruction_releases_associated_resources(): void
    {
        $app = new Application(__DIR__);
        $preview = $app->container()->get(PreviewSessionScopeInterface::class);
        $released = false;

        $preview->registerResource(
            'preview.resource',
            static function () use (&$released): void {
                $released = true;
            }
        );

        $preview->destroy();

        $this->assertTrue($released);
        $this->assertTrue($preview->isDestroyed());
    }

    public function test_fatal_failure_preserves_cleanup_error_for_host_rebuild(): void
    {
        $app = new Application(__DIR__);
        $preview = $app->container()->get(PreviewSessionScopeInterface::class);
        $fatal = new RuntimeException('fatal');
        $rebuildFailure = null;

        $preview->registerResource(
            'broken.resource',
            static function (): void {
                throw new RuntimeException('cleanup failed');
            }
        );
        $app->events()->listen(
            'runtime.rebuild.requested',
            static function (mixed $payload) use (&$rebuildFailure): void {
                $rebuildFailure = $payload;
            }
        );

        $app->bootstrap();
        $app->fail($fatal, 'handle');

        $this->assertTrue($app->isShutdown());
        $this->assertNotNull($rebuildFailure);
        $this->assertFalse($rebuildFailure->cleanupCompleted());
        $this->assertNotNull($rebuildFailure->cleanupException());
        $this->assertSame(
            'cleanup failed',
            $rebuildFailure->cleanupException()->getMessage()
        );
    }

    public function test_shutdown_releases_preview_session_resources(): void
    {
        $app = new Application(__DIR__);
        $preview = $app->container()->get(PreviewSessionScopeInterface::class);
        $released = false;

        $preview->registerResource(
            'preview.resource',
            static function () use (&$released): void {
                $released = true;
            }
        );

        $app->bootstrap();
        $app->shutdown();

        $this->assertTrue($released);
        $this->assertTrue($preview->isDestroyed());
    }

    public function test_application_bootstrap_is_idempotent(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $this->assertTrue(
            $app->isReady()
        );

        $app->bootstrap();

        $this->assertTrue(
            $app->isBootstrapped()
        );

        $this->assertTrue(
            $app->isReady()
        );
    }

    public function test_application_can_be_paused_when_ready(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $this->assertTrue(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isPaused()
        );

        $app->pause();

        $this->assertTrue(
            $app->isPaused()
        );

        $this->assertTrue(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isShutdown()
        );
    }

    public function test_application_can_resume_after_pause(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();
        $app->pause();

        $this->assertTrue(
            $app->isPaused()
        );

        $app->resume();

        $this->assertFalse(
            $app->isPaused()
        );

        $this->assertTrue(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isShutdown()
        );
    }

    public function test_application_can_be_reset_when_ready(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $app->reset();

        $this->assertTrue(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isPaused()
        );

        $this->assertFalse(
            $app->isShutdown()
        );
    }

    public function test_application_can_be_reset_while_paused(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();
        $app->pause();

        $this->assertTrue(
            $app->isPaused()
        );

        $app->reset();

        $this->assertFalse(
            $app->isPaused()
        );

        $this->assertTrue(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isShutdown()
        );
    }

    public function test_application_shutdown_is_idempotent(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $app->shutdown();

        $this->assertTrue(
            $app->isShutdown()
        );

        $this->assertFalse(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isPaused()
        );

        $app->shutdown();

        $this->assertTrue(
            $app->isShutdown()
        );

        $this->assertFalse(
            $app->isReady()
        );

        $this->assertFalse(
            $app->isPaused()
        );
    }

    /**
     * BLOC 3 : CE BLOQUE CONTIENT LES FONCTIONS QUI TESTENT LES CAS D'ERREURS SUR LE LYFECYCLE
     */

    public function test_application_cannot_be_paused_before_bootstrap(): void
    {
        $app = new Application(__DIR__);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cannot pause application before it is ready.'
        );

        $app->pause();
    }

    public function test_application_cannot_resume_without_pause(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cannot resume an application that is not paused.'
        );

        $app->resume();
    }

    public function test_application_cannot_be_reset_before_bootstrap(): void
    {
        $app = new Application(__DIR__);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cannot reset an application that is not ready.'
        );

        $app->reset();
    }

    public function test_application_cannot_be_paused_after_shutdown(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();
        $app->shutdown();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cannot pause application before it is ready.'
        );

        $app->pause();
    }

    public function test_application_cannot_resume_after_shutdown(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();
        $app->shutdown();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cannot resume a shut down application.'
        );

        $app->resume();
    }

    public function test_application_cannot_be_reset_after_shutdown(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();
        $app->shutdown();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cannot reset a shut down application.'
        );

        $app->reset();
    }

    /**
     * BLOC 4 : CE BLOQUE CONTIENT LES FONCTIONS DE TESTE DES SERVICES EXPOSEES
     */


    public function test_application_exposes_container(): void
    {
        $app = new Application(__DIR__);

        $this->assertInstanceOf(
            ContainerInterface::class,
            $app->container()
        );
    }

    public function test_application_exposes_config_repository(): void
    {
        $app = new Application(
            __DIR__,
            [
                'app' => [
                    'name' => 'Velt',
                ],
            ]
        );

        $this->assertInstanceOf(
            ConfigRepositoryInterface::class,
            $app->config()
        );

        $this->assertSame(
            'Velt',
            $app->config()->get('app.name')
        );
    }

    public function test_application_exposes_event_dispatcher(): void
    {
        $app = new Application(__DIR__);

        $this->assertInstanceOf(
            EventDispatcherInterface::class,
            $app->events()
        );
    }

    public function test_application_exposes_env_repository(): void
    {
        $app = new Application(__DIR__);

        $this->assertInstanceOf(
            EnvRepositoryInterface::class,
            $app->env()
        );
    }

    /**
     * BLOC 5 : CE BLOQUE CONTIENT LES FONCTIONS DE TEST D'ENVIRONEMENT ET BINDING
     */

    public function test_application_detects_local_environment(): void
    {
        $basePath = sys_get_temp_dir() . '/velt-local-env';

        if (! is_dir($basePath)) {
            mkdir($basePath);
        }

        file_put_contents(
            $basePath . '/.env',
            'APP_ENV=local'
        );

        $app = new Application($basePath);

        $this->assertTrue(
            $app->isLocal()
        );

        $this->assertFalse(
            $app->isProduction()
        );

        $this->assertFalse(
            $app->isTesting()
        );

        unlink($basePath . '/.env');

        rmdir($basePath);
    }

    public function test_application_detects_testing_environment(): void
    {
        $basePath = sys_get_temp_dir() . '/velt-testing-env';

        if (! is_dir($basePath)) {
            mkdir($basePath);
        }

        file_put_contents(
            $basePath . '/.env',
            'APP_ENV=testing'
        );

        $app = new Application($basePath);

        $this->assertTrue(
            $app->isTesting()
        );

        unlink($basePath . '/.env');

        rmdir($basePath);
    }

    public function test_application_detects_production_environment(): void
    {
        $basePath = sys_get_temp_dir() . '/velt-production-env';

        if (! is_dir($basePath)) {
            mkdir($basePath);
        }

        file_put_contents(
            $basePath . '/.env',
            'APP_ENV=production'
        );

        $app = new Application($basePath);

        $this->assertTrue(
            $app->isProduction()
        );

        unlink($basePath . '/.env');

        rmdir($basePath);
    }

    public function test_application_detects_debug_mode(): void
    {
        $basePath = sys_get_temp_dir() . '/velt-debug-env';

        if (! is_dir($basePath)) {
            mkdir($basePath);
        }

        file_put_contents(
            $basePath . '/.env',
            "APP_ENV=local\nAPP_DEBUG=true"
        );

        $app = new Application($basePath);

        $this->assertTrue(
            $app->isDebug()
        );

        unlink($basePath . '/.env');

        rmdir($basePath);
    }

    public function test_application_registers_base_bindings(): void
    {
        $app = new Application(__DIR__);

        $container = $app->container();

        $this->assertSame(
            $app,
            $container->get('app')
        );

        $this->assertInstanceOf(
            ConfigRepository::class,
            $container->get('config')
        );

        $this->assertInstanceOf(
            EventDispatcherInterface::class,
            $container->get('events')
        );

        $this->assertInstanceOf(
            EnvRepositoryInterface::class,
            $container->get('env')
        );
    }

    public function test_application_registers_contract_bindings(): void
    {
        $app = new Application(__DIR__);

        $container = $app->container();

        $this->assertSame(
            $app,
            $container->get(ApplicationInterface::class)
        );

        $this->assertSame(
            $container,
            $container->get(ContainerInterface::class)
        );

        $this->assertSame(
            $app->config(),
            $container->get(ConfigRepositoryInterface::class)
        );

        $this->assertSame(
            $app->env(),
            $container->get(EnvRepositoryInterface::class)
        );

        $this->assertSame(
            $app->events(),
            $container->get(EventDispatcherInterface::class)
        );

        $this->assertSame(
            $app->exceptions(),
            $container->get(ExceptionHandlerInterface::class)
        );
    }
}
