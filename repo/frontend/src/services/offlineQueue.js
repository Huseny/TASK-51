import { openDB } from 'idb'

const DB_NAME = 'roadlink-offline'
const STORE_NAME = 'pending-actions'

const dbPromise = openDB(DB_NAME, 1, {
  upgrade(db) {
    if (!db.objectStoreNames.contains(STORE_NAME)) {
      db.createObjectStore(STORE_NAME, { keyPath: 'id' })
    }
  },
})

export const enqueuePendingAction = async (action) => {
  const db = await dbPromise
  await db.put(STORE_NAME, action)
}

export const getPendingActions = async () => {
  const db = await dbPromise
  return db.getAll(STORE_NAME)
}

export const getPendingActionsByOwner = async (ownerKey) => {
  const all = await getPendingActions()
  return all.filter((item) => item.owner_key === ownerKey)
}

export const removePendingAction = async (id) => {
  const db = await dbPromise
  await db.delete(STORE_NAME, id)
}

export const clearPendingActions = async () => {
  const db = await dbPromise
  await db.clear(STORE_NAME)
}
