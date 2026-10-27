import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { createSortable } from '../utils/Sortable.js'

/**
 * ConditionalEditor — admin UI for the form-builder conditional rules.
 *
 * Registered against `[data-conditional-editor]`. Behaviours:
 *
 *  - "Add condition" clones the `<template data-conditional-template>` and
 *    appends a new row, replacing every `__INDEX__` token with a unique
 *    counter so the bracket-name keys don't collide.
 *
 *  - "Remove" deletes the closest `[data-conditional-row]`.
 *
 *  - The first row's combinator slot stays hidden because there's nothing
 *    to combine with; rows 2+ show an inline And/Or select. Every select
 *    posts to the same `conditional[combinator]` name (PHP keeps the last
 *    value), and changing one syncs every other select to keep the UI
 *    consistent.
 *
 *  - When `[data-conditional-op]` changes, the row's
 *    `[data-conditional-value]` input is hidden + disabled when the
 *    operator is one of the editor's `data-valueless-ops` JSON list
 *    (is_empty / is_not_empty); the server-side parser strips the value
 *    for those operators on save.
 *
 *  - Drag-to-reorder via the row's `[data-handle]`. Server-side reads
 *    `array_values()` of the conditions array, so DOM order is enough —
 *    no name renumbering needed.
 */
export default class ConditionalEditor extends BaseComponent {
    _getSelector() {
        return 'data-conditional-editor'
    }

    _init(element) {
        const rows     = element.querySelector('[data-conditional-rows]')
        const template = element.querySelector('[data-conditional-template]')
        if (!rows || !template) return {}

        let valuelessOps = []
        try {
            valuelessOps = JSON.parse(element.getAttribute('data-valueless-ops') || '[]')
        } catch (_e) {
            valuelessOps = []
        }

        const initialIndices = Array.from(rows.querySelectorAll('[data-conditional-row]'))
            .map((row) => this._extractIndex(row))
            .filter((n) => Number.isFinite(n))
        const state = {
            counter: initialIndices.length === 0 ? 0 : Math.max(...initialIndices) + 1,
        }

        this._refreshCombinatorSlots(rows)

        const onClick = (event) => {
            const add = event.target.closest('[data-conditional-add]')
            if (add && element.contains(add)) {
                event.preventDefault()
                this._addRow(element, rows, template, state, valuelessOps)
                return
            }
            const remove = event.target.closest('[data-conditional-remove]')
            if (remove && element.contains(remove)) {
                event.preventDefault()
                const row = remove.closest('[data-conditional-row]')
                if (row) {
                    row.remove()
                    this._refreshCombinatorSlots(rows)
                }
            }
        }

        const onChange = (event) => {
            const op = event.target.closest('[data-conditional-op]')
            if (op && element.contains(op)) {
                this._toggleValueColumn(op, valuelessOps)
                return
            }
            const combinator = event.target.closest('[data-conditional-combinator]')
            if (combinator && element.contains(combinator)) {
                this._syncCombinators(rows, combinator.value)
            }
        }

        element.addEventListener('click', onClick)
        element.addEventListener('change', onChange)

        const sortable = createSortable(rows, {
            items:  '[data-conditional-row]',
            handle: '[data-handle]',
            onSort: () => this._refreshCombinatorSlots(rows),
        })

        return {
            cleanup: () => {
                element.removeEventListener('click', onClick)
                element.removeEventListener('change', onChange)
                sortable.destroy()
            },
        }
    }

    _addRow(editor, rowsContainer, template, state, valuelessOps) {
        const fragment = template.content.cloneNode(true)
        const html     = fragment.firstElementChild?.outerHTML
            || (template.innerHTML.includes('data-conditional-row') ? template.innerHTML : null)
        if (!html) return

        const index = state.counter++
        const wrapper = document.createElement('div')
        wrapper.innerHTML = html.replaceAll('__INDEX__', String(index))
        const row = wrapper.firstElementChild
        if (!row) return
        rowsContainer.appendChild(row)

        const op = row.querySelector('[data-conditional-op]')
        if (op) this._toggleValueColumn(op, valuelessOps)

        const currentCombo = editor.getAttribute('data-combinator') || 'all'
        const slotSelect = row.querySelector('[data-conditional-combinator]')
        if (slotSelect) slotSelect.value = currentCombo

        this._refreshCombinatorSlots(rowsContainer)
    }

    _refreshCombinatorSlots(rowsContainer) {
        const rows = Array.from(rowsContainer.querySelectorAll('[data-conditional-row]'))
        rows.forEach((row, index) => {
            const slot = row.querySelector('[data-conditional-combinator-slot]')
            if (!slot) return
            const isFirst = index === 0
            slot.hidden = isFirst
            const select = slot.querySelector('select')
            if (select) select.disabled = isFirst
        })
    }

    _syncCombinators(rowsContainer, value) {
        rowsContainer.querySelectorAll('[data-conditional-combinator]').forEach((select) => {
            if (select.value !== value) select.value = value
        })
        const editor = rowsContainer.closest('[data-conditional-editor]')
        if (editor) editor.setAttribute('data-combinator', value)
    }

    _toggleValueColumn(opSelect, valuelessOps) {
        const row = opSelect.closest('[data-conditional-row]')
        if (!row) return
        const valueInput = row.querySelector('[data-conditional-value]')
        if (!valueInput) return
        const hidden = valuelessOps.includes(opSelect.value)
        valueInput.hidden = hidden
        valueInput.disabled = hidden
    }

    _extractIndex(row) {
        const named = row.querySelector('[name*="conditional[conditions]"]')
        if (!named) return NaN
        const match = named.getAttribute('name')?.match(/conditional\[conditions\]\[(\d+)\]/)
        return match ? Number(match[1]) : NaN
    }
}
