# Backend Handoff — from the SkillLink-web session (2026-08-31)

Context carried over from a Claude Code session in the Nuxt frontend repo
(`C:\Users\hp\Documents\Projects\SkillLink-web`). Two backend work items came
out of it. The frontend changes for item 1 are **already done**; the backend
must be changed to match or the new flow returns 422.

---

## Item 1 — Remove the `in_progress` booking step

### Decision
The booking lifecycle was 5 states and is being trimmed to 4:

```
OLD: pending → accepted → in_progress → awaiting_confirmation → completed
NEW: pending → accepted → awaiting_confirmation → completed
```

Rationale: `accepted` and `in_progress` were two provider-driven steps doing
almost the same thing. `awaiting_confirmation` stays — it is the buyer-protection
gate (provider can't declare the job done and get paid without customer sign-off).

### Frontend already changed (for reference)
In `app/pages/bookings/[id].vue`:
- `lifecycle` array no longer contains `in_progress`.
- The "Mark as started" panel was deleted.
- The `accepted` panel now calls `POST /bookings/{id}/mark-awaiting-confirmation`
  directly (previously it called `/start-progress`).
- Legacy bookings still in `in_progress` are displayed as if `accepted`, and the
  provider "Mark as complete" action accepts both `accepted` and `in_progress`.

### Backend changes required
1. **Booking status enum / state machine**: drop `in_progress`; allow the
   transition `accepted → awaiting_confirmation` directly (provider only).
2. **Remove or neutralise** the `start-progress` route/controller action
   (`POST /bookings/{id}/start-progress`). Leaving it as a no-op that returns
   the booking is fine if something still calls it.
3. **Data migration**: move existing rows with `status = 'in_progress'` to
   `status = 'accepted'` (or `awaiting_confirmation` if that fits the real state
   better — check with the user).
4. Keep `mark-awaiting-confirmation` working when the current status is
   `accepted` (today it may only allow `in_progress`).
5. Double-check `history`/audit-trail writes still fire on the new transition.

### Endpoints in play (verify actual names in `routes/api.php`)
| Transition | Actor | Endpoint |
|---|---|---|
| pending → accepted | provider | `POST /bookings/{id}/accept` |
| pending → rejected | provider | `POST /bookings/{id}/reject` (reason required) |
| accepted → awaiting_confirmation | provider | `POST /bookings/{id}/mark-awaiting-confirmation` |
| awaiting_confirmation → completed | customer | `POST /bookings/{id}/confirm-completion` |
| active → cancelled | either | `POST /bookings/{id}/cancel-by-customer` / `.../cancel-by-provider` |

### Also fix: `/user` payload shape
The frontend `isProvider` check broke because the provider-profile relation was
missing or snake/camel-case mismatched on the `/user` response. Make `/user`
consistently include the provider profile (with its `id`) for provider accounts,
e.g. always eager-load and serialise it as `provider_profile` (or `providerProfile`
— just pick one and keep it stable). Frontend now tolerates several key names
plus a `provider.user.id` fallback, but a consistent payload is the real fix.

---

## Item 2 — Real payments via Chapa (replace the simulation)

### Decision
Use **Chapa** (https://chapa.co) as the payment aggregator. Do **not** integrate
Telebirr directly yet.

Why Chapa over direct Telebirr:
- Card/wallet credentials never touch our servers — smaller attack surface, no
  PCI burden. Chapa is NBE-licensed and PCI-DSS compliant.
- One integration covers Telebirr + CBE Birr + cards + bank transfer.
- Direct Telebirr means holding an RSA private key and implementing signature
  verification ourselves (forgery risk), plus a multi-week merchant onboarding
  with Ethio Telecom (business license required), and it's Telebirr-only.
- Cost: ~3.5% per transaction — acceptable for launch. Revisit direct Telebirr
  only if that fee becomes material at scale.

### Current state (frontend)
`app/pages/bookings/[id].vue` has a payment block that shows only after
`status === 'completed'`. The method dropdown options are all `*_sim` and it
calls `POST /bookings/{id}/pay` with `{ payment_method }`. The backend currently
just marks the booking paid. This is the simulation to replace.

### Design flaw to raise with the user
Payment is collected **after** completion, not held in escrow before work starts.
The UI advertises "100% escrow protection" but nothing stops a customer ghosting
after delivery. Proper flow: authorise/capture at booking time, hold, release on
`confirm-completion`. At minimum, discuss moving the `pay` step to booking
creation rather than after completion.

### Backend implementation (all server-side, never in the client)
1. **Init**: change `POST /bookings/{id}/pay` (or a new `/bookings/{id}/checkout`)
   to:
   - generate a unique `tx_ref`, e.g. `booking-{id}-{uuid}`
   - create a `payments` row with status `pending`, storing `tx_ref`, amount,
     currency `ETB`, booking id
   - call Chapa `POST https://api.chapa.co/v1/transaction/initialize` with amount,
     customer email/name, `tx_ref`, `callback_url` (our webhook), `return_url`
     (frontend booking page)
   - return Chapa's `checkout_url` to the frontend
2. **Frontend** redirects the customer to `checkout_url`.
3. **Webhook**: new route `POST /webhooks/chapa` (CSRF-exempt, no auth middleware):
   - verify the Chapa signature header against `CHAPA_WEBHOOK_SECRET`
   - call `GET https://api.chapa.co/v1/transaction/verify/{tx_ref}`
   - only if `status === 'success'` **and** the verified amount matches the
     booking total: mark the `payments` row `paid`, mark the booking paid,
     credit the provider wallet ledger (gross − 10% platform fee)
   - make this idempotent (webhook can fire more than once)
4. **Never** mark paid from the `return_url` redirect — treat it only as
   "customer came back", then show status from the DB.
5. Config: `CHAPA_SECRET_KEY`, `CHAPA_WEBHOOK_SECRET` in `.env` (backend only),
   never exposed to Nuxt or the browser.
6. Provider payout side still needs a real mechanism (Telebirr B2C transfer or
   manual batch payouts to start) — currently simulated in
   `app/pages/provider/wallet.vue`.

### Test with
Chapa test keys + their test Telebirr/card credentials before touching live keys.

---

## How this handoff was created
Written by Claude Code from the frontend session. To continue: open Claude Code
in this repo (`C:\Users\hp\Documents\Projects\SkillLink`) and say
"read docs/web-handoff-2026-08-31.md and start on item 1".
