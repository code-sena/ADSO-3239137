# D9 — API Error Response Standardization Decision Proposal

**Status:** Partial decision approval recorded; implementation not approved  
**Date:** 2026-11-26  
**Scope:** Error response behavior for Laravel API, React, and the Python seeder

This document is independent of the current contract and records a source-based impact analysis. It does not change `docs/api-contract.md` or any application files.

## 1. Objective

Define one predictable JSON error shape for API failures so React and other clients can make decisions from stable machine-readable codes, administrators receive Spanish messages, and internal server information is not exposed. Preserve current `/api` consumers during transition and introduce the normalized format in `/api/v1` only if that migration approach is approved.

## 2. Current behavior verified in source

The Laravel application currently asks the framework to render JSON for `/api/*` or requests that expect JSON in `api/bootstrap/app.php`. This selects JSON rendering; it does **not** normalize all errors to one schema.

The inspected behavior is mixed:

- `AuthController` returns direct objects such as `{ "message": "Invalid credentials." }`, while Laravel validation exceptions use the framework validation response (typically `message` plus an `errors` object for JSON requests).
- `CheckRole` returns `{ "message": "You are not authorized to perform this action." }` with 403 in English.
- `TechnicalAidController` returns direct `{message}` objects for not-found, forbidden, duplicate-reference, and some conflict cases; Laravel validation exceptions are separate.
- `BeneficiaryController` returns direct `{message}` objects for CRUD outcomes and conflicts, while uniqueness/validation failures use Laravel's validation response.
- `LoanController` combines `abort(status, message)`, validation exceptions, and direct success JSON. The resulting abort response depends on Laravel's exception renderer and debug configuration; it is not a controller-defined `{code,message,details}` envelope.
- Other uncaught exceptions are handled by Laravel. `api/bootstrap/app.php` currently configures JSON rendering but has no custom exception renderer that guarantees the proposed schema or strips diagnostic fields itself.
- The `api/.env.example` defaults `APP_DEBUG=true`, and the retained concurrency evidence in `docs/evidencias/punto-07-concurrencia/punto-07-concurrencia.md` explicitly reports a 409 response containing `file` and `trace` fields, including a local Windows path. That is evidence of an observed disclosure in the recorded test environment; it does not prove every deployment has debug enabled.
- Current tests commonly assert only status, `message`, or Laravel validation keys. They do not establish a uniform error envelope or prove that error bodies never contain paths, SQL, exceptions, or traces.

Therefore current API failures are not uniform, and at least one retained concurrency response demonstrates internal diagnostic leakage.

## 3. Proposed response format

All v1 error responses should use:

```json
{
  "code": "VALIDATION_ERROR",
  "message": "Revisa los datos enviados.",
  "details": [
    {
      "field": "reference",
      "messages": ["La referencia es obligatoria."]
    }
  ]
}
```

Rules proposed:

- `code`: stable uppercase identifier; clients branch on this value, not message text.
- `message`: concise Spanish, safe for the intended user. Do not echo secrets or private input values.
- `details`: always an array. Use `[]` when no field detail applies. For validation, each item has `field` and `messages`; messages is an array of Spanish strings.
- Preserve the correct HTTP status. Do not add internal diagnostic properties such as `exception`, `file`, `line`, `trace`, SQL, or server paths.
- For unexpected errors, return a generic Spanish message and a correlation/request ID only if the project separately approves and can ensure it does not reveal internals. Log diagnostic context server-side with secrets redacted.

Example authorization response:

```json
HTTP/1.1 403 Forbidden
{
  "code": "FORBIDDEN_ROLE",
  "message": "No tienes permisos para realizar esta acción.",
  "details": []
}
```

Example internal error:

```json
HTTP/1.1 500 Internal Server Error
{
  "code": "INTERNAL_ERROR",
  "message": "Ocurrió un error interno. Inténtalo nuevamente.",
  "details": []
}
```

## 4. Proposed codes and HTTP statuses

| Code | Status | Proposed use |
|---|---:|---|
| `INVALID_CREDENTIALS` | 401 | Login failed, without identifying which credential was wrong. |
| `FORBIDDEN_ROLE` | 403 | Authenticated user lacks the required role. |
| `NOT_FOUND` | 404 | Requested resource is missing or intentionally treated as unavailable. |
| `REFERENCE_TAKEN` | 409 | An active technical aid already uses the reference. |
| `DOCUMENT_TAKEN` | 409 | A beneficiary identification document is already registered. |
| `AID_NOT_AVAILABLE` | 409 | The requested aid has no available units. |
| `BENEFICIARY_HAS_OVERDUE` | 409 | Beneficiary has one or more overdue outstanding items. |
| `ITEM_ALREADY_RETURNED` | 409 | A loan line has already been returned. |
| `LAST_UNIT_CONFLICT` | 409 | A concurrent transaction reserved the last unit first. |
| `BENEFICIARY_LIMIT_EXCEEDED` | 422 | Request would exceed two outstanding aids; include safe count details if approved. |
| `VALIDATION_ERROR` | 422 | Request data fails field validation. |
| `UNAUTHENTICATED` | 401 | Request is missing valid authentication for a protected v1 resource. |
| `METHOD_NOT_ALLOWED` | 405 | HTTP method is not supported for the requested v1 resource. |
| `RATE_LIMITED` | 429 | Proposed new code for throttled requests. Include `Retry-After` header if available; do not disclose limiter internals. |
| `INTERNAL_ERROR` | 500 | Proposed new generic code for unexpected server errors. |

