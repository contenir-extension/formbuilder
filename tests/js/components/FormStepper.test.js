import { afterEach, describe, expect, it } from 'vitest'
import FormStepper from '../../../assets/js/components/FormStepper.js'
import { mountedFixtureElement } from '../TestAsset/fixtures.js'

afterEach(() => {
    document.body.innerHTML = ''
})

/**
 * The preview of a stepped form as the form builder renders it: three
 * steps (about, details, review), the first holding a required name.
 */
function makeStepper() {
    return mountedFixtureElement('form-stepper-preview', 'form[data-form-stepper]')
}

function mount(element) {
    const component = new FormStepper({ eventBus: null, logger: null, router: null })
    component.mount(element)
    return component
}

function click(el) {
    el.dispatchEvent(new MouseEvent('click', { bubbles: true }))
}

function step(element, key) {
    return element.querySelector(`[data-form-step="${key}"]`)
}

function fillTheFirstStep(element) {
    element.querySelector('[name="name"]').value = 'Ada'
}

describe('FormStepper navigation', () => {
    it('advances to the next step when the current step is valid', () => {
        const element = makeStepper()
        mount(element)
        fillTheFirstStep(element)

        click(step(element, 'about').querySelector('[data-form-step-next]'))

        expect([step(element, 'details').hidden, step(element, 'about').hidden]).toEqual([false, true])
    })

    it('blocks advancing when a required input on the current step is invalid', () => {
        const element = makeStepper()
        mount(element)

        click(step(element, 'about').querySelector('[data-form-step-next]'))

        expect(step(element, 'about').hidden).toBe(false)
    })

    it('retreats to the previous step', () => {
        const element = makeStepper()
        mount(element)
        fillTheFirstStep(element)
        click(step(element, 'about').querySelector('[data-form-step-next]'))

        click(step(element, 'details').querySelector('[data-form-step-prev]'))

        expect([step(element, 'about').hidden, step(element, 'details').hidden]).toEqual([false, true])
    })

    it('does not retreat past the first step', () => {
        const element = makeStepper()
        mount(element)

        click(element.querySelector('[data-form-step-prev]'))

        expect(step(element, 'about').hidden).toBe(false)
    })

    it('enables a tab once its step has been reached', () => {
        const element = makeStepper()
        mount(element)
        fillTheFirstStep(element)

        click(step(element, 'about').querySelector('[data-form-step-next]'))

        expect([
            element.querySelector('[data-form-step-target="details"]').disabled,
            element.querySelector('[data-form-step-target="review"]').disabled,
        ]).toEqual([false, true])
    })

    it('lets a tab jump back to an already-visited step', () => {
        const element = makeStepper()
        mount(element)
        fillTheFirstStep(element)
        click(step(element, 'about').querySelector('[data-form-step-next]'))

        click(element.querySelector('[data-form-step-target="about"]'))

        expect(step(element, 'about').hidden).toBe(false)
    })

    it('ignores a disabled tab click for a step not yet reached', () => {
        const element = makeStepper()
        mount(element)

        click(element.querySelector('[data-form-step-target="review"]'))

        expect(step(element, 'about').hidden).toBe(false)
    })

    it('does nothing when there are no steps', () => {
        const element = makeStepper()
        element.querySelectorAll('[data-form-step]').forEach((section) => section.remove())

        expect(() => mount(element)).not.toThrow()
    })

    it('stops responding to clicks once unmounted', () => {
        const element = makeStepper()
        const component = mount(element)
        fillTheFirstStep(element)

        component.unmount(element)
        click(step(element, 'about').querySelector('[data-form-step-next]'))

        expect(step(element, 'about').hidden).toBe(false)
    })
})

describe('FormStepper focus', () => {
    it('focuses the first control of the step it shows', () => {
        const element = makeStepper()
        mount(element)
        fillTheFirstStep(element)

        click(step(element, 'about').querySelector('[data-form-step-next]'))

        expect(document.activeElement).toBe(element.querySelector('[name="message"]'))
    })

    it('shows a step that has no control to focus', () => {
        const element = makeStepper()
        step(element, 'details').innerHTML = '<h2>Your enquiry</h2>'
        mount(element)
        fillTheFirstStep(element)

        click(step(element, 'about').querySelector('[data-form-step-next]'))

        expect(step(element, 'details').hidden).toBe(false)
    })
})

describe('FormStepper bounds', () => {
    it('stays on the last step when asked to advance past it', () => {
        const element = makeStepper()
        const extraNext = document.createElement('button')
        extraNext.type = 'button'
        extraNext.setAttribute('data-form-step-next', '')
        step(element, 'review').appendChild(extraNext)
        mount(element)
        fillTheFirstStep(element)
        click(step(element, 'about').querySelector('[data-form-step-next]'))
        click(step(element, 'details').querySelector('[data-form-step-next]'))

        click(extraNext)

        expect(step(element, 'review').hidden).toBe(false)
    })

    it('ignores a tab whose step does not exist', () => {
        const element = makeStepper()
        const stray = document.createElement('button')
        stray.type = 'button'
        stray.setAttribute('data-form-step-target', 'missing')
        element.querySelector('.formbuilder__steps').appendChild(stray)
        mount(element)

        click(stray)

        expect(step(element, 'about').hidden).toBe(false)
    })

    it('does not jump ahead to a step not yet reached even when its tab is enabled', () => {
        const element = makeStepper()
        const review = element.querySelector('[data-form-step-target="review"]')
        review.disabled = false
        mount(element)

        click(review)

        expect([step(element, 'about').hidden, step(element, 'review').hidden]).toEqual([false, true])
    })
})
