<?php

declare(strict_types=1);

namespace Velt\Kernel\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Velt\Kernel\Contracts\CancellationTokenInterface;
use Velt\Kernel\Contracts\ExecutionQueueInterface;
use Velt\Kernel\Contracts\ExecutionTaskInterface;
use Velt\Kernel\Contracts\ExecutionTaskStatus;

final class ExecutionContractTest extends TestCase
{
    public function test_task_can_be_scheduled_and_dequeued(): void
    {
        $queue = new TestExecutionQueue();
        $task = new TestExecutionTask('task-1');

        $queue->enqueue($task);

        $this->assertTrue($queue->has('task-1'));
        $this->assertSame(
            $task,
            $queue->dequeue(new DateTimeImmutable('2026-01-01 12:00:00'))
        );
    }

    public function test_cancelled_task_is_not_dequeued(): void
    {
        $queue = new TestExecutionQueue();
        $task = new TestExecutionTask('task-1');

        $queue->enqueue($task);
        $queue->cancel('task-1');

        $this->assertNull(
            $queue->dequeue(new DateTimeImmutable('2026-01-01 12:00:00'))
        );
        $this->assertFalse($queue->has('task-1'));
    }

    public function test_expired_task_is_not_dequeued(): void
    {
        $queue = new TestExecutionQueue();
        $task = new TestExecutionTask(
            'task-1',
            new DateTimeImmutable('2026-01-01 11:59:00')
        );

        $queue->enqueue($task);

        $this->assertNull(
            $queue->dequeue(new DateTimeImmutable('2026-01-01 12:00:00'))
        );
        $this->assertFalse($queue->has('task-1'));
        $this->assertSame(ExecutionTaskStatus::EXPIRED, $task->status());
    }

    public function test_task_execution_has_an_observable_completed_status(): void
    {
        $task = new TestExecutionTask('task-1');
        $task->execute(new TestCancellationToken());

        $this->assertSame(ExecutionTaskStatus::COMPLETED, $task->status());
        $this->assertNull($task->error());
    }

    public function test_duplicate_and_unknown_task_ids_fail_deterministically(): void
    {
        $queue = new TestExecutionQueue();
        $queue->enqueue(new TestExecutionTask('task-1'));

        $this->expectException(\InvalidArgumentException::class);
        $queue->enqueue(new TestExecutionTask('task-1'));
    }

    public function test_cancellation_token_is_explicit_and_deterministic(): void
    {
        $token = new TestCancellationToken();

        $this->assertFalse($token->isCancellationRequested());
        $token->cancel();
        $this->assertTrue($token->isCancellationRequested());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Execution cancellation requested.');
        $token->throwIfCancellationRequested();
    }

    public function test_unknown_task_cancellation_fails_deterministically(): void
    {
        $queue = new TestExecutionQueue();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Task not found: missing');
        $queue->cancel('missing');
    }
}

final class TestCancellationToken implements CancellationTokenInterface
{
    private bool $cancelled = false;

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancellationRequested(): bool
    {
        return $this->cancelled;
    }

    public function throwIfCancellationRequested(): void
    {
        if ($this->cancelled) {
            throw new RuntimeException('Execution cancellation requested.');
        }
    }
}

final class TestExecutionTask implements ExecutionTaskInterface
{
    private bool $cancelled = false;

    private ExecutionTaskStatus $taskStatus = ExecutionTaskStatus::PENDING;

    private ?Throwable $taskError = null;

    public function __construct(
        private readonly string $taskId,
        private readonly ?DateTimeImmutable $deadline = null
    ) {
    }

    public function id(): string
    {
        return $this->taskId;
    }

    public function execute(CancellationTokenInterface $token): mixed
    {
        $token->throwIfCancellationRequested();

        $this->markCompleted();

        return $this->taskId;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        $this->taskStatus = ExecutionTaskStatus::CANCELLED;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->deadline;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->deadline !== null && $now >= $this->deadline;
    }

    public function status(): ExecutionTaskStatus
    {
        return $this->taskStatus;
    }

    public function markExpired(): void
    {
        $this->taskStatus = ExecutionTaskStatus::EXPIRED;
    }

    public function markCompleted(): void
    {
        $this->taskStatus = ExecutionTaskStatus::COMPLETED;
    }

    public function markFailed(Throwable $exception): void
    {
        $this->taskError = $exception;
        $this->taskStatus = ExecutionTaskStatus::FAILED;
    }

    public function error(): ?Throwable
    {
        return $this->taskError;
    }
}

final class TestExecutionQueue implements ExecutionQueueInterface
{
    /**
     * @var array<string, ExecutionTaskInterface>
     */
    private array $tasks = [];

    public function enqueue(ExecutionTaskInterface $task): void
    {
        if (isset($this->tasks[$task->id()])) {
            throw new \InvalidArgumentException(
                "Task already queued: {$task->id()}"
            );
        }

        $this->tasks[$task->id()] = $task;
    }

    public function cancel(string $taskId): void
    {
        if (! isset($this->tasks[$taskId])) {
            throw new \InvalidArgumentException(
                "Task not found: {$taskId}"
            );
        }

        $this->tasks[$taskId]->cancel();
    }

    public function dequeue(DateTimeImmutable $now): ?ExecutionTaskInterface
    {
        foreach ($this->tasks as $id => $task) {
            if ($task->isCancelled() || $task->isExpired($now)) {
                if ($task->isExpired($now)) {
                    $task->markExpired();
                }

                unset($this->tasks[$id]);
                continue;
            }

            unset($this->tasks[$id]);

            return $task;
        }

        return null;
    }

    public function has(string $taskId): bool
    {
        return isset($this->tasks[$taskId]);
    }

    public function cancelAll(): void
    {
        foreach ($this->tasks as $task) {
            $task->cancel();
        }
    }
}
