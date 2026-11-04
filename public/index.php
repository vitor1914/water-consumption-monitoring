<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../src/Database.php';

$db = new Database(__DIR__ . '/../data/monitoramento.sqlite');
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirectWith(string $message, string $type = 'success'): never {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { redirectWith('Token de segurança inválido. Atualize a página e tente novamente.', 'error'); }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $date = trim((string)($_POST['reading_date'] ?? ''));
        $sector = trim((string)($_POST['sector'] ?? ''));
        $produced = filter_var($_POST['water_produced'] ?? null, FILTER_VALIDATE_FLOAT);
        $billed = filter_var($_POST['water_billed'] ?? null, FILTER_VALIDATE_FLOAT);
        $energy = filter_var($_POST['energy_kwh'] ?? null, FILTER_VALIDATE_FLOAT);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$validDate || $validDate->format('Y-m-d') !== $date || $sector === '' || strlen($sector) > 100 || $produced === false || $billed === false || $energy === false || $produced < 0 || $billed < 0 || $energy < 0 || $billed > $produced || strlen($notes) > 500) {
            redirectWith('Dados inválidos. Confira a data, o setor, os valores e se o volume faturado não supera o produzido.', 'error');
        }
        try {
            $db->create(['reading_date' => $date, 'sector' => $sector, 'water_produced' => $produced, 'water_billed' => $billed, 'energy_kwh' => $energy, 'notes' => $notes]);
            redirectWith('Medição registrada com sucesso.');
        } catch (Throwable $ex) { redirectWith('Não foi possível salvar a medição.', 'error'); }
    }
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) redirectWith('Identificador inválido.', 'error');
        redirectWith($db->delete($id) ? 'Medição excluída.' : 'Medição não encontrada.');
    }
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$sectorFilter = trim((string)($_GET['sector'] ?? ''));
$readings = $db->all($from, $to, $sectorFilter);
$totalProduced = array_sum(array_column($readings, 'water_produced'));
$totalBilled = array_sum(array_column($readings, 'water_billed'));
$totalEnergy = array_sum(array_column($readings, 'energy_kwh'));
$totalLoss = max(0, $totalProduced - $totalBilled);
$lossRate = $totalProduced > 0 ? ($totalLoss / $totalProduced) * 100 : 0;
$energyIntensity = $totalProduced > 0 ? $totalEnergy / $totalProduced : 0;
$sectors = $db->sectors();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$daily = [];
foreach (array_reverse($readings) as $row) {
    $day = $row['reading_date'];
    if (!isset($daily[$day])) $daily[$day] = ['produced' => 0, 'billed' => 0];
    $daily[$day]['produced'] += (float)$row['water_produced'];
    $daily[$day]['billed'] += (float)$row['water_billed'];
}
$chartLabels = array_keys($daily);
$chartProduced = array_map(static fn($v) => round($v['produced'], 2), array_values($daily));
$chartBilled = array_map(static fn($v) => round($v['billed'], 2), array_values($daily));
$lossBySector = [];
foreach ($readings as $row) {
    $name = $row['sector'];
    if (!isset($lossBySector[$name])) $lossBySector[$name] = ['produced' => 0, 'billed' => 0];
    $lossBySector[$name]['produced'] += (float)$row['water_produced'];
    $lossBySector[$name]['billed'] += (float)$row['water_billed'];
}
$sectorLabels = array_keys($lossBySector);
$sectorLosses = array_map(static fn($v) => round($v['produced'] - $v['billed'], 2), array_values($lossBySector));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="relatorio-consumo-agua.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Data', 'Setor', 'Produzido (m³)', 'Faturado (m³)', 'Perdas estimadas (m³)', 'Energia (kWh)', 'Observações'], ';');
    foreach ($readings as $r) fputcsv($out, [$r['reading_date'], $r['sector'], $r['water_produced'], $r['water_billed'], $r['water_produced'] - $r['water_billed'], $r['energy_kwh'], $r['notes']], ';');
    fclose($out); exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Painel demonstrativo de consumo, perdas estimadas de água e eficiência energética.">
  <title>HidroDados | Monitoramento de Água</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<header class="topbar"><div class="brand"><span class="brand-mark">H₂O</span><div><strong>HidroDados</strong><small>Monitoramento operacional</small></div></div><div class="topbar-right"><span class="status-dot"></span> Ambiente demonstrativo</div></header>
