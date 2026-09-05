# Velt Kernel

## Vue d’ensemble

Le Velt Kernel est la couche fondamentale du framework Velt.

Il constitue le **socle d’exécution (runtime)** sur lequel tous les autres modules reposent.

Son rôle est de fournir une infrastructure minimale, stable et indépendante permettant d’initialiser et d’orchestrer une application Velt.

Le Kernel est volontairement :

- indépendant des chemins locaux et des couplages circulaires
- minimaliste
- testable en isolation
- extensible via contrats et providers

---

## Philosophie

Le Kernel repose sur les principes suivants :

- **Simplicité** : uniquement ce qui est essentiel au fonctionnement du framework
- **Isolation** : aucun couplage fort ni dépendance circulaire
- **Extensibilité** : tout passe par des contrats et des extensions propres
- **Testabilité** : le kernel doit fonctionner seul, sans autre composant
- **Prévisibilité** : pas de magie cachée, comportement explicite

---

## Responsabilités du Kernel

Le Kernel est responsable de :

### 1. Cycle de vie de l’application

Il définit les étapes principales d’exécution :

- initialisation (bootstrap)
- enregistrement des services
- démarrage des services (boot)
- exécution du runtime
- terminaison

---

### 2. Configuration

Il fournit un système simple de gestion de configuration (repository).

---

### 3. Container de services

Un conteneur minimal de dépendances permettant :

- d’enregistrer des services
- de les résoudre
- de gérer des instances partagées

---

### 4. Service Providers

Le Kernel permet d’étendre le framework via des providers.

Chaque module peut enregistrer ses services proprement.

---

### 5. Système d’événements (synchrone)

Un dispatcher simple permettant la communication interne entre composants.

---

### 6. Gestion des exceptions

Un gestionnaire centralisé pour uniformiser les erreurs du framework.

---

### 7. Environnement

Chargement des variables d’environnement (`.env`) et gestion des modes :

- local
- testing
- production

---

## 8. Contracts fondamentaux

### Contrats runtime et scopes

Le cycle de vie portable est défini par `RuntimeInterface`: `boot()`, `ready()`, `bootstrap()`, `handle()`, `pause()`, `resume()`, `reset()`, `terminate()`, `shutdown()` et `fail()`. Les événements associés sont centralisés dans `RuntimeLifecycleEventsInterface`.

Les services applicatifs persistants utilisent `ApplicationScopeInterface`. `RequestScopeInterface` est limité à une interaction et `PreviewSessionScopeInterface` peut survivre à plusieurs interactions jusqu'à l'appel explicite de `destroy()`.

Les contrats `ExecutionQueueInterface`, `ExecutionTaskInterface` et `CancellationTokenInterface` ne définissent aucun thread ni mécanisme Android. Les runtimes CLI, web et Android fournissent leurs implémentations concrètes.

Les contrats `CacheInterface`, `MigrationInterface`, `MigrationRunnerInterface` et `KernelExtensionEventsInterface` restent également indépendants de la plateforme.

Le Kernel définit un ensemble de contrats publics qui servent de langage commun entre tous les modules du framework Velt.

Ces contrats ne contiennent aucune logique métier : ils définissent uniquement les règles de communication.

### Liste des contrats publics

1. **ApplicationInterface**

Représente l'application Velt en cours d'execution, il definit le noyau public de l'application.

- accès container
- accès configuration
- accès au chemin racine
- gestion environement minimale

Elle expose :

```array
- basePatch() : chemin racine du projet
- container() : container service
- config() : repository de configuration
- environment() : environnement courant
```

et permet de detecter :

```array
- isLocal()
- isProduction()
- isTesting()
```

2. **ContainerInterface**

Représente le système d’injection de dépendances.

Permet :

```array
- bind() : lier un service
- singleton() : service partagé
- make() : résoudre un service
- has() : verifier une entrée
```

3. **ConfigRepositoryInterface**

Représente le système de configuration.

Permet :

```array
- get(string $key, mixed $default = null)
- set(string $key, mixed $value)
- has(string $key)
- all(): array
```

Les clés utilisent la notation par points :

```text
database.connections.mysql.host
```

4. **ArrayableInterface**

Permet de convertir un objet en tableau.

```text
toArray(): array
```

5. **JsonableInterface**

JsonableInterface peut retourner :

- une chaîne JSON déjà encodée ;
- un tableau PHP qui sera encodé par le transport HTTP.

Exemples :

```php
return '{"message":"ok"}';

ou

return [
    'message' => 'ok',
];
```

6. **RenderableInterface**

