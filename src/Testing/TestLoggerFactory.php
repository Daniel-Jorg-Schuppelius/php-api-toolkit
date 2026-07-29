<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TestLoggerFactory.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Testing;

use ERRORToolkit\Enums\LogType;
use ERRORToolkit\Factories\{ConsoleLoggerFactory, FileLoggerFactory};
use ERRORToolkit\LoggerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Logger für Testläufe eines SDK.
 *
 * Liefert je nach `<PREFIX>_LOG_TYPE` einen Konsolen- oder Datei-Logger und
 * trägt ihn in die {@see LoggerRegistry} ein — ohne den Eintrag landen die
 * Logs des Toolkits im formatarmen syslog-Fallback ohne Aufrufer-Angabe.
 */
final class TestLoggerFactory {
    private static ?LoggerInterface $logger = null;

    /**
     * @param string $envPrefix Präfix der Umgebungsvariablen, z. B. 'DATEV' für
     *                          DATEV_LOG_TYPE und DATEV_LOG_FILE
     */
    public static function get(string $envPrefix = 'API'): LoggerInterface {
        if (self::$logger === null) {
            $logType = self::env($envPrefix . '_LOG_TYPE') ?: LogType::CONSOLE->value;

            self::$logger = match ($logType) {
                LogType::FILE->value => FileLoggerFactory::getLogger(
                    self::env($envPrefix . '_LOG_FILE') ?: sys_get_temp_dir() . '/' . strtolower($envPrefix) . '-sdk.log'
                ),
                default => ConsoleLoggerFactory::getLogger(),
            };

            LoggerRegistry::setLogger(self::$logger);
        }

        return self::$logger;
    }

    /** Setzt den zwischengespeicherten Logger zurück (für Tests der Factory selbst). */
    public static function reset(): void {
        self::$logger = null;
    }

    private static function env(string $name): ?string {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
