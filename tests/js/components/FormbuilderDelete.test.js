import { afterEach, describe, expect, it, vi } from 'vitest'
import { request } from '../../../assets/js/utils/HttpClient.js'
import FormbuilderDelete from '../../../assets/js/components/FormbuilderDelete.js'
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

const DELETE_URL = '/admin/extension/formbuilder/section-delete?form=501&section=601'
const SECTION    = '.builder__section[data-section-id="601"]'

/**
 * The first section of the fields page, and the Delete link its actions
 * menu renders once the host's Dropdown has filled in the tokens.
 */
function makeSectionAndLink({ href = DELETE_URL, target = SECTION } = {}) {
    const section = mountedFixtureElement('fields-editor-form', SECTION)
    const link = document.createElement('a')
    link.setAttribute('href', href)
    link.setAttribute('data-formbuilder-delete', '')
    if (target !== null) link.setAttribute('data-formbuilder-target', target)
    link.textContent = 'Delete'
    document.body.appendChild(link)

    return { section, link }
}

function mount(link) {
    const component = new FormbuilderDelete({ eventBus: null, logger: null, router: null })
    component.mount(link)
    return component
}

function click(link) {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true })
    link.dispatchEvent(event)
    return event
}

function jsonResponse(payload) {
    return { json: () => Promise.resolve(payload) }
}

/** Let the request, its JSON and the removal settle. */
async function settle() {
    await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('FormbuilderDelete', () => {
    it('prevents the link from navigating', () => {
        const { link } = makeSectionAndLink()
        mount(link)
        request.mockReturnValue(new Promise(() => {}))

        expect(click(link).defaultPrevented).toBe(true)
    })

    it('posts to the link href asking for JSON', () => {
        const { link } = makeSectionAndLink()
        mount(link)
        request.mockReturnValue(new Promise(() => {}))

        click(link)

        const [url, options] = request.mock.calls[0]
        expect([url, options.method, options.headers]).toEqual([link.href, 'POST', { 'Accept': 'application/json' }])
    })

    it('sends only the handler token in the body', () => {
        addHandlerToken()
        const { link } = makeSectionAndLink()
        mount(link)
        request.mockReturnValue(new Promise(() => {}))

        click(link)

        const body = request.mock.calls[0][1].body
        expect([...body.entries()]).toEqual([[TOKEN_NAME, TOKEN_VALUE]])
    })

    it('removes the target node when the handler answers ok', async () => {
        const { link } = makeSectionAndLink()
        mount(link)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        click(link)

        await vi.waitFor(() => expect(document.querySelector(SECTION)).toBeNull())
    })

    it('keeps the target node when the handler does not answer ok', async () => {
        const { section, link } = makeSectionAndLink()
        mount(link)
        request.mockResolvedValue(jsonResponse({ ok: false }))

        click(link)
        await settle()

        expect(section.isConnected).toBe(true)
    })

    it('keeps the target node and swallows the error when the request fails', async () => {
        const { section, link } = makeSectionAndLink()
        mount(link)
        request.mockRejectedValue(new Error('HTTP 403'))

        click(link)
        await settle()

        expect(section.isConnected).toBe(true)
    })

    it('swallows an answer that is not JSON', async () => {
        const { section, link } = makeSectionAndLink()
        mount(link)
        request.mockResolvedValue({ json: () => Promise.reject(new SyntaxError('Unexpected token <')) })

        click(link)
        await settle()

        expect(section.isConnected).toBe(true)
    })

    it('removes nothing when the link has no target', async () => {
        const { section, link } = makeSectionAndLink({ target: null })
        mount(link)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        click(link)
        await settle()

        expect(section.isConnected).toBe(true)
    })

    it('removes nothing when the target is not on the page', async () => {
        const { section, link } = makeSectionAndLink({ target: '.builder__section[data-section-id="999"]' })
        mount(link)
        request.mockResolvedValue(jsonResponse({ ok: true }))

        click(link)
        await settle()

        expect([section.isConnected, request.mock.calls.length]).toEqual([true, 1])
    })

    it('stops responding to clicks once unmounted', () => {
        const { link } = makeSectionAndLink()
        const component = mount(link)
        component.unmount(link)
        const noNavigation = (event) => event.preventDefault()
        document.addEventListener('click', noNavigation)

        click(link)
        document.removeEventListener('click', noNavigation)

        expect(request).not.toHaveBeenCalled()
    })
})
