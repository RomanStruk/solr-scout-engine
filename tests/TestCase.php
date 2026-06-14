<?php

namespace RomanStruk\SolrScoutEngine\Tests;

use Illuminate\Support\Facades\Schema;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use RomanStruk\SolrScoutEngine\SolrServiceProvider;
use RomanStruk\SolrScoutEngine\Tests\Fakes\FakeAdapter;
use Solarium\Client;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class TestCase extends BaseTestCase
{
    protected FakeAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new FakeAdapter();

        // Rebind the Solarium client to use the fake adapter (no live Solr).
        $this->app->bind(Client::class, function ($app, array $data) {
            $config = ['endpoint' => config('solr.endpoint')];
            $config['endpoint']['localhost']['core'] = $data['core'];

            return new Client($this->adapter, new EventDispatcher(), $config);
        });

        Schema::create('products', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->float('price')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('category_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('scout.driver', 'solr');
        $app['config']->set('solr.endpoint.localhost', [
            'host' => '127.0.0.1',
            'port' => 8983,
            'path' => '/',
        ]);
        $app['config']->set('solr.preset', 'test_configset');
    }

    protected function getPackageProviders($app)
    {
        return [
            ScoutServiceProvider::class,
            SolrServiceProvider::class,
        ];
    }
}
