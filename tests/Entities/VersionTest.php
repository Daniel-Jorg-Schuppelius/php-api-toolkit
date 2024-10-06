<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VersionTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Entities;

use APIToolkit\Entities\Version;
use Tests\Contracts\Test;

class VersionTest extends Test {
    public function testCreateIDEntity() {
        $id = new Version(null, $this->logger);
        $this->assertTrue($id->isValid());
        $this->assertEquals(1, $id->getValue());
    }
}
