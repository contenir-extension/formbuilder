import { afterEach, describe, expect, it } from 'vitest'
import { setSectionEnabled } from '../../../assets/js/utils/FormSection.js'

afterEach(() => {
    document.body.innerHTML = ''
})

/**
 * A validation rule's options, as a field's form renders them: an input,
 * a select and a textarea, beside a button that is not a form control.
 */
function makeOptions() {
    const options = document.createElement('div')
    options.setAttribute('data-validator-options', '')
    options.innerHTML = `
        <input name="validators[0][options][min]">
        <select name="validators[0][options][mode]"><option>a</option></select>
        <textarea name="validators[0][options][message]"></textarea>
        <button type="button">Help</button>
    `
    document.body.appendChild(options)

    return options
}

function controls(container) {
    return [...container.querySelectorAll('input, select, textarea')]
}

describe('setSectionEnabled', () => {
    it('disables every input, select and textarea when passed false', () => {
        const options = makeOptions()

        setSectionEnabled(options, false)

        expect(controls(options).map((control) => control.disabled)).toEqual([true, true, true])
    })

    it('re-enables every control when passed true', () => {
        const options = makeOptions()
        setSectionEnabled(options, false)

        setSectionEnabled(options, true)

        expect(controls(options).map((control) => control.disabled)).toEqual([false, false, false])
    })

    it('does not touch controls outside the container', () => {
        const options = makeOptions()
        const outside = document.createElement('input')
        document.body.appendChild(outside)

        setSectionEnabled(options, false)

        expect(outside.disabled).toBe(false)
    })

    it('leaves buttons alone', () => {
        const options = makeOptions()

        setSectionEnabled(options, false)

        expect(options.querySelector('button').disabled).toBe(false)
    })
})