The contract currently lists the first eleven codes and statuses. `UNAUTHENTICATED` (401) and `METHOD_NOT_ALLOWED` (405) are approved additions for `/api/v1` by Decision 4 below. `RATE_LIMITED` and `INTERNAL_ERROR` remain proposed additions and require owner approval.

## 5. Laravel consequences

Potential future adaptation points (not changed):

- `api/bootstrap/app.php`: currently sets JSON rendering only. A central exception rendering layer is the likely place to normalize framework exceptions, validation failures, authorization/authentication failures, not-found, throttling, and unexpected 500 errors. It must preserve HTTP headers/status and avoid rendering debug detail to API clients.
- `api/app/Http/Controllers/AuthController.php`: login failures and recovery errors return controller-specific response objects; decide whether v1 controller responses should use a shared error factory/resource or central exception mapping.
- `api/app/Http/Middleware/CheckRole.php`: current 403 is English and only contains `message`.
- `api/app/Http/Controllers/TechnicalAidController.php`: direct 403/404/409/422 response branches and thrown validation exceptions need a single mapping.
- `api/app/Http/Controllers/BeneficiaryController.php`: direct conflict and validation responses need mapping, including duplicate documents.
- `api/app/Http/Controllers/LoanController.php`: `abort()` calls, validation exceptions, locking/transaction failures and inventory conflicts need stable semantic codes. Generic database exceptions must not be mislabeled as business conflicts.
- Feature tests under `api/tests/Feature/Auth`, `TechnicalAids`, `Beneficiaries`, and `Loans`, plus new API contract/error tests, currently encode several legacy response shapes and would need coordinated expectations.

Recommended architecture is a shared API error renderer/exception mapper for v1, with explicit business exceptions or a small response helper for expected domain conflicts. Avoid string-matching English exception messages to derive codes. Do not leak raw exception messages in 500 responses. Keep internal logs useful but redact passwords, tokens, reset codes, email/document data where not needed.

## 6. React consequences and legacy compatibility

The existing client does not consume one error format:

- `app/src/services/api.js` attaches bearer tokens but has no response-error interceptor.
- `app/src/services/uiMessages.js` reads legacy `errors`, `message`, and sometimes error objects, and translates a fixed set of English messages to Spanish. It does not read a stable `code` field or the proposed `{field,messages}` detail objects.
- `Login.jsx` branches primarily on HTTP status and reads `response.data.message`; field-validation behavior expects Laravel's current validation structure.
- Technical-aid forms read Laravel `errors` to extract validation text; beneficiary, loans, returns, and reports use `getUiErrorMessage` or direct message fallbacks.

If standardized responses are introduced abruptly on `/api`, some existing screens may show generic fallback text or lose field-specific validation messages. Recommended transition:

1. Preserve current response bodies on legacy `/api` while adding v1.
2. Add React parsing support for both formats before switching any screen: prefer `code`, display the Spanish `message`, and map `details[].field/messages[]`; retain fallback handling for Laravel `errors` and `{message}`.
3. Migrate a client to v1 only after tests show both legacy and v1 response handling works. This can be an API-service compatibility change without any visual redesign; Spanish copy and existing visual identity remain unchanged.
4. Do not alter the login form or any visual design as part of D9.

The choice to normalize legacy `/api` in place instead of preserving it is a separate owner decision. The safer default is no legacy body-shape change during the controlled version transition.

## 7. Seeder consequences

`tool/seed_demo_data.py` currently:

- Sends JSON requests with `Accept: application/json`.
- Catches `HTTPError` and raises a CLI-safe message containing the HTTP status, method, and path; it does not print or parse the API error response body.
- Validates successful JSON shape for login, category, technical-aid list, and create responses.
- Uses authenticated requests for catalog operations and attempts logout in `finally`.

Therefore the seeder does not need a change merely to tolerate a new error JSON body: it discards HTTP error bodies. It will still fail safely on non-2xx status. If future diagnostics are desired, a separate approved change could parse only the allowlisted `code`/`message`, never dump arbitrary response bodies, credentials, headers, token, or server traces. Do not change or run the seeder as part of this proposal.

## 8. Risks and compatibility across `/api` and `/api/v1`

