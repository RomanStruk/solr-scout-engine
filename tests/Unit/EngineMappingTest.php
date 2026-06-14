<?php

namespace RomanStruk\SolrScoutEngine\Tests\Unit;

use Laravel\Scout\EngineManager;
use RomanStruk\SolrScoutEngine\SolrEngine;
use RomanStruk\SolrScoutEngine\Tests\TestCase;
use RomanStruk\SolrScoutEngine\Tests\TestModels\Product;

class EngineMappingTest extends TestCase
{
    protected function engine(): SolrEngine
    {
        return app(EngineManager::class)->engine('solr');
    }

    protected function respondWith(int $numFound, array $ids): void
    {
        $docs = array_map(fn ($id) => ['id' => (string) $id], $ids);
        $this->adapter->setResponseBody(json_encode([
            'responseHeader' => ['status' => 0],
            'response' => ['numFound' => $numFound, 'start' => 0, 'docs' => $docs],
        ]));
    }

    public function test_get_total_count_reads_num_found(): void
    {
        $this->respondWith(42, [1, 2]);

        $result = $this->engine()->search(Product::search('x'));

        $this->assertSame(42, $this->engine()->getTotalCount($result));
    }

    public function test_map_ids_plucks_document_ids(): void
    {
        $this->respondWith(2, [7, 9]);

        $result = $this->engine()->search(Product::search('x'));

        $this->assertSame(['7', '9'], $this->engine()->mapIds($result)->all());
    }

    public function test_map_returns_models_in_solr_order(): void
    {
        Product::insert([
            ['id' => 1, 'name' => 'first', 'price' => 1, 'active' => 1, 'category_id' => 1],
            ['id' => 2, 'name' => 'second', 'price' => 2, 'active' => 1, 'category_id' => 1],
        ]);

        // Solr returns 2 before 1 (relevance order).
        $this->respondWith(2, [2, 1]);

        $builder = Product::search('x');
        $result = $this->engine()->search($builder);

        $mapped = $this->engine()->map($builder, $result, $builder->model);

        $this->assertSame([2, 1], $mapped->pluck('id')->all());
    }

    public function test_map_returns_empty_when_no_hits(): void
    {
        $this->respondWith(0, []);

        $builder = Product::search('x');
        $result = $this->engine()->search($builder);

        $this->assertTrue($this->engine()->map($builder, $result, $builder->model)->isEmpty());
    }
}
