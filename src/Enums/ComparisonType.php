<?php

declare(strict_types=1);

namespace APIToolkit\Enums;

enum ComparisonType: string {
    case EQUALS = 'equals';
    case CONTAINS = 'contains';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case REGEX = 'regex';
}
