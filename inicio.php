<?php
header('Content-type: text/html; charset=utf-8');
session_start();
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');
include_once 'conexaoSQL.php';

$login = $_SESSION['login'];
$senha = $_SESSION['password'];
session_write_close();
$stmt = sqlsrv_query($conn, "SELECT TB01066_USUARIO Usuario FROM TB01066 WHERE TB01066_USUARIO=? AND TB01066_SENHA=?", [$login, $senha]);
if ($stmt === false) die('<pre>'.htmlspecialchars(exibirErroSql('Validação do usuário'), ENT_QUOTES, 'UTF-8').'</pre>');
$usuario = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$usuario) {
  echo "<script>alert('É necessário fazer login!');location.href='./Login.php'</script>";
  exit;
}

$stmtTecnicos = sqlsrv_query($conn, "SELECT TB01024_CODIGO, TB01024_NOME FROM TB01024 WHERE TB01024_SITUACAO='A' ORDER BY TB01024_NOME");
if ($stmtTecnicos === false) die('<pre>'.htmlspecialchars(exibirErroSql('Consulta de técnicos'), ENT_QUOTES, 'UTF-8').'</pre>');
$optionsTecnicos = '';
while ($rowT = sqlsrv_fetch_array($stmtTecnicos, SQLSRV_FETCH_ASSOC)) {
  $cod = htmlspecialchars($rowT['TB01024_CODIGO'], ENT_QUOTES, 'UTF-8');
  $nome = htmlspecialchars($rowT['TB01024_NOME'], ENT_QUOTES, 'UTF-8');
  $optionsTecnicos .= "<option value=\"$cod\">$cod - $nome</option>";
}
include_once 'models/resumo_os.php';