Permet de rendre un objet en chaîne de caractères.

```text
render(): string
```

### Role des contrats

Les contrats du Kernel :

- définissent le langage du framework
- permettent la communication entre modules
- évitent le couplage entre implémentations
- servent de base à toutes les futures implémentations

### Règles importantes

Les contrats :

- ne contiennent aucune logique métier
- ne dépendent d’aucun module Velt
- doivent rester stables dans le temps
- constituent l’API publique du Kernel

---

## 9. Container de services

Le Kernel fournit un container de services minimal permettant d’enregistrer et résoudre des dépendances internes du framework.

Le container constitue le cœur du runtime Velt.

Tous les futurs modules du framework (HTTP, CLI, UI, Database, Preview, Events, Config) utiliseront ce container pour communiquer et résoudre leurs dépendances.

### Objectif du container

Le container Velt a pour rôle de :

- centraliser les services internes ;
- gérer les dépendances partagées ;
- permettre une architecture découplée ;
- fournir une base stable au runtime du framework.

Le container actuel est volontairement minimaliste afin de rester :

- prévisible ;
- simple à maintenir ;
- testable ;
- indépendant des autres modules.

### Fonctionnalités disponibles

Le container supporte actuellement :

| Méthode       | Rôle                              |
| ------------- | --------------------------------- |
| `bind()`      | Enregistre un service             |
| `singleton()` | Enregistre un service partagé     |
| `instance()`  | Enregistre une instance existante |
| `get()`       | Résout un service                 |
| `has()`       | Vérifie l’existence d’un service  |

### Fonctionnement interne

Le container repose sur trois registres internes :

| Registre      | Rôle                                 |
| ------------- | ------------------------------------ |
| `$bindings`   | Stocke les resolvers des services    |
| `$instances`  | Stocke les instances déjà résolues   |
| `$singletons` | Indique quels services sont partagés |

### Exemples d’utilisation

#### 1. Enregistrer un service simple

```php
use Velt\Kernel\Container;

$container = new Container();

$container->bind(
    'config',
    fn () => new ConfigRepository()
);

$config = $container->get('config');
$container->singleton(
    'app',
    fn () => new Application()
);
```

#### 2. Enregistrer un singleton

```php
$container->singleton(
    'app',
    fn () => new Application()
);

$app1 = $container->get('app');
$app2 = $container->get('app');

var_dump($app1 === $app2); // true
```

#### 3. Enregistrer une instance existante

```php
$app = new Application();

$container->instance('app', $app);

$resolved = $container->get('app');
```

---

## La gestion d'erreur

Si un service demandé n’existe pas, le container lance :

```php

Velt\Kernel\Exceptions\ServiceNotFoundException

```

## 10. Application Runtime et Bootstrap

Le Kernel fournit une classe `Application` représentant le runtime principal de Velt.

Cette classe centralise :

- le chemin racine du projet ;
- le container de services ;
- la configuration ;
- l’environnement courant ;
- les bindings système de base.

---

### Création d’une application

```php
use Velt\Kernel\Application;

$app = new Application(
    basePath: __DIR__,
    config: [
        'app' => [
            'name' => 'Velt',
            'env' => 'local',
        ],
    ]
);
```

### Base path

Le chemin racine de l’application est accessible via :

```php

$app->basePath();

```

### Configuration

Le runtime expose un repository de configuration :

```php

$config = $app->config();

```

Lecture :

```php

$config->get('app.name');


```

Valeur par défaut :

```php

$config->get('app.timezone', 'UTC');

```

---

## 10. Service Providers et Lifecycle

Le Kernel fournit désormais un système minimal de Service Providers permettant aux modules Velt de s’enregistrer proprement dans l’application.

Cette architecture permet de garder le Kernel découplé des couches HTTP, CLI, Database ou Preview, tout en laissant des packages UI autonomes s’enregistrer via des providers.

### Objectif des Service Providers

Les Service Providers servent à :

- enregistrer des services dans le container ;
- démarrer des composants après initialisation ;
- étendre l’application sans couplage fort ;
- standardiser le cycle de vie des modules Velt.

Chaque module futur pourra exposer son propre provider :

```php
$app->registerProvider(HttpServiceProvider::class);

$app->registerProvider(DatabaseServiceProvider::class);
```

### Cycle de vie de l'application

Le cycle de vie minimal d’une application Velt est désormais :

