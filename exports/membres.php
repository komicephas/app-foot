<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = getPdo();
$rows = $pdo->query('SELECT numero_membre, nom, prenom, categorie, type, date_inscription, telephone, statut FROM membres ORDER BY nom, prenom')->fetchAll();

$sheet = (new Spreadsheet())->getActiveSheet();
$sheet->setCellValue('A1', clubSettings()['nom_club']);
$sheet->fromArray(['N° membre', 'Nom', 'Prenom', 'Categorie', 'Type', 'Date inscription', 'Telephone', 'Statut'], null, 'A3');

$rowIndex = 4;
foreach ($rows as $row) {
    $sheet->fromArray([
        $row['numero_membre'],
        $row['nom'],
        $row['prenom'],
        $row['categorie'],
        $row['type'],
        formatDateFr($row['date_inscription']),
        $row['telephone'],
        $row['statut'],
    ], null, 'A' . $rowIndex++);
}

$sheet->getStyle('A3:H3')->getFont()->setBold(true);
$sheet->getStyle('A3:H3')->getFill()->setFillType('solid')->getStartColor()->setARGB('D9E8FF');
foreach (range('A', 'H') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

exportHeader('membres.xlsx');
(new Xlsx($sheet->getParent()))->save('php://output');
