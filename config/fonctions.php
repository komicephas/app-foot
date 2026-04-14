<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('gestion_foot_session');
    session_start();
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clubSettings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    try {
        $stmt = getPdo()->query('SELECT * FROM parametres ORDER BY id ASC LIMIT 1');
        $settings = $stmt->fetch() ?: [
            'nom_club' => 'Mon Club',
            'logo' => null,
            'devise' => 'FCFA',
        ];
    } catch (Throwable $exception) {
        $settings = [
            'nom_club' => 'Mon Club',
            'logo' => null,
            'devise' => 'FCFA',
        ];
    }

    return $settings;
}

function formatAmount(float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

function formatDateFr(?string $date): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    return date('d/m/Y', strtotime($date));
}

function formatDateTimeFr(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    return date('d/m/Y H:i', strtotime($date));
}

function monthLabel(int $month): string
{
    $months = [
        1 => 'Janvier',
        2 => 'Fevrier',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Aout',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Decembre',
    ];

    return $months[$month] ?? (string) $month;
}

function normalizeText(?string $value): string
{
    return trim((string) $value);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Jeton CSRF invalide.');
    }
}

function memberTariffs(string $categorie, string $type): array
{
    $matrix = [
        'membre' => [
            'adulte' => ['inscription' => 2000, 'cotisation' => 500, 'match' => 200],
            'jeune' => ['inscription' => 1000, 'cotisation' => 200, 'match' => 100],
        ],
        'sympathisant' => [
            'adulte' => ['inscription' => 0, 'cotisation' => 0, 'match' => 200],
            'jeune' => ['inscription' => 0, 'cotisation' => 0, 'match' => 100],
        ],
    ];

    return $matrix[$categorie][$type] ?? ['inscription' => 0, 'cotisation' => 0, 'match' => 0];
}

function generateMemberNumber(PDO $pdo, int $year): string
{
    $prefix = 'MBR-' . $year . '-';
    $stmt = $pdo->prepare('SELECT numero_membre FROM membres WHERE annee_inscription = ? AND numero_membre IS NOT NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute([$year]);
    $last = $stmt->fetchColumn();
    $next = 1;

    if ($last && preg_match('/(\d{3})$/', (string) $last, $matches)) {
        $next = ((int) $matches[1]) + 1;
    }

    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function financeEntry(PDO $pdo, string $type, string $categorie, string $description, float $montant, string $dateOperation, ?int $referenceId = null, ?string $referenceType = null): void
{
    $stmt = $pdo->prepare('INSERT INTO finances (type, categorie, description, montant, date_operation, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$type, $categorie, $description, $montant, $dateOperation, $referenceId, $referenceType]);
}

function currentBalance(PDO $pdo): float
{
    $sql = "SELECT
        COALESCE(SUM(CASE WHEN type = 'entrée' THEN montant ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN type = 'sortie' THEN montant ELSE 0 END), 0) AS solde
        FROM finances";
    return (float) $pdo->query($sql)->fetchColumn();
}

function paginate(int $perPage = 10): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset];
}

function paginationLinks(int $total, int $page, int $perPage): string
{
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }

    $query = $_GET;
    $html = '<nav><ul class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $query['page'] = $i;
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . h(http_build_query($query)) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';

    return $html;
}

function uploadImage(array $file, string $targetDir, array $allowed = ['image/jpeg', 'image/png', 'image/webp']): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Echec de televersement du fichier.');
    }

    if (!in_array((string) ($file['type'] ?? ''), $allowed, true)) {
        throw new RuntimeException('Format de fichier non autorise.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('img_', true) . '.' . $extension;
    $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
        throw new RuntimeException('Impossible de deplacer le fichier.');
    }

    return $filename;
}

function renderHeader(string $title, string $active = ''): void
{
    $settings = clubSettings();
    $flash = getFlash();
    $isLogged = !empty($_SESSION['admin_id']);
    $currentPath = $_SERVER['PHP_SELF'] ?? '';
    ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | <?= h($settings['nom_club']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/gestion_foot/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if ($isLogged): ?>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark">FC</div>
            <div>
                <div class="small text-uppercase text-white-50">Gestion</div>
                <strong><?= h($settings['nom_club']) ?></strong>
            </div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?= str_contains($currentPath, '/dashboard.php') ? 'active' : '' ?>" href="/gestion_foot/dashboard.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
            <a class="nav-link <?= str_contains($currentPath, '/membres/') ? 'active' : '' ?>" href="/gestion_foot/membres/index.php"><i class="bi bi-people"></i> Membres</a>
            <a class="nav-link <?= str_contains($currentPath, '/cotisations/') ? 'active' : '' ?>" href="/gestion_foot/cotisations/index.php"><i class="bi bi-cash-coin"></i> Cotisations</a>
            <a class="nav-link <?= str_contains($currentPath, '/matchs/') ? 'active' : '' ?>" href="/gestion_foot/matchs/index.php"><i class="bi bi-trophy"></i> Matchs</a>
            <a class="nav-link <?= str_contains($currentPath, '/finances/') ? 'active' : '' ?>" href="/gestion_foot/finances/index.php"><i class="bi bi-wallet2"></i> Finances</a>
            <a class="nav-link <?= str_contains($currentPath, '/exports/') ? 'active' : '' ?>" href="/gestion_foot/exports/membres.php"><i class="bi bi-file-earmark-spreadsheet"></i> Exports Excel</a>
            <a class="nav-link <?= str_contains($currentPath, '/parametres/') ? 'active' : '' ?>" href="/gestion_foot/parametres/index.php"><i class="bi bi-gear"></i> Parametres</a>
        </nav>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div>
                <h1 class="page-title mb-0"><?= h($title) ?></h1>
                <div class="text-muted small"><?= h($settings['nom_club']) ?></div>
            </div>
            <a href="/gestion_foot/logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Deconnexion</a>
        </header>
        <div class="content-wrapper">
            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>
<?php else: ?>
<main class="login-shell">
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>
<?php
}

function renderFooter(array $extraScripts = []): void
{
    $isLogged = !empty($_SESSION['admin_id']);
    ?>
<?php if ($isLogged): ?>
        </div>
    </main>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/gestion_foot/assets/js/main.js"></script>
<?php foreach ($extraScripts as $script): ?>
<?= $script . PHP_EOL ?>
<?php endforeach; ?>
</body>
</html>
<?php
}

function exportHeader(string $filename): void
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
}
