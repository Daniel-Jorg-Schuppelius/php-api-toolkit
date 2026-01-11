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

use ERRORToolkit\Factories\ConsoleLoggerFactory;
use ERRORToolkit\Traits\ErrorLog;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ApiException extends Exception {
    use ErrorLog;

    protected ?ResponseInterface $response;
    protected ?string $responseContent = null;

    public function __construct(string $message = '', int $code = 0, ?ResponseInterface $response = null, ?Exception $previous = null, ?LoggerInterface $logger = null) {
        parent::__construct($message, $code, $previous);
        $this->initializeLogger($logger);
        $this->response = $response;
        $this->responseContent = $this->extractContent();

        $context = [
            'status_code' => $code,
            'response_content' => $this->responseContent,
        ];

        if ($response !== null) {
            $context['response_headers'] = $response->getHeaders();
        }

        self::logException($this, context: $context);
    }

    public function getResponse(): ?ResponseInterface {
        return $this->response;
    }

    public function getContent(): ?string {
        return $this->responseContent;
    }

    protected function extractContent(): ?string {
        if ($this->response === null) {
            return null;
        }
        $body = $this->response->getBody();
        $content = $body->getContents();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        return $content;
    }
}