- **Legacy client breakage:** Changing `/api` error shapes would affect UI message extraction and test expectations. Preserve legacy body shapes during v1 introduction unless owner explicitly approves a coordinated legacy migration.
- **False code mapping:** Mapping all 409 errors to one code would lose domain meaning; map each business rule specifically. Distinguish a no-stock conflict from a concurrent last-unit race if evidence supports it.
- **Unexpected exception leakage:** Laravel debug rendering can add trace/path details to exception responses. A normalized response must be verified with debug mode both enabled and disabled, without exposing details in either case.
- **Information disclosure via messages/details:** Validation can echo values; allowlist safe fields and avoid returning submitted passwords, tokens, reset codes, private identifiers, SQL, or arbitrary exception messages.
- **Localization drift:** Existing controller messages include both Spanish and English. Machine codes should be language-neutral; user-facing messages should be Spanish and should not become the frontend's programmatic interface.
- **Concurrency ambiguity:** The same 409 status can represent ordinary no-stock or a transaction losing a race. Confirm when to return `AID_NOT_AVAILABLE` versus `LAST_UNIT_CONFLICT`; both must use a generic safe body and preserve transaction rollback.
- **Operational logging:** Hiding details from the response must not cause diagnostic logging of credentials or tokens. Error logging and correlation IDs need a redaction policy.
- **Versioning policy:** A central renderer must scope behavior by API version or route namespace; otherwise it may accidentally normalize legacy endpoints before clients are ready.

## 9. Required automated tests

### Response contract matrix

For v1, assert status, exact top-level keys and types (`code:string`, `message:string`, `details:array`) for 401, 403, 404, 409, 422, 429, and 500. Assert `details: []` when no validation detail exists. Assert each validation detail has `field:string` and `messages:string[]`.

### Code-to-condition mapping

- Login wrong username and wrong password both produce the same `INVALID_CREDENTIALS` response.
- Insufficient role produces `FORBIDDEN_ROLE`.
- Missing resource produces `NOT_FOUND`.
- Duplicate active reference and duplicate beneficiary document map to their respective conflict codes.
- No stock, overdue beneficiary, repeated return, beneficiary outstanding limit, and concurrent last-unit conflict map to their specified distinct codes and statuses.
- Invalid fields produce `VALIDATION_ERROR` and field details; secrets are omitted.
- Throttling returns `RATE_LIMITED` and 429 with safe retry metadata if approved.
- A controlled unexpected exception returns `INTERNAL_ERROR` and 500, without exception detail.

### Sensitive-data and debug-mode checks

- Trigger representative validation, authorization, conflict, and unexpected failures with `APP_DEBUG` true and false in an isolated test configuration.
- Assert serialized response contains no `file`, `line`, `trace`, `exception`, SQL fragments, absolute local paths, stack frames, password, token, reset code, or private submitted value.
- Specifically reproduce the concurrent last-unit failure and inspect the full response body and headers; verify loser receives 409 with a safe code and no path/trace, while transaction state remains atomic. Use isolated test DB/fixtures only; do not use the real project database.
- Check logs with synthetic credentials/tokens to prove secrets are redacted. Do not include real credentials in tests or logs.

### Backward compatibility and React

- While `/api` remains legacy, assert existing payloads relied on by Login and screens are unchanged.
- Unit-test React error parsing for both legacy `{message}`, Laravel `{message,errors}`, and v1 `{code,message,details}` responses. Verify Spanish display and field-level messages with no UI layout change.
- Cover status-based login behavior and all consumers using `getUiErrorMessage`.

### Seeder

- Existing offline tests should continue passing without changes.
- If future seeder diagnostics parse `code`, test that arbitrary response bodies and internals are never printed. No live run is required for this contract decision.

## 10. Technical recommendation

Adopt the normalized schema for `/api/v1` first, using a central Laravel exception renderer plus explicit semantic mapping for expected business conflicts. Keep `/api` response bodies unchanged during transition. Update React's API error reader to support both schemas before moving calls to v1. Leave the seeder unchanged; its current implementation ignores HTTP error bodies safely. Add contract and disclosure tests before migrating any production client.

For concurrency, return `AID_NOT_AVAILABLE` when the transaction observes no stock as a business precondition, and `LAST_UNIT_CONFLICT` only when a concurrent reservation is specifically identified. Both are 409. If the database/locking layer cannot reliably distinguish those conditions, approve one stable code for both rather than infer race type from a generic SQL exception.

## 11. Decisions requiring explicit owner approval

### Decision 1 — `details` schema: APPROVED

The owner approved this schema for future `/api/v1` error responses only:

- Every error response has `code`, `message`, and `details`.
- `details` is always present and is an array.
- For validation errors, each entry has `field` (the affected field name) and `messages` (an array of Spanish messages).
- When there are no additional details, use `"details": []`.
- Never place passwords, tokens, recovery codes, SQL queries, local server paths, internal exceptions, stack traces, or private/sensitive information in `details`.

Approved validation example:

```json
{
  "code": "VALIDATION_ERROR",
  "message": "Revisa los datos enviados.",
  "details": [
    {
      "field": "reference",
      "messages": ["La referencia es obligatoria."]
    }
  ]
}
```

