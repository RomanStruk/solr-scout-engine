<?php

namespace RomanStruk\SolrScoutEngine\Console;

use Exception;
use Illuminate\Console\Command;
use Laravel\Scout\EngineManager;

class IndexCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'solr:index
            {model : Class name of the model whose index should be created}
            {--mode= : Override the Solr mode (core|cloud)}
            {--configset= : Override the configset/preset used for the index}';

    /**
     * @var string
     */
    protected $description = 'Create a Solr core (standalone) or collection (cloud) for the given searchable model';

    public function handle(EngineManager $manager): int
    {
        $engine = $manager->engine('solr');

        try {
            $class = $this->argument('model');

            $model = new $class();

            $options = method_exists($model, 'scoutIndexMigration')
                ? $model->scoutIndexMigration()
                : [];

            if ($mode = $this->option('mode')) {
                $options['mode'] = $mode;
            }
            if ($configset = $this->option('configset')) {
                $options['configset'] = $configset;
            }

            $name = $model->searchableAs();
            $mode = $options['mode'] ?? config('solr.mode', 'core');

            $engine->createIndex($name, $options);

            $label = $mode === 'cloud' ? 'Collection' : 'Core';
            $this->info($label.' ["'.$name.'"] created successfully.');

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
