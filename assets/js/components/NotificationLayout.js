import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { EditorState } from '@codemirror/state'
import { EditorView, keymap, lineNumbers } from '@codemirror/view'
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands'
import {
    HighlightStyle,
    LanguageSupport,
    LRLanguage,
    syntaxHighlighting,
} from '@codemirror/language'
import { tags } from '@lezer/highlight'
import { parser as htmlParser } from '@lezer/html'
import { request, HttpError, TIMEOUTS } from '../utils/HttpClient.js'
import { withToken } from '../utils/HandlerToken.js'

/**
 * A lean HTML language for the notification body editor, built directly on
 * the @lezer/html parser rather than @codemirror/lang-html, so the bundle
 * leaves out @codemirror/lang-css and @codemirror/lang-javascript, which
 * only highlight inside `<style>` and `<script>`: transactional email
 * templates almost never carry a script.
 */
const htmlLanguage = LRLanguage.define({
    name: 'html',
    parser: htmlParser,
    languageData: {
        commentTokens: { block: { open: '<!--', close: '-->' } },
        indentOnInput: /^\s*<\/\w+\W$/,
        wordChars: '-._',
    },
})

/**
 * A highlight style declaring only the tags @lezer/html emits, where
 * @codemirror/language's defaultHighlightStyle maps dozens of tokens this
 * editor never sees.
 */
const htmlHighlightStyle = HighlightStyle.define([
    { tag: tags.tagName, color: '#22863a' },
    { tag: tags.attributeName, color: '#6f42c1' },
    { tag: tags.attributeValue, color: '#032f62' },
    { tag: tags.string, color: '#032f62' },
    { tag: tags.comment, color: '#6a737d', fontStyle: 'italic' },
    { tag: tags.bracket, color: '#22863a' },
    { tag: tags.angleBracket, color: '#22863a' },
    { tag: tags.processingInstruction, color: '#6a737d' },
])

/** How long the editor waits after the last change before asking for a preview, in ms. */
const PREVIEW_DELAY = 400

/**
 * A notification's Layout: a CodeMirror HTML editor for its body template
 * beside a live preview.
 *
 * Markup:
 *   <form data-notification-layout data-preview-url="…the add-on's preview handler…">
 *     <textarea data-notification-layout-editor name="body_template">…</textarea>
 *     <iframe data-notification-layout-preview></iframe>
 *   </form>
 *
 * The textarea stays in the form, hidden by the stylesheet, so a plain
 * submit still posts the editor's content: CodeMirror mounts in a
 * `.notification-layout__codemirror` host inserted before it, and every
 * change is written back to it.
 *
 * The preview is fetched once on mount and again 400 ms after the last
 * change: `body_template` is POSTed to `data-preview-url` with the host's
 * handler token and `Accept: text/html`, and the HTML that comes back (the
 * template rendered against sample entry data) becomes the iframe's
 * `srcdoc`. A newer preview aborts the one in flight, and an answer that
 * arrives after it was superseded is dropped. A non-2xx answer leaves the
 * last preview in place; a network failure or timeout replaces it with the
 * error, so the operator knows the preview is stale.
 *
 * The token picker's items for this editor (`data-token-target="codemirror"`)
 * send a document-level `token:insert` event (TokenInsert), whose token is
 * inserted over the editor's selection.
 */
export default class NotificationLayout extends BaseComponent {
    _getSelector() {
        return 'data-notification-layout'
    }

    _init(root) {
        const textarea = root.querySelector('[data-notification-layout-editor]')
        const iframe = root.querySelector('[data-notification-layout-preview]')
        const previewUrl = root.getAttribute('data-preview-url') || ''
        if (!textarea || !iframe || !previewUrl) return {}

        const host = document.createElement('div')
        host.className = 'notification-layout__codemirror'
        textarea.parentNode.insertBefore(host, textarea)

        let debounceTimer = null
        let controller = null

        const preview = (bodyTemplate) => {
            controller?.abort()
            controller = new AbortController()
            this._fetchPreview(previewUrl, bodyTemplate, iframe, controller.signal)
        }

        const onChange = (bodyTemplate) => {
            textarea.value = bodyTemplate
            clearTimeout(debounceTimer)
            debounceTimer = setTimeout(() => preview(bodyTemplate), PREVIEW_DELAY)
        }

        const view = new EditorView({
            state: EditorState.create({
                doc: textarea.value,
                extensions: [
                    lineNumbers(),
                    history(),
                    keymap.of([...defaultKeymap, ...historyKeymap]),
                    new LanguageSupport(htmlLanguage),
                    syntaxHighlighting(htmlHighlightStyle),
                    EditorView.lineWrapping,
                    EditorView.updateListener.of((update) => {
                        if (update.docChanged) {
                            onChange(update.state.doc.toString())
                        }
                    }),
                ],
            }),
            parent: host,
        })

        preview(textarea.value)

        const onTokenInsert = (event) => {
            const detail = event.detail || {}
            if (detail.target !== 'codemirror') return
            const token = String(detail.token || '')
            if (!token) return
            const { from, to } = view.state.selection.main
            view.dispatch({
                changes: { from, to, insert: token },
                selection: { anchor: from + token.length },
            })
            view.focus()
        }
        document.addEventListener('token:insert', onTokenInsert)

        return {
            cleanup: () => {
                document.removeEventListener('token:insert', onTokenInsert)
                clearTimeout(debounceTimer)
                controller.abort()
                view.destroy()
                host.remove()
            },
        }
    }

    /**
     * POST the template to the preview handler and show what it renders.
     *
     * @param {string} url
     * @param {string} bodyTemplate
     * @param {HTMLIFrameElement} iframe
     * @param {AbortSignal} signal aborted once a newer preview starts
     */
    async _fetchPreview(url, bodyTemplate, iframe, signal) {
        const formData = new FormData()
        formData.append('body_template', bodyTemplate)

        try {
            const response = await request(url, {
                method: 'POST',
                body: withToken(formData),
                credentials: 'same-origin',
                signal,
                headers: { Accept: 'text/html' },
                timeoutMs: TIMEOUTS.LIST,
            })
            const html = await response.text()
            if (signal.aborted) return
            iframe.srcdoc = html
        } catch (err) {
            if (signal.aborted || (err instanceof HttpError && err.kind === 'http')) return
            iframe.srcdoc = `<pre style="color:#fc5130;padding:1rem;">Preview failed: ${err.message}</pre>`
        }
    }
}
