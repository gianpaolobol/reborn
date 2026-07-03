<?php

declare(strict_types=1);

use Reborn\Shared\Support\Env;

return [
    'enabled' => Env::bool('MARKETPLACE_SEARCH_ENABLED', true),
    'mode' => Env::get('MARKETPLACE_SEARCH_MODE', 'assisted_web_search'),
    'fetch_enabled' => Env::bool('MARKETPLACE_SEARCH_FETCH_ENABLED', false),
    'timeout_seconds' => (int) Env::get('MARKETPLACE_SEARCH_TIMEOUT_SECONDS', 6),
    'fetch_query_limit' => (int) Env::get('MARKETPLACE_SEARCH_FETCH_QUERY_LIMIT', 1),
    'max_results' => (int) Env::get('MARKETPLACE_SEARCH_MAX_RESULTS', 10),
    'min_score' => (int) Env::get('MARKETPLACE_SEARCH_MIN_SCORE', 70),
    'thingiverse' => [
        'enabled' => Env::bool('THINGIVERSE_SEARCH_ENABLED', true),
        'mode' => Env::get('THINGIVERSE_SEARCH_MODE', 'web_search'),
        'base_url' => Env::get('THINGIVERSE_BASE_URL', 'https://www.thingiverse.com'),
    ],
    'printables' => [
        'enabled' => Env::bool('PRINTABLES_SEARCH_ENABLED', true),
        'mode' => Env::get('PRINTABLES_SEARCH_MODE', 'web_search'),
        'base_url' => Env::get('PRINTABLES_BASE_URL', 'https://www.printables.com'),
    ],
    'makerworld' => [
        'enabled' => Env::bool('MAKERWORLD_SEARCH_ENABLED', true),
        'mode' => Env::get('MAKERWORLD_SEARCH_MODE', 'web_search'),
        'base_url' => Env::get('MAKERWORLD_BASE_URL', 'https://makerworld.com'),
    ],
    'web_stl' => [
        'enabled' => Env::bool('WEB_STL_SEARCH_ENABLED', false),
        'mode' => Env::get('WEB_STL_SEARCH_MODE', 'duckduckgo_html'),
    ],
];
