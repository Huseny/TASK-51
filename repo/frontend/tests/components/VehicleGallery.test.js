import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import VehicleGallery from '@/components/vehicles/VehicleGallery.vue'

describe('VehicleGallery', () => {
  it('drag-drop reorder emits updated sort order on drop', async () => {
    const wrapper = mount(VehicleGallery, {
      props: {
        mediaItems: [
          { id: 1, mime_type: 'image/jpeg', url: 'a.jpg', is_cover: true },
          { id: 2, mime_type: 'image/jpeg', url: 'b.jpg', is_cover: false },
        ],
      },
      global: {
        stubs: {
          draggable: { template: '<div><slot v-for="item in $attrs.modelValue" :element="item" /></div>' },
        },
      },
    })

    wrapper.vm.items = [wrapper.vm.items[1], wrapper.vm.items[0]]
    wrapper.vm.onDragEnd()

    const emitted = wrapper.emitted('reorder')
    expect(emitted).toBeTruthy()
    expect(emitted[0][0]).toEqual([
      { media_id: 2, sort_order: 0 },
      { media_id: 1, sort_order: 1 },
    ])
  })

  it('upload zone rejects invalid file types before upload', async () => {
    const wrapper = mount(VehicleGallery, {
      props: { mediaItems: [] },
      global: {
        stubs: { draggable: true },
      },
    })

    const invalidFile = new File(['x'], 'bad.pdf', { type: 'application/pdf' })
    await wrapper.vm.onFilesSelected({ target: { files: [invalidFile], value: '' } })

    expect(wrapper.emitted('upload-error')).toBeTruthy()
    expect(wrapper.emitted('upload-files')).toBeFalsy()
  })

  it('cover star icon toggles correctly', async () => {
    const wrapper = mount(VehicleGallery, {
      props: {
        mediaItems: [
          { id: 1, mime_type: 'image/jpeg', url: 'a.jpg', is_cover: true },
          { id: 2, mime_type: 'image/jpeg', url: 'b.jpg', is_cover: false },
        ],
      },
      global: {
        stubs: {
          draggable: {
            props: ['modelValue'],
            template: '<div><template v-for="el in modelValue" :key="el.id"><slot name="item" :element="el" /></template></div>',
          },
        },
      },
    })

    const stars = wrapper.findAll('.star')
    expect(stars[0].classes()).toContain('active')
    expect(stars[1].classes()).not.toContain('active')

    await stars[1].trigger('click')
    expect(wrapper.emitted('set-cover')).toBeTruthy()
    expect(wrapper.emitted('set-cover')[0]).toEqual([2])
  })
})
