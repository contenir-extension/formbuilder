import { afterEach, describe, expect, it } from 'vitest'
import { handlerToken, withToken } from '../../../assets/js/utils/HandlerToken.js'
import { addHandlerToken, TOKEN_NAME, TOKEN_VALUE } from '../TestAsset/token.js'

afterEach(() => {
    document.body.innerHTML = ''
})

describe('handlerToken', () => {
    it('reads the name and value of the hidden input in the token wrapper', () => {
        addHandlerToken('csrf_from_host', 'abc123')

        expect(handlerToken()).toEqual({ name: 'csrf_from_host', value: 'abc123' })
    })

    it('reads the first hidden input when the wrapper holds more than one', () => {
        const wrapper = addHandlerToken('first', '1')
        wrapper.insertAdjacentHTML('beforeend', '<input type="hidden" name="second" value="2">')

        expect(handlerToken()).toEqual({ name: 'first', value: '1' })
    })

    it('ignores a hidden input outside the token wrapper', () => {
        document.body.innerHTML = '<input type="hidden" name="other" value="x">'

        expect(handlerToken()).toBeNull()
    })

    it('returns null when the page has no token', () => {
        expect(handlerToken()).toBeNull()
    })

    it('returns null when the token input has no name', () => {
        addHandlerToken('', 'abc123')

        expect(handlerToken()).toBeNull()
    })

    it('looks only inside the root it is given', () => {
        addHandlerToken('on_page', '1')
        const root = document.createElement('div')
        root.innerHTML = '<div data-formbuilder-token hidden><input type="hidden" name="in_root" value="2"></div>'

        expect(handlerToken(root)).toEqual({ name: 'in_root', value: '2' })
    })
})

describe('withToken', () => {
    it('appends the token to the form data', () => {
        addHandlerToken()

        const data = withToken(new FormData())

        expect(data.get(TOKEN_NAME)).toBe(TOKEN_VALUE)
    })

    it('returns the same form data it was given', () => {
        const data = new FormData()

        expect(withToken(data)).toBe(data)
    })

    it('does not append the token twice when the form data already has it', () => {
        addHandlerToken()
        const data = new FormData()
        data.append(TOKEN_NAME, 'from-the-form')

        withToken(data)

        expect(data.getAll(TOKEN_NAME)).toEqual(['from-the-form'])
    })

    it('leaves the form data alone when the page has no token', () => {
        const data = withToken(new FormData())

        expect([...data.keys()]).toEqual([])
    })

    it('reads the token from the root it is given', () => {
        const root = document.createElement('div')
        root.innerHTML = '<div data-formbuilder-token hidden><input type="hidden" name="in_root" value="2"></div>'

        const data = withToken(new FormData(), root)

        expect(data.get('in_root')).toBe('2')
    })
})
