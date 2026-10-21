import { afterEach, describe, expect, it, vi } from 'vitest'
import { request } from '../../../assets/js/utils/HttpClient.js'
import EditableLegend from '../../../assets/js/components/EditableLegend.js'
import { addHandlerToken, TOKEN_NAME, TOKEN_VALUE } from '../TestAsset/token.js'

vi.mock('../../../assets/js/utils/HttpClient.js', () => ({
    request: vi.fn(),
    TIMEOUTS: { MUTATE: 30000 },
}))

function savedResponse(legend) {
    return { json: () => Promise.resolve({ ok: true, legend }) }
}

afterEach(() => {
    document.body.innerHTML = ''
    vi.clearAllMocks()
})

function makeLegend({ text = 'Personal Details', empty = false, url = '/admin/extension/formbuilder/section-rename?section=1' } = {}) {
    const wrapper = document.createElement('div')
    const span = document.createElement('span')
    span.setAttribute('data-editable-legend', '')
    if (url !== null) span.setAttribute('data-update-url', url)
    if (empty) span.setAttribute('data-empty', 'true')
    span.textContent = text
    const button = document.createElement('button')
    button.setAttribute('data-edit-legend', '')
    wrapper.appendChild(span)
    wrapper.appendChild(button)
    document.body.appendChild(wrapper)
    return { span, button }
}

function mount(span) {
    const component = new EditableLegend({ eventBus: null, logger: null, router: null })
    component.mount(span)
    return component
}

function rename(span, button, value) {
    button.click()
    span.textContent = value
    span.dispatchEvent(new Event('blur'))
}

describe('EditableLegend entering edit mode', () => {
    it('makes the span contenteditable and focuses it on trigger click', () => {
        const { span, button } = makeLegend()
        mount(span)

        button.click()

        expect(span.getAttribute('contenteditable')).toBe('true')
        expect(span.classList.contains('is-editing')).toBe(true)
        expect(document.activeElement).toBe(span)
    })

    it('selects the legend text so typing replaces it', () => {
        const { span, button } = makeLegend()
        mount(span)

        button.click()

        expect(window.getSelection().toString()).toBe('Personal Details')
    })

    it('still enters edit mode when the window has no selection', () => {
        vi.spyOn(window, 'getSelection').mockReturnValue(null)
        const { span, button } = makeLegend()
        mount(span)

        button.click()

        expect(span.getAttribute('contenteditable')).toBe('true')
    })

    it('clears the placeholder text when entering edit on an empty legend', () => {
        const { span, button } = makeLegend({ text: 'Untitled', empty: true })
        mount(span)

        button.click()

        expect(span.textContent).toBe('')
    })

    it('does nothing when there is no sibling edit trigger', () => {
        const span = document.createElement('span')
        span.setAttribute('data-editable-legend', '')
        document.body.appendChild(span)

        expect(() => mount(span)).not.toThrow()
    })

    it('does nothing when the legend has no parent', () => {
        const span = document.createElement('span')
        span.setAttribute('data-editable-legend', '')

        expect(() => mount(span)).not.toThrow()
    })
})

