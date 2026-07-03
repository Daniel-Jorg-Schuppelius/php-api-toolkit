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

use APIToolkit\Entities\{ProgramVersion, Version};
use Tests\Contracts\Test;

class VersionTest extends Test {
    public function test_create_version_entity(): void {
        $version = new Version(null, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(1, $version->getValue());
    }

    public function test_version_with_numeric_value(): void {
        $version = new Version(5, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(5, $version->getValue());
    }

    public function test_version_to_string(): void {
        $version = new Version(42, $this->logger);
        $this->assertEquals('42', (string) $version);
    }

    public function test_version_compare_to(): void {
        $v1 = new Version(1, $this->logger);
        $v2 = new Version(2, $this->logger);
        $v3 = new Version(1, $this->logger);

        $this->assertLessThan(0, $v1->compareTo($v2));
        $this->assertGreaterThan(0, $v2->compareTo($v1));
        $this->assertEquals(0, $v1->compareTo($v3));
    }

    public function test_version_is_newer_than(): void {
        $v1 = new Version(1, $this->logger);
        $v2 = new Version(2, $this->logger);

        $this->assertTrue($v2->isNewerThan($v1));
        $this->assertFalse($v1->isNewerThan($v2));
    }

    public function test_version_is_older_than(): void {
        $v1 = new Version(1, $this->logger);
        $v2 = new Version(2, $this->logger);

        $this->assertTrue($v1->isOlderThan($v2));
        $this->assertFalse($v2->isOlderThan($v1));
    }

    public function test_version_equals(): void {
        $v1 = new Version(5, $this->logger);
        $v2 = new Version(5, $this->logger);
        $v3 = new Version(6, $this->logger);

        $this->assertTrue($v1->equalsVersion($v2));
        $this->assertFalse($v1->equalsVersion($v3));
    }

    // ProgramVersion Tests
    public function test_create_program_version_entity(): void {
        $version = new ProgramVersion(null, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('v0.0.0', $version->getValue());
    }

    public function test_program_version_parsing(): void {
        $version = new ProgramVersion('v1.2.3', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(1, $version->getMajor());
        $this->assertEquals(2, $version->getMinor());
        $this->assertEquals(3, $version->getPatch());
        $this->assertNull($version->getPreRelease());
        $this->assertNull($version->getBuildMetadata());
    }

    public function test_program_version_without_v(): void {
        $version = new ProgramVersion('2.0.1', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(2, $version->getMajor());
        $this->assertEquals(0, $version->getMinor());
        $this->assertEquals(1, $version->getPatch());
    }

    public function test_program_version_pre_release(): void {
        $version = new ProgramVersion('1.0.0-alpha', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('alpha', $version->getPreRelease());
        $this->assertTrue($version->isPreRelease());
    }

    public function test_program_version_pre_release_with_number(): void {
        $version = new ProgramVersion('1.0.0-beta.2', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('beta.2', $version->getPreRelease());
    }

    public function test_program_version_build_metadata(): void {
        $version = new ProgramVersion('1.0.0+build.123', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('build.123', $version->getBuildMetadata());
    }

    public function test_program_version_pre_release_and_build_metadata(): void {
        $version = new ProgramVersion('1.0.0-rc.1+20231225', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('rc.1', $version->getPreRelease());
        $this->assertEquals('20231225', $version->getBuildMetadata());
    }

    public function test_program_version_major_only(): void {
        $version = new ProgramVersion('5', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(5, $version->getMajor());
        $this->assertEquals(0, $version->getMinor());
        $this->assertEquals(0, $version->getPatch());
    }

    public function test_program_version_major_minor_only(): void {
        $version = new ProgramVersion('3.14', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(3, $version->getMajor());
        $this->assertEquals(14, $version->getMinor());
        $this->assertEquals(0, $version->getPatch());
    }

    public function test_program_version_compare_to_same_major(): void {
        $v1 = new ProgramVersion('1.0.0', $this->logger);
        $v2 = new ProgramVersion('1.1.0', $this->logger);

        $this->assertLessThan(0, $v1->compareTo($v2));
        $this->assertGreaterThan(0, $v2->compareTo($v1));
    }

    public function test_program_version_compare_to_same_major_minor(): void {
        $v1 = new ProgramVersion('2.3.1', $this->logger);
        $v2 = new ProgramVersion('2.3.5', $this->logger);

        $this->assertLessThan(0, $v1->compareTo($v2));
    }

    public function test_program_version_pre_release_comparison(): void {
        $stable = new ProgramVersion('1.0.0', $this->logger);
        $alpha = new ProgramVersion('1.0.0-alpha', $this->logger);
        $beta = new ProgramVersion('1.0.0-beta', $this->logger);

        // Stabil > Pre-Release
        $this->assertGreaterThan(0, $stable->compareTo($alpha));
        $this->assertLessThan(0, $alpha->compareTo($stable));

        // beta > alpha (alphabetisch)
        $this->assertGreaterThan(0, $beta->compareTo($alpha));
    }

    public function test_program_version_get_normalized(): void {
        $version = new ProgramVersion('v1.2.3-beta+build', $this->logger);
        $this->assertEquals('1.2.3-beta+build', $version->getNormalized());
    }

    public function test_program_version_get_normalized_without_pre_release(): void {
        $version = new ProgramVersion('2.0.0', $this->logger);
        $this->assertEquals('2.0.0', $version->getNormalized());
    }

    public function test_invalid_program_version(): void {
        $version = new ProgramVersion('not-a-version', $this->logger);
        $this->assertFalse($version->isValid());
    }

    public function test_program_version_numeric_fallback(): void {
        $version = new ProgramVersion(42, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(42, $version->getValue());
    }

    public function test_program_version_is_newer_than(): void {
        $v1 = new ProgramVersion('1.0.0', $this->logger);
        $v2 = new ProgramVersion('2.0.0', $this->logger);

        $this->assertTrue($v2->isNewerThan($v1));
        $this->assertFalse($v1->isNewerThan($v2));
    }
}
