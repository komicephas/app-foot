<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = getPdo();
$rows = $pdo->query('SELECT * FROM finances ORDER BY date_operation ASC, id ASC')->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', clubSettings()['nom_club']);
$sheet->fromArray(['Date', 'Type', 'Categorie', 'Description', 'Montant', 'Solde cumulé'], null, 'A3');

$rowIndex = 4;
$running = 0;
foreach ($rows as $row) {
    $running += $row['type'] === 'entrée' ? (float) $row['montant'] : -(float) $row['montant'];
    $sheet->fromArray([
        formatDateFr($row['date_operation']),
        $row['type'],
        $row['categorie'],
        $row['description'],
        (float) $row['montant'],
        $running,
    ], null, 'A' . $rowIndex++);
}
$sheet->getStyle('A3:F3')->getFont()->setBold(true);
$sheet->getStyle('A3:F3')->getFill()->setFillType('solid')->getStartColor()->setARGB('D9E8FF');
foreach (range('A', 'F') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

exportHeader('finances.xlsx');
(new Xlsx($spreadsheet))->save('php://output');
