# D8 — Authentication Transition Decision

**Status:** Transition strategy approved; implementation not authorized  
**Decision date:** 2026-11-26  
**Scope:** Authentication transition and compatibility; no code or route changes

## Approved decision

The authentication transition will preserve the current working workflow while a versioned API is prepared and verified:

1. Keep the existing email-and-password login working on the current unversioned `/api` routes.
2. Keep the existing `/api` routes available during a controlled transition.
3. Prepare a separate v1 login at `POST /api/v1/auth/login`, using username and password.
4. The proposed v1 token lifetime is 8 hours (`expires_in: 28800` seconds).
5. Run legacy and v1 APIs in parallel during migration. Do not silently redirect or change the meaning of the legacy routes.
6. Do not change the React login form or its current visual design in this decision.
7. Do not change `tool/seed_demo_data.py` or its current authentication mechanism in this decision.
8. Do not remove or replace legacy routes until compatibility has been verified and the owner has given explicit authorization.
9. Migrate the frontend only after the relevant tests pass and the owner grants a later approval.

This approval is for the transition strategy only. It does not authorize Phase 2, implementation of v1, security remediation, database changes, seeder execution, or removal of any route.

## Target v1 login contract (proposed)

**Request** — `POST /api/v1/auth/login`, public, JSON:

```json
{
  "username": "manager",
  "password": "<secret>"
}
```

**Success** — HTTP 200, JSON:

```json
{
  "access_token": "<opaque-token>",
  "token_type": "Bearer",
  "expires_in": 28800,
  "role": "manager"
}
```

Passwords and password hashes must never appear in responses or logs. Protected v1 routes use `Authorization: Bearer <access_token>`. The 8-hour duration is the approved target for the proposed v1 contract; exact expiry enforcement and token revocation behavior require future implementation tests. Legacy login continues using its existing email/password request and response until separately migrated.

## Mandatory security review: public manager registration

The current source exposes `POST /api/register` without authentication. `AuthController::register` validates registration details and assigns `role = manager`; callers do not need an administrator token. This allows an unauthenticated person who can reach the API to create an account with operational Manager access.

### Risk

Public account creation as Manager bypasses the specification's administrator-managed provisioning rule. An attacker could obtain a valid token and use any Manager-authorized operational routes, including beneficiary and loan workflows. Rate limiting or stronger password validation alone would not resolve the authorization flaw.

### Required security outcome for a separately authorized change

- Only an authenticated Admin may create Manager accounts, through the approved manager-provisioning endpoint.
- Unauthenticated requests must not create a Manager account or receive a usable Manager token.
- The legacy email/password login remains available to already provisioned accounts so login compatibility is preserved independently from public registration.
- The legacy `POST /api/register` route must not continue granting Manager access to anonymous callers once the separately authorized security remediation is implemented.
- Its exact compatibility response (for example, a generic rejection or a retired-route response) must be selected and documented before implementation. This decision does not select or authorize that response.
- Do not remove the route as part of the present documentation-only decision. The current public-registration risk remains open until an owner-authorized security change is deployed and verified.

## Compatibility constraints

| Client | Current behavior to preserve now | Later migration condition |
|---|---|---|
| React login | Existing email/password form, current design, current `/api/login` request and response handling | Change to username and v1 response only after v1 integration tests pass and a later explicit approval is given. Preserve Spanish UI, Mobility Aids identity, colors, typography, layout, and navigation. |
| Python seeder | Existing `/api` base URL, email/password login, token parsing, and logout behavior | Change only under an approved seeder migration plan, after v1 auth/category/catalog compatibility is verified. Keep preview behavior. |
| Existing API consumers | Current unversioned routes remain available and retain their current semantics during transition | Any deprecation/removal requires compatibility evidence and explicit owner authorization. |

## Future files likely involved

These are planning references only; none was modified for this decision:

- Laravel: `api/routes/api.php`, `api/app/Http/Controllers/AuthController.php`, `api/app/Models/User.php`, Sanctum configuration, authentication/role middleware, and API exception handling.
- Laravel tests: `api/tests/Feature/Auth/LoginTest.php`, `RegisterTest.php`, `RegisterValidationTest.php`, `LogoutTest.php`, `AuthenticationMiddlewareTest.php`, plus versioned API contract tests.
- React: `app/src/pages/auth/Login.jsx`, API client configuration/services, and auth state/token storage. No visual change is authorized.
- Seeder: `tool/seed_demo_data.py` and `tool/test_seed_demo_data.py`, only after separate approval.
- Contract documents: `docs/api-contract.md`, `docs/spec.md`, and relevant ADR/task records, after decisions are approved for implementation.

## Required future tests

### Legacy compatibility

- Existing provisioned account can still log in at the legacy email/password route and receives the legacy response shape during transition.
- Existing React authentication state continues to accept and use that response; logout and protected requests still work.
- Existing seeder authentication contract remains covered by its offline tests. No seeder execution is needed to establish this decision.
- Legacy login, logout, and protected route regression tests remain green when v1 routes are added.

### v1 authentication

- Correct username/password returns HTTP 200 and the exact response keys/types shown above.
- Invalid credentials return a generic HTTP 401 that does not disclose whether username or password was incorrect.
- `expires_in` is exactly 28800 and the underlying token is rejected after expiry.
- Missing, malformed, expired, and revoked bearer tokens receive HTTP 401; logout revokes the presented token according to the approved v1 behavior.
- Password and hash are absent from every success/error response and application log.
- Legacy and v1 token formats cannot be confused or accidentally accepted outside their intended guards.

### Public-registration security gate

- Anonymous `POST /api/register` cannot create a user, cannot obtain a Manager token, and cannot gain access to protected Manager endpoints after the security remediation is authorized.
- Manager attempts to provision another Manager receive HTTP 403; Admin provisioning succeeds with role fixed to `manager`.
- Attempts to set `admin` through API input are rejected; hashes and credentials are never returned.
- Tests assert the chosen legacy registration response and verify no user row is created for rejected requests, using an isolated test database only.
- Protected beneficiary, loan, return, and catalog actions are inaccessible to an unauthenticated registrant.

### Integration and transition gate

- Run Laravel feature tests against the isolated test database, not a real/project database.
- Test both clients against a local/disposable environment: React legacy login first, then a dedicated v1 client integration after its separately approved migration; seeder compatibility tests remain offline unless a later run is explicitly authorized.
- Verify routes in the runtime route table and exercise health, login, authenticated read, logout, and post-logout 401 for both API generations where applicable.
- Do not migrate React or the seeder until the v1 API and the corresponding compatibility suite pass. Do not remove legacy routes until there is recorded client migration evidence and explicit owner approval.

## Acceptance criteria for the transition decision

This decision is considered documented when:

1. Legacy email/password login and `/api` route preservation are stated as requirements for the transition.
2. The proposed v1 username/password endpoint and 28800-second token target are recorded.
3. React design/form and seeder behavior are explicitly frozen until later approval.
4. Legacy route removal requires both compatibility verification and explicit authorization.
5. The public Manager registration risk and required security outcome are recorded without changing or deleting the route in this phase.
6. The test plan covers legacy compatibility, v1 auth, registration authorization, token expiry/revocation, and isolated integration verification.

**Current state:** Documentation-only decision recorded. The public registration risk is unresolved in implementation and must be addressed through a separately authorized security change before production acceptance. Stop here pending the owner's next authorization.
