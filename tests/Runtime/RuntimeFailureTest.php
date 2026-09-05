<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Velt\Kernel\Contracts\RuntimeFailureInterface;
use Velt\Kernel\Runtime\RuntimeFailure;

final class RuntimeFailureTest extends TestCase
{
    public function test_failure_contains_exception_and_phase(): void
    {
        $exception = new RuntimeException('fatal runtime error');
        $failure = new RuntimeFailure($exception, 'handle');

        $this->assertInstanceOf(RuntimeFailureInterface::class, $failure);
        $this->assertSame($exception, $failure->exception());
        $this->assertSame('handle', $failure->phase());
        $this->assertTrue($failure->cleanupCompleted());
        $this->assertNull($failure->cleanupException());
    }
}
