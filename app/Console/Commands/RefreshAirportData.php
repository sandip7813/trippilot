<?php

namespace App\Console\Commands;

use App\Services\Airports\AirportDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the public-domain OurAirports dataset and keeps only airports a
 * traveler can actually fly into: scheduled passenger service, an IATA code,
 * and a real airport type (no heliports, seaplane bases or closed fields).
 */
class RefreshAirportData extends Command
{
    private const array AIRPORT_TYPES = ['large_airport', 'medium_airport', 'small_airport'];

    protected $signature = 'trippilot:refresh-airports';

    protected $description = 'Download the OurAirports dataset and rebuild the bundled airports file used for nearest-airport lookups';

    public function handle(): int
    {
        $response = Http::connectTimeout(10)
            ->timeout(120)
            ->retry([500, 2000], throw: false)
            ->get((string) config('integrations.airports.source_url'));

        if ($response->failed()) {
            $this->components->error("Could not download the airports dataset (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $source = fopen('php://temp', 'r+');

        if ($source === false) {
            $this->components->error('Could not buffer the airports dataset.');

            return self::FAILURE;
        }

        fwrite($source, $response->body());
        rewind($source);

        $header = fgetcsv($source, escape: '');

        if ($header === false || array_diff(['type', 'iata_code', 'scheduled_service', 'latitude_deg', 'longitude_deg'], $header) !== []) {
            fclose($source);
            $this->components->error('The airports dataset has an unexpected format.');

            return self::FAILURE;
        }

        $rows = [];

        while (($row = fgetcsv($source, escape: '')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $record = array_combine($header, $row);

            if ($record['scheduled_service'] !== 'yes'
                || trim($record['iata_code']) === ''
                || ! in_array($record['type'], self::AIRPORT_TYPES, true)) {
                continue;
            }

            $rows[] = [
                strtoupper(trim($record['iata_code'])),
                trim($record['name']),
                trim($record['municipality']),
                strtoupper(trim($record['iso_country'])),
                $record['type'],
                round((float) $record['latitude_deg'], 5),
                round((float) $record['longitude_deg'], 5),
            ];
        }

        fclose($source);

        if ($rows === []) {
            $this->components->error('No airports with scheduled service were found in the dataset.');

            return self::FAILURE;
        }

        usort($rows, fn (array $left, array $right): int => $left[0] <=> $right[0]);

        $path = (string) config('integrations.airports.data_path');
        File::ensureDirectoryExists(dirname($path));

        $output = fopen($path, 'w');

        if ($output === false) {
            $this->components->error("Could not write the airports file at {$path}.");

            return self::FAILURE;
        }

        fputcsv($output, AirportDirectory::CSV_HEADER, escape: '');

        foreach ($rows as $row) {
            fputcsv($output, $row, escape: '');
        }

        fclose($output);

        $this->components->info('Saved '.count($rows).' airports to '.$path.'.');

        return self::SUCCESS;
    }
}
