const FIXTURES = import.meta.glob('../fixtures/*/*.html', {
    query: '?raw',
    import: 'default',
    eager: true,
})

/**
 * Markup the fields screen renders, which tests load instead of
 * hand-written markup so the JS is exercised against what the server
 * sends. They were captured from the CMS's built-in Forms module (the
 * fields page's `.builder` and a field's `[data-conditional-editor]`),
 * whose markup the add-on keeps.
 *
 * @param {string} name e.g. "components/fields-editor-form"
 * @returns {string} the saved HTML
 */
export function fixture(name) {
    const path = Object.keys(FIXTURES).find((key) =>
        key.endsWith(`/${name}.html`),
    )
    if (path === undefined) {
        throw new Error(`No fixture named ${name}`)
    }

    return FIXTURES[path]
}

/**
 * A fixture's element, parsed as the browser would parse the response.
 *
 * @param {string} name
 * @param {string} selector
 * @returns {Element}
 */
export function fixtureElement(name, selector) {
    const doc = new DOMParser().parseFromString(fixture(name), 'text/html')
    const element = doc.querySelector(selector)
    if (! element) {
        throw new Error(`${name}.html has no ${selector}`)
    }

    return element
}

/**
 * A fixture's element, imported into this document and appended to its
 * body, so a component can mount on it and the test can fire events at it.
 *
 * @param {string} name
 * @param {string} selector
 * @returns {Element}
 */
export function mountedFixtureElement(name, selector) {
    const element = document.importNode(fixtureElement(name, selector), true)
    document.body.appendChild(element)

    return element
}
