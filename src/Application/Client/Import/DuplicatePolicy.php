<?php

declare(strict_types=1);

namespace App\Application\Client\Import;

enum DuplicatePolicy: string
{
    case Skip = 'skip';
    case Update = 'update';
}
