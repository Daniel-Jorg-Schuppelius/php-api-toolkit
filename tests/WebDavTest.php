<?php
/*
 * Created on   : Fri Sep 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebDavTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\WebDav\{MultiStatus, Propfind};
use InvalidArgumentException;
use Tests\Contracts\Test;

class WebDavTest extends Test {
    public function test_propfind_body_declares_namespaces_and_properties(): void {
        $body = Propfind::body(['d:getetag', 'oc:fileid'], ['oc' => 'http://owncloud.org/ns']);

        $this->assertSame(
            '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
            . '<d:prop><d:getetag/><oc:fileid/></d:prop></d:propfind>',
            $body,
        );
    }

    public function test_propfind_body_rejects_injection_and_unknown_prefixes(): void {
        foreach ([['d:getetag/><x'], ['oc:fileid'], [], ['getetag']] as $properties) {
            try {
                Propfind::body($properties);
                $this->fail('Exception erwartet für ' . json_encode($properties));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_multistatus_keeps_only_successful_propstat_values(): void {
        $xml = <<<'XML'
<?xml version="1.0"?>
<d:multistatus xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <d:response>
    <d:href>/remote.php/dav/files/u/Ordner/</d:href>
    <d:propstat>
      <d:prop><d:resourcetype><d:collection/></d:resourcetype><d:getetag>"abc"</d:getetag></d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
    <d:propstat>
      <d:prop><d:getcontentlength/></d:prop>
      <d:status>HTTP/1.1 404 Not Found</d:status>
    </d:propstat>
  </d:response>
  <d:response>
    <d:href>/remote.php/dav/files/u/Ordner/Bericht%20M%C3%A4rz.pdf</d:href>
    <d:propstat>
      <d:prop>
        <d:resourcetype/>
        <d:getcontentlength>1234</d:getcontentlength>
        <oc:fileid>42</oc:fileid>
      </d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
</d:multistatus>
XML;

        $responses = MultiStatus::parse($xml);

        $this->assertCount(2, $responses);
        [$dir, $file] = $responses;
        $this->assertTrue($dir->isCollection);
        $this->assertSame('abc', $dir->etag());
        $this->assertNull($dir->property('DAV:', 'getcontentlength'), '404-propstat zählt nicht');
        $this->assertFalse($file->isCollection);
        $this->assertSame('/remote.php/dav/files/u/Ordner/Bericht%20M%C3%A4rz.pdf', $file->href);
        $this->assertSame('1234', $file->property('DAV:', 'getcontentlength'));
        $this->assertSame('42', $file->property('http://owncloud.org/ns', 'fileid'));
        $this->assertNull($file->status);
    }

    public function test_propstat_without_status_counts_as_successful(): void {
        $xml = '<d:multistatus xmlns:d="DAV:"><d:response><d:href>/dav/unter/</d:href>'
            . '<d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop></d:propstat>'
            . '</d:response></d:multistatus>';

        [$response] = MultiStatus::parse($xml);

        $this->assertTrue($response->isCollection);
    }

    public function test_multistatus_reports_response_level_deletions(): void {
        $xml = '<d:multistatus xmlns:d="DAV:"><d:response><d:href>/cal/a.ics</d:href>'
            . '<d:status>HTTP/1.1 404 Not Found</d:status></d:response>'
            . '<d:response><d:href>/cal/b.ics</d:href><d:propstat><d:prop><d:getetag>"2"</d:getetag></d:prop>'
            . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
            . '<d:sync-token>http://x/sync/5</d:sync-token></d:multistatus>';

        [$gone, $changed] = MultiStatus::parse($xml);

        $this->assertTrue($gone->isGone());
        $this->assertSame(404, $gone->status);
        $this->assertFalse($changed->isGone());
        $this->assertSame('2', $changed->etag());
    }

    public function test_multistatus_rejects_malformed_xml_and_ignores_entities(): void {
        $xxe = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
            . '<d:multistatus xmlns:d="DAV:"><d:response><d:href>&x;</d:href></d:response></d:multistatus>';
        $this->assertSame([], MultiStatus::parse($xxe), 'Entity bleibt unaufgelöst, href leer → übersprungen');

        $this->expectException(InvalidArgumentException::class);
        MultiStatus::parse('<d:multistatus');
    }
}
