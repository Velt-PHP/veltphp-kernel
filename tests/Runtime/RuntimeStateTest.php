<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Runtime;

use PHPUnit\Framework\TestCase;
use Velt\Kernel\Contracts\ResettableStateInterface;
use Velt\Kernel\Contracts\RuntimeStateInterface;
use Velt\Kernel\Runtime\RuntimeState;

final class RuntimeStateTest extends TestCase
{
    public function test_runtime_state_implements_resettable_state_interface(): void
    {
        $state = new RuntimeState();

        $this->assertInstanceOf(
            ResettableStateInterface::class,
            $state
        );
    }

    public function test_runtime_state_implements_runtime_state_contract(): void
    {
        $this->assertInstanceOf(
            RuntimeStateInterface::class,
            new RuntimeState()
        );
    }

    public function test_runtime_state_starts_in_initial_state(): void
    {
        $state = new RuntimeState();

        $this->assertFalse(
            $state->isReady()
        );

        $this->assertFalse(
            $state->isPaused()
        );

        $this->assertFalse(
            $state->isShutdown()
        );
    }

    public function test_runtime_state_can_be_marked_ready(): void
    {
        $state = new RuntimeState();

        $state->markReady();

        $this->assertTrue(
            $state->isReady()
        );

        $this->assertFalse(
            $state->isPaused()
        );

        $this->assertFalse(
            $state->isShutdown()
        );
    }

    public function test_runtime_state_can_be_paused_and_resumed(): void
    {
        $state = new RuntimeState();

        $state->markReady();
        $state->pause();

        $this->assertTrue(
            $state->isPaused()
        );

        $state->resume();

        $this->assertFalse(
            $state->isPaused()
        );

        $this->assertTrue(
            $state->isReady()
        );
    }

    public function test_runtime_state_reset_clears_pause_only(): void
    {
        $state = new RuntimeState();

        $state->markReady();
        $state->pause();

        $state->reset();

        $this->assertFalse(
            $state->isPaused()
        );

        $this->assertTrue(
            $state->isReady()
        );

        $this->assertFalse(
            $state->isShutdown()
        );
    }

    public function test_runtime_state_shutdown_clears_ready_and_pause(): void
    {
        $state = new RuntimeState();

        $state->markReady();
        $state->pause();

        $state->shutdown();

        $this->assertFalse(
            $state->isReady()
        );

        $this->assertFalse(
            $state->isPaused()
        );

        $this->assertTrue(
            $state->isShutdown()
        );
    }

    public function test_runtime_state_requires_ready_before_bootstrapping(): void
    {
        $state = new RuntimeState();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot mark a runtime as bootstrapped before it is ready.'
        );

        $state->markBootstrapped();
    }

    public function test_runtime_state_rejects_invalid_pause_and_resume_transitions(): void
    {
        $state = new RuntimeState();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot pause a runtime before it is ready.'
        );

        $state->pause();
    }

    public function test_runtime_state_rejects_resume_without_pause(): void
    {
        $state = new RuntimeState();
        $state->markReady();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot resume a runtime that is not paused.'
        );

        $state->resume();
    }

    public function test_runtime_state_rejects_transitions_after_shutdown(): void
    {
        $state = new RuntimeState();
        $state->shutdown();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot make a shut down runtime ready.'
        );

        $state->markReady();
    }

    public function test_runtime_state_tracks_bootstrapped_and_terminated_flags(): void
    {
        $state = new RuntimeState();

        $state->markReady();
        $state->markBootstrapped();
        $state->markTerminated();

        $this->assertTrue($state->isBootstrapped());
        $this->assertTrue($state->isTerminated());
    }

    public function test_runtime_state_reset_clears_current_execution_termination(): void
    {
        $state = new RuntimeState();

        $state->markReady();
        $state->markTerminated();
        $state->reset();

        $this->assertFalse($state->isTerminated());
        $this->assertTrue($state->isReady());
    }
}
