<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiException.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Exceptions;

use APIToolkit\Factories\ConsoleLoggerFactory;
use APIToolkit\Traits\ErrorLog;
use Exception;
use Psr\Log\LoggerInterface;

class ApiException extends Exception {
    use ErrorLog;

    protected $response;

    public function __construct($message = '', int $code = 0, $response = null, Exception $previous = null, LoggerInterface $logger = null) {
        parent::__construct($message, $code, $previous);
        $this->logger = $logger ?? ConsoleLoggerFactory::getLogger();
        $this->response = $response;
        $content = $this->getContent();
        $this->logError("$message (Errorcode: $code)" . (empty($content) ? "" : ": " . $content));
    }

    public function getResponse() {
        return $this->response;
    }

    public function getContent() {
        if ($this->response === null) {
            return null;
        }
        return $this->response->getBody()->getContents();
    }
}
