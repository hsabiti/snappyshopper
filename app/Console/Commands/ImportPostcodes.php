<?php

namespace App\Console\Commands;

use App\Models\Postcode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportPostcodes extends Command
{
    protected $signature = 'import:postcodes {--source= : Path to CSV file} {--batch=1000}';
    protected $description = 'Import UK postcode data with lat/lng from CSV';

    public function handle(): int
    {
        $source = $this->option('source') ?: base_path('database/data/sample_postcodes.csv');
        $batchSize = (int) $this->option('batch');

        if (!file_exists($source)) {
            $this->error("CSV not found: {$source}");
            return self::FAILURE;
        }

        $fh = fopen($source, 'r');
        if ($fh === false) {
            $this->error("Unable to open CSV: {$source}");
            return self::FAILURE;
        }

        $header = fgetcsv($fh);
        if (!$header) {
            $this->error("Empty CSV: {$source}");
            return self::FAILURE;
        }

        $map = array_flip(array_map('trim', $header));
        foreach (['postcode','lat','lng'] as $col) {
            if (!isset($map[$col])) {
                $this->error("CSV missing required column: {$col}");
                return self::FAILURE;
            }
        }

        $buffer = [];
        $processed = $skipped = 0;

        while (($row = fgetcsv($fh)) !== false) {
            $processed++;

            $postcode = Postcode::normalize((string)($row[$map['postcode']] ?? ''));
            $lat = $row[$map['lat']] ?? null;
            $lng = $row[$map['lng']] ?? null;

            if ($postcode === '' || !is_numeric($lat) || !is_numeric($lng)) {
                $skipped++;
                continue;
            }

            $buffer[] = [
                'postcode' => $postcode,
                'lat' => (float)$lat,
                'lng' => (float)$lng,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($buffer) >= $batchSize) {
                $this->flush($buffer);
                $buffer = [];
            }
        }

        if ($buffer) {
            $this->flush($buffer);
        }

        fclose($fh);

        $this->info("Imported postcodes. Processed={$processed}, skipped={$skipped}");
        return self::SUCCESS;
    }

    private function flush(array $rows): void
    {
        DB::table('postcodes')->upsert(
            $rows,
            ['postcode'],
            ['lat','lng','updated_at']
        );
    }
}