Approved example without details:

```json
{
  "code": "FORBIDDEN_ROLE",
  "message": "No tienes permisos para realizar esta acción.",
  "details": []
}
```

This approval is limited to the structure and content rules for `details`. It does not approve the overall D9 proposal, error-code mappings, legacy `/api` changes, or implementation.

### Decision 2 — API version scope: APPROVED

The owner approved the following transition scope:

- `/api/v1` will use the standardized error response structure: `code`, Spanish `message`, and `details` (always an array, as established by approved Decision 1).
- Existing `/api` endpoints retain their current response structure during the transition period, including their existing `message` and Laravel `errors` fields where applicable.
- Migrating or normalizing legacy `/api` responses requires a separate review and explicit owner authorization.
- No existing React screen is migrated as part of this decision. Existing frontend error handling and screens remain on their current API behavior until a separately approved migration.
- This staged scope is consistent with the approved D8 authentication transition strategy. It approves documentation scope only; it does not authorize backend implementation, frontend changes, or the start of Phase 2.

### Decision 3 — Spanish error messages: APPROVED

The owner approved **controlled contextual messages in Spanish**. A message may vary according to safe, relevant context, provided that:

- The stable meaning and intended client handling of its `code` do not change.
- The message remains understandable Spanish and describes the same error condition represented by that code.
- Context is limited to information safe for the affected user and necessary to explain the condition.
- Messages never expose credentials, tokens, recovery codes, private or sensitive data, SQL, local paths, exception text, or stack traces. Do not echo untrusted request values.

This approves the message policy only. It does not approve the remaining error-code additions/mappings, `/api` behavior changes, or implementation.

### Decision 4 — Authentication and method errors: APPROVED

The owner approved these stable codes for `/api/v1` only:

| Code | HTTP status | Spanish message |
|---|---:|---|
| `UNAUTHENTICATED` | 401 | `Debes iniciar sesión para acceder a este recurso.` |
| `METHOD_NOT_ALLOWED` | 405 | `El método solicitado no está permitido para este recurso.` |

Both responses use the standardized D9 envelope and always include `details` as an array:

```json
{
  "code": "UNAUTHENTICATED",
  "message": "Debes iniciar sesión para acceder a este recurso.",
  "details": []
}
```

```json
{
  "code": "METHOD_NOT_ALLOWED",
  "message": "El método solicitado no está permitido para este recurso.",
  "details": []
}
```

These Spanish messages must not expose sensitive information or internal server details. This approval is limited to these two codes, statuses, messages, and their `/api/v1` scope. Legacy `/api` behavior remains governed by approved Decision 2. No implementation is authorized.

### Decision 5 — Availability and concurrency errors: APPROVED (combined Alternative A + B)

#### Current implementation findings

The loan registration path in `api/app/Http/Controllers/LoanController.php::store`:

1. Opens a database transaction (`DB::transaction(..., 3)`), which asks Laravel to retry eligible transaction failures up to three times.
2. Locks the beneficiary row using `lockForUpdate()` while checking the outstanding-loan rules.
3. Collects the requested technical-aid IDs, sorts them, then loads their rows in ID order with `lockForUpdate()`.
4. Checks each locked row's current `available_units`; when it is below one, it calls `abort(409, 'No hay unidades disponibles ...')`.
5. For accepted aids, decrements `available_units` and creates the loan line inside the same transaction.

This ordering and transaction provide serialization for competing requests on databases that implement row-level `FOR UPDATE` locks. In the last-unit race, a waiting request can acquire the lock after the winning transaction commits, observe `available_units == 0`, and take the same insufficient-availability branch as a request made when inventory was already zero. The controller does not record whether the request waited, compare a pre-lock snapshot, catch a specific concurrency exception, or raise a distinct `LAST_UNIT_CONFLICT` business exception. `DB::transaction` retry count is not itself a detector for this specific race.

The source therefore reliably observes **no available unit at the point it checks the locked row**, but it does not reliably identify why the count became zero. A generic database/locking exception is not currently mapped in this controller to `LAST_UNIT_CONFLICT`; if uncaught, it follows Laravel's exception rendering path. It must not be classified as a last-unit conflict merely because it occurred during a loan transaction.

#### Existing test and evidence

- `api/tests/Feature/Loans/LoanApiTest.php::test_manager_cannot_register_loan_when_no_units_are_available` sets `available_units` to zero before a single request and asserts HTTP 409. It covers the normal business availability condition only; it does not assert a code envelope and does not create simultaneous requests.
- The same feature test file covers a successful loan and checks that availability decreases, but source inspection found no automated two-request concurrency test distinguishing pre-existing zero inventory from a concurrent winner.
- `docs/evidencias/punto-07-concurrencia/punto-07-concurrencia.md` records one manual two-manager scenario: one request returned 201, the other returned 409, and the inspected test database showed one loan/line and zero available units. This supports correct outcome for that one recorded race, but the document explicitly states repeatability and automated concurrency coverage are not established.
- The retained evidence says the losing 409 response included `file` and `trace`, with a local Windows path. It establishes a disclosure risk in the recorded run, not a safe or stable error-code distinction.
- No tests were executed for this review; these are source and retained-evidence findings.

