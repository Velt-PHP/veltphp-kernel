<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Noms d'evenements communs aux runtimes.
 */
interface RuntimeLifecycleEventsInterface
{
    public const BOOTING = 'application.booting';
    public const BOOTED = 'application.booted';
    public const READY = 'application.ready';
    public const BOOTSTRAPPING = 'application.bootstrapping';
    public const BOOTSTRAPPED = 'application.bootstrapped';
    public const PAUSED = 'application.paused';
    public const RESUMED = 'application.resumed';
    public const RESET = 'application.reset';
    public const SHUTTING_DOWN = 'application.shutting_down';
    public const SHUTDOWN = 'application.shutdown';
    public const RUNTIME_FAILED = 'runtime.failed';
    public const REBUILD_REQUESTED = 'runtime.rebuild.requested';
}
