<?php

namespace RomanStruk\SolrScoutEngine;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\EngineManager;
use RomanStruk\SolrScoutEngine\Console\IndexCommand;
use RomanStruk\SolrScoutEngine\Console\UploadConfigsetCommand;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Curl;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SolrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/solr.php', 'solr');

        // Scout should build queries through our Builder (overrides getTotalCount).
        $this->app->bind(ScoutBuilder::class, Builder::class);

        $this->app->scoped(BasicDebug::class, fn () => new BasicDebug());

        // A fresh Solarium client per core (resolved with makeWith(['core' => ...])).
        $this->app->bind(Client::class, function ($app, array $data) {

            $adapter = new Curl(['timeout' => (int) $app['config']->get('solr.timeout', 600)]);

            $config = ['endpoint' => $app['config']->get('solr.endpoint')];
            $config['endpoint']['localhost']['core'] = $data['core'];

            $client = new Client($adapter, new EventDispatcher(), $config);

            if ($app['config']->get('solr.debug', false)) {
                $client->registerPlugin('debugger', $app[BasicDebug::class]);
            }

            return $client;
        });
    }

    public function boot(): void
    {
        resolve(EngineManager::class)->extend('solr', fn () => new SolrEngine());

        Collection::macro('getFacetSet', function (): array {
            return app(EngineManager::class)->driver('solr')->getFacetSet();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([IndexCommand::class, UploadConfigsetCommand::class]);

            $this->publishes([
                __DIR__.'/../config/solr.php' => $this->app->configPath('solr.php'),
            ], 'solr-config');
        }
    }
}
