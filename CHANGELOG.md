# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.2.0] - 2026-10-27

Requires contenir/contenir-extension-api 0.7.1 or later.

### Changed

- Delete form is drawn as a destructive operation, apart from the rest: it takes the form, its fields,
  notifications, integrations and every entry. It still asks first.
- Fields, rows, validators and conditions are dragged on Parallelogram's drag engine (`@parallelogram-js/core`
  `^0.7.22`) in place of SortableJS, which is no longer required. A row and a list of rules move up and down only;
  a field moves across as well, between columns and rows. A reorder posts only when a drop changed the order, and
  every row's field strip shows it can take a drop only while a field, not a row, is dragged.

### Removed

- The add-on's own `[data-identifier]` component. The CMS registers that selector itself, so it left the add-on's
  out with a console warning; a field's ID now follows its label through the CMS's component alone.

## [0.1.0] - 2026-10-22

The form builder as an add-on, in place of the CMS's built-in Forms module, road-tested on a copy of a live site.

### Added

- The add-on, as a module (phase 9, step 2a): the Forms area, with the id `forms` the CMS's built-in module had,
  so its screens keep that module's place in the rail and its permissions.
- The Forms home, `/admin/extension/formbuilder/forms`: a card per form with its status, slug and layout, its title
  leading to its entries and its footer to each of its screens, and Add form offering the built-in templates (contact,
  newsletter, product enquiry, event registration, booking request and feedback) or a blank form.
- Requires contenir/contenir-extension-api `^0.7` (tabs, mail, downloads, parent pages, private storage and site
  settings) and contenir/contenir-formbuilder `^2.0.1` from Packagist.
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

- A form's Entries tab (phase 9, step 2d): its entries newest first, fifty to a page, found by entry number or
  address, status (with each status's count) and period, each with a menu to view it, move it to complete, spam or
  archive, or delete it. An entry shows its answers a card per group of the form, and values stored under fields the
  form no longer has. An entry held as spam and marked complete has its notifications and webhooks sent then, once.
  Viewing needs View, a status Update and deleting Delete, as the built-in module's did.
- Export CSV, without spam or including it, needing Export: streamed as it is read through the host's download
  (contenir/contenir-extension-api#17), with no limit on rows, and a cell a spreadsheet would read as a formula
  written so it can't run.

- Private uploads (FRM-2): an entry's file downloads from the storage the host lends as contenir/contenir-storage's
  `StorageManager`, from its `private` profile, which no URL reaches, or from the site's public storage until it is
  moved. Notification emails link a file with the new `{file:<field>}` tag, offered in the tag picker: a link signed
  with the add-on's own key (`ext_formbuilder_key`, made on first use) that `/api/extension/formbuilder/file` honours
  for seven days. Move uploads to private storage, on the Forms home, needing Update, moves every form's uploads
  there once, following a file private storage renamed. Deleting an entry deletes its files too.
- Requires contenir/contenir-storage `^2.3`, for `StorageInterface::read()`
  (contenir/contenir-storage#12).
- Retention (FRM-3): a new form keeps its submissions' personal details for 365 days, shown in its Retention field,
  which an editor can clear to keep them for ever; forms made before keep what they had. Entries past their form's
  period are redacted every night at 03:00 (their answers and uploaded files deleted, their address cleared), and
  Redact expired entries on the Forms home, needing Delete, does it at once. An entry whose files can't be deleted is
  kept and tried again next time.
- The token picker offers `{settings:<key>}` under Site settings, for any of the site's settings the host
  lends read-only.

- Tools in the Forms menu (Simon, 21 Oct): Redact expired entries and Move uploads to private storage each open a
  page saying what they do (how many entries a redaction would take now), with the button there, instead of sitting
  in the Forms home's header. `FormsToolsMenu` declares the group.

- Preview says what its test notifications did: how many were sent with Test: subjects and how many failed (the log
  says why), instead of always saying they were sent.

### Changed

- A webhook's method is POST or PUT, as its screen offers; anything else posted is read as POST.
- A field added in the modal goes at its row's end, and an edited field keeps its place. The built-in module posted
  no sort, read it as 0, and so put both first in their row. Editing a webhook keeps its place too.
- Requires contenir/contenir-extension-api (the API's name since v0.6.1) with tabs on template screens (contenir/contenir-extension-api#12 and #14), from its main
  until that is tagged.
