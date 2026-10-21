import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { HttpError, postJson, request } from '../../../assets/js/utils/HttpClient.js'

function jsonResponse(body, init = {}) {
    return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
        ...init,
    })
}

/**
 * A fetch stub that mirrors the one real behaviour this module relies on:
 * an already-aborted signal rejects with an AbortError. Otherwise it
 * resolves right away, which is fine for tests that never abort.
 */
function fetchThatRespectsAbort(response) {
    return vi.fn((url, opts) => new Promise((resolve, reject) => {
        if (opts.signal.aborted) {
            reject(new DOMException('The operation was aborted', 'AbortError'))
            return
        }
        resolve(response)
    }))
}

/**
 * A fetch stub that simulates a stalled network request: it never
 * resolves on its own, only rejecting once the request signal aborts
 * (via timeout or caller cancellation).
 */
function fetchThatHangsUntilAborted() {
    return vi.fn((url, opts) => new Promise((resolve, reject) => {
        if (opts.signal.aborted) {
            reject(new DOMException('The operation was aborted', 'AbortError'))
            return
        }
        opts.signal.addEventListener('abort', () => {
            reject(new DOMException('The operation was aborted', 'AbortError'))
        })
    }))
}

beforeEach(() => {
    vi.useFakeTimers()
})

afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
})

describe('request', () => {
    it('resolves with the response on a successful request', async () => {
        vi.stubGlobal('fetch', fetchThatRespectsAbort(jsonResponse({ ok: true })))

        const response = await request('/api/x')

        expect(response.ok).toBe(true)
    })

    it('sends the XMLHttpRequest marker header alongside caller headers', async () => {
        const fetchMock = fetchThatRespectsAbort(jsonResponse({}))
        vi.stubGlobal('fetch', fetchMock)

        await request('/api/x', { headers: { Accept: 'application/json' } })

        expect(fetchMock.mock.calls[0][1].headers).toEqual({
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        })
    })

    it('throws an HttpError with status and body on a non-2xx response', async () => {
        vi.stubGlobal('fetch', fetchThatRespectsAbort(new Response('nope', { status: 404 })))

        const error = await request('/api/missing').catch((e) => e)

        expect(error).toBeInstanceOf(HttpError)
        expect(error.kind).toBe('http')
        expect(error.status).toBe(404)
        expect(error.body).toBe('nope')
    })

    it('throws a network HttpError when fetch rejects for a reason other than abort', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

        const error = await request('/api/x').catch((e) => e)

        expect(error).toBeInstanceOf(HttpError)
        expect(error.kind).toBe('network')
    })

    it('throws a timeout HttpError once the timeout elapses', async () => {
        vi.stubGlobal('fetch', fetchThatHangsUntilAborted())

        const pending = request('/api/slow', { timeoutMs: 1000 }).catch((e) => e)
        await vi.advanceTimersByTimeAsync(1000)

        const error = await pending
        expect(error).toBeInstanceOf(HttpError)
        expect(error.kind).toBe('timeout')
    })

    it('throws an aborted HttpError when the caller signal is already aborted', async () => {
        vi.stubGlobal('fetch', fetchThatRespectsAbort(jsonResponse({})))
        const controller = new AbortController()
        controller.abort()

        const error = await request('/api/x', { signal: controller.signal }).catch((e) => e)

        expect(error).toBeInstanceOf(HttpError)
        expect(error.kind).toBe('aborted')
    })

    it('throws an aborted HttpError when the caller signal fires mid-request', async () => {
        vi.stubGlobal('fetch', fetchThatHangsUntilAborted())
        const controller = new AbortController()

        const pending = request('/api/x', { signal: controller.signal }).catch((e) => e)
        controller.abort()

        const error = await pending
        expect(error).toBeInstanceOf(HttpError)
        expect(error.kind).toBe('aborted')
    })
})

describe('request failures', () => {
    it('keeps a null body when the error response body cannot be read', async () => {
        const response = new Response('nope', { status: 500 })
        vi.spyOn(response, 'text').mockRejectedValue(new TypeError('body used'))
        vi.stubGlobal('fetch', fetchThatRespectsAbort(response))

        const error = await request('/api/x').catch((e) => e)

        expect([error.kind, error.status, error.body]).toEqual(['http', 500, null])
    })

    it('describes a network failure that is not an Error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue('offline'))

        const error = await request('/api/x').catch((e) => e)

        expect(error.message).toBe('Network error: offline')
    })
})

describe('postJson', () => {
    it('posts the form data and returns the parsed JSON body', async () => {
        const fetchMock = fetchThatRespectsAbort(jsonResponse({ id: 42 }))
        vi.stubGlobal('fetch', fetchMock)
        const formData = new FormData()

        const result = await postJson('/api/save', formData)

        expect(result).toEqual({ id: 42 })
        expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'POST', body: formData })
    })

    it('throws an HttpError when the response body is not valid JSON', async () => {
        vi.stubGlobal('fetch', fetchThatRespectsAbort(new Response('<html>not json</html>', { status: 200 })))

        const error = await postJson('/api/save', new FormData()).catch((e) => e)

        expect(error).toBeInstanceOf(HttpError)
        expect(error.kind).toBe('http')
        expect(error.message).toMatch(/not valid JSON/)
    })
})
