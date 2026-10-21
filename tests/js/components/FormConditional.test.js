import { afterEach, describe, expect, it } from 'vitest'
import FormConditional from '../../../assets/js/components/FormConditional.js'

afterEach(() => {
    document.body.innerHTML = ''
})

function makeForm(rule, extraFields = '') {
    const form = document.createElement('form')
    form.innerHTML = `
        <input name="status" value="draft">
        <input type="checkbox" name="flag" value="1">
        ${extraFields}
        <div data-form-conditional='${JSON.stringify(rule)}'>
            <input name="conditional_field">
        </div>
    `
    document.body.appendChild(form)
    return form
}

function mount(element) {
    const component = new FormConditional({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

function change(form, name, value) {
    const el = form.querySelector(`[name="${name}"]`)
    el.value = value
    el.dispatchEvent(new Event('change', { bubbles: true }))
}

describe('FormConditional equals', () => {
    it('hides the wrapper when the rule does not match on mount', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'equals', value: 'published' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(true)
    })

    it('shows the wrapper once the field changes to match', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'equals', value: 'published' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        change(form, 'status', 'published')

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('disables the inner controls while hidden', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'equals', value: 'published' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[name="conditional_field"]').disabled).toBe(true)
    })

    it('re-enables the inner controls once shown', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'equals', value: 'published' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        change(form, 'status', 'published')

        expect(form.querySelector('[name="conditional_field"]').disabled).toBe(false)
    })
})

describe('FormConditional combinators', () => {
    it('shows when any condition matches', () => {
        const form = makeForm({
            show_when: { any: [
                { field: 'status', op: 'equals', value: 'published' },
                { field: 'status', op: 'equals', value: 'draft' },
            ] },
        })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('requires every condition to match for "all"', () => {
        const form = makeForm({
            show_when: { all: [
                { field: 'status', op: 'equals', value: 'draft' },
                { field: 'flag', op: 'equals', value: '1' },
            ] },
        })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(true)
    })
})

describe('FormConditional operators', () => {
    it('matches not_equals', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'not_equals', value: 'published' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('matches is_empty and is_not_empty', () => {
        const form = makeForm({ show_when: { all: [{ field: 'missing', op: 'is_empty' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('matches contains', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'contains', value: 'raf' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('treats an unknown operator as never matching', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'mystery', value: 'draft' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(true)
    })
})

describe('FormConditional checkboxes', () => {
    it('reads a checked checkbox value into the field values', () => {
        const form = makeForm({ show_when: { all: [{ field: 'flag', op: 'equals', value: '1' }] } })
        form.querySelector('[name="flag"]').checked = true
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('treats an unchecked checkbox as absent', () => {
        const form = makeForm({ show_when: { all: [{ field: 'flag', op: 'is_empty' }] } })
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })
})

describe('FormConditional edge cases', () => {
    it('shows by default when there is no show_when rule', () => {
        const form = makeForm({})
        mount(form.querySelector('[data-form-conditional]'))

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(false)
    })

    it('does nothing when the JSON attribute is invalid', () => {
        const element = document.createElement('div')
        element.setAttribute('data-form-conditional', 'not json')
        const form = document.createElement('form')
        form.appendChild(element)
        document.body.appendChild(form)

        expect(() => mount(element)).not.toThrow()
    })

    it('does nothing when there is no enclosing form', () => {
        const element = document.createElement('div')
        element.setAttribute('data-form-conditional', JSON.stringify({ show_when: { all: [] } }))
        document.body.appendChild(element)

        expect(() => mount(element)).not.toThrow()
    })

    it('stops reacting to changes once unmounted', () => {
        const form = makeForm({ show_when: { all: [{ field: 'status', op: 'equals', value: 'published' }] } })
        const component = mount(form.querySelector('[data-form-conditional]'))

        component.unmount(form.querySelector('[data-form-conditional]'))
        change(form, 'status', 'published')

        expect(form.querySelector('[data-form-conditional]').hidden).toBe(true)
    })
})

/**
 * The wrapper of a form made with makeForm, marked hidden before mounting,
 * so a test can tell whether the component evaluated its rule at all.
 */
function hiddenWrapper(form) {
    const wrapper = form.querySelector('[data-form-conditional]')
    wrapper.hidden = true

    return wrapper
}

function visibility(rule, extraFields = '') {
    const form = makeForm(rule, extraFields)
    mount(form.querySelector('[data-form-conditional]'))

    return form.querySelector('[data-form-conditional]').hidden ? 'hidden' : 'shown'
}

describe('FormConditional unreadable rules', () => {
    it.each([
        ['an empty attribute', ''],
        ['a JSON null', 'null'],
        ['a JSON false', 'false'],
    ])('leaves the wrapper as rendered when the rule is %s', (_label, attribute) => {
        const form = makeForm({})
        const wrapper = hiddenWrapper(form)
        wrapper.setAttribute('data-form-conditional', attribute)

        mount(wrapper)

        expect(wrapper.hidden).toBe(true)
    })

    it('leaves the wrapper as rendered when mounted on an element without the attribute', () => {
        const form = makeForm({})
        const wrapper = hiddenWrapper(form)
        wrapper.removeAttribute('data-form-conditional')

        mount(wrapper)

        expect(wrapper.hidden).toBe(true)
    })
})

describe('FormConditional rule shapes', () => {
    it.each([
        ['a rule that is not an object', 'published'],
        ['show_when that is not an object', { show_when: 'published' }],
        ['show_when with no conditions', { show_when: {} }],
        ['conditions that are not a list', { show_when: { all: 'published' } }],
        ['an empty list of conditions', { show_when: { all: [] } }],
    ])('shows the wrapper for %s', (_label, rule) => {
        const form = makeForm({})
        const wrapper = hiddenWrapper(form)
        wrapper.setAttribute('data-form-conditional', JSON.stringify(rule))

        mount(wrapper)

        expect(wrapper.hidden).toBe(false)
    })

    it.each([
        ['a null condition', null],
        ['a condition that is not an object', 'status'],
        ['a condition with no field', { op: 'is_empty' }],
        ['a condition with no operator', { field: 'missing' }],
    ])('never matches %s', (_label, condition) => {
        expect(visibility({ show_when: { all: [condition] } })).toBe('hidden')
    })
})

describe('FormConditional emptiness', () => {
    it('shows for is_not_empty when the field has a value', () => {
        expect(visibility({ show_when: { all: [{ field: 'status', op: 'is_not_empty' }] } })).toBe('shown')
    })

    it('hides for is_empty when the field has a value', () => {
        expect(visibility({ show_when: { all: [{ field: 'status', op: 'is_empty' }] } })).toBe('hidden')
    })

    it('treats a multi-select with nothing selected as empty', () => {
        const fields = '<select name="colours" multiple><option value="red">Red</option></select>'

        expect(visibility({ show_when: { all: [{ field: 'colours', op: 'is_empty' }] } }, fields)).toBe('shown')
    })

    it('never matches contains with an empty value', () => {
        expect(visibility({ show_when: { all: [{ field: 'status', op: 'contains', value: '' }] } })).toBe('hidden')
    })

    it('compares a rule value that is not a scalar as empty', () => {
        expect(visibility({ show_when: { all: [{ field: 'missing', op: 'equals', value: { a: 1 } }] } })).toBe('shown')
    })
})

describe('FormConditional field values', () => {
    it('ignores controls without a name', () => {
        const fields = '<button type="button">Go</button><input value="published">'

        expect(visibility({ show_when: { all: [{ field: 'status', op: 'equals', value: 'draft' }] } }, fields)).toBe('shown')
    })

    it('collects every checked box of a checkbox group', () => {
        const fields = `
            <input type="checkbox" name="tags[]" value="news" checked>
            <input type="checkbox" name="tags[]" value="events" checked>
        `

        expect(visibility({ show_when: { all: [{ field: 'tags', op: 'equals', value: 'events' }] } }, fields)).toBe('shown')
    })

    it('matches contains against any checked box of a group', () => {
        const fields = '<input type="checkbox" name="tags[]" value="events" checked>'

        expect(visibility({ show_when: { all: [{ field: 'tags', op: 'contains', value: 'vent' }] } }, fields)).toBe('shown')
    })

    it('reads the checked radio of a group', () => {
        const fields = `
            <input type="radio" name="size" value="s">
            <input type="radio" name="size" value="m" checked>
        `

        expect(visibility({ show_when: { all: [{ field: 'size', op: 'equals', value: 'm' }] } }, fields)).toBe('shown')
    })

    it('reads every selected option of a multi-select', () => {
        const fields = `
            <select name="colours" multiple>
                <option value="red" selected>Red</option>
                <option value="blue" selected>Blue</option>
            </select>
        `

        expect(visibility({ show_when: { all: [{ field: 'colours', op: 'equals', value: 'blue' }] } }, fields)).toBe('shown')
    })

    it('reads the selected option of a single select', () => {
        const fields = '<select name="kind"><option value="a">A</option><option value="b" selected>B</option></select>'

        expect(visibility({ show_when: { all: [{ field: 'kind', op: 'equals', value: 'b' }] } }, fields)).toBe('shown')
    })
})
