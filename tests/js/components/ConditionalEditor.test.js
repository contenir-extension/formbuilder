import { afterEach, describe, expect, it, vi } from 'vitest'
import { createSortable } from '../../../assets/js/utils/Sortable.js'
import ConditionalEditor from '../../../assets/js/components/ConditionalEditor.js'
import { mountedFixtureElement } from '../TestAsset/fixtures.js'

vi.mock('../../../assets/js/utils/Sortable.js', () => ({
    createSortable: vi.fn(() => ({ destroy: vi.fn() })),
}))

afterEach(() => {
    document.body.innerHTML = ''
    vi.clearAllMocks()
})

/**
 * The conditional-logic editor of a field, as the form builder renders it
 * for a field with siblings to depend on: no rules yet, the row template,
 * the add button, and the operators that take no value.
 */
function makeEditor({ rows = 0 } = {}) {
    const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
    mount(element)
    for (let n = 0; n < rows; n++) {
        click(element.querySelector('[data-conditional-add]'))
    }

    return element
}

/** A rule row from the editor's own template, numbered as the server would number a stored rule. */
function storedRow(element, index) {
    const template = element.querySelector('template[data-conditional-template]')
    const holder = document.createElement('div')
    holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index))

    return holder.firstElementChild
}

function mount(element) {
    const component = new ConditionalEditor({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

function click(el) {
    el.dispatchEvent(new MouseEvent('click', { bubbles: true }))
}

function rows(element) {
    return element.querySelectorAll('[data-conditional-row]')
}

function fieldSelectName(row) {
    return row.querySelector('select[name^="conditional[conditions]"]').name
}

describe('ConditionalEditor adding rows', () => {
    it('clones the template and appends a new row', () => {
        const element = makeEditor({ rows: 1 })

        click(element.querySelector('[data-conditional-add]'))

        expect(rows(element)).toHaveLength(2)
    })

    it('replaces the __INDEX__ tokens in each new row with a unique counter', () => {
        const element = makeEditor({ rows: 1 })

        click(element.querySelector('[data-conditional-add]'))

        expect([...rows(element)].map(fieldSelectName)).toEqual([
            'conditional[conditions][0][field]',
            'conditional[conditions][1][field]',
        ])
    })

    it('continues the counter from the highest stored index', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.querySelector('[data-conditional-rows]').appendChild(storedRow(element, 5))
        mount(element)

        click(element.querySelector('[data-conditional-add]'))

        expect(fieldSelectName(rows(element)[1])).toBe('conditional[conditions][6][field]')
    })
})

describe('ConditionalEditor removing rows', () => {
    it('removes the row on remove click', () => {
        const element = makeEditor({ rows: 2 })

        click(element.querySelectorAll('[data-conditional-remove]')[0])

        expect([...rows(element)].map(fieldSelectName)).toEqual(['conditional[conditions][1][field]'])
    })
})

describe('ConditionalEditor combinator slots', () => {
    it('hides the combinator on the first row and shows it on the rest', () => {
        const element = makeEditor({ rows: 2 })

        const slots = [...element.querySelectorAll('[data-conditional-combinator-slot]')].map((slot) => slot.hidden)
        expect(slots).toEqual([true, false])
    })

    it('syncs every combinator select when one changes', () => {
        const element = makeEditor({ rows: 2 })
        const selects = element.querySelectorAll('[data-conditional-combinator]')

        selects[1].value = 'any'
        selects[1].dispatchEvent(new Event('change', { bubbles: true }))

        expect(selects[0].value).toBe('any')
    })

    it('re-evaluates combinator visibility after removing the first row', () => {
        const element = makeEditor({ rows: 2 })

        click(element.querySelectorAll('[data-conditional-remove]')[0])

        expect(element.querySelector('[data-conditional-combinator-slot]').hidden).toBe(true)
    })
})

describe('ConditionalEditor value column', () => {
    it('hides and disables the value input for an operator that takes none', () => {
        const element = makeEditor({ rows: 1 })
        const op = element.querySelector('[data-conditional-op]')

        op.value = 'is_empty'
        op.dispatchEvent(new Event('change', { bubbles: true }))

        const value = element.querySelector('[data-conditional-value]')
        expect([value.hidden, value.disabled]).toEqual([true, true])
    })

    it('shows the value input again for an operator that takes one', () => {
        const element = makeEditor({ rows: 1 })
        const op = element.querySelector('[data-conditional-op]')
        op.value = 'is_empty'
        op.dispatchEvent(new Event('change', { bubbles: true }))

        op.value = 'equals'
        op.dispatchEvent(new Event('change', { bubbles: true }))

        const value = element.querySelector('[data-conditional-value]')
        expect([value.hidden, value.disabled]).toEqual([false, false])
    })
})

describe('ConditionalEditor reordering', () => {
    it('creates a sortable over the rows keyed by handle', () => {
        const element = makeEditor()

        expect(createSortable).toHaveBeenCalledWith(element.querySelector('[data-conditional-rows]'), expect.objectContaining({
            items: '[data-conditional-row]',
            handle: '[data-handle]',
        }))
    })
})

describe('ConditionalEditor edge cases', () => {
    it('does nothing when the rows container or template is missing', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.querySelector('template[data-conditional-template]').remove()
        mount(element)

        click(element.querySelector('[data-conditional-add]'))

        expect(rows(element)).toHaveLength(0)
    })

    it('falls back to an empty valueless-ops list on invalid JSON', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.setAttribute('data-valueless-ops', 'not json')
        mount(element)
        click(element.querySelector('[data-conditional-add]'))
        const op = element.querySelector('[data-conditional-op]')

        op.value = 'is_empty'
        op.dispatchEvent(new Event('change', { bubbles: true }))

        expect(element.querySelector('[data-conditional-value]').hidden).toBe(false)
    })

    it('destroys the sortable on unmount', () => {
        const sortableInstance = { destroy: vi.fn() }
        createSortable.mockReturnValueOnce(sortableInstance)
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        const component = mount(element)

        component.unmount(element)

        expect(sortableInstance.destroy).toHaveBeenCalled()
    })
})

