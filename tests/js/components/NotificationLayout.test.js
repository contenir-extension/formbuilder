import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { EditorSelection } from '@codemirror/state'
import { EditorView } from '@codemirror/view'
import { HttpError, request } from '../../../assets/js/utils/HttpClient.js'
import NotificationLayout from '../../../assets/js/components/NotificationLayout.js'
import { mountedFixtureElement } from '../TestAsset/fixtures.js'
import { addHandlerToken, TOKEN_NAME, TOKEN_VALUE } from '../TestAsset/token.js'

vi.mock('../../../assets/js/utils/HttpClient.js', () => ({
    request: vi.fn(),
    HttpError: class extends Error {
        constructor(kind, message) {
            super(message)
            this.kind = kind
        }
    },
    TIMEOUTS: { LIST: 15000 },
}))

const INITIAL_BODY = '<p>Hello {field:name}</p>'

function htmlResponse(body) {
    return Promise.resolve({ text: () => Promise.resolve(body) })
}

/**
 * A preview request the test settles itself, which rejects as the HTTP
 * client does once the component aborts it.
 *
 * @returns {{signal: () => AbortSignal}}
 */
function heldRequest() {
    let options
    request.mockImplementationOnce((_url, requestOptions) => new Promise((_resolve, reject) => {
        options = requestOptions
        options.signal.addEventListener('abort', () => reject(new HttpError('aborted', 'Request aborted')))
    }))

    return { signal: () => options.signal }
}

beforeEach(() => {
    request.mockReturnValue(htmlResponse('<p>preview</p>'))
})

/**
 * Every mount adds a document-level `token:insert` listener, which clearing
 * the body leaves behind, so each mount is unmounted after its test.
 */
let mounted = []

afterEach(() => {
    mounted.forEach(({ component, root }) => component.unmount(root))
    mounted = []
    document.body.innerHTML = ''
    vi.clearAllMocks()
    vi.useRealTimers()
})

function layout() {
    return mountedFixtureElement('components/notification-layout', '[data-notification-layout]')
}

function mount(root) {
    const component = new NotificationLayout({ eventBus: null, logger: null, router: null })
    component.mount(root)
    mounted.push({ component, root })
    return component
}

function editorView(root) {
    return EditorView.findFromDOM(root.querySelector('.cm-editor'))
}

function insertToken(token, target = 'codemirror') {
    document.dispatchEvent(new CustomEvent('token:insert', { detail: { token, target } }))
}

/** Mount with fake timers and let the preview fetched on mount settle. */
async function mountSettled(root) {
    vi.useFakeTimers()
    const component = mount(root)
    await vi.advanceTimersByTimeAsync(0)
    request.mockClear()
    return component
}

describe('NotificationLayout setup', () => {
    it('mounts a CodeMirror editor in a host just before the textarea', () => {
        const root = layout()
        mount(root)

        const textarea = root.querySelector('textarea')
        expect(textarea.previousElementSibling.matches('.notification-layout__codemirror')).toBe(true)
        expect(textarea.previousElementSibling.querySelector('.cm-editor')).not.toBeNull()
    })

    it('starts the editor with the textarea content', () => {
        const root = layout()
        mount(root)

        expect(editorView(root).state.doc.toString()).toBe(INITIAL_BODY)
    })

    it('posts the body template to the preview URL as HTML on mount', async () => {
        const root = layout()
        mount(root)

        await vi.waitFor(() => expect(request).toHaveBeenCalledTimes(1))
        const [url, options] = request.mock.calls[0]
        expect([url, options.method, options.headers, options.credentials, options.timeoutMs, options.body.get('body_template')]).toEqual([
            '/admin/extension/formbuilder/notification-preview?form=501&notification=951',
            'POST',
            { Accept: 'text/html' },
            'same-origin',
            15000,
            INITIAL_BODY,
        ])
    })

    it('carries the host handler token with the preview', async () => {
        addHandlerToken()
        const root = layout()
        mount(root)

        await vi.waitFor(() => expect(request).toHaveBeenCalledTimes(1))
        expect(request.mock.calls[0][1].body.get(TOKEN_NAME)).toBe(TOKEN_VALUE)
    })

    it('does nothing without a preview URL', () => {
        const root = layout()
        root.removeAttribute('data-preview-url')
        mount(root)

        expect([root.querySelector('.notification-layout__codemirror'), request.mock.calls.length]).toEqual([null, 0])
    })

    it('does nothing without the editor textarea', () => {
        const root = layout()
        root.querySelector('[data-notification-layout-editor]').remove()
        mount(root)

        expect([root.querySelector('.notification-layout__codemirror'), request.mock.calls.length]).toEqual([null, 0])
    })

    it('does nothing without the preview iframe', () => {
        const root = layout()
        root.querySelector('[data-notification-layout-preview]').remove()
        mount(root)

        expect([root.querySelector('.notification-layout__codemirror'), request.mock.calls.length]).toEqual([null, 0])
    })
})

