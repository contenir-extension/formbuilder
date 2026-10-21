import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { createSortable } from '../utils/Sortable.js'
import { setSectionEnabled } from '../utils/FormSection.js'

/**
 * Field add/edit form behaviour.
 *
 * Mounted on `[data-field-form]`. Two things happen here:
 *
 * 1. Validator option reveal. Each `[data-validator-row]` has a
 *    `[data-validator-toggle]` checkbox; the per-validator option inputs
 *    sit in a `[data-validator-options]` wrapper that's hidden until the
 *    checkbox is ticked. Hidden options also disable their inputs so
 *    blank-but-present rows don't survive the POST.
 *
 * 2. Validator drag-to-reorder. The `[data-validator-rows]` container
 *    becomes a SortableJS list keyed off the row's `[data-handle]`; on
 *    sort, every `validators[N][...]` name index is renumbered to match
 *    the new DOM order so the server iterates them in the user's chosen
 *    execution order.
 *
 * Field type is locked once the field is created — picked from the Add
 * Field dropdown (URL `?type=`) and never editable afterwards. The
 * partial renders only the groups the chosen type supports, so there's
 * no JS-side group toggle to maintain.
 */
export default class FieldForm extends BaseComponent {
    _getSelector() {
        return 'data-field-form'
    }

    _init(element) {
        const cleanup = this._initValidators(element)
        return {
            cleanup: () => cleanup && cleanup(),
        }
    }

    _initValidators(element) {
        const rows = element.querySelector('[data-validator-rows]')
        if (!rows) return null

        rows.querySelectorAll('[data-validator-row]').forEach((row) => {
            this._applyValidatorOptionVisibility(row)
        })

        const onChange = (event) => {
            const checkbox = event.target.closest('[data-validator-toggle]')
            if (!checkbox || !rows.contains(checkbox)) return
            const row = checkbox.closest('[data-validator-row]')
            if (row) this._applyValidatorOptionVisibility(row)
        }
        rows.addEventListener('change', onChange)

        const sortable = createSortable(rows, {
            draggable: '[data-validator-row]',
            handle: '[data-handle]',
            onSort: () => this._renumberValidators(rows),
        })

        return () => {
            rows.removeEventListener('change', onChange)
            sortable.destroy()
        }
    }

    _applyValidatorOptionVisibility(row) {
        const checkbox = row.querySelector('[data-validator-toggle]')
        const options = row.querySelector('[data-validator-options]')
        if (!checkbox || !options) return
        const on = checkbox.checked
        options.hidden = !on
        setSectionEnabled(options, on)
    }

    _renumberValidators(container) {
        let index = 0
        container.querySelectorAll('[data-validator-row]').forEach((row) => {
            row.querySelectorAll('input, select, textarea').forEach((input) => {
                const name = input.getAttribute('name')
                if (!name) return
                input.setAttribute('name', name.replace(/validators\[\d+\]/, `validators[${index}]`))
            })
            index++
        })
    }
}
