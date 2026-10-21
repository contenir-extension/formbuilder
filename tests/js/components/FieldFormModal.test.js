import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { HttpError, request } from '../../../assets/js/utils/HttpClient.js'
import FieldFormModal from '../../../assets/js/components/FieldFormModal.js'
import { addHandlerToken, TOKEN_NAME, TOKEN_VALUE } from '../TestAsset/token.js'

vi.mock('../../../assets/js/utils/HttpClient.js', async (importOriginal) => ({
    HttpError: (await importOriginal()).HttpError,
    request: vi.fn(),
    TIMEOUTS: { LIST: 15000, MUTATE: 30000 },
}))

/**
 * The modal listens for trigger clicks on the document, which outlives each
 * test's markup, so every component a test mounts is unmounted after it.
 */
const mounted = []

afterEach(() => {
    mounted.splice(0).forEach(([component, modal]) => component.unmount(modal))
    document.body.innerHTML = ''
    vi.clearAllMocks()
})

function makeModal() {
    const modal = document.createElement('div')
    modal.dataset.fieldModal = ''
    document.body.appendChild(modal)
    return modal
}

function makeTrigger(href = '/admin/extension/formbuilder/field-add?form=501&type=text') {
    const trigger = document.createElement('a')
    trigger.href = href
    trigger.setAttribute('data-field-modal-trigger', '')
    document.body.appendChild(trigger)
    return trigger
}

function mount(modal) {
    const component = new FieldFormModal({ eventBus: null, logger: null, router: null })
    component.mount(modal)
    mounted.push([component, modal])
    return component
}

function textResponse(body) {
    return { text: () => Promise.resolve(body) }
}

function jsonResponse(payload) {
    return { json: () => Promise.resolve(payload) }
}

describe('FieldFormModal opening', () => {
    it('fetches the trigger href and injects the returned HTML', async () => {
        const modal = makeModal()
        const trigger = makeTrigger('/admin/extension/formbuilder/field-add')
        mount(modal)
        request.mockResolvedValue(textResponse('<form data-field-modal-form></form>'))

        trigger.click()
        await vi.waitFor(() => expect(modal.innerHTML).toContain('data-field-modal-form'))

        expect(request).toHaveBeenCalledWith(trigger.href, expect.objectContaining({
            credentials: 'same-origin',
        }))
    })

    it('opens a modal that can open itself', () => {
        const modal = makeModal()
        modal.open = vi.fn()
        const trigger = makeTrigger()
        mount(modal)
        request.mockReturnValue(new Promise(() => {}))

        trigger.click()

        expect(modal.open).toHaveBeenCalled()
    })

    it('opens from a click inside a trigger', () => {
        const modal = makeModal()
        const trigger = makeTrigger()
        trigger.innerHTML = '<span>Edit</span>'
        mount(modal)
        request.mockReturnValue(new Promise(() => {}))

        trigger.querySelector('span').click()

        expect(request).toHaveBeenCalledWith(trigger.href, expect.anything())
    })

    it('shows a loading placeholder immediately', () => {
        const modal = makeModal()
        const trigger = makeTrigger()
        mount(modal)
        request.mockReturnValue(new Promise(() => {}))

        trigger.click()

        expect(modal.innerHTML).toContain('Loading')
    })

    it('shows a fallback message when the fetch fails', async () => {
        const modal = makeModal()
        const trigger = makeTrigger()
        mount(modal)
        request.mockRejectedValue(new Error('network down'))

        trigger.click()
        await vi.waitFor(() => expect(modal.innerHTML).toContain('Couldn'))
    })

    it('ignores triggers without an href', () => {
        const modal = makeModal()
        mount(modal)
        const trigger = document.createElement('button')
        trigger.setAttribute('data-field-modal-trigger', '')
        document.body.appendChild(trigger)

        trigger.click()

        expect(request).not.toHaveBeenCalled()
    })

    it('ignores clicks elsewhere on the page', () => {
        const modal = makeModal()
        mount(modal)
        const other = document.createElement('button')
        document.body.appendChild(other)

        other.click()

        expect(request).not.toHaveBeenCalled()
    })

    it('stops opening once unmounted', () => {
        const modal = makeModal()
        const trigger = makeTrigger()
        const component = mount(modal)
        component.unmount(modal)
        const noNavigation = (event) => event.preventDefault()
        document.addEventListener('click', noNavigation)

        trigger.click()
        document.removeEventListener('click', noNavigation)

        expect(request).not.toHaveBeenCalled()
    })
})

