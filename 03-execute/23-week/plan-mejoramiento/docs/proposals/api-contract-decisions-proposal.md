# Mobility Aids — API Contract Decision Proposal

**Status:** Proposal for owner approval; no implementation authorization implied  
**Scope:** Phase 1, contract decisions only  
**Prepared:** 2026-11-26

This document is separate from the approved/current contract. It proposes decisions based on `docs/spec.md`, `docs/api-contract.md`, the Laravel source, React clients, and `tool/seed_demo_data.py`. No application code, routes, existing documentation, credentials, or database records were changed to prepare it. The current API and seeder remain unchanged.

## Approval summary

Every row marked **Yes** requires explicit owner approval before implementation. In particular, the approved specification should remain the behavioral authority; where this proposal recommends an exception or resolves a contract ambiguity, approval should include an update to the governing specification/contract before implementation.

| ID | Decision | Approval required? |
|---|---|---|
| D1 | Technical Aid canonical JSON schema and compatibility fields | Yes |
| D2 | Image upload transport and lifecycle | Yes |
| D3 | Maintenance inventory and return condition | Yes |
| D4 | Password recovery public policy | Yes |
| D5 | Manager-only loan and return permissions | Yes |
| D6 | Category response schema | Yes |
| D7 | Preserve summary report alongside date-range report | Yes |
| D8 | v1 auth, token, user creation, and legacy transition | Transition strategy approved; implementation and registration remediation still require separate authorization |
| D9 | Standard error envelope and codes | Yes |
| D10 | Beneficiary list/detail/delete semantics | Yes |
| D11 | Search semantics and NFR-01 measurement | Yes |

## D1 — Technical Aid request and response schema

**Recommended behavior.** Use JSON for aid create/update when no file is involved. Preserve catalog fields already consumed by React and the current API: `name`, `reference`, `category_id`, `description`, `total_units`, `maintenance_units`, `available_units`, `image_path`, `is_active`, and category relationship data. Only Admin may create, update, or deactivate; authenticated Admin and Manager may list and retrieve active aids. Clients may submit `total_units` and `maintenance_units`, but never `available_units`; the server computes availability and enforces `0 <= available_units <= total_units`.

**Proposed request (JSON):**

```json
{
  "name": "Silla de ruedas estándar",
  "reference": "AID-001",
  "category_id": "<uuid-v4>",
  "description": "Descripción opcional",
  "total_units": 8,
  "maintenance_units": 1
}
```

`name`, `reference`, `category_id`, and `total_units` are required on create. `description` is nullable. `maintenance_units` defaults to zero. Integer counts must be JSON integers, `total_units >= 1`, and `0 <= maintenance_units <= total_units`. Update accepts a non-empty partial object with the same writable fields. `available_units`, `image_path`, `is_active`, IDs, and timestamps are read-only. A create returns 201; update returns 200; both use `{ "technical_aid": { ... } }`. List uses `{ "items": [...], "page": 1, "size": 20, "total": 0 }`; detail uses `{ "technical_aid": { ... } }`. Duplicate active reference returns 409 / `REFERENCE_TAKEN`.

**Authorization.** List/detail: authenticated Admin or Manager. Create/update/deactivate: Admin only.

**React impact.** Technical aids screen already sends a `FormData` payload for create/update because it supports images. It will need a coordinated client change if JSON is used without a file; the recommended D2 multipart rule lets that screen retain its existing flow. Its list expects `items/page/size/total` and category data, so those names should remain stable. No visual redesign is implied.

**Seeder impact.** Current seeder sends JSON with `name`, `reference`, `category_id`, `description`, `total_units`, and `maintenance_units`, and expects `technical_aid` in the response. This proposal preserves that shape; no seeder change is needed for D1 alone.

**Tests.** Contract tests for required/optional fields and types; server-computed availability; invariant and inventory adjustment on total changes; admin/manager matrix; create/update/show/list response schemas; duplicate active reference conflict; no sensitive/internal fields.

**Explicit approval:** Yes. The contract presently lists only inventory fields while the implemented screen and model also use name, description, image, and maintenance.

## D2 — Image upload handling

**Recommended behavior.** Keep image upload as `multipart/form-data` when an image is attached; accept JSON for no-image create/update. In multipart, scalar fields retain the D1 names/types and `image` is an optional file (image MIME types, maximum 5 MiB). Define replacement and removal explicitly: a new file replaces the old image after successful persistence; omission preserves it; removal uses an explicit `remove_image: true` flag. Responses expose a stable `image_url` or nullable value, not a server filesystem path. Failed validation or persistence must not leave orphaned files.

