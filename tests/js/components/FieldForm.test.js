import { afterEach, describe, expect, it, vi } from 'vitest'
import { createSortable } from '../../../assets/js/utils/Sortable.js'
import FieldForm from '../../../assets/js/components/FieldForm.js'

vi.mock('../../../assets/js/utils/Sortable.js', () => ({
    createSortable: vi.fn(() => ({ destroy: vi.fn() })),
}))

afterEach(() => {
    document.body.innerHTML = ''
    vi.clearAllMocks()
})

function validatorRow(name, checked) {
    return `
        <div data-validator-row>
            <span data-handle></span>
            <input type="checkbox" data-validator-toggle ${checked ? 'checked' : ''}>
            <div data-validator-options>
                <input name="${name}">
            </div>
        </div>
    `
}

function makeForm() {
    const form = document.createElement('div')
    form.dataset.fieldForm = ''
    form.innerHTML = `
        <div data-validator-rows>
            ${validatorRow('validators[0][options][a]', false)}
            ${validatorRow('validators[1][options][b]', true)}
        </div>
    `
    document.body.appendChild(form)
    return form
}

function mount(element) {
    const component = new FieldForm({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

describe('FieldForm validator visibility', () => {
    it('hides and disables options for an unchecked validator on mount', () => {
        const form = makeForm()
        mount(form)
        const [first] = form.querySelectorAll('[data-validator-row]')

        expect(first.querySelector('[data-validator-options]').hidden).toBe(true)
        expect(first.querySelector('input[name^="validators"]').disabled).toBe(true)
    })

    it('shows and enables options for a checked validator on mount', () => {
        const form = makeForm()
        mount(form)
        const [, second] = form.querySelectorAll('[data-validator-row]')

        expect(second.querySelector('[data-validator-options]').hidden).toBe(false)
        expect(second.querySelector('input[name^="validators"]').disabled).toBe(false)
    })

    it('toggles visibility when the checkbox changes', () => {
        const form = makeForm()
        mount(form)
        const [first] = form.querySelectorAll('[data-validator-row]')
        const checkbox = first.querySelector('[data-validator-toggle]')

        checkbox.checked = true
        checkbox.dispatchEvent(new Event('change', { bubbles: true }))

        expect(first.querySelector('[data-validator-options]').hidden).toBe(false)
    })

    it('does nothing when there is no validator rows container', () => {
        const form = document.createElement('div')
        document.body.appendChild(form)

        expect(() => mount(form)).not.toThrow()
    })
})

describe('FieldForm validator reordering', () => {
    it('creates a sortable over the validator rows keyed by the handle', () => {
        const form = makeForm()
        mount(form)

        expect(createSortable).toHaveBeenCalledWith(
            form.querySelector('[data-validator-rows]'),
            expect.objectContaining({ items: '[data-validator-row]', handle: '[data-handle]' }),
        )
    })

    it('renumbers every input name to match the new row order after a sort', () => {
        const form = makeForm()
        mount(form)
        const rows = form.querySelector('[data-validator-rows]')
        rows.appendChild(rows.firstElementChild)
        const { onSort } = createSortable.mock.calls[0][1]

        onSort()

        const inputs = rows.querySelectorAll('input[name^="validators"]')
        expect(inputs[0].name).toBe('validators[0][options][b]')
        expect(inputs[1].name).toBe('validators[1][options][a]')
    })

    it('destroys the sortable and removes listeners on unmount', () => {
        const form = makeForm()
        const sortableInstance = { destroy: vi.fn() }
        createSortable.mockReturnValueOnce(sortableInstance)
        const component = mount(form)

        component.unmount(form)

        expect(sortableInstance.destroy).toHaveBeenCalled()
    })
})

describe('FieldForm unusual markup', () => {
    it('ignores a change to a validator option', () => {
        const form = makeForm()
        mount(form)
        const [, second] = form.querySelectorAll('[data-validator-row]')
        second.querySelector('[data-validator-toggle]').checked = false
        const option = second.querySelector('input[name^="validators"]')

        option.dispatchEvent(new Event('change', { bubbles: true }))

        expect(second.querySelector('[data-validator-options]').hidden).toBe(false)
    })

    it('ignores a toggle that is not inside a validator row', () => {
        const form = makeForm()
        mount(form)
        const rows = form.querySelector('[data-validator-rows]')
        rows.insertAdjacentHTML('beforeend', '<input type="checkbox" data-validator-toggle>')

        expect(() => rows.lastElementChild.dispatchEvent(new Event('change', { bubbles: true }))).not.toThrow()
    })

    it('ignores a toggle around the rows rather than in them', () => {
        const form = makeForm()
        const wrapper = document.createElement('label')
        wrapper.setAttribute('data-validator-toggle', '')
        const rows = form.querySelector('[data-validator-rows]')
        rows.replaceWith(wrapper)
        wrapper.appendChild(rows)
        mount(form)
        const [first] = rows.querySelectorAll('[data-validator-row]')

        first.querySelector('input[name^="validators"]').dispatchEvent(new Event('change', { bubbles: true }))

        expect(first.querySelector('[data-validator-options]').hidden).toBe(true)
    })

    it('leaves a validator row without options alone', () => {
        const form = makeForm()
        form.querySelector('[data-validator-rows]').insertAdjacentHTML(
            'beforeend',
            '<div data-validator-row><input type="checkbox" data-validator-toggle></div>',
        )

        expect(() => mount(form)).not.toThrow()
    })

    it('renumbers only named controls', () => {
        const form = makeForm()
        form.querySelector('[data-validator-row]').insertAdjacentHTML('beforeend', '<input data-unnamed>')
        mount(form)
        const rows = form.querySelector('[data-validator-rows]')
        rows.appendChild(rows.firstElementChild)

        createSortable.mock.calls[0][1].onSort()

        expect(rows.querySelector('[data-unnamed]').hasAttribute('name')).toBe(false)
    })
})
