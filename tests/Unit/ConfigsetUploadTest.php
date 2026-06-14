<?php

namespace RomanStruk\SolrScoutEngine\Tests\Unit;

use Exception;
use Laravel\Scout\EngineManager;
use RomanStruk\SolrScoutEngine\SolrEngine;
use RomanStruk\SolrScoutEngine\Tests\TestCase;
use Solarium\Core\Client\Request;

class ConfigsetUploadTest extends TestCase
{
    protected string $configsetDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configsetDir = sys_get_temp_dir().'/solr_test_configset_'.uniqid();
        mkdir($this->configsetDir.'/lang', 0777, true);
        file_put_contents($this->configsetDir.'/solrconfig.xml', '<config/>');
        file_put_contents($this->configsetDir.'/managed-schema.xml', '<schema/>');
        file_put_contents($this->configsetDir.'/lang/stopwords.txt', 'the');
    }

    protected function tearDown(): void
    {
        @unlink($this->configsetDir.'/lang/stopwords.txt');
        @unlink($this->configsetDir.'/managed-schema.xml');
        @unlink($this->configsetDir.'/solrconfig.xml');
        @rmdir($this->configsetDir.'/lang');
        @rmdir($this->configsetDir);

        parent::tearDown();
    }

    protected function engine(): SolrEngine
    {
        return app(EngineManager::class)->engine('solr');
    }

    public function test_upload_posts_zip_to_configsets_api(): void
    {
        config()->set('solr.mode', 'cloud');
        $this->adapter->setResponseBody('{"responseHeader":{"status":0}}');

        $this->engine()->uploadConfigset('products', $this->configsetDir);

        $uri = strtolower($this->adapter->lastUri());

        $this->assertStringContainsString('admin/configs', $uri);
        $this->assertStringContainsString('action=upload', $uri);
        $this->assertStringContainsString('name=products', $uri);
        $this->assertStringContainsString('overwrite=true', $uri);
        $this->assertSame(Request::METHOD_POST, $this->adapter->lastRequest->getMethod());
    }

    public function test_upload_refuses_standalone_mode(): void
    {
        config()->set('solr.mode', 'core');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SolrCloud mode');

        $this->engine()->uploadConfigset('products', $this->configsetDir, ['mode' => 'core']);
    }

    public function test_upload_fails_without_solrconfig(): void
    {
        config()->set('solr.mode', 'cloud');
        $empty = sys_get_temp_dir().'/solr_empty_'.uniqid();
        mkdir($empty);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('solrconfig.xml not found');

            $this->engine()->uploadConfigset('products', $empty, ['mode' => 'cloud']);
        } finally {
            @rmdir($empty);
        }
    }

    public function test_upload_fails_for_missing_directory(): void
    {
        config()->set('solr.mode', 'cloud');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configset directory not found');

        $this->engine()->uploadConfigset('products', '/no/such/dir', ['mode' => 'cloud']);
    }
}
