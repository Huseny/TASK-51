# Business Logic Questions Log

1. [Authentication Lockout Flow]
   - **Question**: Should the 5th failed login return `401` or immediately return `429`?
   - **My Understanding**: The acceptance tests mention "After 5 failures, 6th attempt returns 429," which implies attempt 5 still returns `401` and activates lockout.
   - **Solution**: Implemented lock activation on the 5th failure while still returning `401`; subsequent attempts during lockout return `429` with `locked_until`.

2. [Role Middleware and Admin Access]
   - **Question**: Should admin bypass role checks automatically, or only when explicitly listed in allowed roles?
   - **My Understanding**: Middleware should enforce only explicitly allowed roles for predictability and explicit authorization.
   - **Solution**: Implemented strict role matching and explicitly included `admin` on routes where universal admin access is expected.

3. [Rider Cancel Permission]
   - **Question**: Can a rider cancel an `in_progress` trip?
   - **My Understanding**: No. Rider-initiated cancel is only allowed in `matching` or `accepted`.
   - **Solution**: Implemented cancel policy restriction to rider-owned orders in `matching` or `accepted` only.

4. [Auto-Revert Behavior]
   - **Question**: Should auto-revert create a new order or reuse the same order?
   - **My Understanding**: Reuse the same order record and return it to `matching`.
   - **Solution**: Implemented `reassign` transition on the same order ID, clearing `driver_id` and `accepted_at` and auditing the transition.

5. [Nearby-in-Time Driver Queue]
   - **Question**: How is "nearby-in-time" defined for available rides?
   - **My Understanding**: Include rides whose `time_window_start` is within +/- 2 hours of current time.
   - **Solution**: Implemented configurable `RIDE_AVAILABLE_WINDOW_HOURS` (default `2`) and filtered `matching` rides accordingly.

6. [Read Receipts Behavior]
   - **Question**: Are read receipts opt-in per user or always on?
   - **My Understanding**: Read receipts are always on and visible to participants.
   - **Solution**: Implemented read receipts as default behavior via `message_read_receipts`, with idempotent mark-read API and sender-visible receipt data.

7. [DND Timezone Basis]
   - **Question**: Should DND use server timezone or user timezone?
   - **My Understanding**: Use server timezone for now to keep offline logic deterministic.
   - **Solution**: Implemented DND window checks using server time in `DndService` and documented this behavior.

8. [Media Asset Deletion Strategy]
   - **Question**: Should media assets be hard-deleted or soft-deleted?
   - **My Understanding**: Soft-delete media assets so deduplicated references remain valid and recoverable.
   - **Solution**: Added `deleted_at` on `media_assets` and only remove `vehicle_media` pivot rows when media is detached from a vehicle.

9. [Extension vs MIME Mismatch]
   - **Question**: Should extension vs MIME mismatch (e.g., `.jpg` filename with PNG bytes) be rejected?
   - **My Understanding**: For JPEG/PNG family mismatches, accept if MIME is valid image type and log warning.
   - **Solution**: Validated allowed MIME + extension set, tolerated JPEG/PNG mismatch by MIME with warning log, and normalized storage extension from MIME.

10. [Shared Inventory Scope]
   - **Question**: For `shared` inventory strategy, is stock tracked per variant or pooled across shared variants of the same product?
   - **My Understanding**: Shared inventory is pooled across all `shared` variants belonging to a single product.
   - **Solution**: Implemented pooled stock checks/decrements across all locked `shared` variants under the same product during purchase.

11. [Suppressed Notifications Handling]
   - **Question**: What happens to notifications suppressed by frequency caps?
   - **My Understanding**: Suppressed notifications are silently dropped. The prompt says alerts are constrained to a maximum, which implies hard limits and no delayed queueing.
   - **Solution**: Implemented strict cap enforcement that discards suppressed notifications and does not enqueue them for later delivery.

12. [Offline Collaborative Filtering Scope]
   - **Question**: How should collaborative filtering be implemented efficiently in offline PHP/MySQL without external ML tooling?
   - **My Understanding**: Full matrix factorization is too heavy for this stage and environment.
   - **Solution**: Implemented a simplified collaborative baseline using global item popularity weighted by interaction scores, then fused it with per-user content similarity on categories/tags.

13. [Region Multi-Match Priority]
   - **Question**: What happens if a ride origin address matches keywords from multiple regions?
   - **My Understanding**: First match wins.
   - **Solution**: Implemented sequential region matching using the order defined in `database/data/regions.json`; first matched region is selected, otherwise `Other`.

14. [Offline Sync Failure Handling]
   - **Question**: What happens if a queued offline request fails during sync (for example, a 422 validation error)?
   - **My Understanding**: The user should be notified and the request should not be retried indefinitely by default.
   - **Solution**: Implemented an error toast on sync failure and discarded the failed queue item in this MVP flow.
