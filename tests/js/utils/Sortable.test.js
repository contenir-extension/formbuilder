import { describe, expect, it, vi } from 'vitest'
import Sortable from 'sortablejs'
import { createSortable } from '../../../assets/js/utils/Sortable.js'

vi.mock('sortablejs', () => ({
    default: { create: vi.fn(() => ({ destroy: vi.fn() })) },
}))

describe('createSortable', () => {
    it('creates a Sortable instance on the given element with the shared defaults', () => {
        const el = document.createElement('div')

        createSortable(el)

        expect(Sortable.create).toHaveBeenCalledWith(el, {
            forceFallback: true,
            animation: 150,
            ghostClass: 'sortable--ghost',
            chosenClass: 'sortable--chosen',
            dragClass: 'sortable--drag',
        })
    })

    it('merges caller options over the defaults', () => {
        const el = document.createElement('div')
        const onSort = vi.fn()

        createSortable(el, { draggable: '.item', handle: '.handle', onSort })

        expect(Sortable.create).toHaveBeenCalledWith(el, expect.objectContaining({
            draggable: '.item',
            handle: '.handle',
            onSort,
            forceFallback: true,
        }))
    })

    it('lets caller options override a default', () => {
        const el = document.createElement('div')

        createSortable(el, { ghostClass: 'custom--ghost' })

        expect(Sortable.create).toHaveBeenCalledWith(el, expect.objectContaining({
            ghostClass: 'custom--ghost',
        }))
    })

    it('returns the created Sortable instance', () => {
        const el = document.createElement('div')

        expect(createSortable(el)).toHaveProperty('destroy')
    })
})
