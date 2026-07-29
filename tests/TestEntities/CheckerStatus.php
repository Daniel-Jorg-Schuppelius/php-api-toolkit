<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckerStatus.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

enum CheckerStatus: string {
    case Open = 'open';
    case Closed = 'closed';
}
