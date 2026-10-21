import Sortable from 'sortablejs'

/**
 * Create a SortableJS instance with the admin's shared defaults: the forced
 * fallback drag (consistent cross-browser behaviour), a 150ms animation, and
 * the `sortable--ghost` / `sortable--chosen` / `sortable--drag` classes.
 *
 * Callers pass the per-list bits — `draggable`, `handle`, `group`, `filter`,
 * `onSort`/`onEnd`/`onStart`, etc. Any default can be overridden (e.g. the
 * sitemap supplies its own `sitemap__item--*` classes).
 *
 * @param {HTMLElement} el
 * @param {object} [options]
 * @returns {Sortable}
 */
export function createSortable(el, options = {}) {
    return Sortable.create(el, {
        forceFallback: true,
        animation: 150,
        ghostClass: 'sortable--ghost',
        chosenClass: 'sortable--chosen',
        dragClass: 'sortable--drag',
        ...options,
    })
}