#### Can the current API distinguish the cases?

No. The sequential test's already-zero condition and the manual race both end as HTTP 409 after the same availability check. The current controller response is a message-only abort, not a versioned code. The evidence distinguishes the scenarios for the human observer from the setup and database timeline, but the request handler does not make that distinction. It would be unsupported to claim that current production logic can emit `LAST_UNIT_CONFLICT` only for a proven concurrent race.

#### Alternatives

**Alternative A — Two codes, with strict detection.** Keep `AID_NOT_AVAILABLE` for stock already insufficient at the relevant request check, and return `LAST_UNIT_CONFLICT` only after the application can positively establish that a competing transaction reserved the last unit. Both remain HTTP 409. The v1 bodies would follow approved decisions 1–4, for example:

```json
{
  "code": "AID_NOT_AVAILABLE",
  "message": "No hay unidades disponibles de esta ayuda técnica.",
  "details": []
}
```

```json
{
  "code": "LAST_UNIT_CONFLICT",
  "message": "Otra operación reservó la última unidad. Verifica la disponibilidad e inténtalo nuevamente.",
  "details": []
}
```

Advantages: clients can distinguish an ordinary inventory condition from a verified race and offer a more relevant next step; preserves the distinction already listed in `docs/api-contract.md`. Disadvantages: current code does not supply this evidence, so implementation would need an explicit, database-appropriate reliable detection strategy and automated concurrency proof. A pre-lock read alone can be stale and cannot establish a race safely without a defined comparison/locking protocol.

**Alternative B — One stable availability code when cause is indistinguishable.** Return `AID_NOT_AVAILABLE` for any request that, after the serialized inventory check, cannot obtain a unit. Do not emit `LAST_UNIT_CONFLICT` unless a later implementation proves the specific race. Both sequential exhaustion and the competing loser receive HTTP 409 with the same code and a safe Spanish message, such as “No hay unidades disponibles de esta ayuda técnica.”

Advantages: matches what the current handler actually knows, has simple stable semantics, avoids false claims, and is testable with the present business rule. Disadvantages: clients cannot distinguish a prior stock shortage from a just-lost race; the contract's separate `LAST_UNIT_CONFLICT` meaning would need an approved documentation decision or remain reserved for a future verified implementation.

**Alternative C — Generic `INVENTORY_CONFLICT` code for both.** Introduce a new 409 code for any inability to reserve stock. This avoids implying a cause, but adds a code, does not improve user action compared with Alternative B, and conflicts with the existing contract's `AID_NOT_AVAILABLE` code. There is no evidence that the current code benefits from this additional abstraction.

#### Risks of incorrect classification

- Reporting `LAST_UNIT_CONFLICT` for a normal request made against already exhausted inventory falsely asserts a concurrent event the handler did not observe.
- Reporting `AID_NOT_AVAILABLE` for a deadlock, connection failure, lock timeout, or unrelated SQL exception hides an operational failure and can mislead support; such exceptions should receive a generic safe server error unless a specifically recognized condition is mapped.
- Mapping all HTTP 409 responses by status alone is unsafe because 409 also represents other business conflicts (for example, duplicate reference or already-returned item).
- A broad catch of database exceptions that converts them to `LAST_UNIT_CONFLICT` could conceal defects, outages, or integrity failures. Do not infer semantic codes from raw exception strings.
- Any response, including concurrency errors, must use the approved v1 envelope and disclose no SQL, exception class, local path, or stack trace. The legacy `/api` body remains unchanged during the transition per Decision 2.

#### Approved decision — combined Alternative A + B

The owner approved a two-stage strategy:

**Stage 1 — Current behavior policy (Alternative B).** For the current implementation's observed no-availability condition, the stable semantic code is `AID_NOT_AVAILABLE`. When the future standardized `/api/v1` error envelope is implemented under separate authorization, use this code whenever the loan cannot proceed because the locked inventory check finds no units. Do not use `LAST_UNIT_CONFLICT` based only on HTTP 409, a generic database/locking exception, or an observed lack of stock. Do not claim that concurrency occurred unless the application can reliably identify that specific situation. The current Laravel implementation remains unchanged by this decision; its existing `/api` response format and behavior are not altered.

The proposed v1 response for this condition is:

```json
{
  "code": "AID_NOT_AVAILABLE",
  "message": "No hay unidades disponibles de esta ayuda técnica.",
  "details": []
}
```

**Stage 2 — Future improvement (Alternative A).** A future, separately authorized implementation may emit `LAST_UNIT_CONFLICT` only if all of the following are satisfied:

