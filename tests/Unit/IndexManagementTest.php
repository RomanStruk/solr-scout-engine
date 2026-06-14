<?php

namespace RomanStruk\SolrScoutEngine\Tests\Unit;

use Laravel\Scout\EngineManager;
use RomanStruk\SolrScoutEngine\SolrEngine;
use RomanStruk\SolrScoutEngine\Tests\TestCase;

class IndexManagementTest extends TestCase
{
    protected function engine(): SolrEngine
    {
        return app(EngineManager::class)->engine('solr');
    }

    public function test_create_index_uses_configset_preset(): void
    {
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->createIndex('products');

        $uri = strtolower($this->adapter->lastUri());

        $this->assertStringContainsString('action=create', $uri);
        $this->assertStringContainsString('name=products', $uri);
        $this->assertStringContainsString('test_configset', $uri);
    }

    public function test_create_index_allows_configset_override(): void
    {
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->createIndex('products', ['configset' => 'custom_set']);

        $this->assertStringContainsString('custom_set', $this->adapter->lastUri());
    }

    public function test_delete_index_unloads_and_removes_data(): void
    {
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->deleteIndex('products');

        $uri = strtolower($this->adapter->lastUri());

        $this->assertStringContainsString('action=unload', $uri);
        $this->assertStringContainsString('deleteindex=true', $uri);
    }

    public function test_create_index_creates_collection_in_cloud_mode(): void
    {
        config()->set('solr.mode', 'cloud');
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->createIndex('products', ['num_shards' => 2, 'replication_factor' => 3]);

        $uri = strtolower($this->adapter->lastUri());

        $this->assertStringContainsString('admin/collections', $uri);
        $this->assertStringContainsString('action=create', $uri);
        $this->assertStringContainsString('name=products', $uri);
        $this->assertStringContainsString('collection.configname=test_configset', $uri);
        $this->assertStringContainsString('numshards=2', $uri);
        $this->assertStringContainsString('replicationfactor=3', $uri);
    }

    public function test_mode_can_be_overridden_per_call(): void
    {
        config()->set('solr.mode', 'core');
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->createIndex('products', ['mode' => 'cloud']);

        $this->assertStringContainsString('admin/collections', strtolower($this->adapter->lastUri()));
    }

    public function test_delete_index_deletes_collection_in_cloud_mode(): void
    {
        config()->set('solr.mode', 'cloud');
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->deleteIndex('products');

        $uri = strtolower($this->adapter->lastUri());

        $this->assertStringContainsString('admin/collections', $uri);
        $this->assertStringContainsString('action=delete', $uri);
        $this->assertStringContainsString('name=products', $uri);
    }
}
