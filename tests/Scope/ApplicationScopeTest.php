<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Scope;

use PHPUnit\Framework\TestCase;
use Velt\Kernel\Contracts\ApplicationScopeInterface;
use Velt\Kernel\Exceptions\ServiceNotFoundException;
use Velt\Kernel\Scope\ApplicationScope;

final class ApplicationScopeTest extends TestCase
{
    public function test_scope_is_an_explicit_persistent_service_contract(): void
    {
        $scope = new ApplicationScope();
        $service = new \stdClass();

        $scope->instance('service', $service);

        $this->assertInstanceOf(ApplicationScopeInterface::class, $scope);
        $this->assertSame($service, $scope->get('service'));
        $this->assertSame($service, $scope->get('service'));
    }

    public function test_scope_reports_missing_service_deterministically(): void
    {
        $scope = new ApplicationScope();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(
            'Persistent application service not found: missing'
        );

        $scope->get('missing');
    }

    public function test_scope_lists_only_explicitly_persistent_services(): void
    {
        $scope = new ApplicationScope();
        $service = new \stdClass();

        $scope->instance('service', $service);

        $this->assertSame(['service' => $service], $scope->all());
        $this->assertTrue($scope->has('service'));
        $this->assertFalse($scope->has('temporary.service'));
    }
}
