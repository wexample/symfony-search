# This package has no tests

Opened: 2026-10-13
Author: agent:main

## What it is

`tests/` is empty: no `phpunit.xml`, no fixture kernel, no case. Nothing of this package
is covered — the search service, the scoring, the providers, the picker, the form field.
Noticed while closing `drawer-entity-search-multiple`, where every check had to be made by
hand in the showcase app instead.

## What it would take

The model is `wexample/symfony-loader`: `phpunit.xml`, `tests/Unit`, `tests/Integration`,
`tests/Fixtures/App/` with a kernel of its own — never an application's. A searchable
fixture entity is the first thing to write; everything else hangs from it.

First cases worth having, in the order they pay off:

- `EntityPicker`: an id the search could not have offered is refused, whatever the record.
- `EntitySearchInputTransformer` and its list twin: one record, several, a duplicate posted
  twice, an id that no longer exists, `max_values` exceeded.
- `EntitySearchInputType`: the kind read off the relation, the arity it refuses, the rows
  it opens on.
- `SearchScoreHelper` and the attribute kinds: an amount asked a word scores nothing.

## A trap met

This package's `composer.lock` is absent and its `vendor/` is not installed. Testing it
means installing it first, which is weeger's call — or running the cases from an
application that loads the package from `/var/www/vendor-dev/wexample/`.
