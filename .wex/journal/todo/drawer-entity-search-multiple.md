# Entity search field: multiple values as chips, list-only variant, smarter autocomplete

Opened: 2026-10-09
Updated: 2026-10-09
Author: agent:archeology-drawers

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It comes from the 2026-10 "bottom of the drawers" archaeology pass. Read it, then discuss it with the owner: every design choice below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - **Before anything else**: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/.wex/local/package-agent-intro.md` — where you stand in the suite, what a package must look like, how to work, and how to judge a drawer todo.
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2`, `sources.md.j2`, and the drawers page linked below.
> - Safety: `NETWORK/local/network` runs on production data — read its code only, never run anything against it. Anonymize anything taken from it. Never copy secrets found in its history.

Drawers page: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/drawers-screens.md.j2`, section 3.

## What it is

network's Vue 2 search field family, used in production for mail recipients, mail attachments, project contributors and invoice relations/transactions:

- a search box whose autocomplete stays open while hovered (so clicking a result is not lost on blur), flips **above** the input when there is not enough room below inside the nearest scrollable parent, and whose Escape key blurs and **restores the value from before the edit**;
- a form widget that holds **several selected entities shown as chips** (entity card + "×"), with a `maxValues` cap (autocomplete closes at the cap), duplicate guard, hidden input filled with comma-separated ids, the text input hidden once a single-value field is filled, client-side "required" blocking both normal and AJAX submit;
- a **list-only variant** (no input: just the chips) for attachments, where removing asks for confirmation, every change auto-submits the form, and the list reloads when another component signals new attachments for that form.

`symfony-search` today has `src/Form/Type/EntitySearchInputType.php` + `assets/vue/form/fields/entity-search-input.vue` (single value); `symfony-design-system` has `assets/components/select-search` (Escape handling only). None of the points above.

## Where the sources are

Read-only, in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user/`:

- `src/Wex/BaseBundle/Resources/js/vue/search-box.vue`, `templates/vue/search-box.vue.twig`, `css/vue/search-box.scss` (flip logic in `search()`, uses `findScrollParent` from `js/helpers/DomHelper`)
- `src/Wex/BaseBundle/Resources/js/vue/{search-results,entity-selected}.vue` + templates
- `src/Wex/BaseBundle/Resources/{js,templates,css}/forms_themes/vue/entity-search.*`
- `front/vue/forms/attachment-entities.vue(.twig)`, `front/vue/forms/mail-recipient-search.vue(.twig)`
- uses: `front/pages/entity/project/tabs/view/vue/contributors.vue.twig`, `front/pages/entity/invoice/tabs/edit/vue/{relations,transactions}.vue.twig`
- 2019 ancestors: `git -C /home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/repo.git show 2eb36455e^:project/assets/js/vue/SearchBox.vue`

## What to recover

Behaviour only (the code is Vue 2 options API with `extends` chains and globals — do not port it):

1. `multiple` + `max_values` options on `EntitySearchInputType`, chips display, duplicate guard, transformer accepting a list.
2. A "list-only" mode (no text input) with optional confirm-on-remove and auto-submit-on-change.
3. Autocomplete placement flip against the scroll parent; keep open while hovered; Escape restores.

## Proposed modern shape (suggestion)

- Options on the existing type rather than a new type: `multiple` (bool), `max_values` (int|null), `input` (bool, false = list-only), `remove_confirm` (string|null), `submit_on_change` (bool).
- Chips rendered with the same selected title/subtitle/icon options the single mode already has.
- Placement flip as a small generic helper in `symfony-design-system` (or `js-helpers`) so `select-search` and the dropdowns can use it too.
- The "attachments changed" signal as a DS event rather than a global string event.

## Open questions

- Multiple values: hidden field with ids, or one hidden input per value (`name[]`)? The legacy used a comma list.
- Should list-only + auto-submit live here, or in `symfony-media` for attachments only?
- Is the flip helper wanted in the design system for all floating panels?