```bash

1. load environment : Charge les variables d’environnement (.env) et définit le mode de l’application (local, testing, production).

2. load configuration : Charge les fichiers/configurations nécessaires au fonctionnement de l’application.

3. register providers : Enregistre tous les services et dépendances dans le container.

4. boot providers : Démarre et initialise les services après leur enregistrement.

5. handle request or command : Exécute la requête HTTP, la commande CLI ou le runtime demandé.

6. send response or output : Retourne une réponse HTTP, un JSON, un affichage console ou une sortie runtime.

7. terminate : Termine proprement le cycle d’exécution et libère les ressources si nécessaire.

```

### Contract ServiceProviderInterface

le kernel expose maintenant :

```php
Velt\Kernel\Contracts\ServiceProviderInterface
```

avec deux méthodes :

```php
register(): void

boot(): void
```

- register() : enregistrer des servives dans le container

exemple :

```php
$this->app
    ->container()
    ->singleton(...);
```

- boot() : démarrer des services après leur enregistrement

elle permet de :

- démarrer des services ;
- initialiser des composants ;
- enregistrer des listeners ;
- finaliser le runtime.

### Classe abstraite ServiceProvider

le kernel fournit une classe abstraite :

```php
Velt\Kernel\ServiceProvider
```

elle fourni l'app directement via : `$this->app`

### Application runtime

La classe `Application` supporte maintenant :

```php
registerProvider()

boot()
```

---

## 11. EventDispatcher (synchrone interne)

Le Kernel intègre désormais un système d’événements synchrone minimal permettant la communication interne entre composants du framework.

Ce système n’est pas le système d’événements applicatif complet (qui sera introduit plus tard). Il est exclusivement destiné aux hooks internes du cycle de vie du Kernel.

### Role

Le dispatcher permet de :

```bash
- enregistrer des listeners sur des événements ;
- déclencher des événements synchrones ;
- exécuter plusieurs listeners pour un même événement ;
- garantir un ordre d’exécution stable ;
- propager les erreurs sans les masquer.
```

### Interface EventDispatcherInterface

Le contrat expose deux méthodes principales :

```php
listen()
```

Permet d’enregistrer un listener sur un événement donné.

```php
listen(string $event, callable $listener): void
```

```php
dispatch()
```

Déclenche un événement et exécute tous les listeners associés.

```php
dispatch(object|string $event, mixed $payload = null): array
```

### Comportement du dispatcher

Le dispatcher garantit :

- exécution synchrone immédiate ;
- ordre d’exécution respecté (FIFO) ;
- propagation des exceptions ;
- absence de dépendances externes ;
- simplicité maximale (pas de queue, async ou middleware).

### Intégration dans le lifecycle

Le EventDispatcher est maintenant intégré au Kernel et utilisé dans le cycle de vie de l’application.

Événements internes du Kernel

Le Kernel émet désormais les événements suivants :

```php
provider.registered
application.booted
```

### Garanties du système

Le EventDispatcher garantit :

- aucun couplage avec HTTP, CLI, UI ou Database ;
- exécution strictement synchrone ;
- comportement déterministe ;
- tests isolés sans dépendances externes.

---

## 12. Gestion des variables d’environnement

Le Kernel intègre un système minimal de gestion des variables d’environnement.

Les variables sont chargées automatiquement depuis un fichier `.env` situé à la racine de l’application.

### Exemple de fichier `.env`

```env
APP_NAME=Velt
APP_ENV=local
APP_DEBUG=true
```

### Repository ENV

Le système repose sur un EnvRepository dédié.

Il permet :

- de lire des variables d’environnement ;
- de définir des valeurs runtime ;
- de charger un fichier .env ;
- de fournir des valeurs par défaut.

### Modes application supportés

Le Kernel reconnaît officiellement les modes suivants :

```bash
local
testing
production
```

### Helpers runtime

```php
$app->environment();

$app->isLocal();

$app->isTesting();

$app->isProduction();

$app->isDebug();
```

### Casting automatique

Les valeurs suivantes sont automatiquement converties :

| Valeur `.env` | Type runtime  |
| ------------- | ------------- |
| `true`        | `bool(true)`  |
| `false`       | `bool(false)` |
| `null`        | `null`        |
| `empty`       | `''`          |

---

## 13. Gestion centralisée des exceptions

Le Kernel intègre un système minimal de gestion centralisée des exceptions.

Ce composant permet :

- de reporter les erreurs ;
- de transformer les exceptions en structure exploitable ;
- de différencier les comportements debug et production ;
- de préparer l’intégration HTTP, CLI et Preview.

### Philosophie

Le Kernel ne génère pas directement :

- de réponse HTTP ;
- de page HTML ;
- de JSON API ;
- de sortie terminal.

Le handler retourne uniquement une structure d’erreur neutre.

