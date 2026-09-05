<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Velt\Kernel\Application;
use Velt\Kernel\Contracts\ApplicationInterface;
use Velt\Kernel\Contracts\CliRuntimeInterface;
use Velt\Kernel\Contracts\HttpRuntimeInterface;
use Velt\Kernel\Contracts\RuntimeInterface;

final class RuntimeContractTest extends TestCase
{
    public function test_runtime_interface_defines_common_lifecycle_methods(): void
    {
        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'container',
            'Velt\\Kernel\\Contracts\\ContainerInterface'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'events',
            'Velt\\Kernel\\Contracts\\EventDispatcherInterface'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'boot',
            'void'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'ready',
            'void'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'bootstrap',
            'void'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'handle',
            'mixed'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'terminate',
            'void'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'fail',
            'void'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'isBootstrapped',
            'bool'
        );

        $this->assertMethodReturnType(
            RuntimeInterface::class,
            'isTerminated',
            'bool'
        );
    }

    public function test_application_interface_extends_runtime_interface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                ApplicationInterface::class,
                RuntimeInterface::class
            )
        );
    }

    public function test_application_is_a_runtime(): void
    {
        $app = new Application(__DIR__);

        $this->assertInstanceOf(
            RuntimeInterface::class,
            $app
        );
    }

    public function test_http_and_cli_runtime_interfaces_extend_runtime_interface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                HttpRuntimeInterface::class,
                RuntimeInterface::class
            )
        );

        $this->assertTrue(
            is_subclass_of(
                CliRuntimeInterface::class,
                RuntimeInterface::class
            )
        );
    }

    private function assertMethodReturnType(
        string $class,
        string $method,
        string $expected
    ): void {
        $returnType = (new ReflectionMethod(
            $class,
            $method
        ))->getReturnType();

        $this->assertInstanceOf(
            \ReflectionType::class,
            $returnType
        );

        $this->assertSame(
            $expected,
            $returnType->getName()
        );
    }
}
