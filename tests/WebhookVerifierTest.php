<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookVerifierTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\Webhook\WebhookVerifier;
use InvalidArgumentException;
use Tests\Contracts\Test;

class WebhookVerifierTest extends Test {
    private string $secret = 'whsec_test_secret';
    private string $body = '{"event":"payment.succeeded","id":"evt_123"}';

    public function test_verify_accepts_matching_hex_signature(): void {
        $verifier = new WebhookVerifier;
        $sig = hash_hmac('sha256', $this->body, $this->secret);

        $this->assertTrue($verifier->verify($this->body, $sig, $this->secret));
        $this->assertTrue($verifier->verify($this->body, 'sha256=' . $sig, $this->secret));
    }

    public function test_verify_accepts_matching_base64_signature(): void {
        $verifier = new WebhookVerifier;
        $sig = base64_encode(hash_hmac('sha256', $this->body, $this->secret, true));

        $this->assertTrue($verifier->verify($this->body, $sig, $this->secret));
    }

    public function test_verify_rejects_wrong_signature_and_tampered_body(): void {
        $verifier = new WebhookVerifier;
        $sig = hash_hmac('sha256', $this->body, $this->secret);

        $this->assertFalse($verifier->verify($this->body, 'deadbeef', $this->secret));
        $this->assertFalse($verifier->verify($this->body . 'x', $sig, $this->secret));
        $this->assertFalse($verifier->verify($this->body, $sig, 'wrong_secret'));
        $this->assertFalse($verifier->verify($this->body, '', $this->secret));
    }

    public function test_verify_timestamped_accepts_fresh_signature(): void {
        $verifier = new WebhookVerifier;
        $ts = 1_700_000_000;
        $sig = hash_hmac('sha256', $ts . '.' . $this->body, $this->secret);
        $header = "t={$ts},v1={$sig}";

        $this->assertTrue($verifier->verifyTimestamped($this->body, $header, $this->secret, 300, $ts + 10));
    }

    public function test_verify_timestamped_rejects_replay_outside_tolerance(): void {
        $verifier = new WebhookVerifier;
        $ts = 1_700_000_000;
        $sig = hash_hmac('sha256', $ts . '.' . $this->body, $this->secret);
        $header = "t={$ts},v1={$sig}";

        $this->assertFalse($verifier->verifyTimestamped($this->body, $header, $this->secret, 300, $ts + 1000));
    }

    public function test_verify_timestamped_rejects_wrong_signature(): void {
        $verifier = new WebhookVerifier;
        $ts = 1_700_000_000;

        $this->assertFalse($verifier->verifyTimestamped($this->body, "t={$ts},v1=deadbeef", $this->secret, 300, $ts + 10));
        $this->assertFalse($verifier->verifyTimestamped($this->body, 'no-timestamp-here', $this->secret, 300, $ts));
    }

    public function test_constructor_rejects_unknown_algorithm(): void {
        $this->expectException(InvalidArgumentException::class);
        new WebhookVerifier('not-a-real-algo');
    }
}
