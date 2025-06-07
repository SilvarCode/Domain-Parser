
# SilvarCode Domain Parser

[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)]()
[![Release](https://img.shields.io/badge/release-latest-blue.svg)]()

**SilvarCode Domain Parser** is a robust and efficient PHP library for parsing and validating domain names using the [Public Suffix List](https://publicsuffix.org).

It supports complex TLD structures and wildcard suffixes, making it suitable for both general-purpose and enterprise-level domain validation.

## Features

- ✅ Parses **registrable domains** from full hostnames
- 🔍 Extracts **subdomain** and **subdomain hierarchy**
- 🌐 Supports wildcard and multi-level TLDs (e.g., `*.k12.ak.us`, `*.sch.uk`)
- 📥 Uses the official Public Suffix List
- 🧠 Configurable **in-memory caching**
- 🛠️ Custom suffix injection for **internal/private networks**

## Requirements

- PHP 8.2 or higher

## Installation

```bash
composer require silvarcode/domain-parser
```

## Usage

### Instantiating the DomainParser

You can create a new instance of the `DomainParser` class with optional memory caching enabled and provide custom suffixes if needed:

| Parameter     | Type  | Description                                                      |
| ------------- | ----- | ---------------------------------------------------------------- |
| `memoryCache` | bool  | Whether to load the suffix set into memory for improved performance |
| `suffixSet`   | array | An optional list of custom suffixes (e.g., for internal domains) |

### Example

```php
use SilvarCode\DomainParser\Parser\DomainParser;

// Instantiate parser with memory cache enabled
$parser = new DomainParser(true);

// Instantiate parser with memory cache and custom suffixes
$parser2 = new DomainParser(true, ['com.internal']);

$checkHost1 = 'sub2.sub1.example.com';
$checkHost2 = 'sub2.sub1.example.com.internal';

// Show parsing results for checkHost1 using $parser
$parser->showResult([
    'tld' => $parser->tld($checkHost1),
    'domain' => $parser->getRegistrableDomain($checkHost1),
    'subdomain' => $parser->getSubdomain($checkHost1),
    'subdomains' => $parser->getSubdomains($checkHost1),
]);

// Show parsing results for checkHost2 using $parser
$parser->showResult([
    'tld' => $parser->tld($checkHost2),
    'domain' => $parser->getRegistrableDomain($checkHost2),
    'subdomain' => $parser->getSubdomain($checkHost2),
    'subdomains' => $parser->getSubdomains($checkHost2),
]);

// Show parsing results for checkHost2 using $parser2 with custom suffixes
$parser2->showResult([
    'tld' => $parser2->tld($checkHost2),
    'domain' => $parser2->getRegistrableDomain($checkHost2),
    'subdomain' => $parser2->getSubdomain($checkHost2),
    'subdomains' => $parser2->getSubdomains($checkHost2),
]);
```

### 💻 Command-Line Usage (via Composer)
If you’ve defined the CLI entry point in composer.json like this:

```json
"bin": [
  "bin/check.php"
],
"scripts": {
  "check-domain": "bin/check.php"
}

```

You can run domain checks directly from the terminal:

```bash
composer check-domain sub2.sub1.example.com
```

Output:
```yaml
Host: sub2.sub1.example.com
Domain: example.com
Subdomain: sub2
Subdomains:
  - sub2
  - sub1
```

## Learn More!

We’ve published an in-depth blog post on our website that covers the design, features, and practical usage of the SilvarCode Domain Parser.  
It’s a great resource if you want to understand the package better or see real-world examples.

👉 [Read the full blog post here](https://silvarcode.com/blog/en-gb/post/a062f6fa-f753-4c3c-8031-20a65b8e9aff/introducing-silvarcode-domain-parser-a-simple-and-accurate-php-tool-for-validation)

Feel free to share your feedback or questions in the GitHub issues or discussions!


## License

**MIT License**  
© 2025 SILVARCODE LTD  
Author: Marcus Ribeiro (<mds@silvarcode.com>)

This software is provided *"as is"*, without any warranty of any kind whatsoever.
