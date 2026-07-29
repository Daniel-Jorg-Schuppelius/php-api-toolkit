<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MoneyAccessorTrait.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Traits;

use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Geldbeträge von API-Entities.
 *
 * REST-APIs liefern Beträge als JSON-Zahlen; die Hydrierung legt sie deshalb
 * weiterhin als float ab (mehr Genauigkeit hat die Quelle nicht). **Gelesen**
 * wird ausschließlich {@see Money}: die Umwandlung passiert an dieser einen
 * Stelle, inklusive Währung — ab dort rechnet der Aufrufer exakt.
 *
 * Die Belegwährung wird aus dem ersten gefundenen Währungsfeld der Entity
 * gelesen; ein SDK mit abweichender Feldbenennung überschreibt dafür
 * {@see currencyFieldCandidates()}, ein SDK mit anderer Standardwährung
 * {@see defaultCurrency()}.
 */
trait MoneyAccessorTrait {
    /**
     * Rohbetrag der API → Money in der Belegwährung (null bleibt null).
     */
    protected function toMoney(?float $amount, ?CurrencyCode $currency = null): ?Money {
        if ($amount === null) {
            return null;
        }

        return Money::ofFloat($amount, $currency ?? $this->entityCurrency());
    }

    /**
     * Belegwährung der Entity; ohne eigenes Feld gilt defaultCurrency().
     */
    protected function entityCurrency(): CurrencyCode {
        foreach ($this->currencyFieldCandidates() as $field) {
            if (!property_exists($this, $field) || !isset($this->{$field})) {
                continue;
            }

            $value = $this->{$field};
            if ($value instanceof CurrencyCode) {
                return $value;
            }
            if (is_string($value) && $value !== '') {
                return CurrencyCode::tryFrom(strtoupper($value)) ?? $this->defaultCurrency();
            }
        }

        return $this->defaultCurrency();
    }

    /**
     * Property-Namen, unter denen die Entity ihre Währung führen kann.
     *
     * @return array<int, string>
     */
    protected function currencyFieldCandidates(): array {
        return ['currency', 'currencyCode', 'currency_code', 'waehrung'];
    }

    /**
     * Währung, wenn die Entity keine eigene führt — deutschsprachige
     * Buchhaltungs-APIs rechnen in Euro.
     */
    protected function defaultCurrency(): CurrencyCode {
        return CurrencyCode::Euro;
    }
}
