<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Link.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Entities\Information;

use APIToolkit\Contracts\Abstracts\NamedValue;
use CommonToolkit\Helper\Data\WebLinkHelper;
use Psr\Log\LoggerInterface;

class Link extends NamedValue {
    public function __construct(mixed $data = null, ?LoggerInterface $logger = null) {
        if (is_string($data)) {
            $data = WebLinkHelper::normalize($data) ?? $data;
        }
        parent::__construct($data, $logger);
        $this->entityName = 'link';
    }

    public function isValid(): bool {
        return WebLinkHelper::isUrl($this->value);
    }

    public function isValidStrict(bool $checkDns = false): bool {
        return WebLinkHelper::validateUrl($this->value, $checkDns);
    }

    public function isHttpUrl(): bool {
        return WebLinkHelper::isHttpUrl($this->value);
    }

    public function isSecure(): bool {
        return WebLinkHelper::isSecure($this->value);
    }

    public function getScheme(): ?string {
        return WebLinkHelper::getScheme($this->value);
    }

    public function getHost(): ?string {
        return WebLinkHelper::getHost($this->value);
    }

    public function getDomain(): ?string {
        return WebLinkHelper::getDomain($this->value);
    }

    public function getSubdomain(): ?string {
        return WebLinkHelper::getSubdomain($this->value);
    }

    public function getPath(): ?string {
        return WebLinkHelper::getPath($this->value);
    }

    public function getPort(): ?int {
        return WebLinkHelper::getPort($this->value);
    }

    public function isReachable(int $timeout = 5): bool {
        return WebLinkHelper::isReachable($this->value, $timeout);
    }
}