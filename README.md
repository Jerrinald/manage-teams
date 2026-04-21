# Manage Teams — API REST de gestion d'équipes

API REST développée en **Symfony 8.0** et **PHP 8.4** pour la gestion d'équipes, de membres et de tâches. Projet conçu comme un terrain d'exploration des dernières nouveautés du langage et du framework (property hooks, `ObjectMapper`, `#[AsCommand]` invocable, Messenger, UUID v7), avec une attention portée à l'authentification sécurisée, à l'autorisation fine et aux traitements asynchrones.

> Pour une vision détaillée des choix techniques, voir [docs/architecture.md](docs/architecture.md).

---

## Fonctionnalités métier

- **Authentification JWT** (access token + refresh token) avec révocation côté serveur (blocklist sur le `jti`).
- **Inscription** (`POST /api/register`), **login** (`POST /api/login`), **refresh** (`POST /api/token/refresh`), **logout** (révocation immédiate du token courant).
- **Gestion d'équipes** : CRUD complet, un utilisateur peut appartenir à plusieurs équipes avec **un rôle différent par équipe** (Admin / Member / Guest).
- **Gestion des membres** : ajout, listing et retrait — protégé par Voter (`MANAGE_MEMBERS`).
- **Gestion des tâches** : CRUD, affectation à un membre de l'équipe, transitions de statut contrôlées (`Todo → InProgress → Done`, pas de retour arrière).
- **Notifications asynchrones** : dispatch d'un message lors de l'affectation d'une tâche ou d'un changement d'assigné, traitement hors requête HTTP.
- **Rappels de tâches en retard** via commande CLI invocable (`tasks:remind`).
- **Purge automatisable** des tokens révoqués expirés (`tokens:cleanup`).
- **Documentation OpenAPI** auto-générée (Swagger UI sur `/api/doc`).

---

## Stack technique

| Catégorie | Outils |
|---|---|
| Langage / Framework | PHP 8.4, Symfony 8.0 |
| Persistance | Doctrine ORM 3, MySQL, Doctrine Migrations |
| Authentification | LexikJWTAuthenticationBundle, GesdinetJWTRefreshTokenBundle |
| Asynchrone | Symfony Messenger (transport Doctrine) |
| Mapping | Symfony ObjectMapper (attributs `#[Map]`) |
| Validation | Symfony Validator (contraintes sur DTO) |
| Sérialisation | Symfony Serializer + `#[MapRequestPayload]` |
| Identifiants | `symfony/uid` — UUID v7 (`BINARY(16)`) |
| Documentation API | NelmioApiDocBundle (Swagger UI) |
| Outils | Maker Bundle, Symfony Flex, Bruno (collection versionnée) |

---

## Concepts et notions mis en œuvre

### Architecture & organisation du code
- **Séparation stricte DTO / Entity / View** : `Dto/Input` pour l'entrée validée, entités pour le domaine, `Dto/Output` pour ne jamais exposer directement l'entité au client.
- **Repositories typés** avec méthodes métier (`findOneByTeamAndUser`, `findOverdue`, `deleteExpired`) plutôt que du `find()` générique dans les controllers.
- **Controllers fins** : ils orchestrent, la logique métier vit dans les entités (setter hooks), les Voters (sécurité) et les enums (règles de transition et de permission).
- **Injection de dépendances** via constructeurs `readonly` — immutabilité des services.

### Sécurité
- **Authentification JWT stateless** avec Lexik (signature RS256/HS256 selon config) et refresh tokens (rotation).
- **Révocation de token** : à la déconnexion, le `jti` du JWT est inséré dans la table `blocked_tokens`. Un `JwtAuthenticatedListener` branché sur l'event `lexik_jwt_authentication.on_jwt_authenticated` rejette tout token révoqué avant même d'atteindre le controller.
- **Voters Symfony** (`TeamVoter`, `TaskVoter`) pour l'autorisation contextuelle : l'utilisateur peut être Admin de l'équipe A et simple Member de l'équipe B. L'autorisation est calculée au cas par cas, pas via des rôles globaux.
- **Hachage de mot de passe** via `UserPasswordHasherInterface` (algorithme auto).
- **Pattern Voter → Enum** : les Voters délèguent la décision aux méthodes de l'enum `TeamRole` (`canEditTasks()`, `canManageMembers()`). Zéro `if/else` éparpillé, la matrice de permissions est centralisée.
- **Firewall `^/api`** stateless, `access_control` explicite sur les routes publiques (`/api/register`, `/api/login`, `/api/token/refresh`, `/api/doc`).

