# Support contact form was a mockup — wired to the backend (2026-09-11)

## What was found

The `/support` page's "Contact Support & Report Issues" form
(`app/pages/support.vue` in the web repo) didn't call any API — `submitContact()`
just flipped a `submitted` boolean and showed a fake "Message Received" screen.
Whatever the user typed was discarded.

Separately, the admin "Technical Inquiries" tab (`app/pages/admin/support.vue`)
read/wrote `localStorage.getItem('skilllink_technical_inquiries')` — a
browser-local key, not a backend table — so even a real submission from a
customer's browser could never reach an admin's browser on another machine.

The "User Reports" and "Order Disputes" tabs on that same admin page were
**already** correctly wired to `GET/PATCH /admin/support/reports` and
`GET/PATCH /admin/support/disputes` — those needed no backend change. Disputes
shows empty because nothing in the product currently lets a customer or
provider raise one (no "raise a dispute" UI exists yet); that's a separate,
larger feature, not a wiring bug.

## Backend change

Rather than a new table, "technical inquiry" reuses `UserReport` (the model
behind "User Reports") with a `type` column distinguishing the two use cases:

- Migration `2026_09_11_000000_add_type_to_user_reports_table`:
  - `reported_user_id` is now nullable (a technical inquiry isn't a report
    against another user).
  - Adds `type` (`'user'` default | `'technical'`) and `subject` (nullable
    string) columns.
  - Extends the `status` enum to add `'resolved'` (user reports keep using
    `pending`/`reviewed`/`dismissed`; technical inquiries use `pending`/`resolved`).
- `UserReport` model: `type`/`subject` added to `$fillable`; added
  `scopeUserReports` / `scopeTechnicalInquiries` query scopes.
- New `App\Http\Controllers\Api\SupportContactController::store` —
  `POST /api/support/technical-inquiries` (`auth:sanctum`, `verified.email`).
  Takes `{ subject, message }`; `reporter_id` is always the authenticated
  user (never trusts a client-supplied name/email), `reported_user_id` is
  null, `type = 'technical'`.
- `SupportDashboardController`:
  - `reports()` now takes `?type=user|technical` (defaults to `user`) and
    filters on it — same endpoint serves both admin tabs.
  - `updateReport()` accepts `status = resolved` in addition to the existing
    values.
  - `stats()`'s report KPIs (`pending_reports`, `total_reports`,
    `recent_reports`) now scope to `type = user` via `UserReport::userReports()`,
    and a new `open_technical_inquiries` KPI was added (not yet consumed by
    the frontend — the admin page computes that count client-side from the
    loaded list instead, same as it already did for reports/disputes).

Verified end-to-end against the running server: logged in as the seeded
`customer@skilllink.test`, POSTed a technical inquiry, confirmed it appears
under `admin/support/reports?type=technical` for `support@skilllink.test` and
is absent from `?type=user`, then resolved it via `PATCH`. The test row was
deleted afterward.

## Frontend (SkillLink-web)

- `app/pages/support.vue`: `submitContact()` now POSTs to
  `/support/technical-inquiries`. Added a required Subject field. If the
  visitor isn't logged in, the form shows a sign-in prompt instead of
  submitting (the endpoint requires an authenticated user — the ticket is
  always attributed to the real account, not a typed name/email). Name/email
  fields are pre-filled from `/user` when logged in but are display-only;
  they aren't sent to the API.
- `app/pages/admin/support.vue`:
  - `loadReports()` now requests `?type=user`.
  - `loadTechnicalInquiries()` now calls `GET /admin/support/reports?type=technical`
    and maps the response onto the shape the existing table/modal already
    expected (`provider_name`, `email`, `subject`, `issue`, `status: 'open'|'resolved'`)
    — no template changes needed.
  - `resolveTechnicalInquiry()` now `PATCH`es `/admin/support/reports/{id}`
    with `{ status: 'resolved' }` instead of writing to `localStorage`.
