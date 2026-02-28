<?php

namespace App;

use DateTimeImmutable;
use Exception;

final class Parser
{
    /**
     * @throws \DateMalformedStringException
     */
    public function parse(string $inputPath, string $outputPath): void
    {
        ini_set('memory_limit','16G');
        $output = [];

        $input = fopen($inputPath, 'r');

        while ($csvRow = fgetcsv($input, escape: '')) {
            $urlInput = $csvRow[0];
            $dateInput = $csvRow[1];

            $date = new DateTimeImmutable($dateInput);
            $parsedUrl = parse_url($urlInput);
            $path = $parsedUrl['path'];

            $output[$path] ??= [];

            $formattedDate = $date->format('Y-m-d');
            $output[$path][$formattedDate] ??= 0;
            $output[$path][$formattedDate]++;
        }

        foreach ($output as &$data) {
            ksort($data);
        }

        $json = json_encode($output, flags: JSON_PRETTY_PRINT);
        file_put_contents($outputPath, $json);
    }
}
