import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { request, TIMEOUTS } from '../utils/HttpClient.js'
import { withToken } from '../utils/HandlerToken.js'

/**
 * Field add/edit modal controller.
 *
 * Mounted on `<p-modal data-field-modal>` once on the fields page. On
 * click of any `[data-field-modal-trigger]` (the Add Field dropdown
 * items, the per-field Edit pencil) the link's href is fetched with
 * `X-Requested-With: XMLHttpRequest`; the add-on's handler responds with
 * the modal partial (form + slot-tagged title and actions). The HTML is
 * injected into the modal's light DOM and `<p-modal>`'s shadow slots
 * pull title / content / actions into place.
 *
 * The form's submit is intercepted: the form's fields, with the host's
 * handler token, POST back to the form's action over fetch.
 *
 *   - 200 JSON `{ ok: true, redirect }`: the modal closes and the page
 *     goes to `redirect`, or reloads when there is none.
 *   - 422 with an HTML body: the save had field errors, and the body is
 *     the re-rendered modal, which replaces the modal's content.
 *   - Anything else: the submit button is re-enabled for another try.
 *
 * The submit button sits in the modal's actions slot, outside the form
 * (`form="field-modal-form"`), so it is looked up in the modal.
 *
 * The form is rendered into the light DOM, so MutationObserver-driven
 * components inside it (FieldForm for the type-aware toggling,
 * ConditionalEditor, DataIdentifier, dropdowns) auto-mount as soon as
 * the HTML is injected.
 */
export default class FieldFormModal extends BaseComponent {
    _getSelector() {
        return 'data-field-modal'
    }

    _init(modal) {
        const onTriggerClick = (event) => {
            const trigger = event.target.closest('[data-field-modal-trigger]')
            if (!trigger || !trigger.href) return
            event.preventDefault()
            this._open(modal, trigger.href)
        }
        document.addEventListener('click', onTriggerClick)

        const onSubmit = (event) => {
            const form = event.target.closest('[data-field-modal-form]')
            if (!form || !modal.contains(form)) return
            event.preventDefault()
            this._submit(modal, form)
        }
        modal.addEventListener('submit', onSubmit)

        return {
            cleanup: () => {
                document.removeEventListener('click', onTriggerClick)
                modal.removeEventListener('submit', onSubmit)
            },
        }
    }

    async _open(modal, url) {
        modal.innerHTML = '<p slot="title">Loading…</p>'
        if (typeof modal.open === 'function') modal.open()
        try {
            const response = await request(url, {
                credentials: 'same-origin',
                timeoutMs:   TIMEOUTS.LIST,
            })
            const html = await response.text()
            modal.innerHTML = html
        } catch (_error) {
            modal.innerHTML = '<p slot="title">Couldn’t load that field</p><p>Try refreshing the page.</p>'
        }
    }

    async _submit(modal, form) {
        const data = withToken(new FormData(form))
        const submitBtn = modal.querySelector('button[type="submit"]')
        if (submitBtn) submitBtn.disabled = true
        try {
            const response = await request(form.action, {
                method:      'POST',
                body:        data,
                headers:     { 'Accept': 'application/json' },
                credentials: 'same-origin',
                timeoutMs:   TIMEOUTS.MUTATE,
            })
            const payload = await response.json()
            if (payload.ok) {
                if (typeof modal.close === 'function') modal.close()
                if (payload.redirect) {
                    window.location.assign(payload.redirect)
                } else {
                    window.location.reload()
                }
                return
            }
        } catch (error) {
            if (this._isFieldErrors(error)) {
                modal.innerHTML = error.body
                return
            }
        }
        if (submitBtn) submitBtn.disabled = false
    }

    /**
     * A 422 from the handler, whose body is the re-rendered modal.
     *
     * @param {Error} error what request() or the JSON parse threw
     * @returns {boolean}
     */
    _isFieldErrors(error) {
        return error.kind === 'http'
            && error.status === 422
            && typeof error.body === 'string'
    }
}
