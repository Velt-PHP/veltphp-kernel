<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

/**
 * Migration independante du stockage et de la plateforme.
 */
interface MigrationInterface
{
    /**
     * Identifiant stable de la migration.
     */
    public function id(): string;

    /**
     * Applique la migration via l'adaptateur du runtime.
     */
    public function up(): void;

    /**
     * Annule la migration via l'adaptateur du runtime.
     */
    public function down(): void;
}
