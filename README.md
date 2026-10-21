# contenir-extension/formbuilder

[![Continuous Integration](https://github.com/contenir-extension/formbuilder/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir-extension/formbuilder/actions/workflows/continuous-integration.yml)

The form builder module for Contenir CMS: the Forms area, with forms, fields, notifications, webhooks and entries,
over [contenir/contenir-formbuilder](https://github.com/contenir/contenir-formbuilder)'s persistence. It is the first
**module**: an add-on with an admin area of its own (the extensions plan's section 10, decision M2). It replaces the
CMS's built-in Forms module without any change for sites: the same place in the rail, the same permissions and the
same `form_*` tables.

It is being built in stages (phase 9, step 2):

| Stage | What it adds |
|---|---|
| 2a | The add-on itself, the Forms area and its home: a card per form, and Add form from a template |
| 2b | Form settings, the fields editor and the preview |
| 2c | Notifications and webhooks |
| 2d | Entries, CSV export, private uploads with signed downloads, and 12-month retention for new forms |

Public sites keep posting entries to the CMS's `/admin/forms/submit`, which stays in the CMS.

## What it adds

**The Forms area**, from `config/admin.xml`. Its id is `forms`, as the built-in module's was, so a role allowed
Forms keeps every forms screen: the CMS guards a module's screens by its area, with the privilege each screen
needs.

**Its screens** are admin handlers at `/admin/extension/formbuilder/<name>`. Where a screen can be described as
data, it is a described screen, drawn like a settings screen. The few that can't be described are templates in
`templates/`, drawn inside the dashboard with the API's helpers only:

| Handler | Screen | Drawn as |
|---|---|---|
| `forms` | the Forms home: a card per form with its status, slug and layout, and Add form | `templates/forms/index.phtml` |
| `form-add`, `form` | Add form, and a form's Settings tab with Delete form | described screens |
| `fields` | a form's Fields tab: its sections, groups, rows and fields, arranged by dragging | `templates/forms/fields.phtml` |
| `preview` | a form's Preview tab: the form as the site renders it, and a test submission | `templates/forms/preview.phtml` |
| `notifications`, `notification-add`, `notification`, `notification-layout` | a form's Notifications tab, and each notification's Details and Layout | `templates/forms/notification*.phtml` |
| `webhooks` | a form's Integrations tab | `templates/forms/webhooks.phtml` |
| `webhook-add`, `webhook` | a webhook's settings | described screens |
| `field-add`, `field-edit` | a field's settings, in the fields editor's modal, or on a page without scripts | `templates/forms/fields/modal.phtml`, `templates/forms/field.phtml` |

The fields editor's other handlers (`section-insert`, `group-delete`, `row-reorder` and the like) answer its scripts
with fragments or JSON. Every one needs Update in the Forms area, as the built-in fields screen did, and changes only
parts of the form the request names.

**Its scripts and styles** are dashboard components and a stylesheet in its published assets. The sources are in
`assets/`; `npm run build` builds them with esbuild and Sass into `public/`, which is committed, and CI checks the
build matches its sources. `npm test` runs their tests (vitest), against markup that
`tests/Integration/Contract/ComponentFixtureTest.php` keeps in step with the templates: regenerate it with
`UPDATE_CONTRACT_FIXTURES=1 vendor/bin/phpunit --filter ComponentFixture`. Every post carries the host's handler
token, which the scripts copy from the hidden field the page prints.

**Its tables** keep their names, `form` and `form_*` (P10), so public sites read them as before. The library's
baseline migration creates them on a new site and leaves an existing site's tables as they are. Any table the
add-on adds later is named `ext_formbuilder_*`.

**Its database** is the site's, which the CMS offers add-ons as the `extensions.database.site` service: a php-db
adapter over the site's own connection.

## Requirements

- PHP 8.3, 8.4 or 8.5
- contenir/contenir-extension-api with tabs on template screens (contenir/contenir-extension-api#12 and #14, until they are tagged)
- contenir/contenir-formbuilder 2.0 (one persistence layer over php-db)
- Node 22, to build the scripts and styles

## Development

```bash
composer install
composer check
npm ci
npm run test:coverage
npm run build
```

`composer check` runs Mago (format, lint, the boundary guard and analysis), both test suites and Infection. The
integration suite runs the add-on on the API's in-memory host, over an in-memory SQLite site database with the form
tables migrated. Format with Mago 1.52.0, which CI pins.

See [contenir-extension/tracking](https://github.com/contenir-extension/tracking#building-an-add-on), the reference
add-on, for how an add-on is laid out, built, tested and released.
