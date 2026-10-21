import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { setSectionEnabled } from '../utils/FormSection.js'

/**
 * FormConditional — show/hide a form column based on other field values.
 *
 * Registered against `[data-form-conditional]`. The attribute carries a
 * rule JSON in the same shape that
 * `\Contenir\FormBuilder\Conditional\RuleEvaluator` consumes server-side:
 *
 *   {"show_when": {"all": [{"field": "x", "op": "equals", "value": "y"}]}}
 *
 * The component listens for `change` and `input` events on the closest
 * `<form>` ancestor, re-evaluates the rule against current form values,
 * and toggles the wrapper's `hidden` attribute. Inputs inside a hidden
 * wrapper are also marked `disabled` so the browser excludes their
 * values from submission — matching the server-side gating in
 * `FormSubmissionService::applyConditionalGating`.
 *
 * Operator semantics MUST track RuleEvaluator.php — any new operator
 * added there has to be mirrored in `_matches` below.
 */
export default class FormConditional extends BaseComponent {
    _getSelector() {
        return 'data-form-conditional'
    }

    _init(element) {
        const form = element.closest('form')
        if (!form) return {}

        let rule = null
        try {
            rule = JSON.parse(element.getAttribute('data-form-conditional') || 'null')
        } catch (_e) {
            return {}
        }
        if (!rule) return {}

        const onChange = () => this._evaluate(element, form, rule)
        form.addEventListener('change', onChange)
        form.addEventListener('input', onChange)

        /**
         * Apply initial state in case server-side rendering and live
         * values disagree (e.g. browser autofill mid-load).
         */
        this._evaluate(element, form, rule)

        return {
            cleanup: () => {
                form.removeEventListener('change', onChange)
                form.removeEventListener('input', onChange)
            },
        }
    }

    _evaluate(element, form, rule) {
        const values = this._collectValues(form)
        const visible = this._shouldShow(rule, values)
        element.hidden = !visible
        setSectionEnabled(element, visible)
    }

    _collectValues(form) {
        const out = {}
        for (const el of form.elements) {
            if (!el.name) continue
            const baseName = el.name.replace(/\[\]$/, '')
            if (el.type === 'checkbox') {
                if (el.checked) {
                    if (el.name.endsWith('[]')) {
                        out[baseName] = out[baseName] || []
                        out[baseName].push(el.value)
                    } else {
                        out[baseName] = el.value
                    }
                }
            } else if (el.type === 'radio') {
                if (el.checked) out[baseName] = el.value
            } else if (el.tagName === 'SELECT' && el.multiple) {
                out[baseName] = Array.from(el.selectedOptions).map((o) => o.value)
            } else {
                out[baseName] = el.value
            }
        }
        return out
    }

    _shouldShow(rule, values) {
        if (!rule || typeof rule !== 'object') return true
        const showWhen = rule.show_when
        if (!showWhen || typeof showWhen !== 'object') return true
        const combinator = Array.isArray(showWhen.any) ? 'any' : 'all'
        const conditions = showWhen[combinator] || []
        if (!Array.isArray(conditions) || conditions.length === 0) return true

        if (combinator === 'any') {
            return conditions.some((c) => this._matches(c, values))
        }
        return conditions.every((c) => this._matches(c, values))
    }

    _matches(condition, values) {
        if (!condition || typeof condition !== 'object') return false
        const field = String(condition.field || '')
        const op    = String(condition.op || '')
        if (!field || !op) return false

        const current  = Object.prototype.hasOwnProperty.call(values, field) ? values[field] : null
        const expected = condition.value

        switch (op) {
            case 'equals':       return this._equalsAny(current, expected)
            case 'not_equals':   return !this._equalsAny(current, expected)
            case 'is_empty':     return this._isEmpty(current)
            case 'is_not_empty': return !this._isEmpty(current)
            case 'contains':     return this._containsAny(current, expected)
            default:             return false
        }
    }

    _equalsAny(current, expected) {
        const exp = this._scalarStr(expected)
        if (Array.isArray(current)) {
            return current.some((v) => this._scalarStr(v) === exp)
        }
        return this._scalarStr(current) === exp
    }

    _containsAny(current, expected) {
        const needle = this._scalarStr(expected)
        if (needle === '') return false
        if (Array.isArray(current)) {
            return current.some((v) => this._scalarStr(v).includes(needle))
        }
        return this._scalarStr(current).includes(needle)
    }

    _isEmpty(value) {
        if (value === null || value === undefined) return true
        if (Array.isArray(value)) return value.length === 0
        return this._scalarStr(value) === ''
    }

    _scalarStr(value) {
        if (value === null || value === undefined || value === false) return ''
        if (typeof value === 'object') return ''
        return String(value)
    }
}
