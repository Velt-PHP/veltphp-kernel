<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Etats observables d'une tache d'execution.
 */
enum ExecutionTaskStatus: string
{
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
