# Frontend fixes batch — backend changes (2026-09-10)

A round of frontend issues was worked through in the Nuxt session. Most were
frontend-only (they wired up endpoints that already existed). Two touched this
repo; both are recorded here.

---

## 1. Completed bookings drop off the dashboard 24h after completion

The customer/provider dashboard's "work queue" needed to hide completed
bookings 24 hours after the customer confirms completion. The bookings list
payload had no completion timestamp to key that off — only `created_at`.

### Backend (this repo)
- Migration `2026_09_10_000000_add_completed_at_to_bookings_table`:
  - Adds nullable `completed_at` datetime to `bookings` (after `cancelled_at`).
  - Backfills existing `completed` bookings from the latest `booking_history`
    row with `status = 'completed'` (falling back to `updated_at`), so the
    24h rule works retroactively.
- `Booking` model: `completed_at` added to `$fillable` and cast to `datetime`.
- `BookingController::confirmCompletion` now sets `completed_at => now()` when
  transitioning a booking to `completed`. This is the only place that
  transition happens.
- No change to the list endpoints — `completed_at` is a plain column so it
  serialises automatically in `myBookingsAsCustomer` / `myBookingsAsProvider`.

### Frontend (SkillLink-web)
- `app/pages/dashboard.vue` filters out completed bookings whose
  `completed_at` (fallback `updated_at`/`created_at`) is more than 24h old,
  across all work-queue tabs. The lifetime "Completed" stat card is unchanged.

---

## 2. Provider phone revealed to the customer only after a booking is accepted

Previously the provider's `phone` (and `email`) were serialised on the public
`GET /providers/{id}` payload and on the booking payload regardless of status —
the frontend just never displayed them. The chosen policy: a customer sees the
provider's phone number once they have an **accepted** booking together.

### Backend (this repo)
- `ProviderProfileController::showPublic` now calls
  `$profile->user?->makeHidden(['phone', 'email'])` — contact details are no
  longer in the public provider profile response.
- `BookingController::show`: when the booking status is not one of
  `accepted` / `in_progress` / `awaiting_confirmation` / `completed`, both the
  provider's and the customer's `phone` are hidden from the payload
  (`makeHidden(['phone'])`). Once the booking is live, both parties can see
  each other's number.

### Frontend (SkillLink-web)
- `app/pages/bookings/[id].vue` shows the counterpart's phone as a `tel:` link
  in the booking header when the booking is accepted or later.

### Known follow-up (not done here)
`phone` is still present on `provider.user` in the services list/detail
(`ServiceController@index/show`) and job-offer (`OfferController@index`)
payloads. The frontend does not render it in those places, but if contact
privacy matters more broadly, add `phone`/`email` to `User::$hidden` globally
and `makeVisible` only where a party is legitimately entitled to it
(`/user`, accepted bookings, admin dashboards).

---

## Frontend-only items in the same batch (no backend change)

- **Decline button on job offers** — `POST /offers/{offerId}/reject` already
  existed; the customer UI (`jobs/[id].vue`, dashboard "Compare offers") now
  calls it.
- **Edit / delete a posted job** — `PUT /jobs/{id}` and `DELETE /jobs/{id}`
  already existed (open jobs only); added owner-facing Edit/Delete UI and an
  edit mode on `jobs/post.vue` via `?edit=<id>`.
- **Collapsible booking history** — `bookings/[id].vue` now shows the history
  trail behind a "View / Hide booking history" toggle.
- **Provider portfolio management** — `POST/DELETE /provider/profile/portfolio`
  already existed and the public profile already rendered `portfolio`; added
  an upload/delete UI on `provider/services.vue`.
- **Messages page scroll** — CSS-only fix so the chat pane scrolls internally
  instead of the whole page.
- **AI assistant** — `server/api/ai-chat.post.ts` now takes conversation
  `history`, and the system prompt allows answering platform questions (escrow,
  fees, how it works) instead of always returning a provider list; the local
  fallback answers common FAQs. NOTE: the `GEMINI_API_KEY` in the web project's
  `.env` is not a valid Google AI Studio key (`AQ.Ab8…`, not `AIza…`), so every
  live call currently fails and the code falls back to the local recommender —
  this needs a real key to actually use Gemini.
