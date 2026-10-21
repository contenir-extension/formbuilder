/**
 * Enable or disable every form control (input / select / textarea) within a
 * container in one call. Disabling also drops those controls from form
 * submission — usually the point: an inactive or hidden section shouldn't post
 * stale values.
 *
 * @param {Element} container
 * @param {boolean} enabled
 */
export function setSectionEnabled(container, enabled) {
    container.querySelectorAll('input, select, textarea').forEach((control) => {
        control.disabled = !enabled
    })
}
