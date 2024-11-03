<?php
/*
 * Created on   : Sat Nov 02 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VAT.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Tax;

use APIToolkit\Entities\ID;
use Psr\Log\LoggerInterface;

class VAT extends ID {
    protected const PATTERNS = [
        'AT' => '/^U\d{8}$/',                       // Österreich
        'BE' => '/^\d{10}$/',                       // Belgien
        'BG' => '/^\d{9,10}$/',                     // Bulgarien
        'CY' => '/^\d{8}[A-Z]$/',                   // Zypern
        'CZ' => '/^\d{8,10}$/',                     // Tschechien
        'DE' => '/^\d{9}$/',                        // Deutschland
        'DK' => '/^\d{8}$/',                        // Dänemark
        'EE' => '/^\d{9}$/',                        // Estland
        'EL' => '/^\d{9}$/',                        // Griechenland
        'ES' => '/^[A-Z]\d{7}[A-Z]$/',              // Spanien
        'FI' => '/^\d{8}$/',                        // Finnland
        'FR' => '/^[A-Z0-9]{2}\d{9}$/',             // Frankreich, mit möglichen Buchstaben
        'HR' => '/^\d{11}$/',                       // Kroatien
        'HU' => '/^\d{8}$/',                        // Ungarn
        'IE' => '/^\d{7}[A-Z]{1,2}$/',              // Irland
        'IT' => '/^\d{11}$/',                       // Italien
        'LT' => '/^(\d{9}|\d{12})$/',               // Litauen, entweder 9 oder 12 Ziffern
        'LU' => '/^\d{8}$/',                        // Luxemburg
        'LV' => '/^\d{11}$/',                       // Lettland
        'MT' => '/^\d{8}$/',                        // Malta
        'NL' => '/^\d{9}B\d{2}$/',                  // Niederlande
        'PL' => '/^\d{10}$/',                       // Polen
        'PT' => '/^\d{9}$/',                        // Portugal
        'RO' => '/^\d{2,10}$/',                     // Rumänien, 2 bis 10 Ziffern
        'SE' => '/^\d{12}$/',                       // Schweden
        'SI' => '/^\d{8}$/',                        // Slowenien
        'SK' => '/^\d{10}$/',                       // Slowakei
        'GB' => '/^(\d{9}|\d{12}|(GD|HA)\d{3})$/',  // Vereinigtes Königreich, mit speziellen GD/HA-Codes
        'CH' => '/^CHE\d{9}(MWST|TVA|IVA)$/',       // Schweiz, mit Mehrwertsteuerangabe
        'NO' => '/^\d{9}MVA$/',                     // Norwegen, mit MVA-Suffix
        'IS' => '/^\d{5,6}$/',                      // Island, 5 oder 6 Ziffern
        'LI' => '/^\d{11}$/',                       // Liechtenstein
        'RU' => '/^\d{10,12}$/',                    // Russland, 10 oder 12 Ziffern
    ];

    public function __construct($data = null, ?LoggerInterface $logger = null) {
        if (isset($data) && is_string($data)) {
            $data = strtoupper(trim($data));
        } elseif (is_null($data)) {
            $data = "";
        }
        parent::__construct($data, $logger);
    }

    public function isValid(): bool {
        if (!isset($this->value) || strlen($this->value) < 9) {
            return false;
        }

        $countryCode = substr($this->value, 0, 2);
        $vatNumber = substr($this->value, 2);

        return isset(self::PATTERNS[$countryCode]) && preg_match(self::PATTERNS[$countryCode], $vatNumber) === 1;
    }
}
