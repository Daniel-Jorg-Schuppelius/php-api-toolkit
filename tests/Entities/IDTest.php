<?php

declare(strict_types=1);

namespace Tests\Entities;

use APIToolkit\Entities\GUID;
use APIToolkit\Entities\ID;
use Tests\Contracts\Test;

class IDTest extends Test {
    public function testCreateIDEntity() {
        $id = new ID(null, $this->logger);
        $this->assertTrue($id->isValid());
        $this->assertEquals(0, $id->getValue());
    }

    public function testCreateGUIDEntity() {
        $id = new GUID(null, $this->logger);
        $this->assertTrue($id->isValid());
        $this->assertEquals("00000000-0000-0000-0000-000000000000", $id->getValue());
    }
}