/**
 * The fixture's editor with its row template swapped for other markup, for
 * the defensive paths a template the server renders never takes.
 */
function editorWithTemplate(templateHtml) {
    const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
    element.querySelector('template[data-conditional-template]').innerHTML = templateHtml
    mount(element)

    return element
}

describe('ConditionalEditor unusual templates', () => {
    it('adds nothing when the template is empty', () => {
        const element = editorWithTemplate('')

        click(element.querySelector('[data-conditional-add]'))

        expect(rows(element)).toHaveLength(0)
    })

    it('adds nothing when the template names a row but holds no element', () => {
        const element = editorWithTemplate('<!-- data-conditional-row -->')

        click(element.querySelector('[data-conditional-add]'))

        expect(element.querySelector('[data-conditional-rows]').children).toHaveLength(0)
    })

    it('adds a row that has no operator, combinator or value', () => {
        const element = editorWithTemplate('<div data-conditional-row><input name="conditional[conditions][__INDEX__][field]"></div>')

        click(element.querySelector('[data-conditional-add]'))
        click(element.querySelector('[data-conditional-add]'))

        expect([...rows(element)].map((row) => row.querySelector('input').name)).toEqual([
            'conditional[conditions][0][field]',
            'conditional[conditions][1][field]',
        ])
    })

    it('adds a row whose combinator slot has no select', () => {
        const element = editorWithTemplate('<div data-conditional-row><span data-conditional-combinator-slot></span></div>')

        click(element.querySelector('[data-conditional-add]'))
        click(element.querySelector('[data-conditional-add]'))

        expect([...element.querySelectorAll('[data-conditional-combinator-slot]')].map((slot) => slot.hidden)).toEqual([true, false])
    })

    it('adds a row whose operator has no value input beside it', () => {
        const element = editorWithTemplate('<div data-conditional-row><select data-conditional-op><option value="is_empty">is empty</option></select></div>')

        click(element.querySelector('[data-conditional-add]'))

        expect(rows(element)).toHaveLength(1)
    })
})

describe('ConditionalEditor stored rules', () => {
    it('starts counting from zero when stored rows carry no indexed names', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.querySelector('[data-conditional-rows]').innerHTML = '<div data-conditional-row></div>'
        mount(element)

        click(element.querySelector('[data-conditional-add]'))

        expect(fieldSelectName(rows(element)[1])).toBe('conditional[conditions][0][field]')
    })

    it('starts counting from zero when a stored name has no numeric index', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.querySelector('[data-conditional-rows]').innerHTML =
            '<div data-conditional-row><input name="conditional[conditions][new][field]"></div>'
        mount(element)

        click(element.querySelector('[data-conditional-add]'))

        expect(fieldSelectName(rows(element)[1])).toBe('conditional[conditions][0][field]')
    })
})

