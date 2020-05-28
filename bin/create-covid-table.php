<?php

define('ROOT_DIR', realpath(__DIR__ . '/..'));

require_once ROOT_DIR . '/vendor/autoload.php';

use App\Domain\ReportedCases;
use App\Infrastructure\CovidCsvReader;
use App\Infrastructure\MediaWiki\PortugueseTable;
use Symfony\Component\Finder\Finder;

echo "looking for last updated file\n";
$files = Finder::create()
    ->files()
    ->in(ROOT_DIR . '/var/input/')
    ->name('*-brasil-covid-data.csv')
    ->sortByName()
    ->getIterator();

if (!count($files)) {
    echo "No files found\n";
    return 1;
}

$filename = end($files)->getPathname();
$separator = ';';

echo "extracting local data from CSV files\n";
$reader = new CovidCsvReader();

$reportedCases = $reader->read($filename, $separator);

$outputDir = ROOT_DIR . "/var/output/wikipedia-pt";
@mkdir($outputDir);

echo "parsing table\n";
$tableParser = new PortugueseTable();
$contents = $tableParser->parse($reportedCases);

$outputFile = $outputDir . '/table.txt';
file_put_contents($outputFile, $contents);

echo "table parsed!\n";
echo "check {$outputFile}\n";

return 0;
