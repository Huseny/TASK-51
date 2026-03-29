<script setup>
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'

const props = defineProps({
  mediaItems: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['reorder', 'set-cover', 'remove', 'upload-error', 'upload-files'])

const items = ref([])

watch(
  () => props.mediaItems,
  (value) => {
    items.value = [...value]
  },
  { immediate: true }
)

const acceptedTypes = {
  'image/jpeg': { max: 8388608 },
  'image/png': { max: 8388608 },
  'video/mp4': { max: 209715200 },
}

const sortedPayload = computed(() =>
  items.value.map((item, index) => ({ media_id: item.id, sort_order: index }))
)

const onDragEnd = () => {
  emit('reorder', sortedPayload.value)
}

const onFilesSelected = (event) => {
  const fileList = Array.from(event.target.files || [])
  const valid = []

  for (const file of fileList) {
    const rule = acceptedTypes[file.type]
    if (!rule) {
      emit('upload-error', 'Only JPEG, PNG and MP4 files are allowed.')
      continue
    }

    if (file.size > rule.max) {
      emit('upload-error', 'File exceeds maximum allowed size.')
      continue
    }

    valid.push(file)
  }

  if (valid.length) {
    emit('upload-files', valid)
  }

  event.target.value = ''
}

const canBeCover = (item) => ['image/jpeg', 'image/png'].includes(item.mime_type)

defineExpose({ onDragEnd, onFilesSelected, items })
</script>

<template>
  <section class="gallery">
    <label class="upload-zone">
      <input type="file" multiple accept=".jpg,.jpeg,.png,.mp4" @change="onFilesSelected">
      <strong>Drop images or videos here</strong>
      <span>Images: JPEG/PNG up to 8 MB • Videos: MP4 up to 200 MB</span>
    </label>

    <draggable
      v-model="items"
      item-key="id"
      class="grid"
      handle=".drag-handle"
      @end="onDragEnd"
    >
      <template #item="{ element }">
        <article class="media-card">
          <span class="drag-handle">⋮⋮</span>
          <img v-if="['image/jpeg', 'image/png'].includes(element.mime_type)" :src="element.url" alt="media">
          <div v-else class="video-thumb">▶ Video</div>

          <div class="actions">
            <button
              v-if="canBeCover(element)"
              type="button"
              class="star"
              :class="{ active: element.is_cover }"
              @click="emit('set-cover', element.id)"
            >
              ★
            </button>
            <button type="button" class="trash" @click="emit('remove', element.id)">🗑</button>
          </div>
        </article>
      </template>
    </draggable>
  </section>
</template>

<style scoped>
.gallery {
  display: grid;
  gap: var(--space-3);
}

.upload-zone {
  border: 1px dashed rgba(151, 164, 208, 0.45);
  border-radius: var(--radius-md);
  padding: var(--space-4);
  display: grid;
  gap: var(--space-1);
  cursor: pointer;
}

.upload-zone input {
  display: none;
}

.upload-zone strong {
  font-size: 0.95rem;
}

.upload-zone span {
  color: var(--color-text-muted);
  font-size: 0.82rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: var(--space-3);
}

.media-card {
  border: 1px solid rgba(151, 164, 208, 0.24);
  border-radius: var(--radius-md);
  overflow: hidden;
  position: relative;
}

.drag-handle {
  position: absolute;
  right: 8px;
  top: 8px;
  z-index: 2;
  background: rgba(0, 0, 0, 0.45);
  padding: 2px 6px;
  border-radius: 999px;
  cursor: grab;
}

img,
.video-thumb {
  width: 100%;
  height: 120px;
  object-fit: cover;
}

.video-thumb {
  display: grid;
  place-items: center;
  color: var(--color-text-muted);
  background: rgba(151, 164, 208, 0.12);
}

.actions {
  display: flex;
  justify-content: space-between;
  padding: 8px;
}

.star,
.trash {
  border: none;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.star.active {
  color: #ffd166;
}
</style>
