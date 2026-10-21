import { afterEach, describe, expect, it, vi } from 'vitest'
import { createSortable } from '../../../assets/js/utils/Sortable.js'
import { request } from '../../../assets/js/utils/HttpClient.js'
import FieldsEditor from '../../../assets/js/components/FieldsEditor.js'
import { mountedFixtureElement } from '../TestAsset/fixtures.js'
import { addHandlerToken, TOKEN_NAME, TOKEN_VALUE } from '../TestAsset/token.js'

vi.mock('../../../assets/js/utils/Sortable.js', () => ({
    createSortable: vi.fn(() => ({ destroy: vi.fn() })),
}))

vi.mock('../../../assets/js/utils/HttpClient.js', () => ({
    request: vi.fn().mockResolvedValue({}),
    TIMEOUTS: { MUTATE: 30000 },
}))

afterEach(() => {
    document.body.innerHTML = ''
    vi.clearAllMocks()
    request.mockResolvedValue({})
})

/**
 * The first group of a form in the builder, as the fields page renders it:
 * row 801 holding fields 901 and 902, then the empty row 804, with the
 * reorder addresses on the group.
 */
function makeGroup({ rowReorderUrl = null, fieldReorderUrl = null } = {}) {
    const element = mountedFixtureElement('fields-editor-form', '[data-formbuilder-rows][data-group-id="701"]')
    if (rowReorderUrl !== null) {
        element.setAttribute('data-row-reorder-url', rowReorderUrl)
    }
    if (fieldReorderUrl !== null) {
        element.setAttribute('data-field-reorder-url', fieldReorderUrl)
    }

    return element
}

const ROW_REORDER_URL   = '/admin/extension/formbuilder/row-reorder?form=501&group=701'
const FIELD_REORDER_URL = '/admin/extension/formbuilder/field-reorder?form=501'

