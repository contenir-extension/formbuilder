import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'

/**
 * A token picker's menu item, which inserts its merge tag.
 *
 * Markup, as the host's Dropdown clones it from the picker's `<template>`
 * into the page when a trigger opens it:
 *   <a href="#" data-token-insert="{field:email}" data-token-target="#ntf-subject">…</a>
 *
 * When the target is an input or textarea the token replaces its selection
 * (or goes at its caret), the caret moves past it, the field is focused and
 * an `input` event bubbles from it, so listeners wired to the field see the
 * change. A field with no caret to read, such as an email input, has the
 * token appended. Any other target, including `codemirror` for a
 * notification's Layout editor, gets a document-level `token:insert`
 * CustomEvent with `{ token, target }` for its owner (NotificationLayout)
 * to handle.
 */
export default class TokenInsert extends BaseComponent {
    _getSelector() {
        return 'data-token-insert'
    }

    _init(element) {
        const onClick = (event) => {
            event.preventDefault()
            const token = element.getAttribute('data-token-insert') || ''
            const target = element.getAttribute('data-token-target') || ''
            if (!token) return

            const node = target ? document.querySelector(target) : null
            if (node && (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA')) {
                this._insertAtCaret(node, token)
                return
            }

            document.dispatchEvent(new CustomEvent('token:insert', {
                detail: { token, target },
            }))
        }
        element.addEventListener('click', onClick)

        return {
            cleanup: () => element.removeEventListener('click', onClick),
        }
    }

    /**
     * @param {HTMLInputElement|HTMLTextAreaElement} field
     * @param {string} text
     */
    _insertAtCaret(field, text) {
        const hasCaret = field.selectionStart !== null
        const start = hasCaret ? field.selectionStart : field.value.length
        const end = hasCaret ? field.selectionEnd : field.value.length
        field.value = field.value.slice(0, start) + text + field.value.slice(end)
        if (hasCaret) {
            const caret = start + text.length
            field.setSelectionRange(caret, caret)
        }
        field.focus()
        field.dispatchEvent(new Event('input', { bubbles: true }))
    }
}
