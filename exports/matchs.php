<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = getPdo();
$rows = $pdo->query("
    SELECT ma.date_match, ma.adversaire, ma.lieu,
           COALESCE(CONCAT(m.nom, ' ', m.prenom), pm.nom_visiteur) AS participant,
           pm.type_participant, pm.montant
    FROM participations_match pm
    INNER JOIN matchs ma ON ma.id = pm.match_id
    LEFT JOIN membres m ON m.id = pm.membre_id
    ORDER BY ma.date_match DESC, pm.id DESC
")->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', clubSettings()['nom_club']);
$sheet->fromArray(['Date match', 'Adversaire', 'Lieu', 'Participant', 'Type', 'Montant payé'], null, 'A3');

$rowIndex = 4;
foreach ($rows as $row) {
    $sheet->fromArray([
        formatDateFr($row['date_match']),
        $row['adversaire'],
        $row['lieu'],
        $row['participant'],
        $row['type_participant'],
        (float) $row['montant'],
    ], null, 'A' . $rowIndex++);
}
$sheet->getStyle('A3:F3')->getFont()->setBold(true);
$sheet->getStyle('A3:F3')->getFill()->setFillType('solid')->getStartColor()->setARGB('D9E8FF');
foreach (range('A', 'F') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

exportHeader('matchs.xlsx');
(new Xlsx($spreadsheet))->save('php://output');
