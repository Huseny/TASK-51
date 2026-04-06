# RoadLink Design Document

## Architecture Diagram

```mermaid
flowchart LR
    User[Browser PWA] -->|HTTPS REST| FE[Vue 3 SPA + Pinia + Service Worker]
    FE -->|/api/v1| BE[Laravel 11 API]
    BE --> DB[(MySQL 8)]
    BE --> FS[(Local Storage: media/exports)]

    subgraph Scheduling
      Cron[Laravel Scheduler schedule:work]
      Cron --> AutoCancel[ride:auto-cancel-unmatched]
      Cron --> AutoRevert[ride:auto-revert-no-show]
      Cron --> Disband[ride:disband-stale-exception-chats]
      Cron --> Reco[ComputeRecommendations Job]
    end

    BE --> Scheduling
```

## RideOrder State Machine

```mermaid
stateDiagram-v2
    [*] --> created
    created --> matching: submit
    matching --> accepted: accept
    accepted --> in_progress: start
    in_progress --> completed: complete

    matching --> canceled: cancel
    accepted --> canceled: cancel

    accepted --> exception: flag_exception
    in_progress --> exception: flag_exception
    exception --> matching: reassign
    accepted --> matching: reassign (no-show auto-revert)
```

## Tech Stack Justification

- Laravel 11 provides strong request validation, policies, middleware, queue/scheduler primitives, and deterministic REST behavior for offline replay patterns.
- Vue 3 + Pinia + Vite keeps the UI modular, fast, and easy to run locally with PWA support for offline shell caching.
- MySQL 8 centralizes transactional workflows (ride state transitions, purchases, idempotency keys, recommendations, reports).
- Local filesystem storage avoids cloud dependencies and satisfies offline-first constraints for media and CSV exports.
- Workbox-backed service worker + IndexedDB queue enables queued write operations with idempotency keys and reconnect sync.

## Security + Traceability Notes

- Group chat membership is stateful: active participation requires `group_chat_participants.left_at IS NULL`.
- Fleet dispatch is separated from driver self-service through dedicated `/api/v1/fleet/*` routes and policies.
- Recommendation reproducibility is versioned through structured feature-set tables instead of model snapshot JSON alone.

## Offline/No-External Dependency Notes

- No external map, payment, auth, recommendation, or notification providers are required for core operation.
- Region mapping for reports uses bundled local JSON at `repo/backend/database/data/regions.json`.
- Recommendation batch jobs run in Laravel scheduler using PHP/MySQL aggregations only.
