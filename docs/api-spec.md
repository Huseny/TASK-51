# RoadLink API Specification

Base path: `/api/v1`

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/auth/register` | Public | Register local user account |
| POST | `/auth/login` | Public | Login and issue Sanctum token |
| POST | `/auth/logout` | Auth | Revoke current token |
| GET | `/auth/me` | Auth | Get current authenticated user |

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

## Chat

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/ride-orders/{rideOrder}/chat` | Auth participant | Fetch chat and latest messages |
| POST | `/group-chats/{chat}/messages` | Auth participant | Send chat message |
| GET | `/group-chats/{chat}/messages` | Auth participant | Poll messages |
| POST | `/group-chats/{chat}/read` | Auth participant | Mark chat messages as read |
| PATCH | `/group-chats/{chat}/dnd` | Auth participant | Update DND window |

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
| POST | `/reports/export` | Admin/Fleet | Create CSV export and signed URL |
| GET | `/reports/exports/{filename}` | Signed URL | Download exported CSV |
| GET | `/reports/templates` | Admin/Fleet | List report templates |
| POST | `/reports/templates` | Admin/Fleet | Create report template |
| PATCH | `/reports/templates/{template}` | Admin/Fleet owner | Update template |
| DELETE | `/reports/templates/{template}` | Admin/Fleet owner | Delete template |

## Utility/Admin

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| GET | `/admin/panel` | Admin | Admin access probe route |
