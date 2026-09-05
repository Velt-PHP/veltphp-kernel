<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Velt\Kernel\Contracts\CacheInterface;
use Velt\Kernel\Contracts\ConfigRepositoryInterface;
use Velt\Kernel\Contracts\EventDispatcherInterface;
use Velt\Kernel\Contracts\KernelExtensionEventsInterface;
use Velt\Kernel\Contracts\MigrationInterface;
use Velt\Kernel\Contracts\MigrationRunnerInterface;

final class ExtensionContractsTest extends TestCase
{
    public function test_existing_configuration_and_event_contracts_remain_portable(): void
    {
        $this->assertTrue(interface_exists(ConfigRepositoryInterface::class));
        $this->assertTrue(interface_exists(EventDispatcherInterface::class));
        $this->assertStringNotContainsString(
            'Android',
            (string) file_get_contents(
                dirname(__DIR__, 2) . '/src/Contracts/ConfigRepositoryInterface.php'
            )
        );
    }

    public function test_migration_contracts_support_cli_or_web_adapters(): void
    {
        $migration = new class implements MigrationInterface {
            public bool $applied = false;

            public function id(): string
            {
                return '001_create_users';
            }

            public function up(): void
            {
                $this->applied = true;
            }

            public function down(): void
            {
                $this->applied = false;
            }
        };

        $runner = new class implements MigrationRunnerInterface {
            /** @var array<string, bool> */
            private array $states = [];

            public function register(MigrationInterface $migration): void
            {
                $this->states[$migration->id()] = false;
            }

            public function migrate(): void
            {
                foreach ($this->states as $id => $state) {
                    if (! $state) {
                        $this->states[$id] = true;
                    }
                }
            }

            public function rollback(): void
            {
                foreach ($this->states as $id => $state) {
                    if ($state) {
                        $this->states[$id] = false;
                    }
                }
            }

            public function status(): array
            {
                return $this->states;
            }
        };

        $runner->register($migration);
        $runner->migrate();

        $this->assertSame(['001_create_users' => true], $runner->status());
    }

    public function test_cache_contract_exposes_only_generic_operations(): void
    {
        $cache = new class implements CacheInterface {
            /** @var array<string, mixed> */
            private array $items = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->items[$key] ?? $default;
            }

            public function put(string $key, mixed $value, ?int $ttl = null): void
            {
                $this->items[$key] = $value;
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->items);
            }

            public function delete(string $key): void
            {
                unset($this->items[$key]);
            }

            public function clear(): void
            {
                $this->items = [];
            }
        };

        $cache->put('key', 'value', 60);
        $this->assertSame('value', $cache->get('key'));
        $cache->clear();
        $this->assertFalse($cache->has('key'));
    }

    public function test_extension_event_names_are_stable(): void
    {
        $this->assertSame(
            'kernel.configuration.loaded',
            KernelExtensionEventsInterface::CONFIGURATION_LOADED
        );
        $this->assertSame(
            'kernel.migration.failed',
            KernelExtensionEventsInterface::MIGRATION_FAILED
        );
        $this->assertSame(
            'kernel.cache.cleared',
            KernelExtensionEventsInterface::CACHE_CLEARED
        );
    }
}
