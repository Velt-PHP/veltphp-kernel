<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Scope;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Velt\Kernel\Contracts\PreviewSessionScopeInterface;
use Velt\Kernel\Exceptions\ServiceNotFoundException;
use Velt\Kernel\Scope\PreviewSessionScope;

final class PreviewSessionScopeTest extends TestCase
{
    public function test_session_values_survive_until_session_reset(): void
    {
        $scope = new PreviewSessionScope();

        $scope->set('preview.route', 'home');
        $scope->reset();

        $this->assertInstanceOf(PreviewSessionScopeInterface::class, $scope);
        $this->assertFalse($scope->has('preview.route'));
    }

    public function test_destroy_releases_resources_and_closes_session(): void
    {
        $scope = new PreviewSessionScope();
        $released = [];

        $scope->registerResource('first', static function () use (&$released): void {
            $released[] = 'first';
        });
        $scope->registerResource('second', static function () use (&$released): void {
            $released[] = 'second';
        });

        $scope->destroy();

        $this->assertSame(['second', 'first'], $released);
        $this->assertTrue($scope->isDestroyed());

        $this->expectException(RuntimeException::class);
        $scope->set('after.destroy', true);
    }

    public function test_missing_value_fails_deterministically(): void
    {
        $scope = new PreviewSessionScope();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage(
            'Preview session value not found: missing'
        );

        $scope->get('missing');
    }
}