function h($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); }
function dataBr($valor) { return $valor instanceof DateTime ? $valor->format('d/m/Y') : ''; }
function contador($valor) { return $valor === null || $valor === '' ? '0' : h($valor); }
function badgesContadores($row, $prefixo, $somenteDois = false) {
  $itens = ['A4' => 'A4', 'A4C' => 'A4C'];
  if (!$somenteDois) $itens += ['DG' => 'DG', 'A3' => 'A3', 'A3C' => 'A3C'];
  $html = '<div class="counter-grid">';
  foreach ($itens as $campo => $rotulo) $html .= '<span><small>'.$rotulo.'</small>'.contador($row[$prefixo.$campo] ?? null).'</span>';
  return $html.'</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ABERTURA OS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="shortcut icon" href="MEDIA/logo.png" type="image/x-icon">
  <link rel="stylesheet" href="css/style.css">
</head>
<body data-tipo-os="<?= h($tipoOS) ?>">
  <div id="loadingOverlay" class="loading-overlay" role="status" aria-live="polite" aria-hidden="true">
    <div class="loading-box"><div class="spinner-border mb-3"></div><div id="loadingMessage" class="fw-bold">Carregando...</div><div class="small text-muted mt-1">Aguarde um momento</div></div>
  </div>

  <header class="header-bg text-center">
    <form action="logout.php" method="POST" class="logout-form" data-no-loading>
      <button type="submit" class="btn logout-button" aria-label="Sair do sistema">Sair</button>
    </form>
    <?php if ($tipoOS === 'afericao' && $consultaSolicitada): ?>
      <div class="container-fluid px-3 text-start">
        <div class="d-inline-flex flex-wrap gap-2 my-1">
          <span class="badge rounded-pill bg-light text-primary"><strong>Equipamentos:</strong> <?= $totais['equipamentos'] ?></span>
          <span class="badge rounded-pill bg-light text-primary"><strong>Aferidos:</strong> <?= $totais['aferidos'] ?></span>
          <span class="badge rounded-pill bg-light text-primary"><strong>Não aferidos:</strong> <?= $totais['nao_aferidos'] ?></span>
          <span class="badge rounded-pill bg-light text-primary"><strong>OS abertas:</strong> <?= $totais['os_abertas'] ?></span>
        </div>
      </div>
    <?php endif; ?>
    <div class="container">
      <img src="MEDIA/logo.png" class="logo-left" alt="Logo">
      <h4 class="mb-3">ABERTURA OS</h4>
      <form method="POST" action="inicio.php" class="row g-2 tipo-os-nav mx-auto" style="max-width:1050px">
        <div class="col-md-4"><button name="tipo" value="afericao" class="btn w-100 <?= $tipoOS === 'afericao' ? 'active' : '' ?>">OS DE AFERIÇÃO</button></div>
        <div class="col-md-4"><button name="tipo" value="preventiva" class="btn w-100 <?= $tipoOS === 'preventiva' ? 'active' : '' ?>">OS PREVENTIVA</button></div>
        <div class="col-md-4"><button name="tipo" value="revisao" class="btn w-100 <?= $tipoOS === 'revisao' ? 'active' : '' ?>">REVISÃO INTERNA</button></div>
      </form>
    </div>
  </header>

  <main class="workspace">
    <aside id="filterSidebar" class="filter-sidebar">
      <button type="button" class="filter-toggle" onclick="alternarFiltros()" aria-label="Recolher filtros">‹</button>
      <form action="inicio.php" method="POST" id="filterForm">
        <input type="hidden" name="tipo" value="<?= h($tipoOS) ?>">
        <h5 class="filter-title">Filtros</h5>
        <p class="filter-hint">
          <?php if ($tipoOS === 'preventiva'): ?>Informe modelo ou contrato.
          <?php elseif ($tipoOS === 'revisao'): ?>Informe modelo ou situação.
          <?php else: ?>Informe contrato ou situação.<?php endif; ?>
        </p>
        <?php if ($tipoOS === 'afericao'): ?>
          <label>Mês de referência</label><input id="mesFiltro" name="mesFiltro" value="<?= h($mesFiltro) ?>" placeholder="MM/AAAA" required>
        <?php endif; ?>
        <?php if ($tipoOS !== 'revisao'): ?>
          <label>Contrato</label><input id="contratoFiltro" name="contratoFiltro" value="<?= h($contratoFiltro) ?>">
          <label>Situação <?= $tipoOS === 'afericao' ? '*' : '' ?></label><input id="situacaoFiltro" name="situacaoFiltro" value="<?= h($situacaoFiltro) ?>">
          <label>Modelo</label><input id="modeloFiltro" name="modeloFiltro" value="<?= h($modeloFiltro) ?>">
          <label>Cidade</label><input id="cidadeFiltro" name="cidadeFiltro" value="<?= h($cidadeFiltro) ?>">
          <label>Bairro</label><input id="bairroFiltro" name="bairroFiltro" value="<?= h($bairroFiltro) ?>">
          <label>Estado</label><input id="estadoFiltro" name="estadoFiltro" value="<?= h($estadoFiltro) ?>">
          <label>Área de intervenção</label><input id="areaFiltro" name="areaFiltro" value="<?= h($areaFiltro) ?>">
          <label>Patrimônio ou série</label><input id="patFiltro" name="patFiltro" value="<?= h($patFiltro) ?>">
          <label>Série</label><input id="serieFiltro" name="serieFiltro" value="<?= h($serieFiltro) ?>">
          <?php if ($tipoOS === 'preventiva'): ?>
            <label>Páginas mín. desde preventiva</label><input type="number" min="0" id="paginasDesdePreventivaFiltro" name="paginasDesdePreventivaFiltro" value="<?= h($paginasDesdePreventivaFiltro) ?>">
          <?php endif; ?>
        <?php else: ?>
          <label>Situação</label><input id="situacaoFiltro" name="situacaoFiltro" value="<?= h($situacaoFiltro) ?>">
          <label>Modelo</label><input id="modeloFiltro" name="modeloFiltro" value="<?= h($modeloFiltro) ?>">
          <label>Série</label><input id="serieFiltro" name="serieFiltro" value="<?= h($serieFiltro) ?>">
        <?php endif; ?>
        <button class="btn btn-filter w-100 mt-3" type="submit">FILTRAR</button>
      </form>
    </aside>

    <section class="results-panel">
      <div class="results-toolbar">
        <div>
          <strong><?= count($equipamentos) ?> equipamento(s)</strong>
          <span id="selectionCount" class="text-muted ms-2">0 selecionado(s)</span>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <label class="select-all-label" for="checkAll"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"> Selecionar todos</label>
          <button class="btn btn-primary px-3" id="openSelectedButton" onclick="gerarOSSelecionadas(event)" disabled>Abrir OS para selecionados</button>
        </div>
      </div>

      <div class="equipment-list">
        <?php foreach ($equipamentos as $row):
          $aberta = !empty($row['OsCodigo']) && $row['OsFechamento'] === null;
          $orbixAtualizado = false;
          if ($tipoOS === 'afericao' && ($row['OrbixData'] ?? null) instanceof DateTime) {
            $hoje = new DateTime('today');
            $dataOrbix = clone $row['OrbixData'];
            $dataOrbix->setTime(0, 0, 0);
            $orbixAtualizado = $hoje->diff($dataOrbix)->days <= 5;
          }
          $selecionavel = !$aberta && !$orbixAtualizado;
        ?>
          <article class="equipment-row type-<?= h($tipoOS) ?> <?= $aberta ? 'has-open-order' : '' ?>">
            <div class="selection-cell">
              <?php if ($selecionavel): ?><input class="form-check-input check-serie" type="checkbox" value="<?= h($row['Serie']) ?>" data-contrato="<?= h($row['Contrato']) ?>" data-mes="<?= h($row['MesFechamento']) ?>" onchange="atualizarSelecao()"><?php endif; ?>
            </div>
            <div class="identity-card">
              <h6><?= h($row['Serie']) ?></h6>
              <p><strong>Modelo:</strong> <?= h($row['Modelo']) ?></p>
              <p><strong>PAT:</strong> <?= h($row['Patrimonio']) ?></p>
              <?php if ($tipoOS !== 'revisao'): ?><p><strong>Contrato:</strong> <?= h($row['Contrato']) ?></p><?php endif; ?>
            </div>
            <?php if ($tipoOS === 'revisao'): ?>
              <div class="info-card"><h6>Situação</h6><p><?= h($row['Situacao']) ?></p></div>
              <div class="info-card"><h6>OS de revisão</h6><p>OS: <?= h($row['OsCodigo']) ?></p><p>Técnico: <?= h($row['Tecnico']) ?></p><p>Status: <?= h($row['StatusOS']) ?></p></div>
            <?php elseif ($tipoOS === 'preventiva'): ?>
              <div class="info-card location-card"><h6>Localização</h6><p><?= h($row['Cidade']) ?> - <?= h($row['Estado']) ?></p><p><?= h($row['Bairro']) ?></p></div>
              <div class="info-card counter-card"><h6>Última leitura <small><?= dataBr($row['UltimaData']) ?></small></h6><?= badgesContadores($row, 'Ultima') ?></div>
              <div class="info-card counter-card">
                <h6>Última preventiva</h6>
                <?php if (empty($row['OsCodigo'])): ?>
                  <?= badgesContadores($row, 'Ultima', true) ?>
                <?php else: ?>
                  <?= badgesContadores($row, 'Os', true) ?>
                <?php endif; ?>
                <p>OS: <?= h($row['OsCodigo']) ?></p>
                <p>Data: <?= dataBr($row['OsData']) ?></p>
                <p>Dias: <?= h($row['DiasPreventiva']) ?></p>
                <p>Páginas: <?= contador($row['PaginasDesdePreventiva']) ?></p>
              </div>
              <div class="info-card counter-card">
                <h6>Corretiva/instalação</h6>
                <?= badgesContadores($row, 'Corretiva') ?>
                <p>OS: <?= h($row['CorretivaCodigo']) ?></p>
                <p>Data: <?= dataBr($row['CorretivaData']) ?></p>
                <p>Contador: <?= contador($row['CorretivaContador']) ?></p>
              </div>
            <?php else: ?>
              <div class="info-card location-card"><h6>Localização</h6><p><?= h($row['Cidade']) ?> - <?= h($row['Estado']) ?></p><p><?= h($row['Bairro']) ?></p></div>
              <div class="info-card counter-card"><h6>Última captura Orbix <small><?= dataBr($row['OrbixData']) ?></small></h6><?= badgesContadores($row, 'Orbix') ?></div>
              <div class="info-card counter-card"><h6>Última leitura <small><?= dataBr($row['DataLeitura']) ?></small></h6><?= badgesContadores($row, 'Leitura') ?></div>
              <div class="info-card counter-card">
                <h6>Última OS de aferição <small><?= dataBr($row['OsData']) ?></small></h6>
                <?= badgesContadores($row, 'Os', true) ?>
                <p>OS: <?= h($row['OsCodigo']) ?></p>
              </div>
            <?php endif; ?>
            <div class="action-cell">
              <?php if ($aberta): ?>
                <span class="open-order">OS <?= h($row['OsCodigo']) ?><small><?= h($row['StatusOS']) ?><br><?= h($row['Tecnico']) ?></small></span>
              <?php elseif ($orbixAtualizado): ?>
                <span class="open-order">ORBIX ATUALIZADO<small>Há menos de 5 dias</small></span>
              <?php else: ?>
                <button class="btn btn-sm" data-serie="<?= h($row['Serie']) ?>" onclick="gerarOSIndividual(this, event)">Abrir OS</button>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$equipamentos): ?><div class="empty-state"><?= h($mensagemLista) ?></div><?php endif; ?>
      </div>
    </section>
  </main>

  <div class="modal fade" id="modalTecnico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white"><h5 class="modal-title">Selecionar Técnico</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body p-4"><p id="textoModalTecnico" class="text-muted small mb-4"></p><label for="selectTecnico" class="form-label fw-bold">Técnico responsável</label><select class="form-select" id="selectTecnico"><option value="" disabled selected>Selecione um técnico...</option><?= $optionsTecnicos ?></select></div>
      <div class="modal-footer bg-light border-0"><button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn" onclick="confirmarGerarOS()">Confirmar e abrir OS</button></div>
    </div></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/inicio.js"></script>
</body>
</html>
