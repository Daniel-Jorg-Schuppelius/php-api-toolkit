<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComparisonType.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Enums;

enum ComparisonType: string {
    case EQUALS = 'equals';
    case CONTAINS = 'contains';
    case GREATER_THAN = 'greater_than';
    case LESS_THAN = 'less_than';
    case REGEX = 'regex';
}
