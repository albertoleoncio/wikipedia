<?php

define('ROOT_DIR', realpath(__DIR__ . '/..'));

require_once ROOT_DIR . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ExcelReader;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;

@mkdir(ROOT_DIR . '/var/input');
@mkdir(ROOT_DIR . '/var/tmp');

$archiveFile = ROOT_DIR . sprintf("/var/input/%s-brasil-covid-data.csv", date('Y-m-d'));
$tmpFile = tempnam(ROOT_DIR . '/var/tmp', 'hm-covid-data');

if (file_exists($archiveFile) && date('Y-m-d H', filemtime($archiveFile)) == date('Y-m-d H')) {
    echo "File was recently updated. No need to download it again.\n";
    return 0;
}

echo "Downloading current data\n";
$headers = array(
    'X-Parse-Application-Id: unAFkcaNDeXajurGB7LChj8SgQYS2ptm',
    'TE: Trailers',
);

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => 'https://xx9p7hp1p7.execute-api.us-east-1.amazonaws.com/prod/PortalGeral',
    CURLOPT_HTTPHEADER => $headers
]);
$result = curl_exec($curl);

$contents = json_decode($result, true);
$data = file_get_contents($contents['results']['0']['arquivo']['url']);

echo "Saving data to temporary file\n";
file_put_contents($tmpFile, $data);

echo "Reading contents from Excel file\n";
$reader = new ExcelReader();
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($tmpFile);

echo "Saving data to archive file\n";
$writer = new CsvWriter($spreadsheet);
$writer->setDelimiter(';');
$writer->setEnclosure('');
$writer->save($archiveFile);

echo "Remove temporary file\n";
unlink($tmpFile);

echo "Download complete!\n";

return 0;
