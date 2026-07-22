<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntityRoundTripTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\Entities\Bank\{BIC, IBAN};
use APIToolkit\Entities\Contact\EmailAddress;
use APIToolkit\Entities\{GUID, ID, UUID, Version};
use Tests\Contracts\Test;

class EntityRoundTripTest extends Test {
    /**
     * toArray() must produce the entity's own key and fromArray() must accept
     * it again — previously broken because entityName was assigned after
     * parent::__construct(), so validateData() ran against the FQCN.
     */
    public function test_scalar_value_objects_round_trip_through_array(): void {
        $cases = [
            [new IBAN('DE44500105175407324931'), 'iban'],
            [new BIC('INGDDEFFXXX'), 'bic'],
            [new EmailAddress('user@example.com'), 'emailAddress'],
            [new ID(42), 'id'],
            [new Version(3), 'version'],
            [new UUID('550e8400-e29b-41d4-a716-446655440000'), 'uuid'],
            [new GUID('550e8400-e29b-41d4-a716-446655440000'), 'guid'],
        ];

        foreach ($cases as [$entity, $expectedKey]) {
            $array = $entity->toArray();
            $this->assertArrayHasKey($expectedKey, $array, get_class($entity) . ' should serialize under its own key');

            $restored = $entity::fromArray($array);
            $this->assertSame($entity->getValue(), $restored->getValue(), get_class($entity) . ' round-trip should preserve the value');
        }
    }
}