### Property Hooks
Utilisés comme **gardiens de l'invariant métier**, directement dans l'entité :

```php
// src/Entity/Task.php
public TaskStatus $status {
    set(TaskStatus $next) {
        if (isset($this->status) && !$this->status->canTransitionTo($next)) {
            throw new \DomainException(sprintf(
                'Transition invalide : %s → %s',
                $this->status->value,
                $next->value,
            ));
        }
        $this->status = $next;
    }
}

public \DateTimeImmutable $dueDate {
    set(\DateTimeImmutable|string $value) {
        $this->dueDate = \is_string($value)
            ? new \DateTimeImmutable($value)
            : $value;
    }
}

public string $fullTitle {
    get => strtoupper($this->title).' ['.$this->status->label().']';
}
```

- Setter hook : toute transition invalide lève une `DomainException` (traduite en HTTP 409 par le controller).
- Getter hook : propriété calculée, **non mappée en base**, exposée côté API.

### Backed Enums avec méthodes
- `TaskStatus` : `label()` (i18n manuel FR), `canTransitionTo()` (automate d'état).
- `TeamRole` : `canView()`, `canEditTasks()`, `canManageMembers()` — la matrice des permissions est dans l'enum, le Voter ne fait que déléguer.

### Symfony ObjectMapper
Supprime le boilerplate `$entity->setX($dto->x)` répétitif. Déclaratif via attributs `#[Map]` sur les DTO, avec transformeurs pour les types complexes (dates, UUID, enums).

```php
$user = $objectMapper->map($input, User::class);
return $this->json($objectMapper->map($user, UserView::class), 201);
```

### Commandes invocables (`#[AsCommand]`)
Pas d'héritage de `Command`, juste un `__invoke`. Plus concis, plus testable.

```php
#[AsCommand(name: 'tasks:remind', description: 'Envoie un rappel pour chaque tâche en retard')]
final class RemindOverdueTasksCommand
{
    public function __invoke(MessageBusInterface $bus, TaskRepository $tasks): int { ... }
}
```

### Messenger (traitements asynchrones)
- Transport **Doctrine** (table `messenger_messages`) — aucune infra supplémentaire, switch vers Redis/RabbitMQ en une ligne de `.env`.
- **Messages `readonly`** (DTO immuables) + handlers `#[AsMessageHandler]`.
- Dispatch depuis le controller lors de la création ou d'un changement d'assigné (`TaskAssignedMessage`).
- Dispatch depuis la CLI pour les rappels (`SendTaskReminderMessage`).
- Worker : `php bin/console messenger:consume async -vv` (Supervisor/systemd en prod).

### Identifiants UUID v7
- Stockage `BINARY(16)` (indexable, compact, pas `VARCHAR(36)`).
- **v7 et pas v4** : ordonnés par timestamp → insertions séquentielles, pas de fragmentation d'index B-Tree, cache CPU plus efficace sur les scans récents.
- Sérialisation RFC 4122 automatique dans le JSON.
- Value Resolver natif Symfony pour désérialiser `{id}` en `Uuid` dans les routes.
- Protection implicite contre les IDOR par énumération (contrairement à un ID auto-increment exposé).

### Validation & DTO d'entrée
- `#[MapRequestPayload]` pour désérialiser et valider le body en un seul attribut.
- Contraintes Symfony Validator sur les DTO (`#[Assert\NotBlank]`, `#[Assert\Email]`, `#[Assert\Length]`, etc.).
- Erreurs 422 auto-formatées par Symfony.

### Documentation API
- **Swagger UI** sur [`/api/doc`](http://localhost:8000/api/doc), spec brute sur `/api/doc.json`.
- JWT Bearer configuré en sécurité globale → bouton "Authorize" fonctionnel.
- Opérations hors controllers projet (`/api/login`, `/api/token/refresh`) annotées en YAML.

### Fixtures
- Jeu de données cohérent : 2 équipes, 4 utilisateurs avec rôles variés, utile pour démontrer les règles d'accès via Voter.

---

## Arborescence

```
src/
├── Command/              CleanupBlockedTokensCommand, RemindOverdueTasksCommand (#[AsCommand] invocables)
├── Controller/           Register, Team, Member, Task, Logout
├── Dto/
│   ├── Input/            DTO d'entrée validés
│   └── Output/           DTO de sortie — maîtrise de ce qu'on expose
├── Entity/               User, Team, TeamMember (pivot), Task, BlockedToken, RefreshToken
├── Enum/                 TaskStatus, TeamRole (avec méthodes métier)
├── EventListener/        JwtAuthenticatedListener (révocation), JwtCreatedListener
├── Message/              Messages Messenger readonly
├── MessageHandler/       Handlers #[AsMessageHandler]
├── Repository/           Méthodes métier typées
└── Security/Voter/       TeamVoter, TaskVoter (autorisation contextuelle par équipe)
```

---

## Endpoints principaux

| Méthode | URL | Description | Auth |
|---|---|---|---|
| POST | `/api/register` | Création de compte | Public |
| POST | `/api/login` | Login → access + refresh token | Public |
| POST | `/api/token/refresh` | Rotation du refresh token | Public |
| POST | `/api/logout` | Révoque le token courant | JWT |
| GET / POST | `/api/teams` | Liste / création d'équipe | JWT + Voter |
| PATCH / DELETE | `/api/teams/{id}` | Modifier / supprimer une équipe | JWT + Voter |
| GET / POST | `/api/teams/{id}/members` | Listing / ajout de membres | JWT + Voter |
| DELETE | `/api/teams/{id}/members/{memberId}` | Retirer un membre | JWT + Voter |
| GET / POST | `/api/teams/{id}/tasks` | Listing / création de tâche | JWT + Voter |
| GET / PATCH / DELETE | `/api/tasks/{id}` | Lire / modifier / supprimer | JWT + Voter |
| PATCH | `/api/tasks/{id}/status` | Transition de statut (409 si invalide) | JWT + Voter |
| GET | `/api/doc` | Swagger UI | Public |

Collection **Bruno** versionnée dans [docs/bruno/](docs/bruno/) — scripts post-response qui injectent automatiquement `accessToken`, `refreshToken`, `teamId`, `taskId` dans l'environnement local.

---

## Lancer le projet

```bash
# Dépendances
composer install

# Base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Clés JWT
php bin/console lexik:jwt:generate-keypair

# Serveur
symfony server:start

# Worker Messenger (terminal dédié)
php bin/console messenger:consume async -vv
```

---

## Commandes CLI fournies

```bash
# Envoie un rappel pour chaque tâche en retard (dispatch Messenger)
php bin/console tasks:remind

# Purge les entrées BlockedToken dont expiresAt est dépassé
php bin/console tokens:cleanup
```

---

## Ce que ce projet démontre

- Maîtrise de **Symfony 8** et **PHP 8.4** sur les nouveautés récentes (property hooks, enums enrichis, ObjectMapper, `#[AsCommand]` invocable).
- Connaissance des patterns de **sécurité d'API REST** : JWT stateless, refresh rotation, révocation, autorisation contextuelle par Voter.
- Approche **domain-driven** : les règles métier vivent dans l'entité et les enums, pas dans les controllers.
- Pratique des **traitements asynchrones** (Messenger) avec bonne compréhension des trade-offs de transport.
- Attention portée à la **qualité d'API** : DTO explicites en entrée/sortie, statuts HTTP corrects (409 pour transition invalide, 422 pour validation, 403 pour autorisation), documentation OpenAPI.
- Souci des **détails de performance** (UUID v7 et indexation, `BINARY(16)`, `readonly`, propriétés calculées non mappées).
