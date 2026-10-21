/**
 * Thin fetch() wrapper with timeout, abort, and categorized errors.
 *
 * Every call the fields editor makes to the add-on's handlers goes through
 * here so no operation can hang indefinitely on a stalled server. Callers
 * receive HttpError instances with a `kind` field they can switch on to
 * decide whether to retry, re-render, or roll back an optimistic UI update.
 */

/**
 * Hard timeouts in milliseconds: LIST for reads (opening the field modal),
 * MUTATE for writes (inserts, deletes, renames, reorders and saves), UPLOAD
 * for anything that carries a file.
 */
export const TIMEOUTS = Object.freeze({
    LIST: 15000,
    MUTATE: 30000,
    UPLOAD: 120000,
})

export class HttpError extends Error {
    /**
     * @param {'timeout'|'aborted'|'network'|'http'} kind
     * @param {string} message
     * @param {number|null} status
     * @param {string|null} body
     */
    constructor(kind, message, status = null, body = null) {
        super(message)
        this.name = 'HttpError'
        this.kind = kind
        this.status = status
        this.body = body
    }
}

/**
 * Perform an HTTP request with a hard timeout. Resolves to the Response on
 * success; throws HttpError on timeout, abort, network failure, or any non-2xx
 * status.
 *
 * @param {string|URL} url
 * @param {object} [options]
 * @param {number} [options.timeoutMs]
 * @param {AbortSignal} [options.signal]   - external signal merged with internal timeout signal
 * @param {Function} [options.onProgress]  - currently a no-op for fetch; XHR-based callers handle this themselves
 * @returns {Promise<Response>}
 */
export async function request(url, options = {}) {
    const timeoutMs = options.timeoutMs ?? TIMEOUTS.MUTATE
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort('timeout'), timeoutMs)

    /** The caller can also abort through its own signal (e.g. a cancel button). */
    if (options.signal) {
        if (options.signal.aborted) {
            controller.abort('caller-aborted')
        } else {
            options.signal.addEventListener('abort', () => controller.abort('caller-aborted'), { once: true })
        }
    }

    const fetchOpts = {
        ...options,
        signal: controller.signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    }
    delete fetchOpts.timeoutMs
    delete fetchOpts.onProgress

    let response
    try {
        response = await fetch(url, fetchOpts)
    } catch (e) {
        clearTimeout(timeoutId)
        if (controller.signal.aborted) {
            const reason = controller.signal.reason
            if (reason === 'timeout') {
                throw new HttpError('timeout', `Request timed out after ${timeoutMs}ms: ${url}`)
            }
            throw new HttpError('aborted', `Request aborted: ${url}`)
        }
        throw new HttpError('network', `Network error: ${e.message || e}`)
    }
    clearTimeout(timeoutId)

    if (!response.ok) {
        const body = await response.text().catch(() => null)
        throw new HttpError('http', `HTTP ${response.status} from ${url}`, response.status, body)
    }

    return response
}

/**
 * Convenience helper: POST a FormData body, parse JSON, return the parsed
 * payload. Throws HttpError on any failure (including non-JSON response).
 */
export async function postJson(url, formData, { timeoutMs, signal } = {}) {
    const response = await request(url, {
        method: 'POST',
        body: formData,
        timeoutMs,
        signal,
    })
    try {
        return await response.json()
    } catch (e) {
        throw new HttpError('http', `Response was not valid JSON: ${e.message}`, response.status)
    }
}