<main class="container">
  <section class="hero"><div><p class="eyebrow">PAINEL DE INTELIGÊNCIA OPERACIONAL</p><h1>Consumo e perdas de água</h1><p class="hero-copy">Acompanhe volumes produzidos e faturados, perdas estimadas e intensidade energética em um único lugar.</p></div><a class="button button-light" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">↓ Exportar CSV</a></section>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
  <section class="filters panel"><form method="get" class="filter-form"><label>Data inicial<input type="date" name="from" value="<?= e($from) ?>"></label><label>Data final<input type="date" name="to" value="<?= e($to) ?>"></label><label>Setor<select name="sector"><option value="">Todos os setores</option><?php foreach ($sectors as $sector): ?><option value="<?= e($sector) ?>" <?= $sectorFilter === $sector ? 'selected' : '' ?>><?= e($sector) ?></option><?php endforeach; ?></select></label><button class="button button-primary" type="submit">Aplicar filtros</button><a class="button button-ghost" href="index.php">Limpar</a></form></section>
  <section class="metrics">
    <article class="metric-card"><div class="metric-label">Volume produzido</div><div class="metric-value"><?= number_format($totalProduced, 0, ',', '.') ?><span> m³</span></div><div class="metric-foot">Total no período filtrado</div><span class="metric-icon blue">↗</span></article>
    <article class="metric-card"><div class="metric-label">Volume faturado</div><div class="metric-value"><?= number_format($totalBilled, 0, ',', '.') ?><span> m³</span></div><div class="metric-foot">Volume contabilizado</div><span class="metric-icon green">✓</span></article>
    <article class="metric-card"><div class="metric-label">Perdas estimadas</div><div class="metric-value"><?= number_format($totalLoss, 0, ',', '.') ?><span> m³</span></div><div class="metric-foot"><?= number_format($lossRate, 1, ',', '.') ?>% do volume produzido</div><span class="metric-icon orange">!</span></article>
    <article class="metric-card"><div class="metric-label">Energia consumida</div><div class="metric-value"><?= number_format($totalEnergy, 0, ',', '.') ?><span> kWh</span></div><div class="metric-foot"><?= number_format($energyIntensity, 4, ',', '.') ?> kWh por m³ produzido</div><span class="metric-icon purple">ϟ</span></article>
  </section>
  <section class="charts-grid"><article class="panel chart-panel"><div class="panel-heading"><div><h2>Produção x faturamento</h2><p>Volume diário agregado (m³)</p></div><span class="chart-tag">Tendência</span></div><div class="chart-wrap"><canvas id="dailyChart" aria-label="Gráfico de produção e faturamento por dia"></canvas></div></article><article class="panel chart-panel"><div class="panel-heading"><div><h2>Perdas por setor</h2><p>Diferença entre produzido e faturado</p></div><span class="chart-tag">Comparativo</span></div><div class="chart-wrap"><canvas id="sectorChart" aria-label="Gráfico de perdas por setor"></canvas></div></article></section>
  <section class="content-grid"><article class="panel table-panel"><div class="panel-heading"><div><h2>Registros de medição</h2><p><?= count($readings) ?> registro(s) encontrado(s)</p></div></div><div class="table-scroll"><table><thead><tr><th>Data</th><th>Setor</th><th>Produzido</th><th>Faturado</th><th>Perdas</th><th>Energia</th><th></th></tr></thead><tbody>
  <?php if (!$readings): ?><tr><td colspan="7" class="empty">Nenhuma medição encontrada para os filtros selecionados.</td></tr><?php endif; ?>
  <?php foreach ($readings as $r): $loss = (float)$r['water_produced'] - (float)$r['water_billed']; ?><tr><td><?= e(date('d/m/Y', strtotime($r['reading_date']))) ?></td><td><span class="sector-pill"><?= e($r['sector']) ?></span></td><td><?= number_format((float)$r['water_produced'], 0, ',', '.') ?> m³</td><td><?= number_format((float)$r['water_billed'], 0, ',', '.') ?> m³</td><td><span class="loss-value"><?= number_format($loss, 0, ',', '.') ?> m³</span></td><td><?= number_format((float)$r['energy_kwh'], 0, ',', '.') ?> kWh</td><td><form method="post" onsubmit="return confirm('Excluir esta medição?')"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-button" type="submit" aria-label="Excluir registro">×</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div></article>
  <aside class="panel form-panel"><div class="panel-heading"><div><h2>Nova medição</h2><p>Registre dados operacionais</p></div><span class="plus-icon">+</span></div><form method="post" class="reading-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="create"><label>Data da medição<input type="date" name="reading_date" value="<?= e(date('Y-m-d')) ?>" required></label><label>Setor<input type="text" name="sector" maxlength="100" placeholder="Ex.: Setor Norte" list="sector-list" required><datalist id="sector-list"><?php foreach ($sectors as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></label><div class="form-row"><label>Produzido (m³)<input type="number" name="water_produced" min="0" step="0.01" placeholder="12000" required></label><label>Faturado (m³)<input type="number" name="water_billed" min="0" step="0.01" placeholder="10100" required></label></div><label>Energia consumida (kWh)<input type="number" name="energy_kwh" min="0" step="0.01" placeholder="850" required></label><label>Observações <span class="optional">opcional</span><textarea name="notes" rows="3" maxlength="500" placeholder="Notas sobre a medição..."></textarea></label><button class="button button-primary button-full" type="submit">+ Registrar medição</button></form></aside></section>
  <footer><span>HidroDados · Projeto demonstrativo de análise operacional</span><span>PHP · SQLite · Chart.js</span></footer>
</main>
<script>
const labels = <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const produced = <?= json_encode($chartProduced) ?>;
const billed = <?= json_encode($chartBilled) ?>;
const sectorLabels = <?= json_encode($sectorLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const sectorLosses = <?= json_encode($sectorLosses) ?>;
Chart.defaults.font.family = "Inter, system-ui, sans-serif";
Chart.defaults.color = '#718096';
new Chart(document.getElementById('dailyChart'), {type:'line',data:{labels,datasets:[{label:'Produzido',data:produced,borderColor:'#2476d2',backgroundColor:'rgba(36,118,210,.10)',fill:true,tension:.35,pointRadius:3},{label:'Faturado',data:billed,borderColor:'#18a889',backgroundColor:'rgba(24,168,137,.06)',fill:true,tension:.35,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:7,padding:18}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:'#edf1f6'},ticks:{callback:v=>v.toLocaleString('pt-BR')}}}}});
new Chart(document.getElementById('sectorChart'), {type:'bar',data:{labels:sectorLabels,datasets:[{label:'Perdas estimadas (m³)',data:sectorLosses,backgroundColor:['#f2a34a','#e77c65','#8b82d9','#35a8c8','#6bbf9a'],borderRadius:6,maxBarThickness:44}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:'#edf1f6'},ticks:{callback:v=>v.toLocaleString('pt-BR')}}}}});
</script>
</body></html>
