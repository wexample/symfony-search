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

One attribute, no class:

```php
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[Searchable(
    fields: ['reference', 'label'],
    contexts: [AppSearchContext::HEADER],
    subtitleField: 'label',
    route: 'app_invoice_show',
    weight: 2,
)]
class Invoice extends AbstractEntity
```

`fields` is the only required argument: it is what the SQL looks in and what the score is
computed from. `contexts` left empty means the entity is findable everywhere. `weight`
multiplies the score, which is how an invoice outranks a log line matching just as well.
`route` receives the entity id as `id` and turns into the result's url.

Contexts are declared by the application in its own backed enum — nothing has to know it
exists, because what travels is the string behind the case. The bundle ships
src/Enum/SearchContext.php for the three it has an opinion about.

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

The field itself is not here. A search box is dynamic through and through, so it is a Vue
component of `wexample/symfony-design-system` — `vue/search/search-box` — and so is the
row it draws for each result, `vue/search/search-result`. This bundle is what they talk to:
the box asks the `searchResult` repository generated from src/Entity/SearchResult.php,
which means the host application registers that repository on its API client:

```typescript
protected getRepositoryClasses() {
  return [...ownRepositories, ...searchRepositories];
}
```

Dropped anywhere in a template:

```twig
{{ vue(render_pass, '@WexampleSymfonyDesignSystemBundle/vue/search/search-box', { context: 'header', length: 8 }) }}
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

## Configuration

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
