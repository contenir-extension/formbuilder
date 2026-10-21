import { afterEach, describe, expect, it } from 'vitest'
import DataIdentifier from '../../../assets/js/components/DataIdentifier.js'

afterEach(() => {
    document.body.innerHTML = ''
})

function makeForm({ slugValue = '', sources = '' } = {}) {
    const form = document.createElement('form')
    form.innerHTML = `
        <input name="title">
        <input name="subtitle">
        <input name="slug" data-identifier="${sources}" value="${slugValue}">
    `
    document.body.appendChild(form)
    return form
}

function mount(element) {
    const component = new DataIdentifier({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

describe('DataIdentifier', () => {
    it('mirrors the source field into the identifier as the user types', () => {
        const form = makeForm({ sources: 'title' })
        mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')

        title.dispatchEvent(new Event('focus'))
        title.value = 'Hello World'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('hello-world')
    })

    it('joins multiple source fields with a dash', () => {
        const form = makeForm({ sources: 'title subtitle' })
        mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')
        const subtitle = form.querySelector('[name="subtitle"]')
        subtitle.value = 'Part Two'

        title.dispatchEvent(new Event('focus'))
        title.value = 'Part One'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('part-one-part-two')
    })

    it('strips non-alphanumerics and collapses repeated dashes', () => {
        const form = makeForm({ sources: 'title' })
        mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')

        title.dispatchEvent(new Event('focus'))
        title.value = 'A & B -- C!!'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('a-b-c')
    })

    it('stops mirroring after blur', () => {
        const form = makeForm({ sources: 'title' })
        mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')
        title.dispatchEvent(new Event('focus'))
        title.value = 'first'
        title.dispatchEvent(new Event('keyup'))
        title.dispatchEvent(new Event('blur'))

        title.value = 'second'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('first')
    })

    it('does not mirror while the identifier already has a prefilled value', () => {
        const form = makeForm({ sources: 'title', slugValue: 'existing-slug' })
        mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')

        title.dispatchEvent(new Event('focus'))
        title.value = 'New Title'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('existing-slug')
    })

    it('ignores source names that do not resolve to a field', () => {
        const form = makeForm({ sources: 'title missing' })

        expect(() => mount(form.querySelector('[data-identifier]'))).not.toThrow()
    })

    it('detaches listeners on unmount', () => {
        const form = makeForm({ sources: 'title' })
        const component = mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')
        title.dispatchEvent(new Event('focus'))

        component.unmount(form.querySelector('[data-identifier]'))
        title.value = 'ignored'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('')
    })

    it('mirrors nothing when the identifier names no source fields', () => {
        const form = makeForm({ sources: '' })

        expect(() => mount(form.querySelector('[data-identifier]'))).not.toThrow()
    })

    it('leaves an empty source out of the joined identifier', () => {
        const form = makeForm({ sources: 'title subtitle' })
        mount(form.querySelector('[data-identifier]'))
        const title = form.querySelector('[name="title"]')

        title.dispatchEvent(new Event('focus'))
        title.value = 'Only Title'
        title.dispatchEvent(new Event('keyup'))

        expect(form.querySelector('[data-identifier]').value).toBe('only-title')
    })
})
