# TopBid — working notes

Laravel 12 REST API for an online auction platform. Backend only; there is no
frontend in this repo. Written in English because it is dense with code
identifiers — mixing those with Arabic on one line breaks RTL rendering.

Users list an item, an admin approves it (which starts its countdown), other
users bid in real time, and a scheduled command closes the auction and notifies
everyone involved.

## Commands

```bash
php artisan test                      # full suite, sqlite in-memory
php artisan test --filter=BidTest
php artisan migrate
php artisan auctions:close            # the closing job, runs every minute via schedule
php artisan queue:work                # REQUIRED in any real environment, see below
```

## Layering

`Route → FormRequest → Controller → Service → Repository → Model`

with DTOs (`app/DTOs`), API Resources, Policies, and the `ApiResponse` helper.
Keep new work inside this shape; do not query models directly from controllers.

---

## Conventions that must be followed

### Auction state lives on the model, nowhere else

State is derived from three columns together (`moderation_status`, `is_active`,
`expires_at`). Duplicating those conditions in a query is how this codebase
previously ended up with three different meanings of "expired".

Use the scopes and predicates on `App\Models\Auction`:

```php
// queries
Auction::live() Auction::ended() Auction::awaitingClosure()
Auction::pendingReview() Auction::approved() Auction::rejected()
Auction::wonBy($userId)

// loaded instances — for policies and services
$auction->isLive() $auction->isApproved() $auction->isRejected() $auction->hasEnded()
```

`ended` and `awaitingClosure` are different sets on purpose: `ended` means
approved and out of time, `awaitingClosure` means still flagged active and out
of time, which is what the scheduler acts on.

Never write `where('moderation_status', 'approved')` by hand. If a new state
question comes up, add a scope **and** the matching predicate, and keep them in
agreement — `tests/Unit/AuctionStateTest.php` asserts a query and a loaded
instance classify the same auction identically.

### The stored rejected value is `flagged`

The column is `enum('pending','approved','flagged')` while the API vocabulary is
`rejected`. `Auction::STATUS_REJECTED` is the single place that knows this.
Writing the literal `'rejected'` to the column throws on MySQL in strict mode
and violates a CHECK constraint on SQLite. `AuctionResource` translates it back
to `rejected` on output.

Use `Auction::STATUS_*` constants, not string literals.

### Response shapes

Success with data:

```php
return ApiResponse::successWithData($data, 'Message');
// { "success": true, "data": ..., "message": "..." }
```

Paginated lists must be unwrapped or the pagination metadata is silently lost:

```php
ApiResponse::successWithData(
    SomeResource::collection($paginator)->response()->getData(true),
    'Message'
);
// data: { data: [...], links: {...}, meta: {...} }
```

Errors are rendered centrally by `app/Exceptions/ApiExceptionRenderer.php`,
registered in `bootstrap/app.php`. Every API error comes out as:

```json
{ "success": false, "message": "string", "errors": { "field": ["..."] } }
```

`message` is always a string; `errors` appears only when there is field detail.
Do not hand-roll error responses or catch exceptions just to reformat them —
let them reach the handler. Web routes keep the framework's HTML handling.

Rate limiters in `RouteServiceProvider` deliberately have no `response()`
callback, so they throw and the central handler renders them.

### Auth

JWT only (`php-open-source-saver/jwt-auth`). Sanctum was removed; do not
reintroduce a second guard.

- `jwt_token_version` on users invalidates every issued token at once. Any
  password change or reset increments it.
- Refresh tokens are stored hashed, are single-use, and rotate on every use.
- Protected routes use `['auth:jwt', 'jwt.token.version']`; admin routes add
  `'admin'` (`EnsureUserIsAdmin`).
- Google sign-in lives in `JwtAuthService::loginWithGoogle` and issues sessions
  through the same `issueTokensForUser` path as everything else.

Endpoint reference: [docs/auth-api.md](docs/auth-api.md).

---

## Testing

Pest, sqlite in-memory, `RefreshDatabase` applied to both `Feature` and `Unit`.

### Authenticating in tests

`actingAs($user, 'jwt')` does **not** work on protected routes:
`EnsureJwtTokenVersionMatches` calls `JWTAuth::parseToken()`, which needs a real
`Authorization` header. Use the helper in `tests/Pest.php`:

```php
$this->getJson('/api/me', jwtHeaders($user));
```

`jwtHeaders()` also clears the auth guard and the `tymon.jwt` singleton before
building the header. The container is reused across requests inside one test,
so the guard caches its user and the JWT singleton caches its parsed token;
without that reset a second request authenticates as the first user. Note the
`JWTAuth` facade points at `tymon.jwt.auth`, a *different* object from the
`tymon.jwt` instance the guard uses.

### Other test notes

- `BROADCAST_CONNECTION=null` in `phpunit.xml` is mandatory; without it the
  value falls through to `.env` (`reverb`) and bid tests dial a live server.
- Fake the specific event, `Event::fake([BidPlaced::class])`, not everything —
  a blanket `Event::fake()` also swallows Eloquent model events and disables
  `AuctionObserver`.
- Factory states: `pending()`, `approved()`, `rejected()`, `expired()`. The
  `approved()` state sets its own timing because `AuctionObserver` only listens
  to `updated`, so a factory-created approved auction never passes through it.
- `UploadedFile::fake()->image()` needs the GD extension, which is not
  installed here. Use `->create('x.jpg', 100, 'image/jpeg')`.
- JSON encodes `500.0` as `500`; cast before asserting float equality.
- Every bug fix ships with a regression test in the same commit — one that
  fails if the fix is reverted.

---

## Traps that have already bitten

- **Route parameters are not validated by default.** `FormRequest::validationData()`
  returns `$this->all()`, which excludes route parameters. A rule for a path
  segment needs `prepareForValidation()` to merge it in — see
  `GetAuctionByCategoryRequest`.
- **Notifications are queued.** `AuctionStatusNotification` implements
  `ShouldQueue`, so a queue worker must run or no notification is delivered.
  Tests use `sync`, so this only bites in real environments.
- **SQLite and MySQL disagree on enums.** SQLite enforces them with a CHECK
  constraint, MySQL only in strict mode. The test suite catches enum violations;
  a loose MySQL config would not.
- **`bids` ordering decides the winner.** Order by `amount`, never `created_at`.
  Timestamps are second-precision, so simultaneous bids give an indeterminate
  winner.
- **`chunkById`, not `chunk`, in the closing command.** The loop mutates
  `is_active`, which the query filters on; offset-based chunking skips rows.

## Git

Conventional commits in English (`feat:`, `fix:`, `refactor:`, `test:`).
The body should say *why*, not restate the diff. Commit per logical unit of
work, not per phase, and never commit with a red suite.