**Proposed request:** Same fields as D1 plus `image=<file>` for multipart, or `remove_image=true` to remove. `remove_image` and `image` cannot both be used in one request.

**Proposed response:** Same `technical_aid` envelope as D1, with `image_url: string|null`.

**Authorization.** Admin only for upload/change/removal; list/detail remain available to authenticated Admin and Manager.

**React impact.** The existing technical aids form already builds `FormData`, checks a 5 MiB client limit, and supports image previews. It can continue using multipart; only agreed removal behavior/response mapping may require client adaptation. Preserve its current UI and Spanish labels.

**Seeder impact.** The current seeder uses JSON and sends no image. Its workflow remains compatible.

**Tests.** Multipart success; JSON without file; invalid MIME and oversize; omission preserves image; explicit removal; replacement cleanup; no stored path leak; authorization; rollback/file cleanup when persistence fails.

**Explicit approval:** Yes, especially for image removal semantics and whether public image URLs are acceptable.

## D3 — Maintenance fields and return conditions

**Recommended behavior.** Retain the implementation's maintenance workflow as an approved domain extension: `maintenance_units` is part of inventory, and a return may be classified as `available` or `maintenance`. A returned item always consumes one outstanding loan line; it increments exactly one of `available_units` or `maintenance_units`. Completing maintenance moves one unit from maintenance to available. Keep `total_units = available_units + maintenance_units + outstanding_units` as the inventory accounting invariant. The API contract/specification must explicitly approve this extension because the current target return contract describes restoring an available unit only.

**Return request:**

```http
POST /api/v1/loans/{loan_id}/items/{item_id}/return
Content-Type: application/json
```

```json
{ "condition": "available" }
```

`condition` is `available|maintenance`; recommend making it required in v1 so staff make the disposition deliberately. Return success is 200 with `{ "message": "...", "loan": { ... } }`; repeat return is 409 / `ITEM_ALREADY_RETURNED`. Admin-only `POST /technical-aids/{id}/maintenance/complete` moves one unit to available.

**Authorization.** Return: Manager only. Complete maintenance and catalog maintenance counts: Admin only. Loan reads: Admin and Manager.

**React impact.** Returns screen currently sends `condition` and displays the maintenance disposition. Making it required matches its workflow, but request values and response envelope need contract tests. Preserve the existing Spanish interface.

**Seeder impact.** No effect; it only seeds catalog entries with zero maintenance units and does not call loan APIs.

**Tests.** Both disposition paths; required/invalid condition; inventory invariant; return twice; concurrent duplicate returns; completion moves counts correctly; manager/admin authorization; transactional rollback.

**Explicit approval:** Yes. This is an extension beyond the current written return rule and needs specification approval.

## D4 — Password recovery policy

**Recommended behavior.** Keep recovery public as a narrow, documented exception to the general authenticated-endpoint rule, since an unauthenticated user must be able to recover access. Keep it outside any promise that only login and health are public by explicitly listing the two recovery endpoints. Preserve generic forgot-password success responses regardless of account existence; do not expose the recovery code in API responses. Define rate limits, code expiry, one-time use, and token revocation after reset. Do not return delivery-provider or exception details to clients.

**Proposed formats:**

```json
POST /api/v1/auth/password/forgot
{ "email": "manager@example.org" }
```

```json
{ "message": "Si el correo está registrado, recibirás instrucciones para recuperar el acceso." }
```

```json
POST /api/v1/auth/password/reset
{ "email": "manager@example.org", "code": "123456", "password": "...", "password_confirmation": "..." }
```

Successful reset: 200 `{ "message": "La contraseña se actualizó correctamente." }`. Proposed policy: 6-digit single-use code, 10-minute expiry, three requests per 10 minutes per account/IP, revoke all issued access tokens after password change. Apply the standardized error format from D9 to failures.

**Authorization.** Public endpoints; strict throttling; no authenticated token required.

**React impact.** Login currently links to a recovery flow; preserve its current appearance and adapt only API paths/response handling when v1 is implemented.

**Seeder impact.** No effect; the seeder only logs in and seeds catalog data.

**Tests.** Existing/nonexistent account response equality; throttling; code not returned/logged; expiration; one-time use; invalid code; password policy; all prior tokens revoked; mail failure hides internals.

**Explicit approval:** Yes. The current contract’s public-route list omits recovery although Laravel implements it.

## D5 — Loan and return permissions

