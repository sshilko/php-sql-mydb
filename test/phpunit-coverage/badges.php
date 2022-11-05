<?php

declare(strict_types = 1);

$input = file(__DIR__ . DIRECTORY_SEPARATOR . 'phpunit-coverage.txt', FILE_SKIP_EMPTY_LINES) or exit(1);

echo "\n" . $input . "\n";

$label = 'PHPUnit';
$color = 'blue';
$source = 'https://img.shields.io/static/v1?';
$file = __DIR__ . DIRECTORY_SEPARATOR . 'phpunit-coverage-badge-%s.svg';

foreach ($input as $line) {
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

    $output = strtolower(sprintf($file, $category));
    $image = file_get_contents($remote);
    $imglen = strlen($image);
    if (imglen > 0) {
        file_put_contents($output, $image);
        echo 'Saved badge bytes ' . $imglen;
    } else {
        echo 'Failed to fetch badge';
    }
}
