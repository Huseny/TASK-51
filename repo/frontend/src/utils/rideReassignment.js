const REASSIGNMENT_REASONS = {
  exception_reassignment: 'Exception reported',
  no_show_auto_revert: 'Driver no-show',
  manual_reassignment: 'Manual reassignment',
}

const normalizeReason = (reason) => {
  if (!reason) {
    return 'Driver change'
  }

  return REASSIGNMENT_REASONS[reason] || reason.replaceAll('_', ' ')
}

export const isDriverChange = (entry) => {
  const previousDriverId = entry?.metadata?.previous_driver_id ?? null
  const newDriverId = entry?.metadata?.new_driver_id ?? null

  return previousDriverId !== newDriverId && (previousDriverId !== null || newDriverId !== null)
}

export const isReassignmentEvent = (entry) => {
  if (!entry) {
    return false
  }

  if (entry.metadata?.driver_reassigned === true) {
    return true
  }

  if (entry.from_status === 'exception' && entry.to_status === 'matching') {
    return true
  }

  if (entry.from_status === 'accepted' && entry.to_status === 'matching') {
    return true
  }

  return isDriverChange(entry)
}

export const getLatestReassignment = (logs = []) => {
  const candidates = [...logs]
    .filter(isReassignmentEvent)
    .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))

  if (candidates.length === 0) {
    return null
  }

  const latest = candidates[0]
  const reason = latest.metadata?.reassignment_reason || latest.trigger_reason || null

  return {
    ...latest,
    reason,
    reasonLabel: normalizeReason(reason),
    previousDriverId: latest.metadata?.previous_driver_id ?? null,
    newDriverId: latest.metadata?.new_driver_id ?? null,
    driverUnavailable: latest.to_status === 'matching' || latest.metadata?.new_driver_id === null,
  }
}

export const formatReassignmentReason = normalizeReason
