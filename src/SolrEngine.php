<?php

namespace RomanStruk\SolrScoutEngine;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Solarium\Client;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Result;

class SolrEngine extends Engine
{
    /**
     * Last search result, kept for facet access via Collection macro.
     *
     * @var Result|mixed
     */
    protected mixed $result;

    /**
     * {@inheritDoc}
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return [];
        }

        $client = app()->makeWith(Client::class, ['core' => $models->first()->searchableAs()]);

        $update = $client->createUpdate();

        $models->each(function ($model) use ($update) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }

            $doc = $update->createDocument();
            $doc->id = $model->getScoutKey();
            foreach ($searchableData as $field => $searchableDatum) {
                $doc->{$field} = $searchableDatum;
            }

            $update->addDocument($doc);
        });

        $update->addCommit();

        return $client->update($update)->getData();
    }

    /**
     * {@inheritDoc}
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return [];
        }

        $client = app()->makeWith(Client::class, ['core' => $models->first()->searchableAs()]);

        $update = $client->createUpdate();

        $models->each(function ($model) use ($update) {
            $update->addDeleteById($model->getScoutKey());
        });
        $update->addCommit();

        try {
            return $client->update($update)->getData();
        } catch (Exception $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'limit' => $builder->limit,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'limit' => (int) $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);
    }

    /**
     * Get facets from the last executed search result.
     *
     * @return array<array>
     */
    public function getFacetSet(): array
    {
        if (! isset($this->result)) {
            return [];
        }

        $facets = [];
        foreach ($this->result->getFacetSet()?->getFacets() ?: [] as $key => $facet) {
            $facets[$key] = $this->facetToArray($facet);
        }

        return $facets;
    }

