<?php

namespace RomanStruk\SolrScoutEngine\Tests\Unit;

use Laravel\Scout\EngineManager;
use RomanStruk\SolrScoutEngine\SolrEngine;
use RomanStruk\SolrScoutEngine\Tests\TestCase;
use RomanStruk\SolrScoutEngine\Tests\TestModels\Product;

class EngineCrudTest extends TestCase
{
    protected function engine(): SolrEngine
    {
        return app(EngineManager::class)->engine('solr');
    }

    protected function ackBody(): void
    {
        $this->adapter->setResponseBody('{"responseHeader":{"status":0,"QTime":1}}');
    }

    public function test_update_sends_documents_and_commits(): void
    {
        $this->ackBody();

        $product = new Product(['name' => 'Phone', 'price' => 10, 'active' => 1, 'category_id' => 3]);
        $product->id = 5;

        $this->engine()->update(collect([$product]));

        $body = $this->adapter->lastRequest->getRawData();

        $this->assertStringContainsString('name_txt_uk', $body);
        $this->assertStringContainsString('"commit"', $body);
        $this->assertStringContainsString('"id":5', $body);
    }

    public function test_delete_sends_delete_by_id(): void
    {
        $this->ackBody();

        $product = new Product();
        $product->id = 8;

        $this->engine()->delete(collect([$product]));

        $body = $this->adapter->lastRequest->getRawData();

        $this->assertStringContainsString('"delete":[8]', $body);
        $this->assertStringContainsString('"commit"', $body);
    }

    public function test_flush_deletes_everything(): void
    {
        $this->ackBody();

        $this->engine()->flush(new Product());

        $body = $this->adapter->lastRequest->getRawData();

        $this->assertStringContainsString('id:*', $body);
        $this->assertStringContainsString('"commit"', $body);
    }

    public function test_update_skips_empty_collection(): void
    {
        $this->engine()->update(collect());

        $this->assertNull($this->adapter->lastRequest);
    }
}
