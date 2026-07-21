<?php

declare(strict_types=1);

namespace Rushing\Graphine\Enums;

/**
 * Direction of an adjacency traversal (role 2). Mirrors the ancestors/
 * descendants split that staudenmeir/laravel-adjacency-list exposes over
 * recursive CTEs — the Postgres driver maps these straight onto that library.
 */
enum TraversalDirection: string
{
    case Descendants = 'descendants';
    case Ancestors = 'ancestors';
    case Both = 'both';
}
