<?php
$path = base_path('CONTROL_DE_VACACIONES_2026.xls');

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
$reader->setReadDataOnly(true);
$reader->setLoadSheetsOnly(['2026']);
$spreadsheet = $reader->load($path);
$ws = $spreadsheet->getSheetByName('2026');

for ($row = 5; $row <= 12; $row++) {
    $nombre = trim((string) $ws->getCellByColumnAndRow(2, $row)->getCalculatedValue());
    $cell218 = $ws->getCellByColumnAndRow(218, $row);
    $rawValue = $cell218->getValue();
    $calcValue = $cell218->getCalculatedValue();
    echo "Fila {$row} | {$nombre} | getValue=" . var_export($rawValue, true) . " | getCalculatedValue=" . var_export($calcValue, true) . PHP_EOL;
}
