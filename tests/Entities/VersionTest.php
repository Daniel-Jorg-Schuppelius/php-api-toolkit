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

use APIToolkit\Entities\ProgramVersion;
use APIToolkit\Entities\Version;
use Tests\Contracts\Test;

class VersionTest extends Test {
    public function testCreateVersionEntity(): void {
        $version = new Version(null, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(1, $version->getValue());
    }

    public function testVersionWithNumericValue(): void {
        $version = new Version(5, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(5, $version->getValue());
    }

    public function testVersionToString(): void {
        $version = new Version(42, $this->logger);
        $this->assertEquals('42', (string) $version);
    }

    public function testVersionCompareTo(): void {
        $v1 = new Version(1, $this->logger);
        $v2 = new Version(2, $this->logger);
        $v3 = new Version(1, $this->logger);

        $this->assertLessThan(0, $v1->compareTo($v2));
        $this->assertGreaterThan(0, $v2->compareTo($v1));
        $this->assertEquals(0, $v1->compareTo($v3));
    }

    public function testVersionIsNewerThan(): void {
        $v1 = new Version(1, $this->logger);
        $v2 = new Version(2, $this->logger);

        $this->assertTrue($v2->isNewerThan($v1));
        $this->assertFalse($v1->isNewerThan($v2));
    }

    public function testVersionIsOlderThan(): void {
        $v1 = new Version(1, $this->logger);
        $v2 = new Version(2, $this->logger);

        $this->assertTrue($v1->isOlderThan($v2));
        $this->assertFalse($v2->isOlderThan($v1));
    }

    public function testVersionEquals(): void {
        $v1 = new Version(5, $this->logger);
        $v2 = new Version(5, $this->logger);
        $v3 = new Version(6, $this->logger);

        $this->assertTrue($v1->equalsVersion($v2));
        $this->assertFalse($v1->equalsVersion($v3));
    }

    // ProgramVersion Tests
    public function testCreateProgramVersionEntity(): void {
        $version = new ProgramVersion(null, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('v0.0.0', $version->getValue());
    }

    public function testProgramVersionParsing(): void {
        $version = new ProgramVersion('v1.2.3', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(1, $version->getMajor());
        $this->assertEquals(2, $version->getMinor());
        $this->assertEquals(3, $version->getPatch());
        $this->assertNull($version->getPreRelease());
        $this->assertNull($version->getBuildMetadata());
    }

    public function testProgramVersionWithoutV(): void {
        $version = new ProgramVersion('2.0.1', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(2, $version->getMajor());
        $this->assertEquals(0, $version->getMinor());
        $this->assertEquals(1, $version->getPatch());
    }

    public function testProgramVersionPreRelease(): void {
        $version = new ProgramVersion('1.0.0-alpha', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('alpha', $version->getPreRelease());
        $this->assertTrue($version->isPreRelease());
    }

    public function testProgramVersionPreReleaseWithNumber(): void {
        $version = new ProgramVersion('1.0.0-beta.2', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('beta.2', $version->getPreRelease());
    }

    public function testProgramVersionBuildMetadata(): void {
        $version = new ProgramVersion('1.0.0+build.123', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('build.123', $version->getBuildMetadata());
    }

    public function testProgramVersionPreReleaseAndBuildMetadata(): void {
        $version = new ProgramVersion('1.0.0-rc.1+20231225', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals('rc.1', $version->getPreRelease());
        $this->assertEquals('20231225', $version->getBuildMetadata());
    }

    public function testProgramVersionMajorOnly(): void {
        $version = new ProgramVersion('5', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(5, $version->getMajor());
        $this->assertEquals(0, $version->getMinor());
        $this->assertEquals(0, $version->getPatch());
    }

    public function testProgramVersionMajorMinorOnly(): void {
        $version = new ProgramVersion('3.14', $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(3, $version->getMajor());
        $this->assertEquals(14, $version->getMinor());
        $this->assertEquals(0, $version->getPatch());
    }

    public function testProgramVersionCompareToSameMajor(): void {
        $v1 = new ProgramVersion('1.0.0', $this->logger);
        $v2 = new ProgramVersion('1.1.0', $this->logger);

        $this->assertLessThan(0, $v1->compareTo($v2));
        $this->assertGreaterThan(0, $v2->compareTo($v1));
    }

    public function testProgramVersionCompareToSameMajorMinor(): void {
        $v1 = new ProgramVersion('2.3.1', $this->logger);
        $v2 = new ProgramVersion('2.3.5', $this->logger);

        $this->assertLessThan(0, $v1->compareTo($v2));
    }

    public function testProgramVersionPreReleaseComparison(): void {
        $stable = new ProgramVersion('1.0.0', $this->logger);
        $alpha = new ProgramVersion('1.0.0-alpha', $this->logger);
        $beta = new ProgramVersion('1.0.0-beta', $this->logger);

        // Stabil > Pre-Release
        $this->assertGreaterThan(0, $stable->compareTo($alpha));
        $this->assertLessThan(0, $alpha->compareTo($stable));

        // beta > alpha (alphabetisch)
        $this->assertGreaterThan(0, $beta->compareTo($alpha));
    }

    public function testProgramVersionGetNormalized(): void {
        $version = new ProgramVersion('v1.2.3-beta+build', $this->logger);
        $this->assertEquals('1.2.3-beta+build', $version->getNormalized());
    }

    public function testProgramVersionGetNormalizedWithoutPreRelease(): void {
        $version = new ProgramVersion('2.0.0', $this->logger);
        $this->assertEquals('2.0.0', $version->getNormalized());
    }

    public function testInvalidProgramVersion(): void {
        $version = new ProgramVersion('not-a-version', $this->logger);
        $this->assertFalse($version->isValid());
    }

    public function testProgramVersionNumericFallback(): void {
        $version = new ProgramVersion(42, $this->logger);
        $this->assertTrue($version->isValid());
        $this->assertEquals(42, $version->getValue());
    }

    public function testProgramVersionIsNewerThan(): void {
        $v1 = new ProgramVersion('1.0.0', $this->logger);
        $v2 = new ProgramVersion('2.0.0', $this->logger);

        $this->assertTrue($v2->isNewerThan($v1));
        $this->assertFalse($v1->isNewerThan($v2));
    }
}
