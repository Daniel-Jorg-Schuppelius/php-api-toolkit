<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileLogger.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace APIToolkit\Logger;

use APIToolkit\Contracts\Abstracts\LoggerAbstract;
use Exception;

class FileLogger extends LoggerAbstract {
    protected string $logFile;

    public function __construct(?string $logFile = null) {
        if (is_null($logFile) || !is_writable(dirname($logFile))) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';
        }
        $this->logFile = $logFile;
        if (!file_exists($logFile)) {
            try {
                file_put_contents($logFile, "", FILE_APPEND);
            } catch (Exception $e) {
                throw new Exception("Fehler beim Erstellen der Log-Datei: " . $e->getMessage());
            }
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        $logEntry = parent::generateLogEntry($level, $message, $context);

        try {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        } catch (Exception $e) {
            throw new Exception("Fehler beim Schreiben in die Log-Datei: " . $e->getMessage());
        }
    }
}
