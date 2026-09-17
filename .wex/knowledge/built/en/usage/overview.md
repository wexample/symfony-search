`wexample/symfony-search` answers one question — *what, here, is called this?* — across
things that have nothing in common: rows in a table, pages in a router, and whatever else a
host application decides to make findable.

## Installation

```bash
composer require wexample/symfony-search
```

Then register the bundle in `config/bundles.php`:

```php
Wexample\SymfonySearch\WexampleSymfonySearchBundle::class => ['all' => true],
```

## Making an entity findable

The class says it is findable; the properties say on what.

```php
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[Searchable(contexts: [AppSearchContext::HEADER], route: 'app_invoice_show')]
class Invoice extends AbstractEntity
{
    #[ORM\Column]
    #[SearchText]
    protected string $label;

    #[ORM\Column]
    #[SearchReference(points: 30)]
    protected string $reference;

    #[ORM\Column]
    #[SearchAmount(points: 40)]
    protected float $priceTotal;
}
```

Each attribute says what the property *is*, and the kind is the whole point: it decides the
SQL — a title is searched with `LIKE`, an amount with `=` on its absolute value, an address
whole — and the points, through the matching method of src/Class/SearchScore.php.
A kind that does not fit the query's shape adds no clause and no points: an amount is not
asked `dupont`, a title is not asked `15428`. So `15428` finds the invoice of 15428 before
the log whose id happens to be 15428.

The kinds shipped, each with its default points: `SearchText`, `SearchReference`,
`SearchAmount`, `SearchEmail`, `SearchId`, under src/Attribute/.

Declared on the property, the field travels with a renaming, and **a trait bringing a
column brings the way it is searched with it**. `wexample/symfony-helpers` does exactly
that: `HasTitleTrait`, `HasNameTrait`, `HasBodyTrait`, `HasDescriptionTrait` and
`HasEmailTrait` each carry the attribute for the column they bring, so an entity using them
is findable on those columns with a bare `#[Searchable]` and nothing else:

```php
#[ORM\Entity]
#[Searchable]
class DemoRoom extends AbstractEntity
{
    use HasNameTrait;
}
```

Only entities carrying `#[Searchable]` are read at all, so a trait may carry the attribute
without making every user of it findable. And that package requires nothing from this one:
an attribute is inert until something reflects on it, and only this bundle's registry ever
does — on entities carrying `#[Searchable]`, which cannot exist without it either. The
dependency would otherwise close a circle, the api requiring helpers and this package
requiring the api.

### Taking a field back

An entity using a trait it did not write may refuse what the trait declared, either where
the reader of the entity will see it:

```php
#[SearchIgnore]
#[ORM\Column]
protected ?string $title = null;
```

or on the class, for the entity that would rather not redeclare the property:

```php
#[Searchable(except: ['title'])]
```

### A property that cannot speak for itself

A column whose property is out of reach — a trait of a package that will not name this one,
a mapping declared in XML — is named on the class instead:

```php
#[Searchable(fields: ['body' => new SearchText(points: 5)])]
```

Same classes, same kinds, same points; only the place changes. A property able to carry the
attribute says it itself.

## When the points are not a list

Six of the legacy's seven scoring functions were a field list in disguise. The seventh
halved its points in the header and doubled them on one field, and that one names a class:

```php
#[Searchable(scoring: TransactionSearchScoring::class)]
```

`wex app::state/rectify` writes `src/Search/TransactionSearchScoring.php`, implementing
src/Interface/SearchScoringInterface.php. It is handed the same builder the
declared fields go through, so it reads like the attribute would:

```php
public function score(SearchQuery $query, AbstractEntity $entity, SearchScore $score): void
{
    $score
        ->text($entity->getDescription())
        ->amount($entity->getAmount(), points: 80)
        ->email($entity->getContact()?->getEmail())
        ->inContext(SearchContext::HEADER, factor: .5);
}
```

Each method keeps the guard its kind needs — `->amount()` says nothing to a word,
`->email()` nothing to a fragment — so the class never asks `is_numeric()` itself. The
attributes on the properties still say what the SQL looks in; the class says what it is
worth.
A scoring class is a service: it may take the security or anything else in its constructor.

## Asking

```php
$results = $searchService->search(
    new SearchQuery('dupont', AppSearchContext::HEADER, maxResults: 10)
);
```

Over HTTP, the results are the collection of one entity like any other:

```
GET /api/search-result/list?search=dupont&context=header&length=10
```

`type` restricts the answer to one kind — `?type=invoice` for one entity, `?type=route` for
pages only.

## When the entity has to reason

`#[Searchable]` covers the entity whose answer is a field list. The one whose answer depends
on who is asking — the legacy restricted its user list to other members, or to the user
alone — adds `provider: true` and gets a class:

```php
#[Searchable(fields: ['username', 'email'], provider: true)]
class User extends AbstractEntity
```

`wex app::state/rectify` then writes `src/Search/UserSearchProvider.php`, extending
src/Class/Provider/AbstractEntitySearchProvider.php. Only `configureQuery()`
is left to fill:

```php
protected function configureQuery(QueryBuilder $builder, SearchQuery $query): void
{
    $builder->andWhere(EntitySearchRunner::ALIAS.'.active = true');
}
```