1. A reliable mechanism distinguishes a concurrent last-unit loss from inventory that was already unavailable.
2. Repeatable automated concurrency tests demonstrate that distinction and verify inventory atomicity.
3. The code's meaning is stable and documented.
4. The response follows the approved D9 envelope (`code`, Spanish `message`, and `details` array) and does not expose sensitive or internal information.
5. The owner gives separate explicit authorization for the implementation.

Until then, `LAST_UNIT_CONFLICT` is reserved and must not be inferred from generic database errors or the shared 409 status. This approval records the policy and future gate only; it does not authorize either stage to be implemented. The standardized format remains initially scoped to `/api/v1`; legacy `/api` response bodies remain unchanged during transition under Decision 2.

#### Future tests required (not run)

- Sequential exhausted inventory: one request receives 409 `AID_NOT_AVAILABLE`, no loan/line is added, inventory is unchanged.
- Sequential inventory with one unit: one request succeeds, availability decreases by one, and the loan/line commit atomically.
- Concurrent requests for one available unit using the production database engine or an equivalent isolated integration environment: exactly one succeeds, one receives 409; inventory never becomes negative and no partial loan remains.
- For future Stage 2 only: instrument/construct the detector's exact positive race condition and assert `LAST_UNIT_CONFLICT`; also prove an already-empty inventory request never gets that code. Repeat the race enough times to establish reproducibility. These tests are prerequisites, not authorization to implement.
- Inject or simulate deadlock, lock timeout, and generic database failures; assert none is mislabeled `LAST_UNIT_CONFLICT` or `AID_NOT_AVAILABLE` without a reliable domain observation.
- For all v1 cases, assert the approved `code/message/details` envelope, `details` array, Spanish safe message, and absence of SQL, `file`, `trace`, exception text, credentials, and filesystem paths, including when debug output is enabled in the test configuration.
- Keep legacy `/api` response regression checks separate; do not change legacy response bodies under this decision.

### Decision 6 — Request ID and log redaction: APPROVED (documentation/design policy only)

The owner approved the following policy for a future `/api/v1` implementation. This approval does not authorize implementation:

**Request ID / Correlation ID**

- Generate a server-side UUID v4 for each request.
- Return that identifier in the `X-Request-ID` response header and associate the same identifier with relevant request-scoped application logs.
- Do not blindly trust arbitrary client-supplied identifiers. If external identifiers are accepted in a future design, validate them before use.
- Do not add the request ID to the JSON error body under this decision; preserve the approved `code`, `message`, `details` structure.

**Sensitive information protection and log redaction**

- Use allowlisted structured logging: record only explicitly permitted fields needed for safe troubleshooting.
- Exclude complete request bodies; passwords; authentication tokens; session identifiers; recovery codes; sensitive headers; and private beneficiary information.
- Do not expose SQL statements, database credentials, local filesystem paths, exception traces, or internal implementation details in API responses.
- Retain only information necessary for safe troubleshooting. This policy does not claim that the current logging pipeline already enforces these exclusions.

**Verification limitations remain open**

The investigation did not verify actual runtime logging destinations, retention periods, access controls, or external infrastructure. These aspects are neither verified nor resolved by this approval and must be assessed separately before implementation or operational acceptance.

This approval is limited to the request-ID design policy, the allowlisted logging policy, and the stated exclusions/limitations. The standardized error response remains initially scoped to `/api/v1`; legacy `/api` responses remain unchanged during transition under approved Decision 2. No code, configuration, or runtime behavior is changed by this documentation decision.

#### Request ID findings

Source inspection found no project-defined request/correlation ID mechanism:

- `api/bootstrap/app.php` configures routing, the `role` middleware alias, and JSON rendering for API requests. It does not register a request-ID middleware or configure request-ID propagation.
- Searches of `api/app`, `api/bootstrap`, `api/config`, and `api/tests` found no project references to `X-Request-ID`, correlation IDs, or trace IDs, and no code that reads or returns such an identifier.
- `app/src/services/api.js` sends the configured API base URL and adds a Bearer `Authorization` header when a token exists. It does not generate, send, or consume a request ID.
- Inspected feature tests cover authentication and authorization behavior, but no test asserts request-ID generation, validation, response headers, log correlation, or propagation.
- `api/bootstrap/app.php` has no application exception renderer that could currently attach a request ID to standardized v1 errors. The application also has no custom `api/app/Exceptions/Handler.php` in the inspected source tree.

Consequently, there is no repository evidence that an identifier follows a request from ingress through response and logs. Infrastructure outside the repository (reverse proxy, hosting platform, log collector) was not inspected, so an external identifier or correlation service cannot be ruled out.

#### Logging and sensitive-data findings

