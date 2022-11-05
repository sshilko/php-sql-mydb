<?php

declare(strict_types = 1);

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
$txtFile = $baseDir . 'phpunit-coverage.txt';
$input   = file($txtFile, FILE_SKIP_EMPTY_LINES);

if (false === $input) {
    echo 'Failed to open coverage file: ' . $txtfile;
    exit(1);
}

$label  = 'PHPUnit';
$color  = 'blue';
$source = 'https://img.shields.io/static/v1?';

$output = $baseDir . 'phpunit-coverage-badge-%s.svg';

foreach ($input as $line) {
    if ('' === trim($line)) {
        continue;
    }

    echo trim($line) . "\n";
    $matches = [];
    if (!preg_match('/\b(Classes|Lines|Methods)\b:\s+(.*)/', $line, $matches)) {
        continue;
    }
    $category = trim($matches[1]);

    $query = http_build_query(
        [
            'label' => $label . ' ' . $category,
            'color' => $color,
            'message' => trim($matches[2]),
        ]
    );
    $remote = $source . $query;

    $file = strtolower(sprintf($output, $category));
    $image = file_get_contents($remote);
    $imglen = strlen($image);
    if ($imglen > 0) {
        file_put_contents($file, $image);
        echo 'Saved badge: ' . $file . ' ' . $imglen . " bytes\n";
    } else {
        echo 'Failed to fetch badge from ' . $remote . "\n";
        exit(1);
    }
}
