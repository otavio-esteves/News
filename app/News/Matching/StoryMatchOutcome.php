<?php

namespace App\News\Matching;

enum StoryMatchOutcome: string
{
    case Created = 'created';
    case Matched = 'matched';
    case Pending = 'pending';
    case AlreadyMatched = 'already_matched';
    case Skipped = 'skipped';
}
