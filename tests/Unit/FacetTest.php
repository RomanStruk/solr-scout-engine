<?php

namespace RomanStruk\SolrScoutEngine\Tests\Unit;

use Laravel\Scout\EngineManager;
use RomanStruk\SolrScoutEngine\SolrEngine;
use RomanStruk\SolrScoutEngine\Tests\TestCase;
use RomanStruk\SolrScoutEngine\Tests\TestModels\Product;
use Solarium\QueryType\Select\Query\Query;

class FacetTest extends TestCase
{
    protected function engine(): SolrEngine
    {
        return app(EngineManager::class)->engine('solr');
    }

    public function test_get_facet_set_normalises_field_facets(): void
    {
        $this->adapter->setResponseBody(json_encode([
            'responseHeader' => ['status' => 0],
            'response' => ['numFound' => 0, 'start' => 0, 'docs' => []],
            'facet_counts' => [
                'facet_queries' => [],
                'facet_fields' => [
                    'category_id_i' => ['1', 5, '2', 3],
                ],
                'facet_ranges' => [],
            ],
        ]));

        Product::search('x', function (Query $query) {
            $query->getFacetSet()->createFacetField('category_id_i')->setField('category_id_i');

            return $query;
        })->get();

        $facets = $this->engine()->getFacetSet();

        $this->assertArrayHasKey('category_id_i', $facets);
        $this->assertSame(['1' => 5, '2' => 3], $facets['category_id_i']);
    }

    public function test_get_facet_set_empty_without_result(): void
    {
        $this->assertSame([], $this->engine()->getFacetSet());
    }

    public function test_collection_macro_exposes_facets(): void
    {
        $this->adapter->setResponseBody(json_encode([
            'responseHeader' => ['status' => 0],
            'response' => ['numFound' => 0, 'start' => 0, 'docs' => []],
            'facet_counts' => [
                'facet_queries' => [],
                'facet_fields' => ['category_id_i' => ['1', 5]],
                'facet_ranges' => [],
            ],
        ]));

        Product::search('x', function (Query $query) {
            $query->getFacetSet()->createFacetField('category_id_i')->setField('category_id_i');

            return $query;
        })->get();

        $this->assertSame(['category_id_i' => ['1' => 5]], collect()->getFacetSet());
    }
}
