<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Evenements transverses disponibles pour les extensions du Kernel.
 */
interface KernelExtensionEventsInterface
{
    public const CONFIGURATION_LOADED = 'kernel.configuration.loaded';
    public const MIGRATION_STARTED = 'kernel.migration.started';
    public const MIGRATION_COMPLETED = 'kernel.migration.completed';
    public const MIGRATION_FAILED = 'kernel.migration.failed';
    public const CACHE_READ = 'kernel.cache.read';
    public const CACHE_WRITTEN = 'kernel.cache.written';
    public const CACHE_CLEARED = 'kernel.cache.cleared';
}
