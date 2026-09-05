<?php

declare(strict_types=1);

namespace Velt\Kernel\Contracts;

use Throwable;

/**
 * Représente un runtime générique du Kernel.
 *
 * Le cycle de vie du runtime est indépendant de la plateforme
 * et peut être utilisé par les runtimes HTTP, CLI, mobile,
 * desktop ou worker.
 */
interface RuntimeInterface extends RuntimeLifecycleEventsInterface
{
    /**
     * Retourne le conteneur principal.
     */
    public function container(): ContainerInterface;

    /**
     * Retourne le dispatcher d'événements.
     */
    public function events(): EventDispatcherInterface;

    /**
     * Retourne le scope de l'interaction actuellement executee.
     *
     * Le scope n'est disponible que pendant handle().
     */
    public function requestScope(): RequestScopeInterface;

    /**
     * Demarre les services du runtime. Ce hook est idempotent.
     */
    public function boot(): void;

    /**
     * Marque le runtime comme pret a accepter des interactions.
     * Ce hook est idempotent pour permettre aux runtimes CLI et web
     * de l'appeler directement ou depuis bootstrap().
     */
    public function ready(): void;

    /**
     * Prépare le runtime avant son exécution.
     */
    public function bootstrap(): void;

    /**
     * Exécute l'entrée courante du runtime.
     *
     * @return mixed
     */
    public function handle(
        mixed $input = null
    ): mixed;

    /**
     * Termine l'exécution courante du runtime.
     */
    public function terminate(
        mixed $input = null,
        mixed $output = null
    ): void;

    /**
     * Signale un echec fatal et demande la reconstruction au runtime hote.
     *
     * Le Kernel s'arrete proprement mais ne redemarre jamais lui-meme.
     */
    public function fail(Throwable $exception, string $phase = 'runtime'): void;

    /**
     * Met le runtime en pause sans détruire son instance.
     */
    public function pause(): void;

    /**
     * Reprend un runtime précédemment mis en pause.
     */
    public function resume(): void;

    /**
     * Réinitialise les états temporaires du runtime.
     *
     * Les services applicatifs persistants restent disponibles,
     * sauf s'ils sont explicitement déclarés comme réinitialisables.
     */
    public function reset(): void;

    /**
     * Arrête définitivement l'instance courante du runtime.
     *
     * Une nouvelle instance du runtime doit être créée
     * pour démarrer un nouveau cycle de vie.
     */
    public function shutdown(): void;

    /**
     * Indique si le runtime a atteint l'état prêt.
     */
    public function isReady(): bool;

    /**
     * Indique si le runtime est actuellement en pause.
     */
    public function isPaused(): bool;

    /**
     * Indique si le runtime a été définitivement arrêté.
     */
    public function isShutdown(): bool;

    /**
     * Indique si le runtime a déjà été bootstrapé.
     */
    public function isBootstrapped(): bool;

    /**
     * Indique si le runtime a terminé son exécution courante.
     */
    public function isTerminated(): bool;
}
