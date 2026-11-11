<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdCarrier.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Contracts\Abstracts\NamedEntity;
use APIToolkit\Entities\{GUID, ID};
use Psr\Log\LoggerInterface;

class IdCarrier extends NamedEntity {
    protected ?ID $id;
    protected ?ID $other_id;
    protected ?GUID $owner_id;
    protected ?WrappedObjectID $created_id;
    protected ?RoleNumber $role;

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
    }
}