**Recommended behavior.** Follow `docs/spec.md` and target contract: Manager alone creates loans and records returns. Admin and Manager can list/detail/consult overdue loans. Admin-only report and catalog mutations remain Admin-only. Beneficiary endpoints remain Admin/Manager. This proposal chooses the stricter written rule despite existing tests/controller paths that allow Admin to loan/return.

**Exact request/response.** Keep the current loan request shape:

```json
{ "beneficiary_id": "<uuid-v4>", "items": [{ "technical_aid_id": "<uuid-v4>" }] }
```

Successful create is 201 with the documented loan object (`id`, `beneficiary_id`, UTC `loaned_at`, calculated `status`, `items` with snapshots and return fields). Return uses D3's request/response.

**Authorization.** `POST /loans`: Manager. `POST /loans/{loan_id}/items/{item_id}/return`: Manager. `GET /loans`, detail, overdue: Admin or Manager.

**React impact.** Loan and return screens must use a Manager session for mutations. If admin currently sees those actions, hide/disable them based on role without changing visual design; continue permitting admin read workflows. Confirm route-level access and error display.

**Seeder impact.** No effect; seeder does not create loans or returns.

**Tests.** Table-driven role tests for every route: unauthenticated 401, wrong role 403, permitted role success. Update current tests that assert admin loan/return success. Retain atomicity, stock conflict and repeat-return tests.

**Explicit approval:** Yes, because this intentionally narrows existing behavior and may change which logged-in role can operate the current screens.

## D6 — Category response format

**Recommended behavior.** Use the fixed-list response envelope `{ "items": [...] }`, no pagination. Each item has `id` (UUID), `name` (English canonical name), and optionally `technical_aids_count` only if the API intentionally supports that current field; recommend include it as a documented integer for compatibility. The five canonical names remain Wheelchairs, Crutches, Walkers, Canes, Cushions. No category write endpoints.

**Request/response:** `GET /api/v1/categories`, authenticated; 200:

```json
{
  "items": [
    { "id": "<uuid-v4>", "name": "Wheelchairs", "technical_aids_count": 3 }
  ]
}
```

**Authorization.** Authenticated Admin and Manager.

**React impact.** Technical aids screen currently calls `/technical-aid-categories` and reads `response.data.items` with `id/name`. During v1 adoption change only the endpoint/base URL; the response remains consumable. Preserve Spanish label mapping in UI.

**Seeder impact.** It calls the legacy category route and reads `items`, `id`, `name`. It remains untouched and working on `/api`. After a separately approved migration, it can call v1 without schema transformation if `items/id/name` remain stable.

**Tests.** Auth matrix; exactly five canonical category names; response field types and ordering; absence of mutation routes; category relation remains valid.

**Explicit approval:** Yes, including whether `technical_aids_count` is part of the stable response or omitted from v1.

## D7 — Reports summary compatibility

**Recommended behavior.** Keep existing `GET /api/reports/summary` unchanged during the v1 transition and expose it as `GET /api/v1/reports/summary` for the current dashboard/report widgets. Add the contractual `GET /api/v1/reports/most-requested?from=YYYY-MM-DD&to=YYYY-MM-DD` as a separate endpoint. Do not replace summary data with the historical per-aid report.

**Most-requested response:**

```json
{
  "from": "2026-11-01",
  "to": "2026-11-30",
  "rows": [
    {
      "technical_aid_id": "<uuid-v4>",
      "aid_reference": "AID-001",
      "category_name": "Wheelchairs",
      "times_requested": 12
    }
  ]
}
```

Date bounds are inclusive; empty periods return `rows: []`; end-before-start returns 422 / `VALIDATION_ERROR`; historical reference/category snapshots are used. Summary retains its existing flat counters until clients migrate.

**Authorization.** Admin only for both report endpoints, recommended to align existing summary with the spec's report restriction. If current React renders summary to Manager, confirm whether that dashboard needs its own authorized operational aggregates; do not silently broaden this report permission.

**React impact.** `Reports.jsx` currently reads `/reports/summary`. Keep that response and endpoint available. Add a date-range query/UI only after the new report is ready; no layout change is required by this decision. Check dashboard consumers of summary too.

**Seeder impact.** None.

**Tests.** Summary schema compatibility; report permissions; inclusive date boundaries; invalid range; empty range; descending order; snapshots stable after catalog edit/deactivation; counts loan lines per aid as specified.

**Explicit approval:** Yes. The current summary endpoint is consumed by React but the target contract defines a different Admin-only report.

## D8 — v1 authentication, token, user creation, and legacy transition

