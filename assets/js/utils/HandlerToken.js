/**
 * The host's handler token.
 *
 * Every POST to the add-on's handlers must carry the token the host issues
 * for them. The fields page renders it once, as a hidden input inside a
 * hidden `[data-formbuilder-token]` wrapper:
 *
 *   <div data-formbuilder-token hidden>
 *       <input type="hidden" name="…" value="…">
 *   </div>
 *
 * The input's name belongs to the host, so it is read from the markup and
 * never hard-coded here. The token travels in the request body only; no
 * header carries it.
 */

/**
 * Read the token from the first hidden input inside `[data-formbuilder-token]`.
 *
 * @param {ParentNode} [root]
 * @returns {{name: string, value: string}|null} null when the page has no token
 */
export function handlerToken(root = document) {
    const input = root.querySelector('[data-formbuilder-token] input[type="hidden"]')
    if (!input || !input.name) return null

    return { name: input.name, value: input.value }
}

/**
 * Append the token to a FormData body, unless the body already carries a
 * field of that name (a form rendered with the token inside it).
 *
 * @param {FormData} formData
 * @param {ParentNode} [root]
 * @returns {FormData} the same FormData, for chaining
 */
export function withToken(formData, root = document) {
    const token = handlerToken(root)
    if (token && !formData.has(token.name)) {
        formData.append(token.name, token.value)
    }

    return formData
}
