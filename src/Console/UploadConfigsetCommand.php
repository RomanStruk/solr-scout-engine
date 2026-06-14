<?php

namespace RomanStruk\SolrScoutEngine\Console;

use Exception;
use Illuminate\Console\Command;
use Laravel\Scout\EngineManager;

class UploadConfigsetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'solr:configset
            {name? : Configset name (defaults to the model searchableAs or SOLR_CONFIGSET)}
            {--model= : Searchable model to resolve the configset name/path from}
            {--path= : Directory with the configset files (overrides config and model)}
            {--no-overwrite : Fail instead of overwriting an existing configset}
            {--cleanup : Delete stale files in ZooKeeper when overwriting}
            {--mode= : Override the Solr mode (cloud required for upload)}';

    /**
     * @var string
     */
    protected $description = 'Zip a configset directory and upload it to SolrCloud via the Configsets API';

    public function handle(EngineManager $manager): int
    {
        $engine = $manager->engine('solr');

        $name = $this->argument('name');
        $path = $this->option('path');

        if ($modelClass = $this->option('model')) {
            $model = new $modelClass();
            $name = $name ?: $model->searchableAs();

            if (! $path && method_exists($model, 'solrConfigsetPath')) {
                $path = $model->solrConfigsetPath();
            }
        }

        $name = $name ?: config('solr.preset');

        if (! $name) {
            $this->error('Configset name is required (pass an argument, --model, or set SOLR_CONFIGSET).');

            return self::FAILURE;
        }

        $path = $path ?: rtrim((string) config('solr.configset_path'), '/\\').'/'.$name;

        try {
            $engine->uploadConfigset($name, $path, [
                'overwrite' => ! $this->option('no-overwrite'),
                'cleanup' => (bool) $this->option('cleanup'),
                'mode' => $this->option('mode') ?: config('solr.mode', 'core'),
            ]);

            $this->info('Configset ["'.$name.'"] uploaded successfully from '.$path.'.');

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
