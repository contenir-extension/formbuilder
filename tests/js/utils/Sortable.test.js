import { afterEach, describe, expect, it, vi } from 'vitest'
import { createSortable } from '../../../assets/js/utils/Sortable.js'

const press = (target, key) => target.dispatchEvent(
    new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }),
)

function makeList(rows = ['One', 'Two', 'Three']) {
    const list = document.createElement('ul')
    list.innerHTML = rows.map((label, index) => (
        `<li data-row="${index}"><span data-handle>${label}</span>`
        + `<input type="radio" name="choice-${index}" id="choice-${index}" value="${index}" checked></li>`
    )).join('')
    document.body.appendChild(list)
    return list
}

/** Pick an item up by its handle from the keyboard, step it and drop it. */
function dragByKeyboard(item, steps) {
    const handle = item.querySelector('[data-handle]')
    handle.focus()
    press(handle, ' ')
    for (const key of steps) press(document.activeElement, key)
    press(document.activeElement, ' ')
}

/**
 * Press a mouse on an element and move it past the drag distance. Returns a
 * function that moves the pointer on to `x`, and one that lets it go.
 */
function pressAndMove(element) {
    let last = 20
    const pointer = (type, x) => new PointerEvent(type, {
        bubbles: true, cancelable: true, isPrimary: true, pointerId: 1, pointerType: 'mouse', button: 0, clientX: x, clientY: 0,
    })
    document.elementFromPoint = () => element
    element.dispatchEvent(pointer('pointerdown', 0))
    document.dispatchEvent(pointer('pointermove', last))
    return {
        move: (x) => document.dispatchEvent(pointer('pointermove', (last = x))),
        release: () => document.dispatchEvent(pointer('pointerup', last)),
    }
}

/**
 * Where the copy following the pointer is drawn once the pointer has moved
 * 40px across from where it was pressed: the engine settles its first frame on where the
 * drag began, and the copy follows on the next.
 */
async function mirrorAfterMovingAcross(container) {
    const { move, release } = pressAndMove(container.querySelector('[data-handle]'))
    move(30)
    await nextFrames(2)
    move(40)
    await nextFrames(1)
    const { transform } = container.querySelector('[data-sortable-item-state="mirror"]').style
    release()
    return transform
}

/** The engine and its copy each settle on an animation frame. */
const nextFrames = async (count) => {
    for (let frame = 0; frame < count; frame++) await new Promise((resolve) => requestAnimationFrame(resolve))
}

const sortables = []
function sortable(container, options) {
    const instance = createSortable(container, options)
    sortables.push(instance)
    return instance
}

afterEach(() => {
    sortables.splice(0).forEach((instance) => instance.destroy())
    document.body.innerHTML = ''
    delete document.elementFromPoint
    vi.restoreAllMocks()
})

