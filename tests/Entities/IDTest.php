<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IDTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\Entities;

use APIToolkit\Entities\{GUID, ID};
use Tests\Contracts\Test;

class IDTest extends Test {
    public function test_create_id_entity() {
        $id = new ID(null, $this->logger);
        $this->assertTrue($id->isValid());
        $this->assertEquals(0, $id->getValue());
    }

    public function test_create_guid_entity() {
        $id = new GUID(null, $this->logger);
        $this->assertTrue($id->isValid());
        $this->assertNotEmpty($id->getValue());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $id->getValue());
    }
}
