<?php

namespace App;

use function array_fill;
use function file_put_contents;
use function fopen;
use function fread;
use function gc_disable;
use function strlen;
use function strpos;
use function stream_set_read_buffer;
use function substr;

final class Parser
{
    const int FILE_READ_SIZE = 262_144;

    /**
     * @throws \DateMalformedStringException
     */
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        // pre-compute dates
        $dateIds = [];
        $dates = [];
        $initialCounts = [];
        $dateCount = 0;
        for ($y = 21; $y <= 26; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $maxD = match ($m) {
                    2 => $y === 24 ? 29 : 28,
                    4, 6, 9, 11 => 30,
                    default => 31,
                };
                $mStr = ($m < 10 ? '0' : '') . $m;
                $ymStr = "{$y}-{$mStr}-";
                for ($d = 1; $d <= $maxD; $d++) {
                    $dStr = ($d < 10 ? '0' : '') . $d;
                    $dateIds["{$ymStr}{$dStr}"] = $dateCount;
                    $dates[$dateCount] = "20{$ymStr}{$dStr}";
                    $initialCounts[$dateCount] = 0;
                    $dateCount++;
                }
            }
        }

        $pathCount = 0;
        $outputData = [];

        $input = fopen($inputPath, 'r');
        stream_set_read_buffer($input, self::FILE_READ_SIZE);

        $carry = '';
        while (($chunk = fread($input, self::FILE_READ_SIZE)) !== '') {
            $buffer = $carry . $chunk;
            $position = 0;

            while (($lineEnd = strpos($buffer, PHP_EOL, $position)) !== false) {
                $lineLength = $lineEnd - $position;

                $path = substr($buffer, $position + 25, $lineLength - 51);
                $date = substr($buffer, $lineEnd - 23, 8);
                $dateId = $dateIds[$date];

                if (isset($outputData[$path])) {
                    $outputData[$path][$dateId]++;
                } else {
                    $pathCounts = $initialCounts;
                    $pathCounts[$dateId] = 1;
                    $outputData[$path] = $pathCounts;
                    $pathCount++;
                }
                $position = $lineEnd + 1;
            }

            $carry = substr($buffer, $position);
        }

        if ($carry !== '') {
            $lineLength = strlen($carry);
            $path = substr($carry, 25, $lineLength - 51);
            $date = substr($carry, $lineLength - 23, 8);
            $dateId = $dateIds[$date] ?? null;
            if ($dateId !== null) {
                if (isset($outputData[$path])) {
                    $outputData[$path][$dateId]++;
                } else {
                    $pathCounts = $initialCounts;
                    $pathCounts[$dateId] = 1;
                    $outputData[$path] = $pathCounts;
                    $pathCount++;
                }
            }
        }

        $outputJson = "{" . PHP_EOL;

        $totalPathsCount = $pathCount;
        $pathIndex = 0;
        foreach ($outputData as $path => $pathCounts) {
            $outputJson .= "    \"\/blog\/$path\": {" . PHP_EOL;

            $firstDate = true;
            for ($dateId = 0; $dateId < $dateCount; $dateId++) {
                $count = $pathCounts[$dateId];
                if ($count === 0) {
                    continue;
                }

                if (!$firstDate) {
                    $outputJson .= "," . PHP_EOL;
                }
                $date = $dates[$dateId];
                $outputJson .= "        \"$date\": $count";
                $firstDate = false;
            }

            $outputJson .= PHP_EOL;
            $outputJson .= "    }";
            if ($pathIndex < $totalPathsCount - 1) {
                $outputJson .= ",";
            }
            $outputJson .= PHP_EOL;

            $pathIndex++;
        }

        $outputJson .= "}";

        file_put_contents($outputPath, $outputJson);
    }
}
