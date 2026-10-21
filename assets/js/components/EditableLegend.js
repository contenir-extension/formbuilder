import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { request, TIMEOUTS } from '../utils/HttpClient.js'
import { withToken } from '../utils/HandlerToken.js'

/**
 * Inline-rename for `[data-editable-legend]` spans.
 *
 * Markup:
 *   <span class="..." data-editable-legend
 *         data-update-url="…the add-on's section rename handler…">
 *     Personal Details
 *   </span>
 *   <button type="button" data-edit-legend>pencil</button>
 *
 * Component mounts on the legend span. Clicking any sibling
 * `[data-edit-legend]` button toggles the span to `contenteditable`,
 * focuses it, and selects its text. On Enter (without Shift) or blur
 * the new value POSTs to `data-update-url` as `legend=…` with the host's
 * handler token. The handler answers JSON `{ ok: true, legend }`: on ok
 * the new value stays; on a not-ok answer, non-2xx or network error the
 * visible text reverts to the previous value. Escape cancels without
 * saving.
 */
export default class EditableLegend extends BaseComponent {
    _getSelector() {
        return 'data-editable-legend'
    }

    _init(span) {
        const trigger = this._findTrigger(span)
        if (!trigger) return {}

        const placeholder = 'Untitled'
        const isEmpty = () => span.getAttribute('data-empty') === 'true'

        /**
         * The persisted value, not the visible placeholder. When the
         * span's `data-empty` attribute is set, the visible text is
         * the placeholder ("Untitled"); we want to start the user
         * editing from a blank field instead of having them delete
         * the placeholder first.
         */
        let storedValue = isEmpty() ? '' : span.textContent

        const enter = () => {
            storedValue = isEmpty() ? '' : span.textContent
            span.setAttribute('contenteditable', 'true')
            span.classList.add('is-editing')
            if (isEmpty()) {
                span.textContent = ''
            }
            span.focus()
            const range = document.createRange()
            range.selectNodeContents(span)
            const sel = window.getSelection()
            if (sel) {
                sel.removeAllRanges()
                sel.addRange(range)
            }
        }

        const renderEmpty = () => {
            span.textContent = placeholder
            span.setAttribute('data-empty', 'true')
        }
        const renderValue = (value) => {
            span.textContent = value
            span.removeAttribute('data-empty')
        }

        const leave = (commit) => {
            span.removeAttribute('contenteditable')
            span.classList.remove('is-editing')
            if (!commit) {
                if (storedValue === '') renderEmpty()
                else renderValue(storedValue)
                return
            }
            const next = span.textContent.trim()
            if (next === storedValue.trim()) {
                if (next === '') renderEmpty()
                return
            }
            this._save(span, next).then(() => {
                storedValue = next
                if (next === '') renderEmpty()
                else renderValue(next)
            }).catch(() => {
                if (storedValue === '') renderEmpty()
                else renderValue(storedValue)
            })
        }

        const onClick = (event) => {
            event.preventDefault()
            enter()
        }
        const onKey = (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault()
                span.blur()
            } else if (event.key === 'Escape') {
                event.preventDefault()
                leave(false)
            }
        }
        const onBlur = () => leave(true)

        trigger.addEventListener('click', onClick)
        span.addEventListener('keydown', onKey)
        span.addEventListener('blur', onBlur)

        return {
            cleanup: () => {
                trigger.removeEventListener('click', onClick)
                span.removeEventListener('keydown', onKey)
                span.removeEventListener('blur', onBlur)
            },
        }
    }

    /** The sibling pencil button, anywhere in the same parent. */
    _findTrigger(span) {
        return span.parentElement?.querySelector('[data-edit-legend]') ?? null
    }

    async _save(span, value) {
        const url = span.getAttribute('data-update-url')
        if (!url) return
        const body = new FormData()
        body.append('legend', value)
        const response = await request(url, {
            method:      'POST',
            body:        withToken(body),
            headers:     { 'Accept': 'application/json' },
            credentials: 'same-origin',
            timeoutMs:   TIMEOUTS.MUTATE,
        })
        const payload = await response.json()
        if (!payload.ok) throw new Error('The legend was not saved')
    }
}