describe('EditableLegend saving', () => {
    it('posts the new value as legend to the update URL on blur', async () => {
        const { span, button } = makeLegend({ text: 'Old Name', url: '/admin/extension/formbuilder/section-rename?section=5' })
        mount(span)
        request.mockResolvedValue(savedResponse('New Name'))

        rename(span, button, 'New Name')

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        const [url, options] = request.mock.calls[0]
        expect([url, options.method, options.body.get('legend')])
            .toEqual(['/admin/extension/formbuilder/section-rename?section=5', 'POST', 'New Name'])
    })

    it('sends the handler token and asks for JSON', async () => {
        addHandlerToken()
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        request.mockResolvedValue(savedResponse('New Name'))

        rename(span, button, 'New Name')

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        const options = request.mock.calls[0][1]
        expect([options.body.get(TOKEN_NAME), options.headers]).toEqual([TOKEN_VALUE, { 'Accept': 'application/json' }])
    })

    it('trims the new value before posting it', async () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        request.mockResolvedValue(savedResponse('New Name'))

        rename(span, button, '  New Name  ')

        await vi.waitFor(() => expect(span.textContent).toBe('New Name'))
        expect(request.mock.calls[0][1].body.get('legend')).toBe('New Name')
    })

    it('removes contenteditable once saved', async () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        request.mockResolvedValue(savedResponse('New Name'))

        rename(span, button, 'New Name')

        await vi.waitFor(() => expect(span.hasAttribute('contenteditable')).toBe(false))
    })

    it('keeps the saved value as the one a later Escape reverts to', async () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        request.mockResolvedValue(savedResponse('New Name'))
        rename(span, button, 'New Name')
        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await new Promise((resolve) => setTimeout(resolve, 0))

        button.click()
        span.textContent = 'Abandoned'
        span.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))

        expect(span.textContent).toBe('New Name')
    })

    it('names an untitled legend', async () => {
        const { span, button } = makeLegend({ text: 'Untitled', empty: true })
        mount(span)
        request.mockResolvedValue(savedResponse('Contact'))

        rename(span, button, 'Contact')

        await vi.waitFor(() => expect(span.hasAttribute('data-empty')).toBe(false))
        expect(span.textContent).toBe('Contact')
    })

    it('does not post when the value is unchanged', () => {
        const { span, button } = makeLegend({ text: 'Same Name' })
        mount(span)
        button.click()

        span.dispatchEvent(new Event('blur'))

        expect(request).not.toHaveBeenCalled()
    })

    it('shows the placeholder again when an untitled legend is left blank', () => {
        const { span, button } = makeLegend({ text: 'Untitled', empty: true })
        mount(span)

        rename(span, button, '   ')

        expect([span.textContent, span.getAttribute('data-empty'), request.mock.calls.length]).toEqual(['Untitled', 'true', 0])
    })

    it('renders the placeholder when saved as blank', async () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        request.mockResolvedValue(savedResponse(''))

        rename(span, button, '')

        await vi.waitFor(() => expect(span.getAttribute('data-empty')).toBe('true'))
        expect(span.textContent).toBe('Untitled')
    })

    it('keeps the new value without posting when there is no update URL', async () => {
        const { span, button } = makeLegend({ text: 'Old Name', url: null })
        mount(span)

        rename(span, button, 'New Name')

        await vi.waitFor(() => expect(span.hasAttribute('contenteditable')).toBe(false))
        expect([span.textContent, request.mock.calls.length]).toEqual(['New Name', 0])
    })

    it('reverts to the previous value when the save request fails', async () => {
        request.mockRejectedValue(new Error('network down'))
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)

        rename(span, button, 'New Name')

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(span.textContent).toBe('Old Name'))
    })

    it('reverts to the previous value when the handler does not answer ok', async () => {
        request.mockResolvedValue({ json: () => Promise.resolve({ ok: false }) })
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)

        rename(span, button, 'New Name')

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(span.textContent).toBe('Old Name'))
    })

    it('reverts an untitled legend to the placeholder when the save fails', async () => {
        request.mockRejectedValue(new Error('network down'))
        const { span, button } = makeLegend({ text: 'Untitled', empty: true })
        mount(span)

        rename(span, button, 'Contact')

        await vi.waitFor(() => expect(request).toHaveBeenCalled())
        await vi.waitFor(() => expect(span.textContent).toBe('Untitled'))
        expect(span.getAttribute('data-empty')).toBe('true')
    })

    it('saves on Enter without a newline', () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        const blurSpy = vi.spyOn(span, 'blur')
        mount(span)
        button.click()

        const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true })
        span.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(true)
        expect(blurSpy).toHaveBeenCalled()
    })

    it('lets Shift+Enter through as a newline', () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        button.click()

        const event = new KeyboardEvent('keydown', { key: 'Enter', shiftKey: true, bubbles: true, cancelable: true })
        span.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(false)
    })

    it('lets other keys through', () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        button.click()

        const event = new KeyboardEvent('keydown', { key: 'a', bubbles: true, cancelable: true })
        span.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(false)
    })

    it('cancels without saving on Escape', () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        mount(span)
        button.click()
        span.textContent = 'Changed but abandoned'

        span.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))

        expect(span.textContent).toBe('Old Name')
        expect(request).not.toHaveBeenCalled()
    })

    it('restores the placeholder on Escape for an untitled legend', () => {
        const { span, button } = makeLegend({ text: 'Untitled', empty: true })
        mount(span)
        button.click()
        span.textContent = 'Changed but abandoned'

        span.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))

        expect([span.textContent, span.getAttribute('data-empty')]).toEqual(['Untitled', 'true'])
    })
})

describe('EditableLegend teardown', () => {
    it('stops responding to the trigger once unmounted', () => {
        const { span, button } = makeLegend()
        const component = mount(span)

        component.unmount(span)
        button.click()

        expect(span.hasAttribute('contenteditable')).toBe(false)
    })

    it('stops saving on blur once unmounted', () => {
        const { span, button } = makeLegend({ text: 'Old Name' })
        const component = mount(span)
        button.click()
        component.unmount(span)
        span.textContent = 'New Name'

        span.dispatchEvent(new Event('blur'))

        expect(request).not.toHaveBeenCalled()
    })
})
