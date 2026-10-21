/** The token name and value the tests' fields page carries; the real name is the host's. */
export const TOKEN_NAME  = 'host_handler_token'
export const TOKEN_VALUE = 'test-token-value'

/**
 * Append the fields page's handler token wrapper to the body, as the host
 * renders it.
 *
 * @param {string} [name]
 * @param {string} [value]
 * @returns {HTMLElement} the `[data-formbuilder-token]` wrapper
 */
export function addHandlerToken(name = TOKEN_NAME, value = TOKEN_VALUE) {
    const wrapper = document.createElement('div')
    wrapper.setAttribute('data-formbuilder-token', '')
    wrapper.hidden = true
    wrapper.innerHTML = `<input type="hidden" name="${name}" value="${value}">`
    document.body.appendChild(wrapper)

    return wrapper
}
