import { afterEach, describe, expect, it, vi } from 'vitest'
import TokenInsert from '../../../assets/js/components/TokenInsert.js'
import { mountedFixtureElement } from '../TestAsset/fixtures.js'

/** Document-level listeners a test added, removed after it so none outlives it. */
let listeners = []

afterEach(() => {
    listeners.forEach((listener) => document.removeEventListener('token:insert', listener))
    listeners = []
    document.body.innerHTML = ''
})

function mount(element) {
    const component = new TokenInsert({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

function listenForInserts() {
    const listener = vi.fn()
    document.addEventListener('token:insert', listener)
    listeners.push(listener)
    return listener
}

/** The subject field and its picker's menu as the Dropdown opens it, with the item for `token`. */
function subjectPicker(token = '{form:title}') {
    const field = mountedFixtureElement('components/token-picker-details', '.form__group').querySelector('input')
    const menu = mountedFixtureElement('components/token-picker-details', '.dropdown__menu')
    const item = menu.querySelector(`[data-token-insert="${token}"]`)
    mount(item)
    return { field, item }
}

function makeItem(token, target = null) {
    const item = document.createElement('a')
    item.href = '#'
    item.setAttribute('data-token-insert', token)
    if (target !== null) item.setAttribute('data-token-target', target)
    document.body.appendChild(item)
    return item
}

describe('TokenInsert into a field', () => {
    it('inserts the token at the caret of an input target', () => {
        const { field, item } = subjectPicker()
        field.setSelectionRange(3, 3)

        item.click()

        expect(field.value).toBe('New{form:title} submission')
    })

    it('replaces the selection of an input target', () => {
        const { field, item } = subjectPicker()
        field.setSelectionRange(4, 14)

        item.click()

        expect(field.value).toBe('New {form:title}')
    })

    it('puts the caret after the inserted token', () => {
        const { field, item } = subjectPicker('{field:email}')
        field.setSelectionRange(0, 0)

        item.click()

        expect([field.selectionStart, field.selectionEnd]).toEqual([13, 13])
    })

    it('focuses the target', () => {
        const { field, item } = subjectPicker()

        item.click()

        expect(document.activeElement).toBe(field)
    })

    it('inserts into a textarea target and lets an input event bubble from it', () => {
        const wrapper = document.createElement('div')
        wrapper.innerHTML = '<textarea id="body"></textarea>'
        document.body.appendChild(wrapper)
        const onInput = vi.fn()
        wrapper.addEventListener('input', onInput)
        mount(makeItem('{token}', '#body'))

        document.querySelector('[data-token-insert]').click()

        expect([wrapper.querySelector('textarea').value, onInput.mock.calls.length]).toEqual(['{token}', 1])
    })

    it('appends the token to a field with no caret to read', () => {
        const field = document.createElement('input')
        field.type = 'email'
        field.id = 'to'
        field.value = 'alice@example.com,'
        document.body.appendChild(field)
        const onInput = vi.fn()
        field.addEventListener('input', onInput)
        const item = makeItem('{field:email}', '#to')
        mount(item)

        item.click()

        expect([field.value, onInput.mock.calls.length]).toEqual(['alice@example.com,{field:email}', 1])
    })
})

describe('TokenInsert to an editor', () => {
    it('sends the Layout editor its token as a token:insert event', () => {
        const item = mountedFixtureElement('components/token-picker-layout', '[data-token-insert]')
        mount(item)
        const onInsert = listenForInserts()

        item.click()

        expect(onInsert.mock.calls.map(([event]) => event.detail)).toEqual([{ token: '{field:name}', target: 'codemirror' }])
    })

    it('sends a token:insert event for a target that is not a field', () => {
        const target = document.createElement('div')
        target.id = 'editor'
        document.body.appendChild(target)
        const item = makeItem('{token}', '#editor')
        mount(item)
        const onInsert = listenForInserts()

        item.click()

        expect(onInsert.mock.calls.map(([event]) => event.detail)).toEqual([{ token: '{token}', target: '#editor' }])
    })

    it('sends a token:insert event with an empty target when the item names none', () => {
        const item = makeItem('{token}')
        mount(item)
        const onInsert = listenForInserts()

        item.click()

        expect(onInsert.mock.calls.map(([event]) => event.detail)).toEqual([{ token: '{token}', target: '' }])
    })
})

describe('TokenInsert clicks', () => {
    it('does nothing for an item with an empty token', () => {
        const item = makeItem('', '#missing')
        mount(item)
        const onInsert = listenForInserts()

        item.click()

        expect(onInsert).not.toHaveBeenCalled()
    })

    it('keeps the item link from navigating', () => {
        const item = makeItem('{token}', '')
        mount(item)

        const event = new MouseEvent('click', { bubbles: true, cancelable: true })
        item.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(true)
    })

    it('stops responding to clicks once unmounted', () => {
        const item = makeItem('{token}', 'codemirror')
        const component = mount(item)
        const onInsert = listenForInserts()

        component.unmount(item)
        item.click()

        expect(onInsert).not.toHaveBeenCalled()
    })
})
