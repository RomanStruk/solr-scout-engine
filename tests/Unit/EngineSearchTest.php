<?php

namespace RomanStruk\SolrScoutEngine\Tests\Unit;

use RomanStruk\SolrScoutEngine\Tests\TestCase;
use RomanStruk\SolrScoutEngine\Tests\TestModels\Product;
use Solarium\QueryType\Select\Query\Query;

class EngineSearchTest extends TestCase
{
    public function test_it_builds_filter_queries_sort_and_rows(): void
    {
        Product::search('phone')
            ->where('active_b', 1)
            ->whereIn('category_id_i', [1, 2])
            ->orderBy('price_f', 'asc')
            ->take(5)
            ->get();

        $uri = $this->adapter->lastUri();

        $this->assertStringContainsString('active_b:1', $uri);
        $this->assertStringContainsString('category_id_i:1,2', $uri);
        $this->assertStringContainsString('tag_active_b', $uri);
        $this->assertStringContainsString('price_f asc', $uri);
        $this->assertStringContainsString('rows=5', $uri);
    }

    public function test_paginate_sets_rows_and_start(): void
    {
        Product::search('phone')->paginate(10, 'page', 3);

        $uri = $this->adapter->lastUri();

        $this->assertStringContainsString('rows=10', $uri);
        $this->assertStringContainsString('start=20', $uri);
    }

    public function test_callback_can_mutate_the_query(): void
    {
        Product::search('apple', function (Query $query, string $search) {
            $query->setQuery('name_txt_uk:'.$search);

            return $query;
        })->get();

        $this->assertStringContainsString('name_txt_uk:apple', $this->adapter->lastUri());
    }
}
