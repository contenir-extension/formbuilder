import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'

const SLUG_PATTERN = /[^A-Za-z0-9_-]+/g
const REPEAT_DASH = /-{2,}/g

/**
 * Auto-slug an identifier input from one or more source fields.
 *
 * The element declares its source fields via a space-separated list of
 * input names:
 *
 *   <input name="slug" data-identifier="title subtitle" …>
 *
 * Behaviour preserved from the legacy factory: while a source field has
 * focus, every keyup re-derives the slug (lowercased, dashes for spaces,
 * non-alphanumerics stripped, joined with `-`) and writes it into the
 * identifier input. On blur, the slug is finalised and the keyup/blur
 * listeners detach. If the identifier was already filled when focus
 * landed (server-side prefill or user override), mirroring is skipped
 * for that focus session — but the moment a mirror succeeds, the
 * `prefilled` guard goes false so subsequent edits keep mirroring.
 */
export default class DataIdentifier extends BaseComponent {
    _getSelector() {
        return 'data-identifier'
    }

    _init(element) {
        const state = {
            element,
            prefilled: element.value !== '',
            targets: [],
            focusListeners: new Map(),
        }

        const sourceNames = (element.dataset.identifier || '').split(' ').filter(Boolean)
        sourceNames.forEach((name) => {
            const target = document.querySelector(`[name="${name}"]`)
            if (!target) return

            state.targets.push(target)

            const onFocus = () => this._beginMirroring(state, target)
            target.addEventListener('focus', onFocus)
            state.focusListeners.set(target, onFocus)
        })

        return {
            cleanup: () => {
                state.focusListeners.forEach((listener, target) => {
                    target.removeEventListener('focus', listener)
                })
                state.targets.forEach((target) => {
                    target.removeEventListener('keyup', state.boundMirror)
                    target.removeEventListener('blur', state.boundComplete)
                })
            },
        }
    }

    _beginMirroring(state, target) {
        if (state.prefilled && state.element.value !== '') {
            return
        }
        state.boundMirror = () => this._mirror(state)
        state.boundComplete = () => this._complete(state)
        target.addEventListener('keyup', state.boundMirror)
        target.addEventListener('blur', state.boundComplete)
    }

    _mirror(state) {
        const values = []
        state.prefilled = false
        state.targets.forEach((target) => {
            const slug = target.value
                .toString()
                .trim()
                .replace(/ +/g, '-')
                .replace(SLUG_PATTERN, '')
                .replace(REPEAT_DASH, '-')
                .toLowerCase()
            if (slug !== '') values.push(slug)
        })
        state.element.value = values.join('-')
    }

    _complete(state) {
        this._mirror(state)
        state.targets.forEach((target) => {
            target.removeEventListener('keyup', state.boundMirror)
            target.removeEventListener('blur', state.boundComplete)
        })
    }
}