describe('ConditionalEditor defaults', () => {
    it('treats every operator as taking a value when the editor lists none', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.removeAttribute('data-valueless-ops')
        mount(element)
        click(element.querySelector('[data-conditional-add]'))
        const op = element.querySelector('[data-conditional-op]')

        op.value = 'is_empty'
        op.dispatchEvent(new Event('change', { bubbles: true }))

        expect(element.querySelector('[data-conditional-value]').hidden).toBe(false)
    })

    it('starts a new row on And when the editor has no combinator', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.removeAttribute('data-combinator')
        mount(element)

        click(element.querySelector('[data-conditional-add]'))

        expect(element.querySelector('[data-conditional-combinator]').value).toBe('all')
    })

    it('starts a new row on the editor\'s current combinator', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.setAttribute('data-combinator', 'any')
        mount(element)

        click(element.querySelector('[data-conditional-add]'))

        expect(element.querySelector('[data-conditional-combinator]').value).toBe('any')
    })

    it('records the chosen combinator on the editor', () => {
        const element = makeEditor({ rows: 2 })
        const select = element.querySelectorAll('[data-conditional-combinator]')[1]

        select.value = 'any'
        select.dispatchEvent(new Event('change', { bubbles: true }))

        expect(element.getAttribute('data-combinator')).toBe('any')
    })

    it('still syncs the selects when mounted on an element not marked as an editor', () => {
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        element.removeAttribute('data-conditional-editor')
        mount(element)
        click(element.querySelector('[data-conditional-add]'))
        click(element.querySelector('[data-conditional-add]'))
        const selects = element.querySelectorAll('[data-conditional-combinator]')

        selects[1].value = 'any'
        selects[1].dispatchEvent(new Event('change', { bubbles: true }))

        expect([selects[0].value, element.getAttribute('data-combinator')]).toEqual(['any', 'all'])
    })
})

describe('ConditionalEditor ignored events', () => {
    it('ignores a click on neither the add nor a remove button', () => {
        const element = makeEditor({ rows: 1 })

        click(element.querySelector('[data-conditional-op]'))

        expect(rows(element)).toHaveLength(1)
    })

    it('ignores a remove button that is not inside a row', () => {
        const element = makeEditor({ rows: 1 })
        const stray = document.createElement('button')
        stray.setAttribute('data-conditional-remove', '')
        element.appendChild(stray)

        click(stray)

        expect(rows(element)).toHaveLength(1)
    })

    it('ignores add and remove buttons around the editor rather than in it', () => {
        const wrapper = document.createElement('div')
        wrapper.setAttribute('data-conditional-add', '')
        wrapper.setAttribute('data-conditional-remove', '')
        document.body.appendChild(wrapper)
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        wrapper.appendChild(element)
        mount(element)

        click(element.querySelector('[data-conditional-rows]'))

        expect(rows(element)).toHaveLength(0)
    })

    it('ignores a change to a rule\'s field select', () => {
        const element = makeEditor({ rows: 2 })
        const field = element.querySelector('select[name$="[field]"]')

        field.value = 'email'
        field.dispatchEvent(new Event('change', { bubbles: true }))

        expect(element.getAttribute('data-combinator')).toBe('all')
    })

    it('ignores op and combinator selects around the editor rather than in it', () => {
        const wrapper = document.createElement('div')
        wrapper.setAttribute('data-conditional-op', '')
        wrapper.setAttribute('data-conditional-combinator', '')
        document.body.appendChild(wrapper)
        const element = mountedFixtureElement('conditional-editor-field', '[data-conditional-editor]')
        wrapper.appendChild(element)
        mount(element)

        element.querySelector('[data-conditional-rows]').dispatchEvent(new Event('change', { bubbles: true }))

        expect(element.getAttribute('data-combinator')).toBe('all')
    })

    it('ignores an operator select that is not inside a row', () => {
        const element = makeEditor({ rows: 1 })
        const stray = document.createElement('select')
        stray.setAttribute('data-conditional-op', '')
        stray.innerHTML = '<option value="is_empty">is empty</option>'
        element.appendChild(stray)

        stray.dispatchEvent(new Event('change', { bubbles: true }))

        expect(element.querySelector('[data-conditional-value]').hidden).toBe(false)
    })
})

describe('ConditionalEditor after a drag', () => {
    it('re-evaluates combinator visibility once rows are reordered', () => {
        const element = makeEditor({ rows: 2 })
        const container = element.querySelector('[data-conditional-rows]')
        container.appendChild(container.firstElementChild)
        const { onSort } = createSortable.mock.calls.find(([el]) => el === container)[1]

        onSort()

        expect([...element.querySelectorAll('[data-conditional-combinator-slot]')].map((slot) => slot.hidden)).toEqual([true, false])
    })
})
