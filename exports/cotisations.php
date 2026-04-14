<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = getPdo();
$rows = $pdo->query("
    SELECT c.mois, c.annee, m.nom, m.prenom, m.type, c.montant, c.date_paiement, c.statut
    FROM cotisations c
    INNER JOIN membres m ON m.id = c.membre_id
    ORDER BY c.annee DESC, c.mois DESC, m.nom, m.prenom
")->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', clubSettings()['nom_club']);
$sheet->fromArray(['Mois', 'Annee', 'Nom', 'Prenom', 'Type', 'Montant', 'Date paiement', 'Statut'], null, 'A3');

$rowIndex = 4;
foreach ($rows as $row) {
    $sheet->fromArray([
        monthLabel((int) $row['mois']),
        $row['annee'],
        $row['nom'],
        $row['prenom'],
        $row['type'],
        (float) $row['montant'],
        formatDateFr($row['date_paiement']),
        $row['statut'],
    ], null, 'A' . $rowIndex++);
}
$sheet->getStyle('A3:H3')->getFont()->setBold(true);
$sheet->getStyle('A3:H3')->getFill()->setFillType('solid')->getStartColor()->setARGB('D9E8FF');
foreach (range('A', 'H') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

exportHeader('cotisations.xlsx');
(new Xlsx($spreadsheet))->save('php://output');
