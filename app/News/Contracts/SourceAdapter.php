<?php

namespace App\News\Contracts;

use App\Models\Source;

interface SourceAdapter
{
    public function discover(Source $source): void;
}
