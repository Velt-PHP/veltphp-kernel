<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Scope;

use PHPUnit\Framework\TestCase;
use Velt\Kernel\Contracts\RequestScopeInterface;
use Velt\Kernel\Exceptions\ServiceNotFoundException;
use Velt\Kernel\Scope\RequestScope;

final class RequestScopeTest extends TestCase
{
    public function test_request_scope_stores_values_for_one_interaction(): void
    {
        $scope = new RequestScope();

        $scope->set('request.id', 'one');

        $this->assertInstanceOf(RequestScopeInterface::class, $scope);
        $this->assertTrue($scope->has('request.id'));
        $this->assertSame('one', $scope->get('request.id'));
    }

    public function test_clear_removes_all_request_state(): void
    {
        $scope = new RequestScope();
        $scope->set('request.id', 'one');

        $scope->clear();

        $this->assertFalse($scope->has('request.id'));
        $this->assertSame([], $scope->all());
    }

    public function test_missing_request_value_fails_deterministically(): void
    {
        $scope = new RequestScope();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(
            'Request scope value not found: missing'
        );

        $scope->get('missing');
    }
}