- `api/config/logging.php` uses Laravel's configurable `stack` default, which by default points at the `single` file channel. The single/daily channels use `storage/logs/laravel.log` and the configured `LOG_LEVEL`; the example environment sets `LOG_LEVEL=debug`. The config contains normal channel/handler setup, but no project-defined redaction processor for credentials, authorization headers, request bodies, beneficiary data, or recovery codes.
- The configured Papertrail channel uses `PsrLogMessageProcessor`; source inspection does not establish that it redacts arbitrary sensitive fields.
- The actual `.env` and deployed logging destination, retention, access controls, and runtime `APP_DEBUG`/`LOG_LEVEL` values were not inspected. Do not infer them from `.env.example`.
- Searches found no application code that logs complete request headers or bodies. That is a source-review observation, not proof that framework, server, proxy, or external observability tooling never records them.
- In `AuthController::forgotPassword`, a mail exception is passed to Laravel's `report($exception)`. This may write exception diagnostics through Laravel's configured reporting/logging pipeline. The shown API response is a generic message, but actual log content and destination were not tested.
- `AuthController` handles passwords, bearer-token issuance, and recovery codes. The `User` model hides password and remember-token fields from serialization, but that does not establish that all logs, exceptions, or request dumps are redacted.
- `docs/evidencias/punto-07-concurrencia/punto-07-concurrencia.md` records that a historical concurrency 409 response included `file` and `trace` data with a local Windows path. `docs/evidencias/estado-verificacion-2026-11-26.md` likewise records this as historical evidence and cautions it was not freshly reproduced. This is evidence of an API-response disclosure in the recorded incident, not evidence about current logs.

The repository therefore provides no basis to claim that log redaction is implemented or that logs contain a particular sensitive field. There is a confirmed historical response-disclosure incident and an exception-reporting path whose actual log output remains unverified.

#### Proposed Request ID alternatives

**Request ID Alternative A — Server-generated ID, no client-supplied ID accepted (recommended).** For every `/api/v1` request, generate a cryptographically random UUID v4 at the application boundary. Store it in request context for the duration of the request, return it in `X-Request-ID`, and attach the same value to structured log context. Ignore any inbound `X-Request-ID` as an authoritative value (or overwrite it); this prevents spoofing and log injection. Do not add an ID to the JSON error body initially: the approved body remains exactly `code`, `message`, `details`, while support can use the response header to correlate reports.

Advantages: one trusted value, simpler validation, resistant to arbitrary caller values, minimal JSON/client compatibility impact. Operational drawback: an upstream proxy's ID will not automatically match; infrastructure correlation requires an explicitly configured trusted mapping later. Compatibility: v1-only middleware/header is additive; legacy `/api`, React screens, and seeder can ignore the response header. Tests must verify uniqueness, correct propagation within one request, response header on success and errors, matching log context, and no caller-controlled injection.

**Request ID Alternative B — Accept a validated caller ID, otherwise generate one.** Accept `X-Request-ID` only when it meets a strict format and length (recommend UUID v4 only); otherwise generate a new ID. Echo the accepted/generated value in the response header and add it to request-scoped structured log context.

Advantages: can correlate a request across a trusted gateway and application when both preserve the same ID. Disadvantages: a public client can intentionally reuse IDs, causing collisions or misleading support searches; permissive formats can inject control characters or oversized log values. Compatibility: additive for v1 and ignored by current React/seeder; intermediaries may strip or rewrite the header. Tests must cover valid, malformed, oversized, repeated, and malicious IDs, and prove invalid values are replaced rather than reflected/logged.

**Request ID Alternative C — No application request ID.** Continue relying on server timestamps and platform logs.

Advantages: no code or client-visible header changes. Disadvantages: difficult support correlation across concurrent requests and no consistent link between response and logs. Compatibility is highest, but it does not meet the operational correlation goal of D6.

#### Proposed log-redaction alternatives

**Redaction Alternative A — Allowlisted structured logging; never log request bodies or auth headers (recommended).** Log only operational metadata needed for diagnosis: request ID, route name/template (not raw query values), HTTP method/status, duration, stable error code, and a safe actor identifier only if specifically approved. Do not log `Authorization`, cookies/session IDs, passwords, password confirmation, reset codes, full request/response bodies, beneficiary names/documents, or SQL bind values. Keep detailed exception diagnostics server-side with restricted access and retention, but sanitize exception context and never include credentials or unnecessary personal data.

Advantages: minimizes collection at the source; easier to reason about than trying to scrub arbitrary payloads; request ID plus stable code preserves practical troubleshooting. Disadvantages: less context in routine logs; requires consistent structured logging and restricted diagnostic access. Compatibility: does not change API bodies or UI, but changes operational log content and needs operator agreement. Tests should capture emitted records and assert allowlisted fields and absence of synthetic secrets/PII.

**Redaction Alternative B — Broad logging with a centralized redaction processor.** Permit selected structured request/error context but scrub keys matching a strict denylist (`password`, `token`, `authorization`, `cookie`, `secret`, recovery-code fields, beneficiary identifiers) before any log handler receives it.

