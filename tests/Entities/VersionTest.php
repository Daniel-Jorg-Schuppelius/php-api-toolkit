<?php

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
