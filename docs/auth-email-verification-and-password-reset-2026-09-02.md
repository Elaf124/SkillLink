# Auth — Email verification + password reset (2026-09-02)

Implemented across both repos in one session. Email verification uses a
**6-digit code** (not a magic link); password reset uses the same style.

## Database

Migration `2026_09_02_000000_add_email_verification_and_reset_codes` (already run):

- `users.email_verified_at` (nullable timestamp). **Existing users were
  backfilled to `now()`** so only new sign-ups have to verify.
- `email_verification_codes` — one row per user (`user_id` unique), `code` is
  bcrypt-hashed, `expires_at` (10 min), `last_sent_at` (resend cooldown).
- `password_reset_codes` — keyed by `email`, `code` hashed, `expires_at`
  (15 min), `created_at` (resend cooldown).

## Endpoints

| Method | Route | Auth | Notes |
|---|---|---|---|
| POST | `/api/email/verification-code` | Bearer | Issues + emails a code. 60s cooldown → `429 {message, retry_after}`. Route throttle `10,1`. |
| POST | `/api/email/verify` | Bearer | Body `{code}`. Wrong/expired → `422`. Success sets `email_verified_at`, returns `{message, user}`. |
| POST | `/api/forgot-password` | – | Body `{email}`. **Always `200`** with a generic message (no account enumeration). Route throttle `10,1`. |
| POST | `/api/reset-password` | – | Body `{email, code, password, password_confirmation}`. Wrong/expired → `422`. Success updates password and **revokes all Sanctum tokens**. |

`GET /api/user` now includes `email_verified_at`.

## Enforcement

New middleware alias `verified.email` (`App\Http\Middleware\EnsureEmailIsVerified`)
returns `403 {message, email_verified:false}` when the account isn't verified.
Added to every authenticated **action** group in `routes/api.php` (profiles,
services, jobs, offers, bookings, payments, reviews). **Not** applied to
`/user`, `/logout`, or the `/email/*` routes — an unverified user still needs a
working token to verify.

## Registration / OAuth

- `AuthController::register` is unchanged — new users are created with
  `email_verified_at = null`. The **frontend `/verify-email` page requests the
  first code on mount**, so registration itself sends nothing.
- `GoogleAuthController` sets `email_verified_at = now()` for Google sign-ups
  (Google has already confirmed the address) and backfills it for existing
  Google accounts on next login.

## Mail

`MAIL_MAILER=log` in dev → codes land in `storage/logs/laravel.log`. Mailables
(`VerificationCodeMail`, `PasswordResetCodeMail`) send **synchronously**, so no
queue worker is needed. For production set real SMTP creds in `.env`.

## Frontend (SkillLink-web)

- `pages/verify-email.vue` — code entry, 60s resend cooldown, auto-submit,
  "wrong address? start over". Routes onward after success (provider →
  `/provider/onboarding`, otherwise `/dashboard`).
- `pages/forgot-password.vue` — two steps (email → code + new password).
- `components/CodeInput.vue` — segmented 6-box input.
- `middleware/account-gates.global.ts` — client gate: unverified → `/verify-email`,
  then un-onboarded provider → `/provider/onboarding`.
- `register.vue` now routes to `/verify-email`; `login.vue` "Forgot password?"
  links to `/forgot-password`.

## "Fake email can't register"

Handled implicitly: the account is created but every meaningful action returns
`403` until the emailed code is entered. A non-existent / mistyped address never
receives the code, so the account stays inert. Optional follow-ups: MX check on
register, and a scheduled purge of accounts left unverified for N days.
