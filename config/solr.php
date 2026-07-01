<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Solr endpoint
    |--------------------------------------------------------------------------
    |
    | Solarium endpoint configuration. The `core` is injected dynamically by
    | the package (per model `searchableAs()`), so it is not set here.
    |
    */
    'endpoint' => [
        'localhost' => [
            'host' => env('SOLR_HOST', '127.0.0.1'),
            'port' => env('SOLR_PORT', 8983),
            'path' => env('SOLR_PATH', '/'),
            'username' => env('SOLR_LOGIN'),
            'password' => env('SOLR_PASSWORD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Solr mode
    |--------------------------------------------------------------------------
    |
    | How the index is managed:
    |   'core'  - standalone Solr. Indexes are cores (CoreAdmin API). Configsets
    |             live on the Solr server filesystem (server/solr/configsets).
    |   'cloud' - SolrCloud. Indexes are collections (Collections API). Configsets
    |             live in ZooKeeper and can be uploaded over the Configsets API.
    |
    */
    'mode' => env('SOLR_MODE', 'core'),

    /*
    |--------------------------------------------------------------------------
    | Configset preset
    |--------------------------------------------------------------------------
    |
    | Configset name used when creating an index (createIndex). In 'core' mode
    | it is the on-disk configset name; in 'cloud' mode the collection's
    | configName (must already be uploaded via solr:configset).
    |
    */
    'preset' => env('SOLR_CONFIGSET'),

    /*
    |--------------------------------------------------------------------------
    | Configset source path
    |--------------------------------------------------------------------------
    |
    | Base directory holding configset folders, used by `solr:configset` to zip
    | and upload a configset (cloud mode only). A configset named "products"
    | resolves to "{configset_path}/products" unless an explicit --path or a
    | model `solrConfigsetPath()` method overrides it. The folder must contain
    | solrconfig.xml at its root.
    |
    */
    'configset_path' => env('SOLR_CONFIGSET_PATH', base_path('solr/configsets')),

    /*
    |--------------------------------------------------------------------------
    | SolrCloud collection defaults
    |--------------------------------------------------------------------------
    |
    | Defaults used when creating a collection in 'cloud' mode. Override per
    | call via createIndex($name, ['num_shards' => .., 'replication_factor' => ..]).
    |
    */
    'cloud' => [
        'num_shards' => (int) env('SOLR_NUM_SHARDS', 1),
        'replication_factor' => (int) env('SOLR_REPLICATION_FACTOR', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Curl adapter timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('SOLR_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Solr debug plugin
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) env('SOLR_DEBUG', false),
];