The attribute stays on the entity, and everything in it — fields, title, route, weight —
is still what the provider searches with. What `provider: true` changes is only who runs
the query: the generic provider steps aside, because both answering would show every record
twice.

## Searching something that is not an entity

Implement src/Interface/SearchProviderInterface.php — usually by extending
src/Class/Provider/AbstractSearchProvider.php, which reads the key off the
class name and handles `supports()`. Autoconfiguration does the registration: a provider
joins the search by existing.

```php
class CommandSearchProvider extends AbstractSearchProvider
{
    public function provide(SearchQuery $query): iterable
    {
        foreach ($this->application->all() as $name => $command) {
            $score = SearchScoreHelper::fieldsScore($query->terms, [$name, $command->getDescription()]);

            if ($score > 0) {
                yield $this->createResult($name, $name)->setScore($score);
            }
        }
    }
}
```

The same road is the one to take for an entity whose visibility depends on who is asking:
`#[Searchable]` covers the entities that do not reason, and a provider written by hand
covers the ones that do.

## The front

The bundle ships its own section — src/Controller/Pages/SearchController.php
and assets/pages/search/ — which reads the application it runs in rather than
describing itself: the providers come from the tag, the searchable entities from the
attributes they carry, the endpoint from the router. An application lists it with one line
in its layout:

```twig
{{ menu_item_collapsible_from_controller(render_pass, 'Wexample\\SymfonySearch\\Controller\\Pages') }}
```

A search box is dynamic through and through, so it is a Vue component —
assets/vue/search/search-box.vue — and so is the row it draws for each result,
assets/vue/search/search-result.vue. They live here rather than in
`wexample/symfony-design-system`, because they know this package's API: the box asks the
`searchResult` repository generated from src/Entity/SearchResult.php and sends
it `search`, `context` and `type`. What they borrow from the design system is its
vocabulary — the `bar` partial a row is drawn as — which is the part that knows nothing of
searching. The host application registers the repository on its API client:

```typescript
protected getRepositoryClasses() {
  return [...ownRepositories, ...searchRepositories];
}
```

Dropped anywhere in a template:

```twig
{{ vue(render_pass, '@WexampleSymfonySearchBundle/vue/search/search-box', { context: 'header', length: 8 }) }}
```

### One row per kind of result

The box resolves the row from the result's `type`: a component registered as
`search-result-<type>` draws it, the default row draws everything else — pages included,
which is why nothing has to be declared for them. An application that wants its invoices
to look like invoices extends the box and registers the rows it adds, one Vue component
per entity, each with its `.vue.twig` required and its `.scss`:

```js
export default {
  extends: SearchBox,
  template: '#vue-template-app-vue-search-app-search-box',
  components: { SearchResultInvoice, SearchResultContact }
};
```

A row extends `search-result` and overrides what differs — an icon, the label of its type,
a block of the template. `wexample/symfony-design-system-demo` does exactly this for its
two entities, and the showcase's header uses that box rather than the bare one.

Arrows, Home, End and Enter walk the rows through the keyboard service of the loader, so
a dropdown and a modal open at once never both answer the same key; Escape closes; a click
outside is told by the overlay service. A result whose kind declared no `route` carries no
url: its row stands as a plain block rather than a link.

### Which pages are found

A page is a route served by a controller extending `AbstractPagesController` — the same
test the menu builder makes — reachable by GET without parameters, and open to the current
user by `#[IsGranted]`. Its title is the `page_title` its own translations declare, the one
the menu and the tab show; a route option `search_title` stands in when there is none, and
the route name humanised when there is neither. `options: ['searchable' => false]` hides a
page that has no business being found.

## In a form

src/Form/Type/EntitySearchInputType.php is a field whose value is a record
picked by searching for it:

```php
->add('room', EntitySearchInputType::class, [
    EntitySearchInputType::OPTION_ENTITY_TYPE => 'demo_room',
    'placeholder' => true,
])
```

What the form carries is an identifier, in a hidden input like any other field; what the
person sees is the search box narrowed to that one kind, then the picked record. The rows
do not link there — picking is the point — and the context defaults to `form_field`, which
is what lets an entity be findable in a picker without appearing in the header.

The field lives here and not in `wexample/symfony-forms`, because it offers this package's
feature and would have nothing to show without it. src/Resources/config/ is
not where it is declared: assets/form/form_theme.html.twig holds its one
block and the extension prepends it to `twig.form_themes`, which Twig merges — so a bundle
brings the fields it offers rather than the form package carrying fields whose feature it
does not ship.

## Configuration## Configuration

```yaml
# config/packages/wexample_symfony_search.yaml
wexample_symfony_search:
    # Who may search at all. ROLE_ANONYMOUS opens it to everyone.
    minimum_role: ROLE_USER
    # How many results a query asking for no number gets.
    max_results: 5
```

## Hiding a page from the search

A route is a page unless it says otherwise:

```php
#[Route(path: '/legal', name: 'app_legal', options: ['searchable' => false])]
```

And `['search_title' => 'Legal notice']` names it, for as long as nothing translates route
names.
