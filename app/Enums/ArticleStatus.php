<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Discovered = 'discovered';
    case Fetching = 'fetching';
    case Processed = 'processed';
    case Matched = 'matched';
    case FetchFailed = 'fetch_failed';
    case ProcessingFailed = 'processing_failed';
}