Les autres modules Velt seront responsables du rendu final :

| Module  | Transformation  |
| ------- | --------------- |
| HTTP    | HTML / JSON     |
| CLI     | sortie terminal |
| Preview | overlay debug   |
| Mobile  | écran erreur    |

### ExceptionHandlerInterface

Le contrat principal :

```php
use Throwable;

interface ExceptionHandlerInterface
{
    public function report(Throwable $exception): void;

    public function render(
        Throwable $exception,
        mixed $context = null
    ): array;
}
```

### Handler par défaut

Le Kernel fournit :

```php
Velt\Kernel\Exceptions\DefaultExceptionHandler
```

Ce handler :

- reporte les erreurs via error_log
- protège les informations sensibles
- expose les détails uniquement en mode debug

### Sécurité

Le Kernel évite volontairement :

les dumps automatiques ;
l’exposition des secrets ;
les traces complètes en production ;
les dépendances HTTP...

---

### 14. Renforcer le Container (Autowiring + PSR-11 minimal)

#### Objectif

Cette issue transforme le container du Kernel Velt d’un simple registry de services vers un container d’injection de dépendances minimal avec autowiring contrôlé.

L’objectif est d’introduire une résolution automatique des classes tout en gardant un comportement explicite et prévisible.

#### Fonctionnalités ajoutées

##### 1. Autowiring de classes concrètes

Le container peut maintenant résoudre une classe sans binding préalable :

```php
$service = $container->get(UserService::class);
```

Si la classe existe et est instanciable, elle est automatiquement construite.

##### 2. Injection par constructeur (Reflection)

Les dépendances de type objet sont automatiquement résolues :

```php
class UserService
{
    public function __construct(Logger $logger)
    {
    }
}
```

Le container va automatiquement injecter Logger.

##### 3. Support des bindings classiques

Les méthodes existantes restent inchangées :

```bash
bind()
singleton()
instance()
has()
get()
```

##### 4. Singletons persistants

Un service déclaré en singleton est instancié une seule fois :

```php
$container->singleton('logger', Logger::class);
```

##### 5.Résolution intelligente des closures

Les closures reçoivent désormais le container :

```php
$container->bind('service', function (Container $container) {
    return new Service($container->get(Logger::class));
});
```

##### 6. Exceptions explicites

Le container lève des exceptions claires en cas d’erreur :

```bash
Service introuvable
Classe non instanciable
Dépendance scalaire non résoluble
Paramètre constructeur inconnu
```

---

## Ce que le Kernel ne fait PAS

Le Kernel ne doit jamais gérer :

- le routage HTTP
- les commandes CLI
- le rendu UI / templates
- l’accès base de données
- le système de preview mobile
- la logique métier applicative

Ces responsabilités appartiennent à des modules dédiés.

---

## Règles d’architecture

Le Kernel impose une règle stricte de dépendances :

```text
Kernel → noyau central, stable
Modules / packages → s’enregistrent via le Kernel
```

Les dépendances suivantes sont interdites dans le Kernel :

```text
- Velt\Http
- Velt\Cli
- Velt\Database
- Velt\Preview
```

Le package UI autonome `velt/ui` peut etre consomme via un provider dedie, mais le Kernel ne doit ni copier son code ni creer de dependance circulaire.

---

## Installation (future)

Le Kernel sera installé via Composer dans un projet Velt :

```bash
composer require velt/kernel
```

---

## Tests et validation

Le Kernel est validé uniquement via des tests automatisés.

Lancer les tests, depuis le dossier du package :

```bash
cd packages/kernel
vendor/bin/phpunit
```

---

## Exemple de test actuel

```php
use PHPUnit\Framework\TestCase;
use Velt\Kernel\Application;

final class ApplicationTest extends TestCase
{
    public function test_version_est_disponible(): void
    {
        $app = new Application();

        $this->assertSame('0.1.0', $app::VERSION);
    }
}
```

---

## Philosophie des tests

Les tests du Kernel doivent respecter :

```text
aucune dépendance HTTP / CLI / UI / Database
uniquement PHP + PHPUnit + Kernel
utilisation de fixtures pour simuler des comportements
validation du comportement, pas de l’implémentation interne
```

---

## Critères de validation

Le Kernel est considéré comme valide si :

```text
 - l’autoload Composer fonctionne (PSR-4)
 - la classe Application est instanciable
 - les tests PHPUnit passent
 - aucune dépendance vers d’autres modules Velt
 - le Kernel fonctionne en isolation totale
```

SEM KASANGA KISAMU / Lundi 18 Mai 2026 / 17h15
