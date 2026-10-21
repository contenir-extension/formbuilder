import { afterEach, describe, expect, it, vi } from 'vitest'
import { request } from '../../../assets/js/utils/HttpClient.js'
import FormbuilderInlineForm from '../../../assets/js/components/FormbuilderInlineForm.js'
import { mountedFixtureElement } from '../TestAsset/fixtures.js'
import { addHandlerToken, TOKEN_NAME, TOKEN_VALUE } from '../TestAsset/token.js'

vi.mock('../../../assets/js/utils/HttpClient.js', () => ({
    request: vi.fn(),
    TIMEOUTS: { MUTATE: 30000 },
}))

afterEach(() => {
    document.body.innerHTML = ''
    vi.clearAllMocks()
})

const INSERT_URL = '/admin/extension/formbuilder/row-insert?form=501&group=701&position=1'

function makeForm(action = INSERT_URL) {
    const form = document.createElement('form')
    form.setAttribute('action', action)
    form.setAttribute('data-formbuilder-inline', '')
    form.innerHTML = '<button type="submit">Add</button>'
    document.body.appendChild(form)
    return form
}

/**
 * The per-row trash form for row 801 of the fields page, inside its row,
 * so a delete has the row it targets on the page.
 */
function makeRowDeleteForm() {
    const row = mountedFixtureElement('fields-editor-form', '[data-formbuilder-row][data-row-id="801"]')

    return row.querySelector('form[data-formbuilder-inline-target]')
}

function mount(form) {
    const component = new FormbuilderInlineForm({ eventBus: null, logger: null, router: null })
    component.mount(form)
    return component
}

function submit(form) {
    form.dispatchEvent(new SubmitEvent('submit', { bubbles: true, cancelable: true }))
}

function htmlResponse(html) {
    return { text: () => Promise.resolve(html) }
}

function jsonResponse(payload) {
    return { json: () => Promise.resolve(payload) }
}

describe('FormbuilderInlineForm insert', () => {
    it('posts the form to its action asking for HTML', async () => {
        const form = makeForm()
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-row>row</div>'))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        const [url, options] = request.mock.calls[0]
        expect([url, options.method, options.headers]).toEqual([form.action, 'POST', { 'Accept': 'text/html' }])
    })

    it('sends the form fields with the handler token', async () => {
        addHandlerToken()
        const form = makeForm()
        form.insertAdjacentHTML('afterbegin', '<input type="hidden" name="kind" value="text">')
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-row>row</div>'))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        const body = request.mock.calls[0][1].body
        expect([body.get('kind'), body.get(TOKEN_NAME)]).toEqual(['text', TOKEN_VALUE])
    })

    it('inserts the returned HTML fragment before the form', async () => {
        const form = makeForm()
        mount(form)
        request.mockResolvedValue(htmlResponse('  <div data-formbuilder-row>row</div>\n'))

        submit(form)

        await vi.waitFor(() => expect(form.previousElementSibling).not.toBeNull())
        expect(form.previousElementSibling.getAttribute('data-formbuilder-row')).toBe('')
    })

    it('dispatches formbuilder:row-added for an inserted row', async () => {
        const form = makeForm()
        const onRowAdded = vi.fn()
        document.addEventListener('formbuilder:row-added', onRowAdded)
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-row>row</div>'))

        submit(form)

        await vi.waitFor(() => expect(onRowAdded).toHaveBeenCalled())
        document.removeEventListener('formbuilder:row-added', onRowAdded)
        expect(onRowAdded.mock.calls[0][0].detail.row).toBe(form.previousElementSibling)
    })

    it('does not dispatch formbuilder:row-added for non-row inserts', async () => {
        const form = makeForm()
        const onRowAdded = vi.fn()
        document.addEventListener('formbuilder:row-added', onRowAdded)
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-group>group</div>'))

        submit(form)

        await vi.waitFor(() => expect(form.previousElementSibling).not.toBeNull())
        document.removeEventListener('formbuilder:row-added', onRowAdded)
        expect(onRowAdded).not.toHaveBeenCalled()
    })

    it('bumps the position query param so a repeat insert lands after the new entity', async () => {
        const form = makeForm()
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-row></div>'))

        submit(form)

        await vi.waitFor(() => expect(form.action).toContain('position=2'))
    })

    it('treats an empty position as zero', async () => {
        const form = makeForm('/admin/extension/formbuilder/section-insert?form=501&position=')
        mount(form)
        request.mockResolvedValue(htmlResponse('<section></section>'))

        submit(form)

        await vi.waitFor(() => expect(form.action).toContain('position=1'))
    })

    it('leaves an action without a position alone', async () => {
        const form = makeForm('/admin/extension/formbuilder/section-insert?form=501')
        const before = form.action
        mount(form)
        request.mockResolvedValue(htmlResponse('<section></section>'))

        submit(form)

        await vi.waitFor(() => expect(form.previousElementSibling).not.toBeNull())
        expect(form.action).toBe(before)
    })

    it('leaves an action that is not a valid URL alone', async () => {
        const form = makeForm('http://[not-a-host/section-insert?position=1')
        mount(form)
        request.mockResolvedValue(htmlResponse('<section></section>'))

        submit(form)

        await vi.waitFor(() => expect(form.previousElementSibling).not.toBeNull())
        expect(form.getAttribute('action')).toBe('http://[not-a-host/section-insert?position=1')
    })

    it('re-enables the submit button after a successful insert so it can be reused', async () => {
        const form = makeForm()
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-row></div>'))

        submit(form)

        await vi.waitFor(() => expect(form.previousElementSibling).not.toBeNull())
        expect(form.querySelector('button').disabled).toBe(false)
    })
})