describe('createSortable', () => {
    it('makes each handle reachable with Tab', () => {
        const list = makeList()

        sortable(list, { items: 'li', handle: '[data-handle]' })

        expect([...list.querySelectorAll('[data-handle]')].map((handle) => handle.tabIndex)).toEqual([0, 0, 0])
    })

    it('marks the container idle once it can be sorted', () => {
        const list = makeList()

        sortable(list, { items: 'li', handle: '[data-handle]' })

        expect(list.dataset.sortableState).toBe('idle')
    })

    it('holds the dragged item\'s place with an empty, unnamed copy', () => {
        const list = makeList()
        sortable(list, { items: 'li', handle: '[data-handle]' })
        const handle = list.firstElementChild.querySelector('[data-handle]')

        handle.focus()
        press(handle, ' ')

        const placeholder = list.querySelector('[data-sortable-item-state="placeholder"]')
        expect(placeholder.childElementCount).toBe(0)
        press(document.activeElement, 'Escape')
    })

    it('leaves the dragged item its checked radio while it is held', () => {
        const list = makeList()
        sortable(list, { items: 'li', handle: '[data-handle]' })
        const handle = list.firstElementChild.querySelector('[data-handle]')

        handle.focus()
        press(handle, ' ')

        expect(document.getElementById('choice-0').checked).toBe(true)
        press(document.activeElement, 'Escape')
    })

    it('reports a drop that changed the order once the item is in place', () => {
        const list = makeList()
        const onSort = vi.fn()
        sortable(list, { items: 'li', handle: '[data-handle]', onSort })
        const first = list.firstElementChild

        dragByKeyboard(first, ['ArrowDown'])

        expect(onSort).toHaveBeenCalledWith({
            item: first,
            oldIndex: 0,
            newIndex: 1,
            oldContainer: list,
            newContainer: list,
        })
        expect([...list.children].map((row) => row.dataset.row)).toEqual(['1', '0', '2'])
    })

    it('says nothing of a drop that left the order as it was', () => {
        const list = makeList()
        const onSort = vi.fn()
        sortable(list, { items: 'li', handle: '[data-handle]', onSort })

        dragByKeyboard(list.firstElementChild, [])

        expect(onSort).not.toHaveBeenCalled()
    })

    it('says nothing of a drag cancelled with Escape', () => {
        const list = makeList()
        const onSort = vi.fn()
        sortable(list, { items: 'li', handle: '[data-handle]', onSort })
        const handle = list.firstElementChild.querySelector('[data-handle]')

        handle.focus()
        press(handle, ' ')
        press(document.activeElement, 'ArrowDown')
        press(document.activeElement, 'Escape')

        expect(onSort).not.toHaveBeenCalled()
    })

    it('moves an item between containers that share a group, telling the one it joined', () => {
        const from = makeList(['A'])
        const to = makeList(['B'])
        const onFrom = vi.fn()
        const onTo = vi.fn()
        sortable(from, { items: 'li', handle: '[data-handle]', group: 'pages', onSort: onFrom })
        sortable(to, { items: 'li', handle: '[data-handle]', group: 'pages', onSort: onTo })
        const item = from.firstElementChild

        dragByKeyboard(item, ['ArrowDown'])

        expect(onTo).toHaveBeenCalledWith(expect.objectContaining({ item, oldContainer: from, newContainer: to }))
        expect(onFrom).not.toHaveBeenCalled()
    })

    it('stops sorting a container once destroyed', () => {
        const list = makeList()
        const onSort = vi.fn()
        const instance = createSortable(list, { items: 'li', handle: '[data-handle]', onSort })

        instance.destroy()
        dragByKeyboard(list.firstElementChild, ['ArrowDown'])

        expect(onSort).not.toHaveBeenCalled()
    })

    it('keeps a group sorting while another of its containers remains', () => {
        const first = makeList(['A', 'B'])
        const second = makeList(['C'])
        const onSort = vi.fn()
        sortable(first, { items: 'li', handle: '[data-handle]', group: 'kept', onSort })
        createSortable(second, { items: 'li', handle: '[data-handle]', group: 'kept' }).destroy()

        dragByKeyboard(first.firstElementChild, ['ArrowDown'])

        expect(onSort).toHaveBeenCalledWith(expect.objectContaining({ oldIndex: 0, newIndex: 1 }))
    })

    it('gives the copy that follows the pointer no ids or names a form would read', () => {
        const list = makeList()
        list.firstElementChild.insertAdjacentHTML('beforeend', '<label for="choice-0">Choice</label>')
        sortable(list, { items: 'li', handle: '[data-handle]' })

        const { move, release } = pressAndMove(list.firstElementChild.querySelector('[data-handle]'))
        move(30)
        const mirror = list.querySelector('[data-sortable-item-state="mirror"]')
        const named = mirror.querySelectorAll('[id], [name], [for], [form]')
        release()

        expect(named).toHaveLength(0)
    })

    it('keeps a table row\'s cells in its placeholder, so it spans the table', () => {
        document.body.innerHTML = '<table><tbody><tr><td data-handle>A</td><td>1</td></tr><tr><td data-handle>B</td><td>2</td></tr></tbody></table>'
        const body = document.querySelector('tbody')
        sortable(body, { items: 'tr', handle: '[data-handle]' })

        const { release } = pressAndMove(body.querySelector('[data-handle]'))
        const placeholder = body.querySelector('[data-sortable-item-state="placeholder"]')
        const cells = [...placeholder.cells].map((cell) => cell.textContent)
        release()

        expect(cells).toEqual(['', ''])
    })

    it('keeps the copy in its column for a list that stacks its items', async () => {
        const list = makeList()
        sortable(list, { items: 'li', handle: '[data-handle]' })

        expect(await mirrorAfterMovingAcross(list)).toBe('translate3d(0px, 0px, 0)')
    })

    it('lets the copy move across in a list laid out as a grid', async () => {
        const list = makeList()
        list.style.display = 'grid'
        sortable(list, { items: 'li', handle: '[data-handle]' })

        expect(await mirrorAfterMovingAcross(list)).toBe('translate3d(40px, 0px, 0)')
    })

    it('lets the copy move across between lists of a group, which may stand side by side', async () => {
        const column = makeList()
        sortable(column, { items: 'li', handle: '[data-handle]', group: 'columns' })
        sortable(makeList(), { items: 'li', handle: '[data-handle]', group: 'columns' })

        expect(await mirrorAfterMovingAcross(column)).toBe('translate3d(40px, 0px, 0)')
    })

    it('holds the copy to the axis it is given', async () => {
        const list = makeList()
        list.style.display = 'grid'
        sortable(list, { items: 'li', handle: '[data-handle]', axis: 'y' })

        expect(await mirrorAfterMovingAcross(list)).toBe('translate3d(0px, 0px, 0)')
    })

    it('drags only the inner list\'s item when a list sits inside another list\'s item', () => {
        const outer = makeList(['Block'])
        const inner = makeList(['A', 'B'])
        outer.firstElementChild.appendChild(inner)
        const onOuter = vi.fn()
        const onInner = vi.fn()
        sortable(outer, { items: 'li', handle: '[data-handle]', onSort: onOuter })
        sortable(inner, { items: 'li', handle: '[data-handle]', onSort: onInner })

        dragByKeyboard(inner.firstElementChild, ['ArrowDown'])

        expect([onInner.mock.calls.length, onOuter.mock.calls.length]).toEqual([1, 0])
    })

    it('lets the copy move across in a list laid out as a flex row', async () => {
        const list = makeList()
        list.style.display = 'flex'
        sortable(list, { items: 'li', handle: '[data-handle]' })

        expect(await mirrorAfterMovingAcross(list)).toBe('translate3d(40px, 0px, 0)')
    })

    it('keeps the copy in its column for a list laid out as a flex column', async () => {
        const list = makeList()
        list.style.display = 'flex'
        list.style.flexDirection = 'column'
        sortable(list, { items: 'li', handle: '[data-handle]' })

        expect(await mirrorAfterMovingAcross(list)).toBe('translate3d(0px, 0px, 0)')
    })

    it('gives a table row\'s copy each column\'s width, so its cells line up with the table', () => {
        document.body.innerHTML = '<table><tbody><tr><td data-handle>A</td><td>1</td></tr><tr><td data-handle>B</td><td>2</td></tr></tbody></table>'
        const body = document.querySelector('tbody')
        vi.spyOn(HTMLTableCellElement.prototype, 'getBoundingClientRect').mockImplementation(function cellRect() {
            return { width: [120, 300][this.cellIndex], height: 40, top: 0, left: 0, right: 0, bottom: 0, x: 0, y: 0 }
        })
        sortable(body, { items: 'tr', handle: '[data-handle]' })

        const { move, release } = pressAndMove(body.querySelector('[data-handle]'))
        move(30)
        const mirror = body.querySelector('[data-sortable-item-state="mirror"]')
        const widths = [...mirror.cells].map((cell) => cell.style.width)
        release()

        expect(widths).toEqual(['120px', '300px'])
    })

    it('reorders a list given no onSort', () => {
        const list = makeList()
        sortable(list, { items: 'li', handle: '[data-handle]' })

        dragByKeyboard(list.firstElementChild, ['ArrowDown'])

        expect([...list.children].map((row) => row.dataset.row)).toEqual(['1', '0', '2'])
    })

    it('puts a dragged item back when its list is destroyed mid-drag', () => {
        const list = makeList()
        const onSort = vi.fn()
        const instance = createSortable(list, { items: 'li', handle: '[data-handle]', onSort })
        const handle = list.firstElementChild.querySelector('[data-handle]')
        handle.focus()
        press(handle, ' ')
        press(document.activeElement, 'ArrowDown')

        instance.destroy()

        expect([...list.children].map((row) => row.dataset.row)).toEqual(['0', '1', '2'])
    })
})
