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

        $paths = [];

        while ($csvRow = fgetcsv($input, escape: '')) {
            $urlInput = $csvRow[0];
            $dateInput = $csvRow[1];

            $path = $paths[$urlInput] ??= $this->parsePathFromUrl($urlInput);

            $output[$path] ??= [];

            $date = new DateTimeImmutable($dateInput);
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

    private function parsePathFromUrl(string $urlInput): mixed
    {
        return parse_url($urlInput, PHP_URL_PATH);
    }
}
