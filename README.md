# symfony-search

Version: 1.0.1

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

## Table of Contents

- [Installation](#installation)
- [Making an entity findable](#making-an-entity-findable)
- [Asking](#asking)
- [When the entity has to reason](#when-the-entity-has-to-reason)
- [Searching something that is not an entity](#searching-something-that-is-not-an-entity)
- [The front](#the-front)
- [Configuration](#configuration)
- [Hiding a page from the search](#hiding-a-page-from-the-search)
- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

One query, several families of findable things, one sorted list out. The families do not
know each other and the caller does not know them either — it asks src/Service/SearchService.php
and reads src/Entity/SearchResult.php back.

### The parts

src/WexampleSymfonySearchBundle.php extends `AbstractBundle` from
`wexample/symfony-helpers` and implements `PseudocodeBundleInterface`, which is what lets
the entity of this bundle be exported to TypeScript from the application that installs it.

src/DependencyInjection/WexampleSymfonySearchExtension.php loads the services
and does one thing worth knowing: it calls `registerForAutoconfiguration` on
src/Interface/SearchProviderInterface.php. A provider written in a host
application is tagged by implementing the interface, so nothing has to name the tag — and
no compiler pass has to walk the definitions, which is what
`wexample/symfony-routing` needs only because it keys off an attribute instead.

src/Class/SearchQuery.php is the question: terms, context, how many results,
and optionally one type to restrict to. It is a value, built by a controller, a form field
or a test alike. Two things happen in its constructor and nowhere else — the terms are
trimmed, and the context is turned from a backed enum into the string it carries.

src/Entity/SearchResult.php is the answer. See below.

src/Service/SearchService.php asks every provider that `supports()` the query,
cuts each provider's answers to the asked number, merges, sorts and cuts again.

### Providers

src/Class/Provider/AbstractSearchProvider.php reads the provider's key off its
own class name — `RouteSearchProvider` answers with `route` — so that the key, the class and
the `type` a client asks for are one thing rather than three to keep in agreement.

src/Class/Provider/EntitySearchProvider.php covers every entity carrying
src/Attribute/Searchable.php, read from Doctrine's metadata by
src/Service/SearchableRegistry.php. One provider for all of them, because what
differed between the legacy's seven `*SearchService` classes was a field list, and a field
list is data. Its results carry the *entity* as their type, not the provider — a client
asking for `invoice` gets invoices and never learns who found them.

src/Service/EntitySearchRunner.php is the query and the mapping, held apart
because two providers need it and neither owns it: the generic one runs it over every entity
that declared no provider, and src/Class/Provider/AbstractEntitySearchProvider.php
runs it over its single entity after `configureQuery()` has narrowed the builder. That second
road is the one the legacy's `UserSearchService` needed, and the entity taking it says so with
`provider: true` — which is also what keeps the generic provider from answering alongside it
and returning every record twice.

src/Class/Provider/RouteSearchProvider.php answers with pages, which are
routes. Nothing is indexed: the router already holds the list. What it decides is which
route is a page someone could be looking for, and that decision is four refusals — the
internals (`_`-prefixed and `/api/`), anything that is not a GET, anything that cannot be
linked to without data, and anything `#[IsGranted]` closes to the current user. A route
protected by a firewall pattern or by a check inside the action is not seen from here.

### Why the result is an entity with no ORM mapping

`SearchResult` extends `AbstractEntity` and carries no `#[ORM\Entity]`. Doctrine skips a
class that does not declare itself an entity, so nothing is mapped, nothing is persisted,
and no table exists. What is gained is everything being an entity brings on the way out:
`#[PseudocodeExport]` produces `SearchResult.ts` and its repository, and a collection of
results is a collection of entities like any other, which the front already renders.

That is also why the `Has*Trait` of `symfony-helpers` are not used for its fields — they
carry `#[Column]`, and a column is exactly what none of these fields has.

Identity is `Uuid::v5` over the type and the reference, so the same thing found twice is
the same result twice, in another request or another process.

### Filtering is SQL, ranking is PHP

src/Helper/SearchScoreHelper.php runs after the query, on the rows it
returned. Expressing "the needle is a whole word, near the start, accents aside" as joins
is the shape the legacy avoided and this package avoids too.

The consequence is `EntitySearchProvider::FETCH_FACTOR`: the database is asked for twice
what will be shown, because it cannot order by a score that does not exist yet. Widening
the window is not fixing it — a term matching a thousand rows still ranks only the first
two hundred the database happened to return. That is the known ceiling of the design, and
the reason a full-text engine would be a provider rather than a patch.

`stringScore()` tries three ways of making two strings meet — as written, ignoring case,
then ignoring accents — and the first that answers wins rather than adding to the others.
The needle is `preg_quote`d before the word-boundary test; the legacy interpolated it raw,
so a query containing `(` scored nothing and emitted a warning.

### Boundaries

The DQL is built here, but the query helpers it could have used — `querySearchLike`,
`querySearchNumber`, `querySelectEntity` — stay in `SearchableRepositoryTrait` in
`wexample/symfony-helpers`, for the repository that wants to write its own provider.
Numeric columns reached by `LIKE` are not handled: the declared fields are expected to hold
text.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- wexample/php-helpers: >=3.1.0
- wexample/php-pseudocode: >=2.1.0
- wexample/symfony-helpers: >=7.1.0
- wexample/symfony-api: >=4.0.0
- wexample/symfony-pseudocode: >=2.0.0
- symfony/uid: >=6.2
- symfony/routing: >=6.2
- symfony/security-bundle: >=6.2
- doctrine/orm: >=2.14
- wexample/symfony-loader: >=5.0.0
- wexample/symfony-design-system: >=10.0.0
- wexample/symfony-content: >=1.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