Advantages: can retain richer troubleshooting context. Disadvantages: denylist gaps, nested values, renamed fields, free-text exceptions, SQL binds, and format differences can bypass scrubbing; multiple channels/handlers can behave differently. Compatibility: no API-client changes, but logging configuration and all channel paths must be covered. Tests must exercise nested/case-varied keys, free-text, exception context, every configured handler, and prove secrets are absent after processing. Never treat this alternative as justification to log complete bodies or headers.

**Redaction Alternative C — Minimal operational logs and restricted raw diagnostics only on explicit, controlled incident capture.** Routine logs contain status, duration, request ID, and safe error code. Detailed diagnostics are captured only in a controlled environment with access restriction, synthetic or minimized data, and short retention.

Advantages: lowest routine data exposure. Disadvantages: can slow incident investigation and needs a documented break-glass process; local development may need a separate safe debugging workflow. Compatibility: API clients unaffected. Tests/documentation must ensure production never switches to raw request dumps and incident captures have access/retention controls.

#### Risks and limitations

- A request ID is a correlation handle, not authentication, authorization, or proof of user identity. Never use it to grant access.
- Trusting arbitrary external IDs can create spoofed correlation, collisions, log injection, and high-cardinality logging. If inbound IDs are supported, validate tightly and impose length/character limits.
- Returning the same ID in header and logs is useful only if every relevant middleware/exception path preserves it; failures before application middleware may need edge infrastructure support.
- Putting IDs in JSON `details` would expand the approved error body and is unnecessary if the response header is consistent; any body field would need owner approval and contract revision.
- Redaction performed only at one log handler can be bypassed by another channel or direct `report()` call. Apply policy before fan-out and test each configured sink.
- Removing all exception diagnostics from logs can impair support; retain controlled, access-limited diagnostic context without secrets, unnecessary PII, SQL credentials, or raw request data.
- `APP_DEBUG=true` appears in `.env.example`, but the runtime environment was not read. The historical error evidence shows why both debug and non-debug response paths need verification; it does not establish current runtime settings.
- Laravel/framework, web server, proxy, mail transport, database driver, and external log-service behavior were not executed or inspected comprehensively. No claim is made that repository changes alone cover those layers.

#### Recommendation

The approved design policy selects Request ID Alternative A and Redaction Alternative A for a future `/api/v1` implementation. Runtime logging destinations, retention, access controls, and external infrastructure still require verification. Implementation remains subject to separate authorization. Keep legacy `/api` responses and behavior unchanged during transition under approved Decision 2.

#### Future verification required (not run)

- Feature tests assert every `/api/v1` response path, including standardized errors and unexpected exceptions, has the same `X-Request-ID` in the response header and request-scoped log context.
- Verify generated IDs are unique UUID v4 values; malformed or hostile inbound values are ignored/replaced under Alternative A (or rejected/replaced exactly as specified under Alternative B).
- Test successful, validation, authentication, authorization, 404, 405, business-conflict, throttling, and 500 paths to ensure the ID survives the full middleware/exception lifecycle.
- Use a log-capture test handler and synthetic credentials, tokens, reset codes, cookies, beneficiary data, SQL-like values, local paths, and stack traces. Assert routine logs contain only approved fields and never expose prohibited values.
- Verify each configured channel/handler and exception-reporting path, including the `report($exception)` path in password recovery, without using real credentials or sending real mail.
- Run a controlled debug-on/debug-off API response check in an isolated environment; assert no `file`, `line`, `trace`, exception detail, SQL, or local path is returned. Reproduce the concurrency error and check both body and header while preserving the approved AID/LAST_UNIT policy from Decision 5.
- Test request lifecycle propagation through framework middleware and, if later configured, the trusted proxy/log collector. External infrastructure correlation remains unverified until that environment is available.

### Decisions 7–8 — PENDING

7. The React dual-format compatibility period and its completion criteria.
8. Confirmation that the seeder stays unchanged; current source indicates no D9-driven change is necessary.

No implementation is approved by this document. Phase 2 must not start until the owner reviews and explicitly approves the remaining relevant decisions and separately authorizes implementation.

## 12. Files inspected

- `docs/api-contract.md` and `docs/proposals/api-contract-decisions-proposal.md`
- `api/bootstrap/app.php`
- `api/app/Http/Controllers/AuthController.php`
- `api/app/Http/Controllers/BeneficiaryController.php`
- `api/app/Http/Controllers/TechnicalAidController.php`
- `api/app/Http/Controllers/LoanController.php`
- `api/app/Http/Middleware/CheckRole.php`
- `api/tests/Feature/Auth/*`, `api/tests/Feature/Loans/LoanApiTest.php`, and related feature tests (source inspection only)
- `api/config/app.php`, `api/.env.example`
- `api/config/logging.php`
- Absence of project request-ID middleware/references in `api/app`, `api/bootstrap`, `api/config`, and `api/tests` (source search only)
- `app/src/services/api.js`, `app/src/services/uiMessages.js`, and API-consuming screens (source inspection only)
- `tool/seed_demo_data.py`
- `docs/evidencias/punto-07-concurrencia/punto-07-concurrencia.md`
