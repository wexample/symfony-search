# Entity search field: multiple values as chips, list-only variant, smarter autocomplete

Opened: 2026-10-09
Updated: 2026-10-13
Author: agent:archeology-drawers, then agent:main

## Status

Built, on a design validated by weeger beforehand on an HTML mockup
(`tmp/entity-search-mockup.html`, not versioned). What is left is at the bottom of this
page; the drawer page this came from is
`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/drawers-screens.md.j2`,
section 3, and the legacy sources are listed there.

## Decisions taken

- **Chips: yes**, built on the design system's `bar` — no new component. A field searched
  one record at a time draws them as capsules in a row; a list nobody searches keeps the
  column, its rows being few and what they say long.
- **Auto-submit: not here.** `submit_on_change` already exists on `AbstractForm` of
  `wexample/symfony-forms` and belongs to the form, not to one field. The field only owes
  it a `change` event, which it now raises — its inputs being hidden and filled by hand,
  nothing else would.
- **Placement: the existing pipe.** No `findScrollParent` port: the dropdown opens in the
  top layer (`popover`) and is placed by `floatingPlace` of `symfony-design-system`, as the
  design system's `select` already does. Leaving the clipping context beats measuring it.
- **Keyboard: the keyboard service**, which the box already used. Escape closes; pressed
  again it puts back the question the field was entered with, and only then — a box with
  nothing left to answer leaves Escape to whatever stands behind it.
- **Keep-open-on-hover: already covered.** The legacy needed it because blur closed the
  dropdown; here the overlay service decides, and a blur closes nothing.
- **Several values post `name[]`**, one input per record — not the legacy's comma list.

## Done

- `multiple`, `max_values`, `search`, `remove_confirm` on `EntitySearchInputType`;
  `EntitySearchInputListTransformer`; the cap and the duplicate guard checked on the
  server; the relation's arity has the last word on `multiple`.
- Front: rows or capsules, a remove per record, the confirm service, the `change` event,
  the id moved onto the search input so the label points at something focusable.
- Search box: top-layer dropdown following the field, second Escape, `reset()` after a pick.
- A list with no box says it holds nothing, rather than leaving a label over a blank.
- Demo, in `symfony-design-system-demo`'s `FormSubmitBehaviorDemoForm` — weeger allowed the
  reach outside the package: a `multiple` field capped at three, and a list without its box
  opening on two records, with the question before one goes.
- Verified in the showcase app through a browser: both placements, the cap, the duplicate,
  the removal, the two Escapes, the dropdown outliving the blur, the single field unchanged.
  Posted lists checked over HTTP: two values, over the cap, duplicates, an unknown id.

## What is left

- **The "attachments changed" signal is not carried over.** The legacy reloaded the list
  from `options.updatePath` when another component said new attachments had arrived. Not
  built: the field cannot invent that path, and the reload belongs to whoever fills the
  list — `symfony-media`'s uploader. An ajax form with `submit_on_change` re-renders the
  field with its new rows anyway, which covers most of it. To be decided.
- **No tests.** This package has an empty `tests/`, no `phpunit.xml` and no fixture kernel:
  a chantier of its own, not a step of this one.
- **A duplicate flip lives in `symfony-design-system`**: `button-menu` measures its own
  placement instead of using `FloatingHelper`. Reported, not touched — another package.
