import { Sortable } from '@parallelogram-js/core/draggable'

/**
 * Drag engines by group name, or by container for a list in no group, with
 * the `onSort` callback of each container they hold.
 *
 * @type {Map<string|HTMLElement, { engine: Sortable, callbacks: Map<HTMLElement, Function>, pending: object|null }>}
 */
const groups = new Map()

/**
 * Make a list's items reorderable by dragging, on Parallelogram's drag
 * engine, with the admin's shared behaviour.
 *
 * A drag starts once the pointer has moved four pixels from the handle, or
 * after a short hold on touch, and the page scrolls near its edges. From the
 * keyboard, Space or Enter on a handle picks its item up, the arrow keys move
 * it, Space or Enter drops it and Escape puts it back; each handle is made
 * reachable with Tab.
 *
 * The engine's own placeholder and pointer copy are deep clones, which in a
 * form would duplicate names and ids and take checked radios from the row
 * being dragged. Here the placeholder is an empty box of the row's size and
 * the copy has its ids, names and labels' `for` stripped.
 *
 * State is in `data-sortable-state` on the container (`idle`, `active`,
 * `over`) and `data-sortable-item-state` on the placeholder, the copy and
 * the row just dropped (`placeholder`, `mirror`, `placed`).
 *
 * The copy follows the pointer up and down only, unless the list lays its
 * items out across as well (a grid, or a flex row) or shares a group with
 * other lists, which may stand side by side as columns do; `axis` says
 * otherwise.
 *
 * Containers given the same `group` share one engine, so items move between
 * them. `onSort` runs once a drop has changed the order and the item is back
 * in the page, on the container the item landed in, with
 * `{ item, oldContainer, newContainer, oldIndex, newIndex }`.
 *
 * @param {HTMLElement} container
 * @param {object} options
 * @param {string} options.items - Selector for the container's draggable children
 * @param {string|null} [options.handle] - Selector for the grip within an item
 * @param {string|null} [options.group] - Name shared by containers whose items move between them
 * @param {'x'|'y'|'both'|null} [options.axis] - The way the copy may move; by default, from the group or the container's layout
 * @param {Function} [options.onSort]
 * @returns {{ destroy: () => void }}
 */
export function createSortable(container, { items, handle = null, group = null, axis = null, onSort = () => {} }) {
    const key = group ?? container
    let entry = groups.get(key)
    if (entry) {
        entry.engine.addContainer(container)
    } else {
        entry = createGroup(key, container, { items, handle, axis: axis ?? (group ? 'both' : axisOf(container)) })
    }
    entry.callbacks.set(container, onSort)
    entry.engine.setContainerState(container, 'idle')

    /** Rows added later need their handles reachable with Tab. */
    const observer = new MutationObserver(() => {
        if (!entry.engine.isDragging()) entry.engine.refreshFocusable?.()
    })
    observer.observe(container, { childList: true, subtree: true })
    entry.engine.refreshFocusable?.()

    return {
        destroy: () => {
            observer.disconnect()
            leave(key, entry, container)
        },
    }
}

function createGroup(key, container, { items, handle, axis }) {
    const engine = new Sortable([container], {
        draggable: items,
        handle,
        distance: 4,
        delay: { mouse: 0, pen: 0, touch: 100 },
        attribute: 'data-sortable',
        placeholder: createPlaceholder,
        mirror: { create: createMirror, xAxis: axis !== 'y', yAxis: axis !== 'x' },
    })
    const entry = { engine, callbacks: new Map(), pending: null }

    engine.on('sortable:stop', ({ dragEvent, oldIndex, newIndex, oldContainer, newContainer }) => {
        const reverted = Boolean(dragEvent.sensorEvent?.revert) || dragEvent.canceled()
        const moved = oldContainer !== newContainer || oldIndex !== newIndex
        entry.pending = reverted || !moved
            ? null
            : { item: dragEvent.originalSource, oldIndex, newIndex, oldContainer, newContainer }
    })
    engine.on('drag:stopped', () => {
        const detail = entry.pending
        entry.pending = null
        if (detail) entry.callbacks.get(detail.newContainer)?.(detail)
    })

    groups.set(key, entry)
    return entry
}

/**
 * The way a list's items can move: across as well as down when it lays them
 * out in a grid or a flex row, otherwise only down.
 *
 * @param {HTMLElement} container
 * @returns {'y'|'both'}
 */
function axisOf(container) {
    const { display, flexDirection } = getComputedStyle(container)
    if (display.includes('grid')) return 'both'
    if (display.includes('flex') && !flexDirection.startsWith('column')) return 'both'
    return 'y'
}

function leave(key, entry, container) {
    entry.callbacks.delete(container)
    if (entry.engine.sourceContainer === container) entry.engine.cancel()

    if (entry.callbacks.size > 0) {
        entry.engine.removeContainer(container)
    } else {
        entry.engine.destroy()
        groups.delete(key)
    }
}

/**
 * What holds a dragged item's place: an empty copy of the item, of its size,
 * so the list keeps its shape. A table row keeps its cells, empty, so it
 * spans the table.
 *
 * @param {HTMLElement} item
 * @returns {HTMLElement}
 */
function createPlaceholder(item) {
    const rect = item.getBoundingClientRect()
    const placeholder = item.cloneNode(false)
    placeholder.removeAttribute('id')
    placeholder.setAttribute('aria-hidden', 'true')
    placeholder.tabIndex = -1
    if (item instanceof HTMLTableRowElement) {
        placeholder.append(...[...item.cells].map((cell) => cell.cloneNode(false)))
    } else {
        placeholder.style.width = `${rect.width}px`
    }
    placeholder.style.height = `${rect.height}px`
    placeholder.style.boxSizing = 'border-box'
    return placeholder
}

/**
 * The copy that follows the pointer: the item as it looks, sized to its
 * placeholder, with nothing a form would read or a label point at.
 *
 * @param {HTMLElement} placeholder
 * @param {HTMLElement} item
 * @returns {HTMLElement}
 */
function createMirror(placeholder, item) {
    const rect = placeholder.getBoundingClientRect()
    const mirror = item.cloneNode(true)
    for (const element of [mirror, ...mirror.querySelectorAll('[id], [name], [for], [form]')]) {
        ;['id', 'name', 'for', 'form'].forEach((attribute) => element.removeAttribute(attribute))
    }
    mirror.style.display = ''
    if (item instanceof HTMLTableRowElement) {
        /** Out of its table, a row's cells would size to their content; each keeps its column's width. */
        mirror.style.display = 'table'
        mirror.style.tableLayout = 'fixed'
        ;[...mirror.cells].forEach((cell, index) => {
            cell.style.width = `${placeholder.cells[index].getBoundingClientRect().width}px`
        })
    }
    mirror.style.width = `${rect.width}px`
    mirror.style.height = `${rect.height}px`
    mirror.style.boxSizing = 'border-box'
    mirror.inert = true
    return mirror
}
