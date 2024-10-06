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

namespace Tests;

use APIToolkit\Entities\ProgramVersion;
use Tests\Contracts\Test;

class VersionTest extends Test {
    public function testCreateVersion() {
        $data = "1";
        $data1 = 1;

        $version = new ProgramVersion($data, $this->logger);
        $version1 = new ProgramVersion($data1, $this->logger);
        $this->assertInstanceOf(ProgramVersion::class, $version);
        $this->assertInstanceOf(ProgramVersion::class, $version1);
        $this->assertEquals(1, $version->getValue());
        $this->assertEquals(1, $version1->getValue());
        $this->assertTrue($version->isValid());
        $this->assertTrue($version1->isValid());
    }

    public function testCreateProgramVersion() {
        $data =  "v1.0.0";
        $data1 = "1.0.0";
        $data2 = "1.0a";

        $version = new ProgramVersion($data, $this->logger);
        $version1 = new ProgramVersion($data1, $this->logger);
        $version2 = new ProgramVersion($data2, $this->logger);
        $this->assertInstanceOf(ProgramVersion::class, $version);
        $this->assertInstanceOf(ProgramVersion::class, $version1);
        $this->assertInstanceOf(ProgramVersion::class, $version2);
        $this->assertEquals("v1.0.0", $version->getValue());
        $this->assertEquals("1.0.0", $version1->getValue());
        $this->assertEquals("1.0a", $version2->getValue());
        $this->assertTrue($version->isValid());
        $this->assertTrue($version1->isValid());
        $this->assertTrue($version2->isValid());
    }
}
