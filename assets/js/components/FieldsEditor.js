import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { request, TIMEOUTS } from '../utils/HttpClient.js'
import { createSortable } from '../utils/Sortable.js'
import { withToken } from '../utils/HandlerToken.js'

/**
 * FieldsEditor — drag-and-drop layout editor for the form builder's
 * fields screen.
 *
 * Registered against `[data-formbuilder-rows]` (one per group). Wires up
 * two SortableJS instances per group:
 *
 *   1. Vertical row reorder. The container itself is sortable; each
 *      `[data-formbuilder-row]` child is draggable by its
 *      `data-formbuilder-row-handle` grip. On drop, POSTs the new row
 *      sequence to `data-row-reorder-url`.
 *
 *   2. Field reorder, within and across rows and groups. Every
 *      `[data-formbuilder-fields]` (one per row) joins a single SortableJS
 *      group (FIELD_GROUP) shared with every other field grid in the
 *      form, so a field dragged out of row A can drop into:
 *
 *        - the same row at a different index (within-row reorder),
 *        - another row in the same group (cross-row reorder),
 *        - another row in a different group (cross-group reorder).
 *
 * Both reorders POST a FormData body to the add-on's handlers: a `rows`
 * field holding the JSON-encoded order, plus the host's handler token.
 * A row reorder sends the group's row ids in order (`[3,1,2]`); a field
 * reorder sends every row's field ids keyed by row id
 * (`{"801": [902, 901], "804": []}`). The handlers answer `{ ok: true }`;
 * failures are swallowed, since the DOM already shows the new order.
 *
 * `_postFieldOrder` walks `document.querySelectorAll('[data-formbuilder-fields]')`
 * rather than scoping to the FieldsEditor's element, because cross-group
 * drags need both source and destination rows in the same payload — the
 * source row lives in a different `[data-formbuilder-rows]` container than
 * this instance owns.
 *
 * The `[data-formbuilder-append]` "+ add field" button is excluded from the
 * sortable via SortableJS's `filter` so it stays anchored at the end
 * of every row.
 */
const FIELD_GROUP    = 'forms-formbuilder-fields'
const DRAGGING_CLASS = 'formbuilder__rows--dragging'

export default class FieldsEditor extends BaseComponent {
    _getSelector() {
        return 'data-formbuilder-rows'
    }

    _init(element) {
        const rowReorderUrl   = element.getAttribute('data-row-reorder-url') || ''
        const fieldReorderUrl = element.getAttribute('data-field-reorder-url') || ''

        const onDragStart = () => element.classList.add(DRAGGING_CLASS)
        const onDragEnd   = () => element.classList.remove(DRAGGING_CLASS)

        const rowSortable = createSortable(element, {
            draggable: '[data-formbuilder-row]',
            handle:    '[data-formbuilder-row-handle]',
            onStart: onDragStart,
            onEnd: () => {
                onDragEnd()
                this._postRowOrder(element, rowReorderUrl)
            },
        })

        const fieldSortables = []
        const initFieldContainer = (container) => {
            fieldSortables.push(createSortable(container, {
                group:     FIELD_GROUP,
                draggable: '[data-formbuilder-field]',
                handle:    '[data-formbuilder-field-handle]',
                filter:    '[data-formbuilder-append]',
                preventOnFilter: false,
                direction: 'horizontal',
                onStart: onDragStart,
                onEnd: () => {
                    onDragEnd()
                    this._postFieldOrder(fieldReorderUrl)
                },
            }))
        }
        element.querySelectorAll('[data-formbuilder-fields]').forEach(initFieldContainer)

        /**
         * FormbuilderInlineForm dispatches `formbuilder:row-added` (bubbling)
         * when an XHR row insert lands. Wire SortableJS onto the new row's
         * field container so cross-row drags work without a page reload.
         */
        const onRowAdded = (event) => {
            const row = event.detail?.row
            if (!row || !element.contains(row)) return
            const container = row.querySelector('[data-formbuilder-fields]')
            if (container) initFieldContainer(container)
        }
        element.addEventListener('formbuilder:row-added', onRowAdded)

        return {
            cleanup: () => {
                rowSortable.destroy()
                fieldSortables.forEach((s) => s.destroy())
                element.removeEventListener('formbuilder:row-added', onRowAdded)
            },
        }
    }

    _postRowOrder(rowsContainer, url) {
        if (!url) return
        const rows = Array.from(rowsContainer.querySelectorAll(':scope > [data-formbuilder-row]'))
            .map((row) => Number(row.getAttribute('data-row-id')))
            .filter((id) => Number.isFinite(id) && id > 0)

        this._postOrder(url, rows)
    }

    _postFieldOrder(url) {
        if (!url) return
        const rows = {}
        document.querySelectorAll('[data-formbuilder-fields]').forEach((container) => {
            const rowId = Number(container.getAttribute('data-row-id'))
            if (!Number.isFinite(rowId) || rowId <= 0) return
            const fieldIds = Array.from(container.querySelectorAll(':scope > [data-formbuilder-field]'))
                .map((field) => Number(field.getAttribute('data-field-id')))
                .filter((id) => Number.isFinite(id) && id > 0)
            rows[rowId] = fieldIds
        })

        this._postOrder(url, rows)
    }

    /**
     * POST an order to a reorder handler as `rows` (JSON) plus the token.
     *
     * @param {string} url
     * @param {number[]|Object<string, number[]>} order
     * @returns {Promise<Response|undefined>} never rejects
     */
    _postOrder(url, order) {
        const body = new FormData()
        body.append('rows', JSON.stringify(order))

        return request(url, {
            method:      'POST',
            body:        withToken(body),
            headers:     { 'Accept': 'application/json' },
            credentials: 'same-origin',
            timeoutMs:   TIMEOUTS.MUTATE,
        }).catch(() => {})
    }
}
