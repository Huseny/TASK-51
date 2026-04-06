# RoadLink API Specification

Base path: `/api/v1`

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/auth/register` | Public | Register local user account |
| POST | `/auth/login` | Public | Login and issue Sanctum token |
| POST | `/auth/logout` | Auth | Revoke current token |
| GET | `/auth/me` | Auth | Get current authenticated user |

## Authentication Flow

- Primary auth is Laravel Sanctum bearer tokens.
- `POST /auth/register` and `POST /auth/login` return `user`, `token`, `token_type`, and `expires_at`.
- Protected endpoints require `Authorization: Bearer <token>`.
- `POST /auth/logout` revokes only the current bearer token.
- Stateful cookie/session auth may still exist for legacy environments, but it is deprecated and no longer the primary API contract.

Example:

```http
POST /api/v1/auth/login
Content-Type: application/json

{"username":"driver01","password":"Driver1234!"}
```

```http
GET /api/v1/auth/me
Authorization: Bearer <token>
```

## Rides

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/ride-orders` | Rider | Create ride request |
| GET | `/ride-orders` | Rider | List rider-owned rides |
| GET | `/ride-orders/{rideOrder}` | Auth + policy | Show ride details |
| PATCH | `/ride-orders/{rideOrder}/transition` | Auth + policy | Transition ride state |
| GET | `/driver/available-rides` | Driver/Admin | List nearby-in-time matching rides |
| GET | `/driver/my-rides` | Driver/Admin | List driver-assigned rides |
| GET | `/driver/my-rides/{rideOrder}` | Driver/Admin + policy | Show one driver ride |
| GET | `/fleet/rides/queue` | Fleet/Admin | List dispatch queue rides awaiting assignment (`matching`) |
| GET | `/fleet/rides/active` | Fleet/Admin | List active operational rides |
| GET | `/fleet/rides/{rideOrder}` | Fleet/Admin | Show one operational ride with rider/driver/audit context |
| GET | `/fleet/drivers` | Fleet/Admin | List dispatchable drivers |
| PATCH | `/fleet/rides/{rideOrder}/assign` | Fleet/Admin | Assign a driver to a matching ride |
| PATCH | `/fleet/rides/{rideOrder}/reassign` | Fleet/Admin | Reassign or requeue a ride |
| PATCH | `/fleet/rides/{rideOrder}/cancel` | Fleet/Admin | Cancel an operational ride |

### Ride Time Input

- `POST /ride-orders` accepts normalized 24-hour timestamps and common 12-hour inputs.
- Accepted examples include `2026-04-06 14:30`, `2026-04-06 2:30 PM`, and ISO-8601 values.

## Chat

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/ride-orders/{rideOrder}/chat` | Auth participant | Fetch chat and latest messages |
| POST | `/group-chats/{chat}/messages` | Auth participant | Send chat message |
| GET | `/group-chats/{chat}/messages` | Auth participant | Poll messages |
| POST | `/group-chats/{chat}/read` | Auth participant | Mark chat messages as read |
| PATCH | `/group-chats/{chat}/dnd` | Auth participant | Update DND window |

Chat authorization note:

- "Participant" means active participant only. Rows with `left_at != null` are denied access to read, receipt, and DND endpoints.

## Vehicles & Media

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/vehicles` | Driver/Fleet/Admin | Create vehicle |
| GET | `/vehicles` | Driver/Fleet/Admin | List vehicles |
| GET | `/vehicles/{vehicle}` | Driver/Fleet/Admin | Vehicle detail |
| PUT | `/vehicles/{vehicle}` | Driver/Fleet/Admin | Update vehicle |
| DELETE | `/vehicles/{vehicle}` | Driver/Fleet/Admin | Delete vehicle |
| POST | `/vehicles/{vehicle}/media` | Driver/Fleet/Admin | Upload vehicle media |
| PATCH | `/vehicles/{vehicle}/media/reorder` | Driver/Fleet/Admin | Reorder gallery |
| PATCH | `/vehicles/{vehicle}/media/{mediaId}/cover` | Driver/Fleet/Admin | Set cover media |
| DELETE | `/vehicles/{vehicle}/media/{mediaId}` | Driver/Fleet/Admin | Remove media from vehicle |
| GET | `/media/{media}/url` | Driver/Fleet/Admin | Generate signed download URL |
| GET | `/media/{media}/download` | Signed URL | Download media file |

## Products & Purchases

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/products` | Auth | List products (visibility scoped) |
| GET | `/products/{product}` | Auth | Product detail |
| POST | `/products` | Fleet/Admin | Create product |
| PUT | `/products/{product}` | Fleet/Admin owner | Update product |
| PATCH | `/products/{product}/publish` | Fleet/Admin owner | Publish or unpublish product |
| DELETE | `/products/{product}` | Fleet/Admin owner | Soft-delete product |
| POST | `/products/{product}/purchase` | Rider/Driver | Purchase variant with tiered pricing |

## Notifications

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/notifications` | Auth | List (aggregated) notifications |
| GET | `/notifications/unread-count` | Auth | Poll unread badge count |
| PATCH | `/notifications/{notification}/read` | Auth owner | Mark notification/group read |
| PATCH | `/notifications/read-all` | Auth | Mark all notifications as read |
| GET | `/notification-subscriptions` | Auth | List subscriptions |
| POST | `/notification-subscriptions` | Auth | Create subscription |
| DELETE | `/notification-subscriptions/{notificationSubscription}` | Auth owner | Remove subscription |

Notification authorization note:

- Follower notifications require a real `user_follows` relationship.
- User-created notification subscriptions are limited to `ride_order` and `product` entities.

## Recommendations & Telemetry

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/interactions` | Auth | Log interaction telemetry (`view`/`purchase`) |
| GET | `/recommendations` | Auth | Fetch top recommendations or fallback |

## Reports & Exports

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/reports/trends` | Admin/Fleet | Time-series ride counts |
| GET | `/reports/distribution` | Admin/Fleet | Ride status distribution |
| GET | `/reports/regions` | Admin/Fleet | Region summary via local JSON mapping |
| POST | `/reports/export` | Admin/Fleet | Create CSV/XLSX export and signed URL (`destination` is a safe key, not a filesystem path) |
| GET | `/reports/exports/{reportExport}` | Signed URL + Auth + Role | Download export owned by requester (or admin override) |
| GET | `/reports/templates` | Admin/Fleet | List report templates |
| POST | `/reports/templates` | Admin/Fleet | Create report template |
| PATCH | `/reports/templates/{template}` | Admin/Fleet owner | Update template |
| DELETE | `/reports/templates/{template}` | Admin/Fleet owner | Delete template |

### Report Export Destination Rules

- `destination` accepts only `[A-Za-z0-9_-]` and is treated as a logical bucket key.
- Files are written only under `storage/app/exports/<destination>/...`.
- Arbitrary paths and traversal patterns are rejected.
- This intentionally prevents raw admin-supplied absolute filesystem paths for safety and auditability.
- Extension path: if business requires admin-managed directories, map approved aliases to a server-side allowlist (never pass raw paths directly from API input).

## Utility/Admin

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/admin/panel` | Admin | Admin access probe route |
