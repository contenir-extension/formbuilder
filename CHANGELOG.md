# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2026-10-20

### Added

- The add-on, as a module (phase 9, step 2a): the Forms area, with the id `forms` the CMS's built-in module had,
  so its screens keep that module's place in the rail and its permissions.
- The Forms home, `/admin/extension/formbuilder/forms`: a card per form with its status, slug and layout, its title
  leading to its entries and its footer to each of its screens, and Add form offering the built-in templates (contact,
  newsletter, product enquiry, event registration, booking request and feedback) or a blank form.
- Requires contenir/extension-api `^0.3` and contenir/contenir-formbuilder `^2.0.0-RC3` (the first release with its single persistence layer) from Packagist.
- The `form` and `form_*` tables through contenir/contenir-formbuilder's baseline migration, which leaves a site's
  existing tables as they are (P10).
