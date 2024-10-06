<?php

declare(strict_types=1);

namespace APIToolkit\Enums;

enum Month: int {
    case JANUARY = 1;
    case FEBRUARY = 2;
    case MARCH = 3;
    case APRIL = 4;
    case MAY = 5;
    case JUNE = 6;
    case JULY = 7;
    case AUGUST = 8;
    case SEPTEMBER = 9;
    case OCTOBER = 10;
    case NOVEMBER = 11;
    case DECEMBER = 12;

    public function getName(string $locale = 'en'): string {
        return match ($locale) {
            'de' => match ($this) {
                self::JANUARY => 'Januar',
                self::FEBRUARY => 'Februar',
                self::MARCH => 'März',
                self::APRIL => 'April',
                self::MAY => 'Mai',
                self::JUNE => 'Juni',
                self::JULY => 'Juli',
                self::AUGUST => 'August',
                self::SEPTEMBER => 'September',
                self::OCTOBER => 'Oktober',
                self::NOVEMBER => 'November',
                self::DECEMBER => 'Dezember',
            },
            default => match ($this) {
                self::JANUARY => 'January',
                self::FEBRUARY => 'February',
                self::MARCH => 'March',
                self::APRIL => 'April',
                self::MAY => 'May',
                self::JUNE => 'June',
                self::JULY => 'July',
                self::AUGUST => 'August',
                self::SEPTEMBER => 'September',
                self::OCTOBER => 'October',
                self::NOVEMBER => 'November',
                self::DECEMBER => 'December',
            },
        };
    }

    public static function toArray(bool $leadingZero = false, string $locale = 'en'): array {
        $monthsArray = [];
        foreach (self::cases() as $month) {
            $key = $leadingZero ? str_pad((string)$month->value, 2, '0', STR_PAD_LEFT) : $month->value;
            $monthsArray[$key] = $month->getName($locale);
        }
        return $monthsArray;
    }
}
