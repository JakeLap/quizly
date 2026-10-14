<?php

namespace App\Enums;

enum QuestionSource: string
{
    case Manual = 'manual';
    case Wikidata = 'wikidata';
    case Ai = 'ai';
    case OpenTdb = 'opentdb';
    case Community = 'community';
}
