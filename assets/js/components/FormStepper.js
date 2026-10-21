import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'

/**
 * FormStepper — wizard-style navigation for stepped forms.
 *
 * Registered against `[data-form-stepper]`. Each direct-descendant
 * `[data-form-step]` is a single step; the order in DOM is the order
 * presented to the user. Tabs (`[data-form-step-target=<key>]`) inside
 * the form provide direct navigation; `[data-form-step-prev]` and
 * `[data-form-step-next]` advance/retreat.
 *
 * Forward navigation runs the browser's native validation
 * (`reportValidity`) on the current step's inputs first. Tabs jumping
 * past the current furthest-reached step are disabled until the user
 * has visited that step — preventing skip-ahead while still allowing
 * "go back to fix" via tab clicks.
 */
export default class FormStepper extends BaseComponent {
    _getSelector() {
        return 'data-form-stepper'
    }

    _init(element) {
        const steps = Array.from(element.querySelectorAll('[data-form-step]'))
        const tabs  = Array.from(element.querySelectorAll('[data-form-step-target]'))
        if (steps.length === 0) return {}

        const state = {
            steps,
            tabs,
            currentIndex: 0,
            furthestIndex: 0,
        }

        const onClick = (event) => {
            const next = event.target.closest('[data-form-step-next]')
            const prev = event.target.closest('[data-form-step-prev]')
            const tab  = event.target.closest('[data-form-step-target]')

            if (next && element.contains(next)) {
                event.preventDefault()
                this._advance(element)
            } else if (prev && element.contains(prev)) {
                event.preventDefault()
                this._retreat(element)
            } else if (tab && element.contains(tab) && !tab.disabled) {
                event.preventDefault()
                this._goTo(element, tab.getAttribute('data-form-step-target'))
            }
        }
        element.addEventListener('click', onClick)

        return {
            ...state,
            cleanup: () => element.removeEventListener('click', onClick),
        }
    }

    _advance(form) {
        const state = this.elements.get(form)
        if (!this._validateStep(state.steps[state.currentIndex])) return
        if (state.currentIndex >= state.steps.length - 1) return
        this._show(form, state.currentIndex + 1)
    }

    _retreat(form) {
        const state = this.elements.get(form)
        if (!state || state.currentIndex <= 0) return
        this._show(form, state.currentIndex - 1)
    }

    _goTo(form, key) {
        const state = this.elements.get(form)
        const index = state.steps.findIndex((step) => step.getAttribute('data-form-step') === key)
        if (index < 0 || index > state.furthestIndex) return
        this._show(form, index)
    }

    _show(form, index) {
        const state = this.elements.get(form)

        state.steps.forEach((step, i) => {
            step.hidden = i !== index
            step.classList.toggle('is-active', i === index)
        })
        state.furthestIndex = Math.max(state.furthestIndex, index)
        state.tabs.forEach((tab, i) => {
            tab.classList.toggle('is-active', i === index)
            if (i <= state.furthestIndex) tab.disabled = false
        })
        state.currentIndex = index

        const focusTarget = state.steps[index].querySelector('input, select, textarea, button')
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus({ preventScroll: false })
        }
    }

    _validateStep(step) {
        const inputs = step.querySelectorAll('input, select, textarea')
        for (const input of inputs) {
            if (typeof input.checkValidity === 'function' && !input.checkValidity()) {
                input.reportValidity()
                return false
            }
        }
        return true
    }
}