    /**
     * Recursively normalise a Solarium facet (incl. pivot facets) to an array.
     */
    public function facetToArray($facet): array
    {
        $data = [];

        foreach ($facet as $k => $item) {
            if (is_int($item)) {
                $data[$k] = $item;
            } elseif (method_exists($item, 'getValues')) {
                $data[$k] = $this->facetToArray($item->getValues());
            } elseif (method_exists($item, 'getValue')) {
                if (method_exists($item, 'getPivot')) {
                    $d = [];
                    foreach ($item->getPivot() as $pivot) {
                        $d[] = [
                            'field' => $pivot->getField(),
                            'count' => $pivot->getCount(),
                            'value' => $pivot->getValue(),
                        ];
                    }
                    $data[$k] = [
                        'field' => $item->getField(),
                        'count' => $item->getCount(),
                        'value' => $item->getValue(),
                        'pivot' => $d,
                    ];

                    continue;
                }
                $data[$k] = $item->getValue();
            } else {
                $data[$k] = $item;
            }
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     *
     * @param  Result  $results
     */
    public function mapIds($results)
    {
        if ($results->getNumFound() === 0) {
            return collect();
        }

        return collect($results->getData()['response']['docs'])->pluck('id');
    }

    /**
     * {@inheritDoc}
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results->getNumFound() === 0) {
            return collect();
        }

        $objectIds = collect($results->getData()['response']['docs'])->pluck('id')->all();
        if (count($objectIds) === 0) {
            return collect();
        }

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * {@inheritDoc}
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ($results->getNumFound() === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results->getData()['response']['docs'])->pluck('id')->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(function ($model) use ($objectIds) {
                return in_array($model->getScoutKey(), $objectIds);
            })->sortBy(function ($model) use ($objectIdPositions) {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalCount($results)
    {
        return $results->getNumFound();
    }

    /**
     * {@inheritDoc}
     */
    public function flush($model)
    {
        $client = app()->makeWith(Client::class, ['core' => $model->searchableAs()]);

        $update = $client->createUpdate();
        $update->addDeleteQuery('id:*');
        $update->addCommit();

        return $client->update($update)->getData();
    }

    /**
     * {@inheritDoc}
     *
     * Creates a core (standalone) or a collection (SolrCloud) depending on the
     * configured mode. Pass `['mode' => 'core'|'cloud']` to override per call.
     */
    public function createIndex($name, array $options = [])
    {
        $mode = $options['mode'] ?? config('solr.mode', 'core');
        $configset = $options['configset'] ?? config('solr.preset');

        return $mode === 'cloud'
            ? $this->createCollection($name, $configset, $options)
            : $this->createCore($name, $configset);
    }

    /**
     * {@inheritDoc}
     *
     * Unloads a core (standalone) or deletes a collection (SolrCloud).
     */
    public function deleteIndex($name, array $options = [])
    {
        $mode = $options['mode'] ?? config('solr.mode', 'core');

        return $mode === 'cloud'
            ? $this->deleteCollection($name)
            : $this->deleteCore($name);
    }

    /**
     * Create a standalone core via the CoreAdmin API.
     *
     * The configset must already exist on the Solr server filesystem
     * (server/solr/configsets/{configset}).
     */
    protected function createCore(string $name, ?string $configset): array
    {
        $client = app()->makeWith(Client::class, ['core' => $name]);

        $coreAdminQuery = $client->createCoreAdmin();

        $createAction = $coreAdminQuery->createCreate();
        $createAction->setCore($name)
            ->setConfigSet($configset);
        $coreAdminQuery->setAction($createAction);

        return $client->coreAdmin($coreAdminQuery)->getData();
    }

    /**
     * Create a SolrCloud collection via the Collections API.
     *
     * The configset (collection.configName) must already be uploaded to
     * ZooKeeper (see uploadConfigset / solr:configset).
     */
    protected function createCollection(string $name, ?string $configset, array $options = []): array
    {
        $client = app()->makeWith(Client::class, ['core' => $name]);

        $collections = $client->createCollections();

        $createAction = $collections->createCreate();
        $createAction->setName($name)
            ->setNumShards($options['num_shards'] ?? (int) config('solr.cloud.num_shards', 1))
            ->setReplicationFactor($options['replication_factor'] ?? (int) config('solr.cloud.replication_factor', 1));

        if ($configset) {
            $createAction->setCollectionConfigName($configset);
        }

        $collections->setAction($createAction);

        return $client->collections($collections)->getData();
    }

    /**
     * Unload a standalone core (and remove its data) via the CoreAdmin API.
     */
    protected function deleteCore(string $name): array
    {
        $client = app()->makeWith(Client::class, ['core' => $name]);

        $coreAdminQuery = $client->createCoreAdmin();
        $unloadAction = $coreAdminQuery->createUnload()
            ->setCore($name)
            ->setDeleteIndex(true)
            ->setDeleteDataDir(true)
            ->setDeleteInstanceDir(true);
        $coreAdminQuery->setAction($unloadAction);

        return $client->coreAdmin($coreAdminQuery)->getData();
    }

    /**
     * Delete a SolrCloud collection via the Collections API.
     */
    protected function deleteCollection(string $name): array
    {
        $client = app()->makeWith(Client::class, ['core' => $name]);

        $collections = $client->createCollections();
        $deleteAction = $collections->createDelete()->setName($name);
        $collections->setAction($deleteAction);

        return $client->collections($collections)->getData();
    }

    /**
     * Zip a configset directory and upload it via the Configsets API.
     *
     * SolrCloud only — standalone Solr keeps configsets on its filesystem and
     * has no upload API. The source directory must hold solrconfig.xml at its
     * root (the configset's conf/ contents).
     *
     * @param  string  $name  Configset name in ZooKeeper.
     * @param  string  $sourcePath  Local directory with the configset files.
     * @param  array  $options  overwrite (bool, default true), cleanup (bool), mode.
     */
    public function uploadConfigset(string $name, string $sourcePath, array $options = []): array
    {
        $mode = $options['mode'] ?? config('solr.mode', 'core');

        if ($mode !== 'cloud') {
            throw new Exception(
                'Configset upload is only supported in SolrCloud mode (solr.mode=cloud). '
                .'In standalone mode place the configset under Solr\'s server/solr/configsets directory.'
            );
        }

        $sourcePath = rtrim($sourcePath, '/\\');

        if (! is_dir($sourcePath)) {
            throw new Exception("Configset directory not found: {$sourcePath}");
        }

        if (! is_file($sourcePath.'/solrconfig.xml')) {
            throw new Exception(
                "solrconfig.xml not found in {$sourcePath}. "
                .'The configset directory must contain solrconfig.xml at its root.'
            );
        }

        $zipPath = $this->zipDirectory($sourcePath);

        try {
            $client = app()->makeWith(Client::class, ['core' => $name]);

            $configsets = $client->createConfigsets();
            $uploadAction = $configsets->createUpload();
            $uploadAction->setName($name)
                ->setFile($zipPath)
                ->setOverwrite($options['overwrite'] ?? true)
                ->setCleanup($options['cleanup'] ?? false);
            $configsets->setAction($uploadAction);

            return $client->configsets($configsets)->getData();
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * Zip the contents of a directory into a temp archive (files at the root).
     *
     * @return string Path to the created zip file.
     */
    protected function zipDirectory(string $sourcePath): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'solr_configset_').'.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Unable to create zip archive at {$zipPath}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $localName = ltrim(substr($file->getPathname(), strlen($sourcePath)), '/\\');

            if ($file->isDir()) {
                $zip->addEmptyDir($localName);
            } else {
                $zip->addFile($file->getPathname(), $localName);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Build and execute the Solr select query.
     */
    protected function performSearch(Builder $builder, array $searchParams = [])
    {
        /** @var Client $client */
        $client = app()->makeWith(Client::class, ['core' => $builder->index ?: $builder->model->searchableAs()]);
        $client->getPlugin('postbigrequest');

        $query = $client->createSelect();

        foreach ($builder->wheres as $field => $value) {
            $query->addFilterQuery(
                (new FilterQuery())->setKey($field)->setQuery($field.':'.$value)->addTag('tag_'.$field)
            );
        }

        foreach ($builder->whereIns as $field => $values) {
            $query->addFilterQuery(
                (new FilterQuery())->setKey($field)->setQuery($field.':('.implode(' OR ', $values).')')->addTag('tag_'.$field)
            );
        }

        foreach ($builder->orders as $order) {
            $query->addSort($order['column'], $order['direction']);
        }

        if (isset($searchParams['limit'])) {
            $query->setRows($searchParams['limit']);
        }
        if (isset($searchParams['offset'])) {
            $query->setStart($searchParams['offset']);
        }

        if ($builder->callback) {
            $result = call_user_func(
                $builder->callback,
                $query,
                $builder->query,
                []
            );

            // Callback may either mutate $query (returns the Query) or return a
            // fully resolved result set itself.
            if ($result instanceof Query) {
                return $this->result = $this->executeSelect($client, $result);
            }

            return $this->result = $result;
        }

        return $this->result = $this->executeSelect($client, $query);
    }

    /**
     * Execute a select query and decorate Solarium exceptions with debug output.
     */
    protected function executeSelect(Client $client, Query $query)
    {
        try {
            return $client->select($query);
        } catch (Exception $exception) {
            throw new Exception(
                $exception->getMessage()."\n".implode("\n", app(BasicDebug::class)->display())
            );
        }
    }
}
