# Solr Scout Engine

Laravel Scout driver for Apache Solr, built on top of [Solarium](https://github.com/solariumphp/solarium).

Extracted from a battle-tested implementation used across several production
catalogs. The package ships the engine, a thin Scout `Builder` and configuration;
your application keeps full control over the Solr query through the Scout search
callback.

## Requirements

- PHP `^8.2`
- Laravel `^10` / `^11`
- Laravel Scout `^10`
- Solarium `^6.2`

## Installation

```bash
composer require romanstruk/solr-scout-engine
php artisan vendor:publish --tag=solr-config
```

Set the driver in `.env`:

```dotenv
SCOUT_DRIVER=solr
SOLR_HOST=127.0.0.1
SOLR_PORT=8983
SOLR_PATH=/
SOLR_CONFIGSET=my_configset   # used by solr:index / createIndex
```

The Solr **core** is resolved per model from `searchableAs()`.

## Indexing a model

Use Solr dynamic field suffixes in `toSearchableArray()` (`_s`, `_ss`, `_i`,
`_is`, `_f`, `_b`, `_txt_xx`):

```php
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    public function searchableAs(): string
    {
        return 'products';
    }

    public function toSearchableArray(): array
    {
        return [
            'name_txt_uk' => $this->name,
            'price_f'     => (float) $this->price,
            'active_b'    => (int) $this->active,
            'categories_is' => $this->category_ids,
        ];
    }
}
```

Sync as usual: `php artisan scout:import "App\Models\Product"`.

## Searching

Simple Scout API:

```php
Product::search('apple')
    ->where('active_b', 1)
    ->whereIn('categories_is', [1, 2])
    ->orderBy('price_f', 'asc')
    ->paginate(30);
```

Full control via the search callback — you receive the Solarium
`Select\Query\Query`, mutate it (EDisMax, boosts, filter queries, JSON facets…)
and return it:

```php
use Solarium\QueryType\Select\Query\Query;
use Solarium\Component\DisMax\BoostQuery;

Product::search($term, function (Query $query, string $search) {
    $query->setQuery("name_txt_uk:({$search})");
    $query->getEDisMax()->addBoostQuery(
        (new BoostQuery())->setQuery('status_s:in_stock')->setKey('status')
    );
    $query->getFacetSet()->createFacetField('brand')->setField('brand_ss');

    return $query;
})->paginate(30);
```

### Facets

After a search, facets from the last result are available via a `Collection`
macro:

```php
$products = Product::search('apple', $callback)->paginate(30);
$facets = collect()->getFacetSet(); // normalised array (supports pivot facets)
```

## Index management (standalone & SolrCloud)

The package works with both Solr modes. Pick one in `.env`:

```dotenv
SOLR_MODE=core    # standalone: indexes are cores (CoreAdmin API)
# SOLR_MODE=cloud # SolrCloud: indexes are collections (Collections API)
```

Create the index for a model:

```bash
php artisan solr:index "App\Models\Product"
# override per call:
php artisan solr:index "App\Models\Product" --mode=cloud --configset=products
```

- **core** — creates a core via CoreAdmin using the on-disk configset
  `SOLR_CONFIGSET` (must already exist under `server/solr/configsets/`).
- **cloud** — creates a collection via the Collections API with
  `collection.configName = SOLR_CONFIGSET`, `numShards`, `replicationFactor`
  (`SOLR_NUM_SHARDS`, `SOLR_REPLICATION_FACTOR`, or per-call
  `['num_shards' => .., 'replication_factor' => ..]`).

The engine exposes `createIndex($name, $options)` and
`deleteIndex($name, $options)`. Both honour `mode`/`configset` overrides via
`$options`.

### Uploading a configset (SolrCloud only)

In cloud mode configsets live in ZooKeeper and are uploaded over the Configsets
API. The package zips a local directory (files at the zip root, `solrconfig.xml`
on top) and uploads it:

```bash
php artisan solr:configset products
# from a model (uses searchableAs as the name, solrConfigsetPath() if defined):
php artisan solr:configset --model="App\Models\Product"
# explicit folder + overwrite stale files:
php artisan solr:configset products --path=/path/to/conf --cleanup
```

Resolution of the source folder:

1. `--path` option, else
2. model `solrConfigsetPath()` (when `--model` given), else
3. `SOLR_CONFIGSET_PATH` base dir + `/{name}`.

```dotenv
SOLR_CONFIGSET_PATH=/var/www/app/solr/configsets
```

> Standalone Solr has no configset upload API — `solr:configset` refuses to run
> in `core` mode. Place the configset under `server/solr/configsets/` on the
> Solr server instead.

A model may pin its own configset directory:

```php
public function solrConfigsetPath(): string
{
    return base_path('solr/configsets/products');
}
```

## Testing

```bash
composer install
composer test
```

Tests run against an in-memory Solarium adapter (`tests/Fakes/FakeAdapter`) — no
live Solr instance required.

## Migrating an existing project

Replace a hand-rolled `app/Solr/*` driver with this package:

1. `composer require romanstruk/solr-scout-engine`.
2. Delete `app/Solr/{SolrEngine,Builder,BasicDebug}.php` and the Solr
   registration in your local service provider.
3. Move `config/solarium.php` settings into the published `config/solr.php`
   (`endpoint`, `preset`).
4. Update `use App\Solr\Builder` references to
   `RomanStruk\SolrScoutEngine\Builder`.
5. Existing search callbacks keep working unchanged — they run through
   `$builder->callback`.
