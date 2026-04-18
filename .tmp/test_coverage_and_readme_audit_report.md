# Test Coverage Audit

## Scope and Method

- Audit mode: static inspection only.
- No code/tests/scripts/containers were executed.
- Inspected only routing, tests, frontend test evidence, test runner script, and root README.
- Project type declaration found at top of [README.md](README.md#L1): fullstack.

## Backend Endpoint Inventory

Source of truth: [backend/routes/api.php](backend/routes/api.php#L20).

Total API endpoints discovered: 65.

1. GET /api/v1/readiness
2. POST /api/v1/auth/register
3. POST /api/v1/auth/login
4. POST /api/v1/auth/logout
5. GET /api/v1/auth/me
6. GET /api/v1/admin/panel
7. GET /api/v1/driver/queue
8. GET /api/v1/driver/available-rides
9. GET /api/v1/driver/my-rides
10. GET /api/v1/driver/my-rides/{rideOrder}
11. GET /api/v1/fleet/rides/queue
12. GET /api/v1/fleet/rides/active
13. GET /api/v1/fleet/rides/{rideOrder}
14. GET /api/v1/fleet/drivers
15. PATCH /api/v1/fleet/rides/{rideOrder}/assign
16. PATCH /api/v1/fleet/rides/{rideOrder}/reassign
17. PATCH /api/v1/fleet/rides/{rideOrder}/cancel
18. POST /api/v1/ride-orders
19. GET /api/v1/ride-orders
20. PATCH /api/v1/ride-orders/{rideOrder}/transition
21. GET /api/v1/ride-orders/{rideOrder}
22. GET /api/v1/ride-orders/{rideOrder}/chat
23. POST /api/v1/group-chats/{chat}/messages
24. GET /api/v1/group-chats/{chat}/messages
25. POST /api/v1/group-chats/{chat}/read
26. PATCH /api/v1/group-chats/{chat}/dnd
27. POST /api/v1/vehicles
28. GET /api/v1/vehicles
29. GET /api/v1/vehicles/{vehicle}
30. PUT /api/v1/vehicles/{vehicle}
31. DELETE /api/v1/vehicles/{vehicle}
32. POST /api/v1/vehicles/{vehicle}/media
33. PATCH /api/v1/vehicles/{vehicle}/media/reorder
34. PATCH /api/v1/vehicles/{vehicle}/media/{mediaId}/cover
35. DELETE /api/v1/vehicles/{vehicle}/media/{mediaId}
36. GET /api/v1/media/{media}/url
37. GET /api/v1/products
38. GET /api/v1/products/{product}
39. POST /api/v1/interactions
40. GET /api/v1/recommendations
41. GET /api/v1/notifications
42. GET /api/v1/notifications/unread-count
43. PATCH /api/v1/notifications/{notification}/read
44. PATCH /api/v1/notifications/read-all
45. POST /api/v1/notifications/events
46. POST /api/v1/follows
47. GET /api/v1/notification-subscriptions
48. POST /api/v1/notification-subscriptions
49. DELETE /api/v1/notification-subscriptions/{notificationSubscription}
50. POST /api/v1/products
51. PUT /api/v1/products/{product}
52. PATCH /api/v1/products/{product}/publish
53. DELETE /api/v1/products/{product}
54. POST /api/v1/products/{product}/purchase
55. GET /api/v1/reports/trends
56. GET /api/v1/reports/distribution
57. GET /api/v1/reports/regions
58. GET /api/v1/reports/export-directories
59. POST /api/v1/reports/export
60. GET /api/v1/reports/templates
61. POST /api/v1/reports/templates
62. PATCH /api/v1/reports/templates/{template}
63. DELETE /api/v1/reports/templates/{template}
64. GET /api/v1/media/{media}/download
65. GET /api/v1/reports/exports/{reportExport}

## API Test Mapping Table

| Endpoint                                                             | Covered | Test type                 | Test files                                                                                                               | Evidence                                                                        |
| -------------------------------------------------------------------- | ------- | ------------------------- | ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------- |
| GET /api/v1/readiness                                                | yes     | true no-mock HTTP         | backend/tests/Feature/Health/ReadinessTest.php                                                                           | test_readiness_reports_ready_when_schema_is_current                             |
| POST /api/v1/auth/register                                           | yes     | true no-mock HTTP         | backend/tests/Feature/Auth/RegisterTest.php                                                                              | test_successful_registration_returns_user_with_bearer_token                     |
| POST /api/v1/auth/login                                              | yes     | true no-mock HTTP         | backend/tests/Feature/Auth/LoginTest.php                                                                                 | test_valid_credentials_return_user_with_bearer_token                            |
| POST /api/v1/auth/logout                                             | yes     | true no-mock HTTP         | backend/tests/Feature/Auth/LoginTest.php                                                                                 | test_login_token_can_access_me_endpoint_and_logout_revokes_it                   |
| GET /api/v1/auth/me                                                  | yes     | true no-mock HTTP         | backend/tests/Feature/Auth/LoginTest.php                                                                                 | test_login_token_can_access_me_endpoint_and_logout_revokes_it                   |
| GET /api/v1/admin/panel                                              | yes     | true no-mock HTTP         | backend/tests/Feature/Auth/AdminAccessTest.php                                                                           | test_admin_can_access_admin_panel                                               |
| GET /api/v1/driver/queue                                             | yes     | true no-mock HTTP         | backend/tests/Feature/Auth/AdminAccessTest.php                                                                           | test_driver_can_access_driver_queue                                             |
| GET /api/v1/driver/available-rides                                   | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/DriverAvailableRidesTest.php                                                                 | test_returns_only_matching_rides_within_time_window                             |
| GET /api/v1/driver/my-rides                                          | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/DriverMyRidesTest.php                                                                        | test_driver_can_list_own_rides                                                  |
| GET /api/v1/driver/my-rides/{rideOrder}                              | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/DriverMyRidesTest.php                                                                        | test_driver_can_view_own_ride_detail                                            |
| GET /api/v1/fleet/rides/queue                                        | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetRideManagementTest.php                                                                  | test_fleet_manager_can_view_queue_and_active_rides                              |
| GET /api/v1/fleet/rides/active                                       | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetRideManagementTest.php                                                                  | test_fleet_manager_can_view_queue_and_active_rides                              |
| GET /api/v1/fleet/rides/{rideOrder}                                  | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetShowAndCancelTest.php                                                                   | test_fleet_manager_can_view_ride_detail                                         |
| GET /api/v1/fleet/drivers                                            | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetDriversTest.php                                                                         | test_fleet_manager_can_list_drivers                                             |
| PATCH /api/v1/fleet/rides/{rideOrder}/assign                         | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetRideManagementTest.php                                                                  | test_fleet_manager_can_assign_driver_to_matching_ride                           |
| PATCH /api/v1/fleet/rides/{rideOrder}/reassign                       | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetRideManagementTest.php                                                                  | test_fleet_manager_can_reassign_ride_to_new_driver                              |
| PATCH /api/v1/fleet/rides/{rideOrder}/cancel                         | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/FleetShowAndCancelTest.php                                                                   | test_fleet_manager_can_cancel_a_matching_ride                                   |
| POST /api/v1/ride-orders                                             | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/RideOrderCreationTest.php                                                                    | test_rider_creates_order_with_valid_data                                        |
| GET /api/v1/ride-orders                                              | yes     | true no-mock HTTP         | backend/tests/Feature/Security/DataIsolationTest.php                                                                     | test_ride_index_only_returns_authenticated_riders_orders                        |
| PATCH /api/v1/ride-orders/{rideOrder}/transition                     | yes     | true no-mock HTTP (mixed) | backend/tests/Feature/Rides/DriverTransitionTest.php                                                                     | test_driver_accepts_matching_ride; mocked variant exists                        |
| GET /api/v1/ride-orders/{rideOrder}                                  | yes     | true no-mock HTTP         | backend/tests/Feature/Rides/RideOrderAuthorizationTest.php                                                               | test_admin_can_view_any_order                                                   |
| GET /api/v1/ride-orders/{rideOrder}/chat                             | yes     | true no-mock HTTP         | backend/tests/Feature/Chat/ActiveParticipantAccessTest.php                                                               | test_removed_participant_cannot_view_chat                                       |
| POST /api/v1/group-chats/{chat}/messages                             | yes     | true no-mock HTTP         | backend/tests/Feature/Chat/GroupMessageTest.php                                                                          | test_participant_can_send_message                                               |
| GET /api/v1/group-chats/{chat}/messages                              | yes     | true no-mock HTTP         | backend/tests/Feature/Chat/GroupMessageTest.php                                                                          | test_polling_returns_only_messages_after_given_id                               |
| POST /api/v1/group-chats/{chat}/read                                 | yes     | true no-mock HTTP         | backend/tests/Feature/Chat/ReadReceiptTest.php                                                                           | test_mark_messages_as_read_creates_receipts                                     |
| PATCH /api/v1/group-chats/{chat}/dnd                                 | yes     | true no-mock HTTP         | backend/tests/Feature/Chat/DndTest.php                                                                                   | test_update_dnd_settings_returns_200                                            |
| POST /api/v1/vehicles                                                | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleCrudTest.php                                                                       | test_create_vehicle_returns_201                                                 |
| GET /api/v1/vehicles                                                 | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleCrudTest.php                                                                       | test_list_own_vehicles_only                                                     |
| GET /api/v1/vehicles/{vehicle}                                       | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleCrudTest.php                                                                       | test_admin_can_access_any_vehicle                                               |
| PUT /api/v1/vehicles/{vehicle}                                       | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleUpdateDeleteTest.php                                                               | test_owner_can_update_own_vehicle                                               |
| DELETE /api/v1/vehicles/{vehicle}                                    | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleUpdateDeleteTest.php                                                               | test_owner_can_delete_own_vehicle                                               |
| POST /api/v1/vehicles/{vehicle}/media                                | yes     | HTTP with mocking         | backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php; backend/tests/Feature/Vehicles/VehicleMediaUploadRealTest.php | Storage::fake and Queue::fake/UploadedFile::fake are used                       |
| PATCH /api/v1/vehicles/{vehicle}/media/reorder                       | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleMediaReorderTest.php                                                               | test_reorder_updates_sort_order_correctly                                       |
| PATCH /api/v1/vehicles/{vehicle}/media/{mediaId}/cover               | yes     | true no-mock HTTP         | backend/tests/Feature/Vehicles/VehicleMediaReorderTest.php                                                               | test_set_cover_works_and_only_one_cover_remains                                 |
| DELETE /api/v1/vehicles/{vehicle}/media/{mediaId}                    | yes     | HTTP with mocking         | backend/tests/Feature/Vehicles/VehicleMediaRemoveTest.php                                                                | helper uses Storage::fake and Queue::fake                                       |
| GET /api/v1/media/{media}/url                                        | yes     | HTTP with mocking         | backend/tests/Feature/Vehicles/SignedUrlTest.php                                                                         | test_valid_signed_url_returns_file_content_with_correct_type uses Storage::fake |
| GET /api/v1/products                                                 | yes     | true no-mock HTTP         | backend/tests/Feature/Products/ProductCrudTest.php                                                                       | test_manager_index_includes_own_unpublished_products                            |
| GET /api/v1/products/{product}                                       | yes     | true no-mock HTTP         | backend/tests/Feature/Products/ProductAuthorizationTest.php                                                              | test_unpublished_product_is_hidden_from_rider_but_visible_to_admin              |
| POST /api/v1/interactions                                            | yes     | true no-mock HTTP         | backend/tests/Feature/Recommendations/TelemetryTest.php                                                                  | test_post_interactions_logs_view_and_purchase_scores                            |
| GET /api/v1/recommendations                                          | yes     | true no-mock HTTP         | backend/tests/Feature/Recommendations/FallbackTest.php                                                                   | test_recommendations_fallback_returns_10_random_published_products              |
| GET /api/v1/notifications                                            | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationInboxTest.php                                                            | test_user_can_list_own_notifications                                            |
| GET /api/v1/notifications/unread-count                               | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationInboxTest.php                                                            | test_unread_count_returns_correct_number                                        |
| PATCH /api/v1/notifications/{notification}/read                      | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationInboxTest.php                                                            | test_user_can_mark_notification_as_read                                         |
| PATCH /api/v1/notifications/read-all                                 | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationInboxTest.php                                                            | test_mark_all_read_updates_all_unread_notifications                             |
| POST /api/v1/notifications/events                                    | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationScenarioTest.php                                                         | test_authorized_shared_ride_comment_reply_mention_notifications_are_created     |
| POST /api/v1/follows                                                 | yes     | true no-mock HTTP         | backend/tests/Feature/Social/FollowTest.php                                                                              | test_user_can_follow_another_user                                               |
| GET /api/v1/notification-subscriptions                               | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationSubscriptionManagementTest.php                                           | test_user_can_list_own_subscriptions                                            |
| POST /api/v1/notification-subscriptions                              | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationSubscriptionManagementTest.php                                           | test_rider_can_subscribe_to_own_ride_order_and_then_delete_it                   |
| DELETE /api/v1/notification-subscriptions/{notificationSubscription} | yes     | true no-mock HTTP         | backend/tests/Feature/Notifications/NotificationSubscriptionManagementTest.php                                           | test_user_can_delete_own_subscription                                           |
| POST /api/v1/products                                                | yes     | true no-mock HTTP         | backend/tests/Feature/Products/ProductCrudTest.php                                                                       | test_fleet_manager_can_create_product_with_variants_and_tiers                   |
| PUT /api/v1/products/{product}                                       | yes     | true no-mock HTTP         | backend/tests/Feature/Products/ProductCrudTest.php                                                                       | test_owner_can_update_and_publish_and_delete_product                            |
| PATCH /api/v1/products/{product}/publish                             | yes     | true no-mock HTTP         | backend/tests/Feature/Products/ProductCrudTest.php                                                                       | test_owner_can_update_and_publish_and_delete_product                            |
| DELETE /api/v1/products/{product}                                    | yes     | true no-mock HTTP         | backend/tests/Feature/Products/ProductCrudTest.php                                                                       | test_owner_can_update_and_publish_and_delete_product                            |
| POST /api/v1/products/{product}/purchase                             | yes     | true no-mock HTTP         | backend/tests/Feature/Products/PurchaseLimitTest.php                                                                     | test_purchase_limit_per_user_per_day_is_enforced                                |
| GET /api/v1/reports/trends                                           | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportAggregationTest.php                                                                  | test_trends_groups_by_day_correctly                                             |
| GET /api/v1/reports/distribution                                     | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportDistributionTest.php                                                                 | test_distribution_returns_correct_structure                                     |
| GET /api/v1/reports/regions                                          | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportAggregationTest.php                                                                  | test_regions_matching_maps_main_st_to_downtown                                  |
| GET /api/v1/reports/export-directories                               | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ExportTest.php                                                                             | test_export_directory_listing_returns_allowlisted_roots                         |
| POST /api/v1/reports/export                                          | yes     | true no-mock HTTP (mixed) | backend/tests/Feature/Reports/ReportExportRealisticTest.php; backend/tests/Feature/Reports/ExportTest.php                | realistic test uses real storage, ExportTest uses Storage::fake                 |
| GET /api/v1/reports/templates                                        | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportTemplateTest.php                                                                     | test_user_can_list_own_templates                                                |
| POST /api/v1/reports/templates                                       | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportTemplateTest.php                                                                     | test_fleet_manager_can_create_report_template                                   |
| PATCH /api/v1/reports/templates/{template}                           | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportTemplateTest.php                                                                     | test_user_can_update_own_template                                               |
| DELETE /api/v1/reports/templates/{template}                          | yes     | true no-mock HTTP         | backend/tests/Feature/Reports/ReportTemplateTest.php                                                                     | test_user_can_delete_own_template                                               |
| GET /api/v1/media/{media}/download                                   | yes     | HTTP with mocking         | backend/tests/Feature/Vehicles/SignedUrlTest.php                                                                         | signed URL path requested via get(pathAndQuery) with Storage::fake              |
| GET /api/v1/reports/exports/{reportExport}                           | yes     | HTTP with mocking         | backend/tests/Feature/Reports/ExportTest.php                                                                             | signed URL path requested via get(requestPath) with Storage::fake               |

## API Test Classification

### 1) True No-Mock HTTP

Representative files:

- [backend/tests/Feature/Auth/LoginTest.php](backend/tests/Feature/Auth/LoginTest.php)
- [backend/tests/Feature/Rides/FleetRideManagementTest.php](backend/tests/Feature/Rides/FleetRideManagementTest.php)
- [backend/tests/Feature/Reports/ReportTemplateTest.php](backend/tests/Feature/Reports/ReportTemplateTest.php)
- [backend/tests/Feature/Reports/ReportExportRealisticTest.php](backend/tests/Feature/Reports/ReportExportRealisticTest.php)
- [backend/tests/Integration/HttpIntegrationTest.php](backend/tests/Integration/HttpIntegrationTest.php)

### 2) HTTP with Mocking

Evidence:

- Schema partial mocking in HTTP flow: [backend/tests/Feature/Rides/DriverTransitionTest.php](backend/tests/Feature/Rides/DriverTransitionTest.php#L72)
- Storage/queue fakes around HTTP media endpoints: [backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php](backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php#L18), [backend/tests/Feature/Vehicles/VehicleMediaRemoveTest.php](backend/tests/Feature/Vehicles/VehicleMediaRemoveTest.php#L24), [backend/tests/Feature/Vehicles/SignedUrlTest.php](backend/tests/Feature/Vehicles/SignedUrlTest.php#L19)
- Storage fakes in export/download flow: [backend/tests/Feature/Reports/ExportTest.php](backend/tests/Feature/Reports/ExportTest.php#L18)

### 3) Non-HTTP (unit/integration without HTTP route hit)

Representative files:

- [backend/tests/Unit/PricingServiceTest.php](backend/tests/Unit/PricingServiceTest.php)
- [backend/tests/Unit/IdempotencyMiddlewareTest.php](backend/tests/Unit/IdempotencyMiddlewareTest.php)
- [backend/tests/Feature/Notifications/NotificationAdapterTest.php](backend/tests/Feature/Notifications/NotificationAdapterTest.php) (direct service call)
- [backend/tests/Feature/Vehicles/CompressionJobTest.php](backend/tests/Feature/Vehicles/CompressionJobTest.php)
- [backend/tests/Feature/Rides/RideOrderStateMachineTest.php](backend/tests/Feature/Rides/RideOrderStateMachineTest.php)

## Mock Detection (Strict)

Detected mock/stub/fake usage and location:

- Schema::partialMock / shouldReceive: [backend/tests/Feature/Rides/DriverTransitionTest.php](backend/tests/Feature/Rides/DriverTransitionTest.php#L72)
- Storage::fake: [backend/tests/Feature/Reports/ExportTest.php](backend/tests/Feature/Reports/ExportTest.php#L20), [backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php](backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php#L18), [backend/tests/Feature/Vehicles/SignedUrlTest.php](backend/tests/Feature/Vehicles/SignedUrlTest.php#L19)
- Queue::fake: [backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php](backend/tests/Feature/Vehicles/VehicleMediaUploadTest.php#L19), [backend/tests/Feature/Vehicles/VehicleMediaRemoveTest.php](backend/tests/Feature/Vehicles/VehicleMediaRemoveTest.php#L25)
- Log::spy: [backend/tests/Feature/Notifications/NotificationAdapterTest.php](backend/tests/Feature/Notifications/NotificationAdapterTest.php#L21), [backend/tests/Feature/Vehicles/CompressionJobTest.php](backend/tests/Feature/Vehicles/CompressionJobTest.php#L77)
- Frontend test mocking (vi.mock): [frontend/tests/pages/LoginPage.test.js](frontend/tests/pages/LoginPage.test.js#L16), [frontend/tests/services/apiService.test.js](frontend/tests/services/apiService.test.js#L5)

## Coverage Summary

- Total endpoints: 65
- Endpoints with HTTP tests: 65
- Endpoints with true no-mock HTTP evidence: 60
- Endpoints covered only via HTTP with mocking: 5

Computed metrics:

- HTTP coverage percentage: 100.0%
- True API coverage percentage: 92.3%

## Unit Test Summary

### Backend Unit Tests

Detected backend unit/non-HTTP logic tests:

- [backend/tests/Unit/PricingServiceTest.php](backend/tests/Unit/PricingServiceTest.php)
- [backend/tests/Unit/IdempotencyMiddlewareTest.php](backend/tests/Unit/IdempotencyMiddlewareTest.php)
- [backend/tests/Unit/NotificationCapsTest.php](backend/tests/Unit/NotificationCapsTest.php)
- [backend/tests/Unit/SecurityAuditTest.php](backend/tests/Unit/SecurityAuditTest.php)

Backend modules covered:

- Controllers via Feature HTTP suites for auth/rides/vehicles/reports/notifications/products/chat.
- Services: Pricing service, notification cap logic, recommendation and export flows.
- Middleware/auth/guards: idempotency middleware, role and auth-protected route assertions, token expiry checks.

Important backend modules not directly unit-tested (file-level direct unit evidence missing):

- [backend/app/Services/NotificationService.php](backend/app/Services/NotificationService.php)
- [backend/app/Services/RecommendationService.php](backend/app/Services/RecommendationService.php)
- [backend/app/Http/Middleware/TokenNotExpired.php](backend/app/Http/Middleware)
- [backend/app/Policies](backend/app/Policies)

### Frontend Unit Tests (Strict Requirement)

Detection checks:

- Frontend test files exist: yes, e.g. [frontend/tests/components/VehicleGallery.test.js](frontend/tests/components/VehicleGallery.test.js), [frontend/tests/pages/LoginPage.test.js](frontend/tests/pages/LoginPage.test.js)
- Frontend logic/components targeted: yes (Vue pages/components/stores/services)
- Framework evident: yes, Vitest and Vue Test Utils in [frontend/package.json](frontend/package.json#L10)
- Imports/renders actual frontend modules: yes, e.g. LoginPage and NotificationPanel imports in [frontend/tests/pages/LoginPage.test.js](frontend/tests/pages/LoginPage.test.js#L26), [frontend/tests/components/NotificationPanel.test.js](frontend/tests/components/NotificationPanel.test.js#L17)

Frontend test files (representative):

- Pages: Login/Register/Dashboard/RiderTrips/RiderTripDetail/Reports/FleetRideManagement
- Components: VehicleGallery, NotificationPanel, ChatComposer, DndSettingsModal, TripCard, DriverRideActions, etc.
- Router/store/services: routeGuard, authStore, apiService, networkErrors

Frameworks/tools detected:

- Vitest
- Vue Test Utils
- jsdom

Components/modules covered (representative):

- [frontend/src/pages/LoginPage.vue](frontend/src/pages/LoginPage.vue)
- [frontend/src/pages/RegisterPage.vue](frontend/src/pages/RegisterPage.vue)
- [frontend/src/components/vehicles/VehicleGallery.vue](frontend/src/components/vehicles/VehicleGallery.vue)
- [frontend/src/components/notifications/NotificationPanel.vue](frontend/src/components/notifications/NotificationPanel.vue)
- [frontend/src/stores/authStore.js](frontend/src/stores/authStore.js)
- [frontend/src/router/index.js](frontend/src/router/index.js)
- [frontend/src/services/api.js](frontend/src/services/api.js)

Important frontend components/modules not tested (direct unit evidence not found):

- [frontend/src/pages/vehicles/VehicleDetailPage.vue](frontend/src/pages/vehicles/VehicleDetailPage.vue)
- [frontend/src/pages/vehicles/VehicleListPage.vue](frontend/src/pages/vehicles/VehicleListPage.vue)
- [frontend/src/pages/products/ShopProductListPage.vue](frontend/src/pages/products/ShopProductListPage.vue)
- [frontend/src/pages/products/ShopProductDetailPage.vue](frontend/src/pages/products/ShopProductDetailPage.vue)
- [frontend/src/pages/products/ProductManagerPage.vue](frontend/src/pages/products/ProductManagerPage.vue)
- [frontend/src/pages/driver/DriverMyRidesPage.vue](frontend/src/pages/driver/DriverMyRidesPage.vue)
- [frontend/src/pages/driver/DriverAvailableRidesPage.vue](frontend/src/pages/driver/DriverAvailableRidesPage.vue)
- [frontend/src/pages/driver/DriverRideDetailPage.vue](frontend/src/pages/driver/DriverRideDetailPage.vue)
- [frontend/src/pages/chat/RideChatPage.vue](frontend/src/pages/chat/RideChatPage.vue)
- [frontend/src/pages/settings/NotificationSettingsPage.vue](frontend/src/pages/settings/NotificationSettingsPage.vue)

Mandatory verdict:

- Frontend unit tests: PRESENT

Strict failure rule check:

- Not triggered. Frontend unit tests are present with direct file-level evidence.

### Cross-Layer Observation

- Backend test depth is materially higher than frontend (many backend feature tests versus fewer frontend page/module tests).
- Coverage is not absent on frontend, but the distribution is backend-heavy.

## API Observability Check

Strong:

- Most backend HTTP tests explicitly show method/path, request payloads, and response/body assertions.
- Good examples: [backend/tests/Feature/Rides/RideOrderCreationTest.php](backend/tests/Feature/Rides/RideOrderCreationTest.php), [backend/tests/Feature/Notifications/NotificationInboxTest.php](backend/tests/Feature/Notifications/NotificationInboxTest.php)

Weak:

- Signed-download tests issue GET requests through derived pathAndQuery values, not explicit endpoint literals, reducing direct endpoint readability.
  - [backend/tests/Feature/Vehicles/SignedUrlTest.php](backend/tests/Feature/Vehicles/SignedUrlTest.php)
  - [backend/tests/Feature/Reports/ExportTest.php](backend/tests/Feature/Reports/ExportTest.php)
- Some authorization tests assert status only with minimal response-body verification.

## Test Quality and Sufficiency

- Success paths: broadly covered (auth, ride lifecycle, notifications, reports, vehicles).
- Failure and validation cases: strong across auth/ride/report/media edge conditions.
- Auth/permissions: strongly covered with role and ownership checks.
- Integration boundaries: present (backend HTTP integration tests and frontend e2e files exist).
- Over-mocking risk: localized to media/export storage-related flows and one schema partial mock.

run_tests.sh check:

- [run_tests.sh](run_tests.sh) is Docker-based end-to-end test orchestration.
- No local package-manager command required from the user to run tests directly.

## End-to-End Expectations (Fullstack)

- Evidence of FE↔BE e2e intent exists: [frontend/tests/e2e/auth-and-access.e2e.spec.js](frontend/tests/e2e/auth-and-access.e2e.spec.js), [frontend/tests/e2e/ride-lifecycle-and-reports.e2e.spec.js](frontend/tests/e2e/ride-lifecycle-and-reports.e2e.spec.js).
- Static audit cannot confirm execution quality, only presence.

## Tests Check

- HTTP route coverage breadth: high (all discovered API endpoints have test evidence).
- True no-mock purity: not universal due explicit fakes/mocks in selected flows.
- Frontend unit-test presence: confirmed.
- Fullstack balance: backend-heavy, frontend partially covered.

## Test Coverage Score (0-100)

- Score: 93

## Score Rationale

- - High endpoint coverage and broad functional path coverage.
- - Strong role/authorization and validation testing.
- - Presence of frontend unit tests and e2e test files.
- - True no-mock API coverage is below full due mocked/faked media and signed-download paths.
- - Some observability and assertion depth gaps remain.
- - Backend/frontend testing depth imbalance.

## Key Gaps

1. Mocked-only API endpoint evidence for media upload/remove/url/download and report export download routes.
2. Frontend page/module coverage gaps for several high-surface screens.
3. Some tests rely on status assertions without deeper response contract assertions.

## Confidence and Assumptions

- Confidence: high for route inventory and static mapping; medium for behavioral sufficiency because tests were not run.
- Assumption: endpoint list is exclusively from [backend/routes/api.php](backend/routes/api.php); [backend/routes/web.php](backend/routes/web.php) is not included in API endpoint totals.

## Final Verdict (Test Coverage Audit)

- PASS WITH CRITICAL CAVEATS
- Reason: complete HTTP endpoint coverage is present, but strict no-mock purity is not complete and frontend depth is comparatively lower.

---

# README Audit

README target checked: [README.md](README.md)

## Hard Gates

### Formatting

- Pass. Structured markdown with clear sections and tables.

### Startup Instructions (fullstack)

- Pass. Includes docker-compose up at [README.md](README.md#L120).

### Access Method

- Pass. Backend and frontend URLs/ports are explicitly listed at [README.md](README.md#L138).

### Verification Method

- Pass. API and Web step-by-step verification flows provided at [README.md](README.md#L170).

### Environment Rules (strict)

- Pass. App initialization happens through docker

### Demo Credentials (auth conditional)

- Pass. Credentials and roles are documented in [README.md](README.md#L156).

## Engineering Quality

- Tech stack clarity: strong.
- Architecture explanation: strong with diagram and flow table.
- Testing instructions: present, including [run_tests.sh](run_tests.sh) mention and workflows.
- Security/roles: clearly explained in security/auth sections.
- Workflow/presentation quality: high readability and practical sequencing.

## High Priority Issues

None

## Medium Priority Issues

1. API verification uses host python3 token extraction one-liner, which introduces host-tool reliance not mirrored in container-first strictness.

## Low Priority Issues

1. README references external docs path not shown in current workspace tree (../docs) which may cause navigation confusion for strict reproducibility.

## Hard Gate Failures

None

## README Verdict

- Pass

## Final Verdict (README Audit)

Pass

---

# Combined Final Verdicts

- Test Coverage Audit verdict: PASS WITH CRITICAL CAVEATS
- README Audit verdict: PASS