describe('FieldFormModal submitting', () => {
    const originalLocation = window.location

    /**
     * jsdom's window.location.reload/assign are non-configurable, so
     * vi.spyOn can't wrap them directly — replace the whole location
     * object for this block instead.
     */
    beforeEach(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: { ...originalLocation, reload: vi.fn(), assign: vi.fn() },
        })
    })

    afterEach(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: originalLocation })
    })

    /**
     * The modal partial as the field handler renders it: the form in the
     * content slot, and its Save button in the actions slot, outside the
     * form and tied to it by `form="field-modal-form"`.
     */
    function makeSubmittableForm(modal, action = '/admin/extension/formbuilder/field-save?form=501') {
        modal.innerHTML = `
            <form id="field-modal-form" action="${action}" method="post" data-field-modal-form>
                <input name="label" value="Email">
            </form>
            <div slot="actions"><button type="submit" form="field-modal-form">Save</button></div>
        `
        return modal.querySelector('form')
    }

    function submit(form) {
        form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }))
    }

    function saveButton(modal) {
        return modal.querySelector('button[type="submit"]')
    }

    it('posts the form data with the handler token, asking for JSON', async () => {
        addHandlerToken()
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        const [url, options] = request.mock.calls[0]
        expect([url, options.method, options.headers, options.body.get('label'), options.body.get(TOKEN_NAME)])
            .toEqual([form.action, 'POST', { 'Accept': 'application/json' }, 'Email', TOKEN_VALUE])
    })

    it('does not send the token twice when the form already carries it', async () => {
        addHandlerToken()
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="${TOKEN_NAME}" value="${TOKEN_VALUE}">`)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        expect(request.mock.calls[0][1].body.getAll(TOKEN_NAME)).toEqual([TOKEN_VALUE])
    })

    it('reloads the page on success without a redirect', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(window.location.reload).toHaveBeenCalled())
    })

    it('redirects instead of reloading when the payload has a redirect URL', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockResolvedValue(jsonResponse({ ok: true, redirect: '/admin/extension/formbuilder/fields?form=501' }))

        submit(form)

        await vi.waitFor(() => expect(window.location.assign).toHaveBeenCalledWith('/admin/extension/formbuilder/fields?form=501'))
    })

    it('closes a modal that can close itself on success', async () => {
        const modal = makeModal()
        modal.close = vi.fn()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(modal.close).toHaveBeenCalled())
    })

    it('re-renders the modal from a 422 body when the save has field errors', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockRejectedValue(new HttpError('http', 'HTTP 422', 422, '<form data-field-modal-form>errors</form>'))

        submit(form)

        await vi.waitFor(() => expect(modal.innerHTML).toBe('<form data-field-modal-form="">errors</form>'))
    })

    it('re-enables the submit button when a 422 has no body', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockRejectedValue(new HttpError('http', 'HTTP 422', 422, null))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(saveButton(modal).disabled).toBe(false))
        expect(modal.contains(form)).toBe(true)
    })

    it('re-enables the submit button on any other HTTP error, keeping the form', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockRejectedValue(new HttpError('http', 'HTTP 500', 500, '<p>Server error</p>'))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(saveButton(modal).disabled).toBe(false))
        expect(modal.innerHTML).not.toContain('Server error')
    })

    it('re-enables the submit button when the request fails', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockRejectedValue(new HttpError('network', 'Network error: down'))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(saveButton(modal).disabled).toBe(false))
    })

    it('re-enables the submit button when the answer is not ok', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockResolvedValue(jsonResponse({ ok: false }))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(saveButton(modal).disabled).toBe(false))
        expect(window.location.reload).not.toHaveBeenCalled()
    })

    it('disables the submit button outside the form while the request is in flight', () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockReturnValue(new Promise(() => {}))

        submit(form)

        expect(saveButton(modal).disabled).toBe(true)
    })

    it('submits a modal that has no submit button', async () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        saveButton(modal).remove()
        request.mockRejectedValue(new Error('down'))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
    })

    it('prevents the native form submission', () => {
        const modal = makeModal()
        mount(modal)
        const form = makeSubmittableForm(modal)
        request.mockReturnValue(new Promise(() => {}))

        const event = new SubmitEvent('submit', { bubbles: true, cancelable: true })
        form.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(true)
    })

    it('ignores a submit from a form outside the modal', () => {
        const modal = makeModal()
        mount(modal)
        const outsideForm = document.createElement('form')
        outsideForm.setAttribute('data-field-modal-form', '')
        document.body.appendChild(outsideForm)

        submit(outsideForm)

        expect(request).not.toHaveBeenCalled()
    })

    it('ignores a submit from another form in the modal', () => {
        const modal = makeModal()
        mount(modal)
        const otherForm = document.createElement('form')
        modal.appendChild(otherForm)

        submit(otherForm)

        expect(request).not.toHaveBeenCalled()
    })

    it('stops handling submits once unmounted', () => {
        const modal = makeModal()
        const component = mount(modal)
        const form = makeSubmittableForm(modal)
        component.unmount(modal)

        submit(form)

        expect(request).not.toHaveBeenCalled()
    })
})