describe('NotificationLayout preview', () => {
    it('writes the rendered HTML into the iframe srcdoc', async () => {
        request.mockReturnValue(htmlResponse('<p>rendered</p>'))
        const root = layout()
        mount(root)

        await vi.waitFor(() => expect(root.querySelector('iframe').srcdoc).toBe('<p>rendered</p>'))
    })

    it('writes each change back to the textarea at once', async () => {
        const root = layout()
        await mountSettled(root)

        insertToken('X')

        expect(root.querySelector('textarea').value).toBe(`X${INITIAL_BODY}`)
    })

    it('fetches a new preview 400ms after a change', async () => {
        const root = layout()
        await mountSettled(root)
        request.mockReturnValue(htmlResponse('<p>updated</p>'))

        insertToken('X')
        await vi.advanceTimersByTimeAsync(399)
        const before = request.mock.calls.length
        await vi.advanceTimersByTimeAsync(1)

        expect([before, request.mock.calls[0][1].body.get('body_template'), root.querySelector('iframe').srcdoc])
            .toEqual([0, `X${INITIAL_BODY}`, '<p>updated</p>'])
    })

    it('fetches one preview for changes made within 400ms of each other', async () => {
        const root = layout()
        await mountSettled(root)

        insertToken('X')
        await vi.advanceTimersByTimeAsync(200)
        insertToken('Y')
        await vi.advanceTimersByTimeAsync(400)

        expect([request.mock.calls.length, request.mock.calls[0][1].body.get('body_template')])
            .toEqual([1, `XY${INITIAL_BODY}`])
    })

    it('aborts the preview in flight when a newer one starts', async () => {
        vi.useFakeTimers()
        const first = heldRequest()
        const root = layout()
        mount(root)

        insertToken('X')
        await vi.advanceTimersByTimeAsync(400)

        expect(first.signal().aborted).toBe(true)
    })

    it('keeps the newer preview when a superseded one answers late', async () => {
        vi.useFakeTimers()
        let answerStale
        request.mockImplementationOnce(() => new Promise((resolve) => {
            answerStale = () => resolve({ text: () => Promise.resolve('<p>stale</p>') })
        }))
        request.mockReturnValue(htmlResponse('<p>fresh</p>'))
        const root = layout()
        mount(root)

        insertToken('X')
        await vi.advanceTimersByTimeAsync(400)
        answerStale()
        await vi.advanceTimersByTimeAsync(0)

        expect(root.querySelector('iframe').srcdoc).toBe('<p>fresh</p>')
    })

    it('leaves the last preview in place when the preview is answered with an HTTP error', async () => {
        const root = layout()
        await mountSettled(root)
        request.mockRejectedValue(new HttpError('http', 'HTTP 500'))

        insertToken('X')
        await vi.advanceTimersByTimeAsync(400)

        expect(root.querySelector('iframe').srcdoc).toBe('<p>preview</p>')
    })

    it.each([
        ['a network failure', new HttpError('network', 'Network error: offline')],
        ['a timeout', new HttpError('timeout', 'Request timed out')],
    ])('shows %s in the iframe', async (_case, error) => {
        const root = layout()
        await mountSettled(root)
        request.mockRejectedValue(error)

        insertToken('X')
        await vi.advanceTimersByTimeAsync(400)

        expect(root.querySelector('iframe').srcdoc).toContain(`Preview failed: ${error.message}`)
    })

    it('shows a response it cannot read in the iframe', async () => {
        const root = layout()
        await mountSettled(root)
        request.mockResolvedValue({ text: () => Promise.reject(new Error('body stream failed')) })

        insertToken('X')
        await vi.advanceTimersByTimeAsync(400)

        expect(root.querySelector('iframe').srcdoc).toContain('Preview failed: body stream failed')
    })
})

describe('NotificationLayout token insertion', () => {
    it('replaces the editor selection with the token', () => {
        const root = layout()
        mount(root)
        const view = editorView(root)
        view.dispatch({ selection: EditorSelection.single(3, 8) })

        insertToken('{field:email}')

        expect(view.state.doc.toString()).toBe('<p>{field:email} {field:name}</p>')
    })

    it('puts the caret after the inserted token', () => {
        const root = layout()
        mount(root)
        const view = editorView(root)
        view.dispatch({ selection: EditorSelection.single(3) })

        insertToken('{field:email}')

        expect(view.state.selection.main.head).toBe(16)
    })

    it('focuses the editor after inserting', () => {
        const root = layout()
        mount(root)

        insertToken('{field:email}')

        expect(root.querySelector('.cm-content').contains(document.activeElement)).toBe(true)
    })

    it.each([
        ['another target', new CustomEvent('token:insert', { detail: { token: '{X}', target: '#ntf-subject' } })],
        ['no detail', new CustomEvent('token:insert')],
        ['an empty token', new CustomEvent('token:insert', { detail: { token: '', target: 'codemirror' } })],
        ['no token', new CustomEvent('token:insert', { detail: { target: 'codemirror' } })],
    ])('ignores an insert with %s', (_case, event) => {
        const root = layout()
        mount(root)

        document.dispatchEvent(event)

        expect(editorView(root).state.doc.toString()).toBe(INITIAL_BODY)
    })
})

describe('NotificationLayout teardown', () => {
    it('removes the CodeMirror host on unmount', () => {
        const root = layout()
        const component = mount(root)

        component.unmount(root)

        expect(root.querySelector('.notification-layout__codemirror')).toBeNull()
    })

    it('stops taking tokens once unmounted', () => {
        const root = layout()
        const component = mount(root)

        component.unmount(root)
        insertToken('X')

        expect(root.querySelector('textarea').value).toBe(INITIAL_BODY)
    })

    it('drops a pending preview on unmount', async () => {
        const root = layout()
        const component = await mountSettled(root)

        insertToken('X')
        component.unmount(root)
        await vi.advanceTimersByTimeAsync(400)

        expect(request).not.toHaveBeenCalled()
    })

    it('aborts the preview in flight on unmount', () => {
        const held = heldRequest()
        const root = layout()
        const component = mount(root)

        component.unmount(root)

        expect(held.signal().aborted).toBe(true)
    })

    it('stays silent about a preview aborted on unmount', async () => {
        heldRequest()
        const root = layout()
        const component = mount(root)

        component.unmount(root)
        await vi.waitFor(() => expect(request).toHaveBeenCalledTimes(1))
        await new Promise((resolve) => setTimeout(resolve, 0))

        expect(root.querySelector('iframe').srcdoc).toBe('')
    })
})
