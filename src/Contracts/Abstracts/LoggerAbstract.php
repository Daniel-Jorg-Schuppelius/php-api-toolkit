<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NamedValues.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

abstract class LoggerAbstract implements LoggerInterface {
    public function emergency(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function generateLogEntry($level, string|\Stringable $message, array $context = []): string {
        $timestamp = date('Y-m-d H:i:s');
        $contextString = empty($context) ? "" : " " . json_encode($context);
        return  "[{$timestamp}] {$level}: {$message}{$contextString}";
    }

    abstract public function log($level, string|\Stringable $message, array $context = []): void;
}
