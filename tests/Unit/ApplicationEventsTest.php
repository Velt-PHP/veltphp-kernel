<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Velt\Kernel\Application;
use Velt\Kernel\Contracts\RuntimeLifecycleEventsInterface;
use Velt\Kernel\Tests\Fixtures\FakeServiceProvider;

final class ApplicationEventsTest extends TestCase
{
    public function test_it_dispatches_provider_registered_event(): void
    {
        $app = new Application(__DIR__);

        $called = false;

        $app->events()->listen(
            'provider.registered',
            function () use (&$called): void {
                $called = true;
            }
        );

        $app->registerProvider(
            FakeServiceProvider::class
        );

        $this->assertTrue($called);
    }

    public function test_it_dispatches_application_bootstrap_events_in_order(): void
    {
        $app = new Application(__DIR__);

        $events = [];

        $app->events()->listen(
            'application.bootstrapping',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->events()->listen(
            'application.booted',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->events()->listen(
            RuntimeLifecycleEventsInterface::READY,
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->events()->listen(
            'application.bootstrapped',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->bootstrap();

        $this->assertSame(
            [
                RuntimeLifecycleEventsInterface::BOOTSTRAPPING,
                RuntimeLifecycleEventsInterface::BOOTED,
                RuntimeLifecycleEventsInterface::READY,
                RuntimeLifecycleEventsInterface::BOOTSTRAPPED,
            ],
            $events
        );
    }

    public function test_it_dispatches_application_handle_events_in_order(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $events = [];

        $app->events()->listen(
            'application.handling',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->events()->listen(
            'application.handled',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $result = $app->handle('payload');

        $this->assertSame(
            'payload',
            $result
        );

        $this->assertSame(
            [
                'application.handling',
                'application.handled',
            ],
            $events
        );
    }

    public function test_it_dispatches_application_booted_event(): void
    {
        $app = new Application(__DIR__);

        $called = false;

        $app->events()->listen(
            'application.booted',
            function () use (&$called): void {
                $called = true;
            }
        );

        $app->boot();

        $this->assertTrue($called);
    }

    public function test_it_dispatches_application_terminate_events_in_order(): void
    {
        $app = new Application(__DIR__);

        $app->bootstrap();

        $events = [];

        $app->events()->listen(
            'application.terminating',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->events()->listen(
            'application.terminated',
            function (mixed $payload, object|string $event) use (&$events): void {
                $events[] = $event;
            }
        );

        $app->terminate('input', 'output');

        $this->assertSame(
            [
                'application.terminating',
                'application.terminated',
            ],
            $events
        );
    }
}
