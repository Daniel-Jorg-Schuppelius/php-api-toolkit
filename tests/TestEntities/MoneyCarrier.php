<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoneyCarrier.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests\TestEntities;

use APIToolkit\Traits\MoneyAccessorTrait;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

class MoneyCarrier {
    use MoneyAccessorTrait;

    protected CurrencyCode|string|null $currency;

    public function __construct(CurrencyCode|string|null $currency = null) {
        $this->currency = $currency;
    }

    public function total(?float $amount, ?CurrencyCode $explicitCurrency = null): ?Money {
        return $this->toMoney($amount, $explicitCurrency);
    }
}
