import { describe, expect, it } from 'vitest'
import ConditionalEditor from '../../../assets/js/components/ConditionalEditor.js'
import EditableLegend from '../../../assets/js/components/EditableLegend.js'
import FieldForm from '../../../assets/js/components/FieldForm.js'
import FieldFormModal from '../../../assets/js/components/FieldFormModal.js'
import FieldsEditor from '../../../assets/js/components/FieldsEditor.js'
import FormbuilderDelete from '../../../assets/js/components/FormbuilderDelete.js'
import FormbuilderInlineForm from '../../../assets/js/components/FormbuilderInlineForm.js'
import FormConditional from '../../../assets/js/components/FormConditional.js'
import FormStepper from '../../../assets/js/components/FormStepper.js'
import NotificationLayout from '../../../assets/js/components/NotificationLayout.js'
import TokenInsert from '../../../assets/js/components/TokenInsert.js'

/**
 * Each component's data attribute, which the host's registry mounts it on
 * and under which BaseComponent keeps the element's state, so the state
 * attribute shows the selector the component declares.
 */
describe('Component selectors', () => {
    it.each([
        ['FieldsEditor', FieldsEditor, 'data-formbuilder-rows'],
        ['FormbuilderInlineForm', FormbuilderInlineForm, 'data-formbuilder-inline'],
        ['FormbuilderDelete', FormbuilderDelete, 'data-formbuilder-delete'],
        ['EditableLegend', EditableLegend, 'data-editable-legend'],
        ['FieldFormModal', FieldFormModal, 'data-field-modal'],
        ['FieldForm', FieldForm, 'data-field-form'],
        ['ConditionalEditor', ConditionalEditor, 'data-conditional-editor'],
        ['FormStepper', FormStepper, 'data-form-stepper'],
        ['FormConditional', FormConditional, 'data-form-conditional'],
        ['NotificationLayout', NotificationLayout, 'data-notification-layout'],
        ['TokenInsert', TokenInsert, 'data-token-insert'],
    ])('%s keeps its state under %s', (_name, Component, selector) => {
        const component = new Component({ eventBus: null, logger: null, router: null })
        const element = document.createElement('div')

        component.setState(element, 'ready')

        expect(element.getAttribute(`${selector}-state`)).toBe('ready')
    })
})
