# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2026-10-21

### Added

- The add-on, as a module (phase 9, step 2a): the Forms area, with the id `forms` the CMS's built-in module had,
  so its screens keep that module's place in the rail and its permissions.
- The Forms home, `/admin/extension/formbuilder/forms`: a card per form with its status, slug and layout, its title
  leading to its entries and its footer to each of its screens, and Add form offering the built-in templates (contact,
  newsletter, product enquiry, event registration, booking request and feedback) or a blank form.
- Requires contenir/extension-api `^0.4 || ^0.5` (0.4 for tabs on described screens) and contenir/contenir-formbuilder `^2.0.0-RC3` (the first release with its single persistence layer) from Packagist.
- The `form` and `form_*` tables through contenir/contenir-formbuilder's baseline migration, which leaves a site's
  existing tables as they are (P10).
- A form's Fields tab, `/admin/extension/formbuilder/fields?form=<id>` (phase 9, step 2b): its sections, groups, rows
  and fields as the built-in module drew them, arranged by dragging, added, renamed and deleted in place, and edited
  in the field modal, with a page of their own for a browser without scripts. Every request needs Update in the Forms
  area, as the built-in fields screen did, and changes only parts of the form it names.
- The builder's scripts as dashboard components, built from `assets/` into `public/js` with esbuild, and its styles
  into `public/css/formbuilder.css`, which the dashboard loads on every page. Every post carries the host's handler
  token. `npm run build` rebuilds them, and CI checks the committed build matches its sources.

- A form's Preview tab, `/admin/extension/formbuilder/preview?form=<id>`: the form as the site renders it, and a test
  submission that runs its checks and sends its notifications with `Test: ` before their subjects, storing no entry
  and calling no webhook. It needs View, as the built-in preview did. Its mail goes through the site's, which the
  host lends as `Contenir\Extension\Api\Mail\MailerInterface` (contenir/contenir-extension-api#15); without it,
  each notification is logged as not sent. The preview's scripts (FormStepper for a stepped form, FormConditional for
  fields shown by condition) and its styles move into the add-on with it.

- A form's Notifications tab: its notifications, each turned on or off, deleted, or opened on its Details (who it goes
  to, is from and replies to, and its subject, with the token picker) and Layout (its email's HTML in CodeMirror beside
  a live preview filled in with sample values). Each sub-tab saves without touching the other's settings.
- A form's Integrations tab: its webhooks, each added and edited on a described screen and deleted from the list.
- The scripts these use, NotificationLayout and TokenInsert, and their styles. CodeMirror comes with
  NotificationLayout, which loads only on a notification's Layout.

### Changed

- A webhook's method is POST or PUT, as its screen offers; anything else posted is read as POST.
- A field added in the modal goes at its row's end, and an edited field keeps its place. The built-in module posted
  no sort, read it as 0, and so put both first in their row. Editing a webhook keeps its place too.
- Requires contenir/contenir-extension-api (the API's name since v0.6.1) with tabs on template screens (contenir/contenir-extension-api#12 and #14), from its main
  until that is tagged.
