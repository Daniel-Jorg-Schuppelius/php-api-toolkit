<?php

declare(strict_types=1);

namespace APIToolkit\Entities\Bank;

use APIToolkit\Contracts\Abstracts\NamedValue;
use Psr\Log\LoggerInterface;

class IBAN extends NamedValue {
    public function __construct($data = null, ?LoggerInterface $logger = null) {
        parent::__construct($data, $logger);
        $this->entityName = 'iban';
    }

    public function isValid(): bool {
        if (empty($this->value)) {
            return false;
        }

        $iban = strtoupper(str_replace(' ', '', $this->value));

        if (!$this->isValidLength($iban)) {
            return false;
        }

        return $this->verifyChecksum($iban);
    }

    private function isValidLength(string $iban): bool {
        $ibanLengths = [
            'AL' => 28, // Albanien
            'AD' => 24, // Andorra
            'AT' => 20, // Österreich
            'AZ' => 28, // Aserbaidschan
            'BH' => 22, // Bahrain
            'BE' => 16, // Belgien
            'BA' => 20, // Bosnien und Herzegowina
            'BR' => 29, // Brasilien
            'BG' => 22, // Bulgarien
            'CR' => 22, // Costa Rica
            'HR' => 21, // Kroatien
            'CY' => 28, // Zypern
            'CZ' => 24, // Tschechien
            'DK' => 18, // Dänemark
            'DO' => 28, // Dominikanische Republik
            'EE' => 20, // Estland
            'FO' => 18, // Färöer
            'FI' => 18, // Finnland
            'FR' => 27, // Frankreich
            'GE' => 22, // Georgien
            'DE' => 22, // Deutschland
            'GI' => 23, // Gibraltar
            'GR' => 27, // Griechenland
            'GL' => 18, // Grönland
            'GT' => 28, // Guatemala
            'HU' => 28, // Ungarn
            'IS' => 26, // Island
            'IE' => 22, // Irland
            'IL' => 23, // Israel
            'IT' => 27, // Italien
            'JO' => 30, // Jordanien
            'KZ' => 20, // Kasachstan
            'XK' => 20, // Kosovo
            'KW' => 30, // Kuwait
            'LV' => 21, // Lettland
            'LB' => 28, // Libanon
            'LI' => 21, // Liechtenstein
            'LT' => 20, // Litauen
            'LU' => 20, // Luxemburg
            'MT' => 31, // Malta
            'MR' => 27, // Mauretanien
            'MU' => 30, // Mauritius
            'MD' => 24, // Moldawien
            'MC' => 27, // Monaco
            'ME' => 22, // Montenegro
            'NL' => 18, // Niederlande
            'NO' => 15, // Norwegen
            'PK' => 24, // Pakistan
            'PS' => 29, // Palästina
            'PL' => 28, // Polen
            'PT' => 25, // Portugal
            'QA' => 29, // Katar
            'RO' => 24, // Rumänien
            'SM' => 27, // San Marino
            'SA' => 24, // Saudi-Arabien
            'RS' => 22, // Serbien
            'SK' => 24, // Slowakei
            'SI' => 19, // Slowenien
            'ES' => 24, // Spanien
            'SE' => 24, // Schweden
            'CH' => 21, // Schweiz
            'TN' => 24, // Tunesien
            'TR' => 26, // Türkei
            'AE' => 23, // Vereinigte Arabische Emirate
            'GB' => 22, // Großbritannien
            'VG' => 24, // Britische Jungferninseln
            // TODO: Add more countries
        ];

        $countryCode = substr($iban, 0, 2);

        return isset($ibanLengths[$countryCode]) && strlen($iban) === $ibanLengths[$countryCode];
    }

    private function verifyChecksum(string $iban): bool {
        $movedIban = substr($iban, 4) . substr($iban, 0, 4);

        $numericIban = '';
        foreach (str_split($movedIban) as $char) {
            if (ctype_alpha($char)) {
                $numericIban .= ord($char) - 55;
            } else {
                $numericIban .= $char;
            }
        }

        return bcmod($numericIban, '97') === '1';
    }
}