describe('FormbuilderInlineForm delete', () => {
    it('posts the delete form asking for JSON', async () => {
        const form = makeRowDeleteForm()
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        const [url, options] = request.mock.calls[0]
        expect([url, options.method, options.headers]).toEqual([form.action, 'POST', { 'Accept': 'application/json' }])
    })

    it('sends the handler token with the delete', async () => {
        addHandlerToken()
        const form = makeRowDeleteForm()
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        expect(request.mock.calls[0][1].body.get(TOKEN_NAME)).toBe(TOKEN_VALUE)
    })

    it('removes the node data-formbuilder-inline-target describes on ok', async () => {
        const form = makeRowDeleteForm()
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(document.querySelector('[data-row-id="801"]')).toBeNull())
    })

    it('keeps the node when the handler does not answer ok', async () => {
        const form = makeRowDeleteForm()
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: false }))

        submit(form)

        await vi.waitFor(() => expect(form.querySelector('button').disabled).toBe(false))
        expect(document.querySelector('[data-formbuilder-row][data-row-id="801"]')).not.toBeNull()
    })

    it('inserts nothing even when the delete answers with markup', async () => {
        const form = makeRowDeleteForm()
        const before = form.previousElementSibling
        mount(form)
        request.mockResolvedValue({ json: () => Promise.resolve({ ok: true }), text: () => Promise.resolve('<div>new</div>') })

        submit(form)

        await vi.waitFor(() => expect(form.isConnected).toBe(false))
        expect(form.previousElementSibling).toBe(before)
    })

    it('does nothing more when the target selector is empty', async () => {
        const form = makeForm('/admin/extension/formbuilder/row-delete?row=9')
        form.setAttribute('data-formbuilder-inline-target', '')
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(form.querySelector('button').disabled).toBe(false))
        expect(form.isConnected).toBe(true)
    })

    it('does nothing more when the target is not on the page', async () => {
        const form = makeForm('/admin/extension/formbuilder/row-delete?row=9')
        form.setAttribute('data-formbuilder-inline-target', '#row-9')
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(form.querySelector('button').disabled).toBe(false))
        expect(request).toHaveBeenCalledTimes(1)
    })
})

describe('FormbuilderInlineForm error handling', () => {
    it('re-enables the submit button when the request throws', async () => {
        const form = makeForm()
        mount(form)
        request.mockRejectedValue(new Error('down'))

        submit(form)

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(form.querySelector('button').disabled).toBe(false))
    })

    it('inserts nothing when the insert fails', async () => {
        const form = makeForm()
        mount(form)
        request.mockRejectedValue(new Error('HTTP 403'))

        submit(form)

        await vi.waitFor(() => expect(form.querySelector('button').disabled).toBe(false))
        expect([form.previousElementSibling, form.action]).toEqual([null, new URL(INSERT_URL, window.location.href).href])
    })

    it('disables the submit button while the request is in flight', () => {
        const form = makeForm()
        mount(form)
        request.mockReturnValue(new Promise(() => {}))

        submit(form)

        expect(form.querySelector('button').disabled).toBe(true)
    })

    it('submits a form without a submit button', async () => {
        const form = makeForm()
        form.innerHTML = ''
        mount(form)
        request.mockResolvedValue(htmlResponse('<div data-formbuilder-row></div>'))

        submit(form)

        await vi.waitFor(() => expect(form.previousElementSibling).not.toBeNull())
    })

    it('leaves the button of a form removed from the page alone', async () => {
        const form = makeRowDeleteForm()
        const button = form.querySelector('button')
        mount(form)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        submit(form)

        await vi.waitFor(() => expect(form.isConnected).toBe(false))
        await Promise.resolve()
        expect(button.disabled).toBe(true)
    })

    it('prevents the native form submission', () => {
        const form = makeForm()
        mount(form)
        request.mockReturnValue(new Promise(() => {}))

        const event = new SubmitEvent('submit', { bubbles: true, cancelable: true })
        form.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(true)
    })

    it('stops responding to submits once unmounted', () => {
        const form = makeForm()
        const component = mount(form)

        component.unmount(form)
        submit(form)

        expect(request).not.toHaveBeenCalled()
    })
})
