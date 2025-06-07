#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Parser/DomainParser.php';

use SilvarCode\DomainParser\Parser\DomainParser;

// Check if host argument is provided
if ($argc < 2) {
    fwrite(STDERR, "Usage: php check.php <host>\n");
    exit(1);
}

$host = $argv[1] ?? 'your-domain.com';
$parser = new DomainParser(true, []);
$parser::showResult([
    'host' => $host,
    'domain' => $parser->getRegistrableDomain($host),
    'subdomain' => $parser->getSubdomain($host),
    'subdomains' => $parser->getSubdomains($host),
]);
