<?php

namespace App;

final class Parser
{
    /**
     * @throws \DateMalformedStringException
     */
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        // pre-compute dates
        $dateIds = [];
        $dates = [];
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
                    $key = $ymStr . (($d < 10 ? '0' : '') . $d);
                    $dateIds[$key] = $dateCount;
                    $dates[$dateCount] = $key;
                    $dateCount++;
                }
            }
        }

        $outputData = [];

        $input = fopen($inputPath, 'r');
        stream_set_read_buffer($input, 4_194_304);

        while ($line = fgets($input)) {
            $commaPos = strpos($line, ',');

            // we know url path is 19 chars from left, because host and protocol stay the same
            $path = substr($line, 19, $commaPos - 19);
            // we know the date is the 10 chars after the comma
            $date = substr($line, $commaPos + 3, 8);

            $outputData[$path] ??= [];

            // use dateIds for insertion because those are correctly ordered
            $dateId = $dateIds[$date];

            $outputData[$path][$dateId] ??= 0;
            $outputData[$path][$dateId]++;
        }

        // write JSON in memory
        $output = fopen('php://memory', 'w');

        fwrite($output, "{" . PHP_EOL);

        $totalPathsCount = count($outputData);
        $pathIndex = 0;
        foreach ($outputData as $path => $pathCounts) {
            $escapedPath = str_replace('/', '\/', $path);
            fwrite($output, "    \"$escapedPath\": {" . PHP_EOL);

            $totalDatesCount = count($pathCounts);
            $dateIndex = 0;

            foreach ($dateIds as $dateId) {
                $count = $pathCounts[$dateId] ?? null;
                if ($count === null) {
                    continue;
                }

                // reconstruct date from id
                $date = "20" . $dates[$dateId];

                fwrite($output, "        \"$date\": $count");
                if ($dateIndex < $totalDatesCount - 1) {
                    fwrite($output, ",");
                }
                fwrite($output, PHP_EOL);

                $dateIndex++;
            }

            fwrite($output, "    }");
            if ($pathIndex < $totalPathsCount - 1) {
                fwrite($output, ",");
            }
            fwrite($output, PHP_EOL);

            $pathIndex++;
        }

        fwrite($output, "}");

        rewind($output);

        stream_copy_to_stream($output, fopen($outputPath, 'w'));
    }
}