function mount(element) {
    const component = new FieldsEditor({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

function rowSortable(element) {
    return createSortable.mock.calls.find(([el]) => el === element)[1]
}

function fieldSortable(container) {
    return createSortable.mock.calls.find(([el]) => el === container)[1]
}

function postedRows(call = 0) {
    return JSON.parse(request.mock.calls[call][1].body.get('rows'))
}

describe('FieldsEditor setup', () => {
    it('creates a row sortable over the group element', () => {
        const element = makeGroup()
        mount(element)

        expect(createSortable).toHaveBeenCalledWith(element, expect.objectContaining({
            draggable: '[data-formbuilder-row]',
            handle: '[data-formbuilder-row-handle]',
        }))
    })

    it('creates one field sortable per row, sharing the same drag group', () => {
        const element = makeGroup()
        mount(element)

        const fieldCalls = createSortable.mock.calls.filter(([, opts]) => opts.group === 'forms-formbuilder-fields')
        expect(fieldCalls.map(([el]) => el.getAttribute('data-row-id'))).toEqual(['801', '804'])
        expect(fieldCalls[0][1]).toMatchObject({
            draggable: '[data-formbuilder-field]',
            handle: '[data-formbuilder-field-handle]',
            filter: '[data-formbuilder-append]',
        })
    })
})

describe('FieldsEditor row reordering', () => {
    it('posts the row ids in DOM order as a JSON rows field on row sort end', () => {
        const element = makeGroup()
        mount(element)
        element.appendChild(element.querySelector('[data-formbuilder-row]'))

        rowSortable(element).onEnd()

        expect(request).toHaveBeenCalledWith(ROW_REORDER_URL, expect.objectContaining({ method: 'POST' }))
        expect(postedRows()).toEqual([804, 801])
    })

    it('asks for a JSON answer', () => {
        const element = makeGroup()
        mount(element)

        rowSortable(element).onEnd()

        expect(request.mock.calls[0][1].headers).toEqual({ 'Accept': 'application/json' })
    })

    it('sends the handler token with the row order', () => {
        addHandlerToken()
        const element = makeGroup()
        mount(element)

        rowSortable(element).onEnd()

        expect(request.mock.calls[0][1].body.get(TOKEN_NAME)).toBe(TOKEN_VALUE)
    })

    it('leaves out rows without a usable id', () => {
        const element = makeGroup()
        mount(element)
        element.querySelector('[data-formbuilder-row][data-row-id="804"]').setAttribute('data-row-id', 'new')

        rowSortable(element).onEnd()

        expect(postedRows()).toEqual([801])
    })

    it('does nothing when there is no row reorder URL', () => {
        const element = makeGroup({ rowReorderUrl: '' })
        mount(element)

        rowSortable(element).onEnd()

        expect(request).not.toHaveBeenCalled()
    })

    it('does nothing when the row reorder URL attribute is missing', () => {
        const element = makeGroup()
        element.removeAttribute('data-row-reorder-url')
        mount(element)

        rowSortable(element).onEnd()

        expect(request).not.toHaveBeenCalled()
    })

    /** An unhandled rejection would fail the run, so settling quietly is the assertion. */
    it('swallows a failed reorder, since the page already shows the new order', async () => {
        request.mockRejectedValue(new Error('down'))
        const element = makeGroup()
        mount(element)
        element.appendChild(element.querySelector('[data-formbuilder-row]'))

        rowSortable(element).onEnd()
        await new Promise((resolve) => setTimeout(resolve, 0))

        expect([...element.querySelectorAll(':scope > [data-formbuilder-row]')].map((row) => row.getAttribute('data-row-id')))
            .toEqual(['804', '801'])
    })

    it('toggles the dragging class around a row drag', () => {
        const element = makeGroup()
        mount(element)

        rowSortable(element).onStart()
        const whileDragging = element.classList.contains('formbuilder__rows--dragging')
        rowSortable(element).onEnd()

        expect([whileDragging, element.classList.contains('formbuilder__rows--dragging')]).toEqual([true, false])
    })
})

describe('FieldsEditor field reordering', () => {
    it('posts every row keyed by row id with its field ids in DOM order', () => {
        const element = makeGroup()
        mount(element)
        const container = element.querySelector('[data-formbuilder-fields][data-row-id="801"]')
        container.appendChild(container.querySelector('[data-formbuilder-field]'))

        fieldSortable(container).onEnd()

        expect(request).toHaveBeenCalledWith(FIELD_REORDER_URL, expect.objectContaining({ method: 'POST' }))
        expect(postedRows()).toEqual({ 801: [902, 901], 804: [] })
    })

    it('sends the handler token with the field order', () => {
        addHandlerToken()
        const element = makeGroup()
        mount(element)

        fieldSortable(element.querySelector('[data-formbuilder-fields]')).onEnd()

        expect(request.mock.calls[0][1].body.get(TOKEN_NAME)).toBe(TOKEN_VALUE)
    })

    it('includes rows from other groups, so a cross-group drag sends both ends', () => {
        const element = makeGroup()
        mountedFixtureElement('fields-editor-form', '[data-formbuilder-rows][data-group-id="702"]')
        mount(element)

        fieldSortable(element.querySelector('[data-formbuilder-fields]')).onEnd()

        expect(postedRows()).toEqual({ 801: [901, 902], 804: [], 802: [903] })
    })

    it('leaves out rows and fields without a usable id', () => {
        const element = makeGroup()
        mount(element)
        element.querySelector('[data-formbuilder-fields][data-row-id="804"]').setAttribute('data-row-id', '0')
        element.querySelector('[data-formbuilder-field][data-field-id="902"]').setAttribute('data-field-id', 'x')

        fieldSortable(element.querySelector('[data-formbuilder-fields]')).onEnd()

        expect(postedRows()).toEqual({ 801: [901] })
    })

    it('leaves out a row whose id is not a number', () => {
        const element = makeGroup()
        mount(element)
        element.querySelector('[data-formbuilder-fields][data-row-id="804"]').setAttribute('data-row-id', 'new')

        fieldSortable(element.querySelector('[data-formbuilder-fields]')).onEnd()

        expect(postedRows()).toEqual({ 801: [901, 902] })
    })

    it('does nothing when there is no field reorder URL', () => {
        const element = makeGroup({ fieldReorderUrl: '' })
        mount(element)

        fieldSortable(element.querySelector('[data-formbuilder-fields]')).onEnd()

        expect(request).not.toHaveBeenCalled()
    })

    it('does nothing when the field reorder URL attribute is missing', () => {
        const element = makeGroup()
        element.removeAttribute('data-field-reorder-url')
        mount(element)

        fieldSortable(element.querySelector('[data-formbuilder-fields]')).onEnd()

        expect(request).not.toHaveBeenCalled()
    })

    it('toggles the dragging class around a field drag', () => {
        const element = makeGroup()
        mount(element)
        const options = fieldSortable(element.querySelector('[data-formbuilder-fields]'))

        options.onStart()
        const whileDragging = element.classList.contains('formbuilder__rows--dragging')
        options.onEnd()

        expect([whileDragging, element.classList.contains('formbuilder__rows--dragging')]).toEqual([true, false])
    })

    it('wires a sortable onto a row inserted via formbuilder:row-added', () => {
        const element = makeGroup()
        mount(element)
        createSortable.mockClear()
        const row = element.querySelector('[data-formbuilder-row][data-row-id="804"]').cloneNode(true)
        row.setAttribute('data-row-id', '805')
        row.querySelector('[data-formbuilder-fields]').setAttribute('data-row-id', '805')
        element.appendChild(row)

        element.dispatchEvent(new CustomEvent('formbuilder:row-added', { detail: { row }, bubbles: true }))

        expect(createSortable).toHaveBeenCalledWith(
            row.querySelector('[data-formbuilder-fields]'),
            expect.objectContaining({ group: 'forms-formbuilder-fields' }),
        )
    })

    it('ignores a row-added event for a row outside this group', () => {
        const element = makeGroup()
        mount(element)
        createSortable.mockClear()
        const foreignRow = mountedFixtureElement('fields-editor-form', '[data-formbuilder-row][data-row-id="802"]')

        element.dispatchEvent(new CustomEvent('formbuilder:row-added', { detail: { row: foreignRow }, bubbles: true }))

        expect(createSortable).not.toHaveBeenCalled()
    })

    it('ignores a row-added event without a row', () => {
        const element = makeGroup()
        mount(element)
        createSortable.mockClear()

        element.dispatchEvent(new CustomEvent('formbuilder:row-added', { bubbles: true }))

        expect(createSortable).not.toHaveBeenCalled()
    })

    it('ignores an added row that has no field container', () => {
        const element = makeGroup()
        mount(element)
        createSortable.mockClear()
        const row = document.createElement('div')
        row.setAttribute('data-formbuilder-row', '')
        element.appendChild(row)

        element.dispatchEvent(new CustomEvent('formbuilder:row-added', { detail: { row }, bubbles: true }))

        expect(createSortable).not.toHaveBeenCalled()
    })
})

describe('FieldsEditor teardown', () => {
    it('destroys every sortable instance on unmount', () => {
        const element = makeGroup()
        const instances = []
        createSortable.mockImplementation(() => {
            const instance = { destroy: vi.fn() }
            instances.push(instance)
            return instance
        })
        const component = mount(element)

        component.unmount(element)

        expect(instances).toHaveLength(3)
        instances.forEach((instance) => expect(instance.destroy).toHaveBeenCalled())
    })

    it('stops listening for added rows once unmounted', () => {
        const element = makeGroup()
        const component = mount(element)
        component.unmount(element)
        createSortable.mockClear()
        const row = element.querySelector('[data-formbuilder-row][data-row-id="804"]')

        element.dispatchEvent(new CustomEvent('formbuilder:row-added', { detail: { row }, bubbles: true }))

        expect(createSortable).not.toHaveBeenCalled()
    })
})
