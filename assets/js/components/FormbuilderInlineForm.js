import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { request, TIMEOUTS } from '../utils/HttpClient.js'
import { withToken } from '../utils/HandlerToken.js'

/**
 * Inline-form XHR handler for the form builder's fields screen.
 *
 * Mounted on every `<form data-formbuilder-inline>`. Each submit POSTs the
 * form's own fields, plus the host's handler token, to the form's action on
 * the add-on's handlers. Which kind of form it is comes from its markup, not
 * from the response:
 *
 *   - Delete forms (the per-row trash icon) carry
 *     `data-formbuilder-inline-target`, a CSS selector for the node they
 *     delete. The handler answers JSON `{ ok: true }`, and on ok we remove
 *     that node.
 *   - Insert forms (Add section / Add group / Add row, whose action has
 *     `position=N`) carry no target. The handler answers 200 with an HTML
 *     fragment — the new section, group or row markup — which we insert
 *     before the form, so the new entity appears where the gap-button sat,
 *     then bump `position` for the next insert.
 *
 * Any failure (non-2xx, network, timeout) leaves the page as it was; the
 * submit button is re-enabled either way.
 *
 * After inserting a row we dispatch `formbuilder:row-added` so FieldsEditor
 * can attach a SortableJS instance to the new row's `[data-formbuilder-fields]`
 * container — without that, fields can't be dragged in or out of the new
 * row until a page reload. New groups and sections come with their own
 * `[data-formbuilder-rows]` and FieldsEditor's selector mounts them through
 * the framework's MutationObserver automatically.
 */
export default class FormbuilderInlineForm extends BaseComponent {
    _getSelector() {
        return 'data-formbuilder-inline'
    }

    _init(form) {
        const onSubmit = (event) => {
            event.preventDefault()
            this._submit(form)
        }
        form.addEventListener('submit', onSubmit)

        return {
            cleanup: () => form.removeEventListener('submit', onSubmit),
        }
    }

    async _submit(form) {
        const submitBtn = form.querySelector('button[type="submit"]')
        if (submitBtn) submitBtn.disabled = true

        try {
            if (form.hasAttribute('data-formbuilder-inline-target')) {
                await this._delete(form)
            } else {
                await this._insert(form)
            }
        } catch (_error) {
            /** Swallowed: the page stays as it was and the button comes back below. */
        } finally {
            /**
             * Re-enable on success too: the trigger form is reusable for
             * a second insert. Without this, after one row is added the
             * "Add row" button stays disabled and the user can't add more.
             */
            if (submitBtn && form.isConnected) submitBtn.disabled = false
        }
    }

    async _insert(form) {
        const response = await this._post(form, 'text/html')
        const html = await response.text()
        this._insertBeforeForm(form, html)
        this._bumpPosition(form)
    }

    async _delete(form) {
        const response = await this._post(form, 'application/json')
        const payload = await response.json()
        if (payload.ok) this._removeTarget(form)
    }

    _post(form, accept) {
        return request(form.action, {
            method:      'POST',
            body:        withToken(new FormData(form)),
            headers:     { 'Accept': accept },
            credentials: 'same-origin',
            timeoutMs:   TIMEOUTS.MUTATE,
        })
    }

    _insertBeforeForm(form, html) {
        const template = document.createElement('template')
        template.innerHTML = html.trim()
        const fragment = template.content
        const inserted = Array.from(fragment.children)
        form.parentNode.insertBefore(fragment, form)

        for (const node of inserted) {
            if (node.matches('[data-formbuilder-row]')) {
                form.dispatchEvent(new CustomEvent('formbuilder:row-added', {
                    bubbles: true,
                    detail:  { row: node },
                }))
            }
        }
    }

    /**
     * Bump the `position` query param on the trigger form's action URL.
     * Insert URLs encode "where to drop the new entity" via `position=N`
     * — that value is computed server-side at page render time, so a
     * second click without bumping would insert at the same index instead
     * of after the entity we just added.
     */
    _bumpPosition(form) {
        try {
            const url = new URL(form.action, window.location.href)
            const params = url.searchParams
            if (!params.has('position')) return
            const next = parseInt(params.get('position') || '0', 10) + 1
            params.set('position', String(next))
            form.action = url.toString()
        } catch {
            /** A malformed action URL: leave the form action alone. */
        }
    }

    _removeTarget(form) {
        const selector = form.getAttribute('data-formbuilder-inline-target')
        if (!selector) return
        const node = document.querySelector(selector)
        if (node) node.remove()
    }
}