**Decision status (2026-11-26).** The owner approved the transition strategy documented in [D8 Authentication Transition Decision](D8-authentication-transition-decision.md). Keep the existing email/password login and `/api` routes functioning. Prepare username/password login at `/api/v1/auth/login` with proposed token lifetime `28800` seconds. Run both versions during a controlled transition. Do not change the React login form or Python seeder authentication yet. Do not remove or replace legacy routes until compatibility is verified and explicit authorization is received. Migrate the frontend only after tests pass and a later approval is granted.

**Security issue remains open.** Current `POST /api/register` is public and creates users with role `manager`, contrary to the project specification that administrators create managers. Do not treat the temporary compatibility decision as approval to leave public manager registration exposed for production. No route change is authorized by this decision. Before production use, a separately authorized security change must prevent unauthenticated manager creation while retaining the legacy email/password login for already provisioned accounts. Recommended destination: make manager creation Admin-only and ensure the unauthenticated legacy registration route cannot create Manager accounts. The exact compatibility response for callers of `/api/register` (for example, 404/410 or a generic rejection) must be decided as part of that authorization; it must not silently create another privileged account.

The other proposed v1 semantics remain recommendations pending approval: normalized unique username, duplicate 409, no API assignment of `admin`, documented manager creation by Admin, and token revocation on logout. This D8 approval records the transition sequence and compatibility constraints, not permission to implement any of those behaviors.

**Login request:** `POST /api/v1/auth/login`, JSON `{ "username": "manager", "password": "..." }`.

**Login success (200):** `{ "access_token": "...", "token_type": "Bearer", "expires_in": 28800, "role": "manager" }`.

**Create manager:** `POST /api/v1/users`, Bearer Admin; JSON `{ "name": "Nombre", "username": "manager", "email": "manager@example.org", "password": "...", "password_confirmation": "..." }`. Success 201 `{ "user": { "id": "<uuid-v4>", "name": "Nombre", "username": "manager", "email": "manager@example.org", "role": "manager" } }`; never return password/hash or a token from user creation. Invalid role assignment 422; duplicate username 409.

**Authorization.** Login public. All v1 routes except login, health and approved recovery are protected. User creation Admin-only. User response excludes sensitive fields.

**React impact.** Login currently displays email input and consumes the legacy `{user, token}` shape. The visual design remains unchanged, but switching the field label/value semantics and auth state mapping needs coordinated implementation approval. Legacy behavior stays available until that client migration completes. Manager creation has no existing screen identified in this review; do not invent a UI as part of the contract decision.

**Seeder impact.** Current tool accepts admin email, posts `{email,password}`, reads `user.role` and `token`, and is restricted to base URL `/api`. It must remain unchanged now. After a separately approved migration, provide an explicit compatibility release to accept username, `/api/v1`, `access_token`, and role; keep preview mode and verify before any approved `--apply` use.

**Tests.** Username trimming/lowercase and duplicate conflict; case normalization; public registration denial on v1; Manager 403/Admin create; cannot assign admin; hashed password and no secret response; exact 28800 expiry; login generic 401; logout invalidates that token but not unrelated tokens; expired/revoked tokens 401; legacy endpoint regression tests.

**Explicit approval:** Yes. This is a major client and security behavior transition. Current login and seeder must remain intact until the separate migration strategy is approved.

## D9 — Standard error format

**Recommended behavior.** All v1 errors use `{ "code": "...", "message": "Mensaje en español.", "details": [] }`, with field-level details consistently represented as an array of `{ "field": "...", "messages": ["..."] }`. Never include exception class, SQL, stack trace, host path, credential, or internal diagnostic. Keep current legacy error behavior temporarily so compatibility changes are isolated to v1.

**Examples:**

```json
HTTP 401
{ "code": "INVALID_CREDENTIALS", "message": "Las credenciales no son válidas.", "details": [] }
```

```json
HTTP 422
{ "code": "VALIDATION_ERROR", "message": "Revisa los datos enviados.", "details": [{ "field": "reference", "messages": ["La referencia es obligatoria."] }] }
```

Use the contract's stable codes for conflicts and authorization. Define codes for throttling and internal error before implementation; proposed `RATE_LIMITED` (429) and `INTERNAL_ERROR` (500). Preserve the correct HTTP status.

**Authorization.** Error format does not grant access; apply the endpoint's role policy before returning protected data.

**React impact.** Existing frontend translates legacy messages/statuses. Its API error service should prefer `code` and Spanish `message`, with temporary fallback for legacy responses. This avoids visual/UI change while enabling migration.

**Seeder impact.** Current tool reports HTTP status and avoids printing response bodies, so it remains safe. It can continue to do so until v1 client support is approved.

