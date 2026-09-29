<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Published = 'published';
    case Superseded = 'superseded';
    case Hidden = 'hidden';
    case Failed = 'failed';
}
