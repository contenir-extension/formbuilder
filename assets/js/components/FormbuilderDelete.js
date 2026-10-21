import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { request, TIMEOUTS } from '../utils/HttpClient.js'
import { withToken } from '../utils/HandlerToken.js'

/**
 * Delete links in the form builder's section, group and field menus.
 *
 * Mounted on `<a data-formbuilder-delete>` links. The menus are rendered from
 * a `<template>` by the host's Dropdown, which fills in each link's href (the
 * add-on's delete handler for that entity) and its `data-formbuilder-target`
 * (a CSS selector for the entity's node on the page):
 *
 *   <a href="…/section-delete?…" data-formbuilder-delete
 *      data-formbuilder-target=".builder__section[data-section-id=&quot;601&quot;]">Delete</a>
 *
 * A click POSTs a body holding only the host's handler token to the href.
 * The handler answers JSON `{ ok: true }`, and on ok the target node is
 * removed. Any failure leaves the page as it was.
 */
export default class FormbuilderDelete extends BaseComponent {
    _getSelector() {
        return 'data-formbuilder-delete'
    }

    _init(link) {
        const onClick = (event) => {
            event.preventDefault()
            this._delete(link)
        }
        link.addEventListener('click', onClick)

        return {
            cleanup: () => link.removeEventListener('click', onClick),
        }
    }

    /**
     * @param {HTMLAnchorElement} link
     * @returns {Promise<void>} never rejects
     */
    _delete(link) {
        return request(link.href, {
            method:      'POST',
            body:        withToken(new FormData()),
            headers:     { 'Accept': 'application/json' },
            credentials: 'same-origin',
            timeoutMs:   TIMEOUTS.MUTATE,
        })
            .then((response) => response.json())
            .then((payload) => {
                if (payload.ok) this._removeTarget(link)
            })
            .catch(() => {})
    }

    _removeTarget(link) {
        const selector = link.getAttribute('data-formbuilder-target')
        if (!selector) return
        const node = document.querySelector(selector)
        if (node) node.remove()
    }
}
