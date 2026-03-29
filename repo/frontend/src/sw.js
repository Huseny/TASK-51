/// <reference lib="webworker" />
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching'
import { registerRoute } from 'workbox-routing'
import { NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies'

precacheAndRoute(self.__WB_MANIFEST)
cleanupOutdatedCaches()

registerRoute(
  ({ request }) => request.mode === 'navigate',
  createHandlerBoundToURL('/index.html'),
)

registerRoute(
  ({ request }) => request.destination === 'script' || request.destination === 'style' || request.destination === 'font',
  new StaleWhileRevalidate({ cacheName: 'roadlink-shell-assets' }),
)

registerRoute(
  ({ url, request }) => request.method === 'GET' && url.pathname.startsWith('/api/v1/ride-orders'),
  new NetworkFirst({ cacheName: 'roadlink-rides-cache', networkTimeoutSeconds: 3 }),
)

registerRoute(
  ({ url, request }) => request.method === 'GET' && /\/api\/v1\/group-chats\/[^/]+\/messages/.test(url.pathname),
  new NetworkFirst({ cacheName: 'roadlink-chat-cache', networkTimeoutSeconds: 3 }),
)