**Tests.** Assert schema and code for every 401/403/404/409/422/429/500 class; validation detail shape; Spanish user messages; no sensitive/internal leakage including concurrency conflicts; legacy response regression tests.

**Explicit approval:** Yes, especially exact `details` schema and new 429/500 codes.

## D10 — Beneficiary list/detail/delete semantics

**Recommended behavior.** Align list pagination with the common `page/size` convention (default 20, max 50) and response envelope; support `q` matching name or identification document. Detail includes outstanding aids count. Duplicate document is a business conflict (409 / `DOCUMENT_TAKEN`). Beneficiaries cannot be deleted through the API; do not offer hard delete even when no loans exist. Keep current create fields unless an explicit model decision says otherwise.

**List request:** `GET /api/v1/beneficiaries?q=...&page=1&size=20`. Response `{ "items": [...], "page": 1, "size": 20, "total": 0 }`.

**Detail response:** `{ "beneficiary": { "id": "<uuid-v4>", "full_name": "...", "identification_number": "...", "outstanding_technical_aids": 1 } }`.

**Create request:** `{ "full_name": "...", "identification_number": "..." }`; 201 `{ "beneficiary": { ... } }`.

**Authorization.** Admin and Manager list/detail/create. No delete route in v1. (Whether update is supported should be resolved separately against approved scope; the target contract currently documents no update route.)

**React impact.** Beneficiary list currently sends `page/per_page`, accepts `data` or `items`, and has delete UI. It will need request/response adaptation and the delete action must be removed/disabled only after product approval; preserve Spanish UI and existing layout. Confirm outstanding count use in details.

**Seeder impact.** None; it does not call beneficiary routes.

**Tests.** Search by each field; pagination/default/max boundaries; response envelope and outstanding count; duplicate normalized document behavior and 409; Admin/Manager matrix; no v1 delete route; legacy endpoint behavior during transition.

**Explicit approval:** Yes for removing delete capability from the client/API v1 and for pagination/detail fields. The prohibition itself is already in the approved target contract/specification.

## D11 — Search semantics and NFR-01

**Recommended behavior.** Interpret `q` as a case-insensitive partial match on `reference` only, as stated in the target API contract; `category_id` remains an independent filter. Keep active-only results, ascending reference order, `page` and `size`, default size 20, maximum 50. If product owners want name/description search, approve that as an extension and document a distinct behavior rather than retaining an undocumented mixed search.

**Request:** `GET /api/v1/technical-aids?q=AID-00&category_id=<uuid>&page=1&size=20`. Response follows D1 list envelope. Search query is a string; category ID is UUID v4; page/size are positive integers.

**Authorization.** Authenticated Admin and Manager.

**React impact.** Technical aids screen search currently accepts broad server search and sends `q`; if users expect name/description matches, narrowing to reference will change results. Review Spanish search hint/placeholder in the same migration and keep the visual styling. Category filtering/pagination shapes remain compatible.

**Seeder impact.** It searches `q=MA-DEMO-` to detect deterministic existing rows. Reference-only search exactly preserves and improves correctness of this check. Do not change the current seeder until migration approval.

**Tests.** Partial/case behavior on reference; names/descriptions alone do not match unless extension approved; combination with category; active-only; deterministic order; pagination boundaries and max; duplicate detection across all pages.

**NFR-01 verification.** Run against an isolated environment containing 5,000 catalog aids created using the demo seeder only after separate authorization and only in that disposable test environment. Measure API search request duration from a documented client location, database engine/configuration, hardware, warm-up, concurrency and repeated samples. Acceptance is each agreed statistic under 300 ms; recommend median and p95 both <300 ms, and record maximum too. A single local response or offline seeder test is not proof. No database writes are authorized by this proposal.

**Explicit approval:** Yes for narrowing semantic results to reference-only and for adopting median+p95 as the acceptance interpretation of “respond in less than 300 ms.”

## Recommended approval order

1. Approve D1–D3 together because schemas, image handling, and maintenance counts form one catalog/inventory contract.
2. Approve D4–D5 and D8–D9 as the security/authentication group.
3. Approve D6–D7 and D10–D11 as client/report/search compatibility decisions.
4. Record approved changes in the governing spec/contract and ADRs as applicable before authorizing Phase 2. No item in this proposal authorizes implementation or a seeder run.

## Current stopping point

This is a proposed decision document only. The existing contract, routes, Laravel implementation, React screens, and Python seeder remain unchanged. Await owner approval or requested revisions before Phase 2.
