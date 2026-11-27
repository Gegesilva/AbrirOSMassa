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

$sql = "SELECT
        TB01066_USUARIO Usuario,
        TB01066_SENHA Senha
    FROM TB01066
    WHERE TB01066_USUARIO = ?
        AND TB01066_SENHA = ?";
$stmt = sqlsrv_query($conn, $sql, [$login, $senha]);
if ($stmt === false) {
  die('<pre>' . htmlspecialchars(exibirErroSql('Validação do usuário'), ENT_QUOTES, 'UTF-8') . '</pre>');
}
$usuario = null;
if ($stmt !== false && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $usuario = $row['Usuario'];
}
if ($usuario === null) {
  echo "<script>window.alert('É necessário fazer login!')</script>";
  echo "<script>location.href='./Login.php'</script>";
  exit;
}

$sqlTecnicos = "SELECT TB01024_CODIGO, TB01024_NOME FROM TB01024 WHERE TB01024_SITUACAO = 'A' ORDER BY TB01024_NOME ASC";
$stmtTecnicos = sqlsrv_query($conn, $sqlTecnicos);
if ($stmtTecnicos === false) {
  die('<pre>' . htmlspecialchars(exibirErroSql('Consulta de técnicos'), ENT_QUOTES, 'UTF-8') . '</pre>');
}
$optionsTecnicos = '';
if ($stmtTecnicos !== false) {
  while ($rowT = sqlsrv_fetch_array($stmtTecnicos, SQLSRV_FETCH_ASSOC)) {
    $cod = htmlspecialchars($rowT['TB01024_CODIGO']);
    $nome = htmlspecialchars($rowT['TB01024_NOME']);
    $optionsTecnicos .= "<option value='$cod'>$cod - $nome</option>";
  }
}

include_once 'models/resumo_os.php';
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

<body data-tipo-os="<?= htmlspecialchars($tipoOS) ?>">
  <div id="loadingOverlay" class="loading-overlay" role="status" aria-live="polite" aria-hidden="true">
    <div class="loading-box">
      <div class="spinner-border mb-3" aria-hidden="true"></div>
      <div id="loadingMessage" class="fw-bold">Carregando...</div>
      <div class="small text-muted mt-1">Aguarde um momento</div>
    </div>
  </div>
  <header class="header-bg text-center sticky-top">
    <div class="container-fluid px-3 text-start">
      <div class="d-inline-flex flex-wrap gap-2 my-1">
        <span
          class="badge bg-primary bg-opacity-50 text-primary border border-primary-subtle px-3 py-1 rounded-pill shadow-sm text-white"
          style="font-size: 0.75rem;"><strong class="me-1">Equipamentos:</strong> <?= $totais['equipamentos'] ?></span>
        <span
          class="badge bg-success bg-opacity-50 text-success border border-success-subtle px-3 py-1 rounded-pill shadow-sm text-white"
          style="font-size: 0.75rem;"><strong class="me-1">Aferidos:</strong> <?= $totais['aferidos'] ?></span>
        <span
          class="badge bg-warning bg-opacity-50 text-white border border-warning-subtle px-3 py-1 rounded-pill shadow-sm"
          style="font-size: 0.75rem;"><strong class="me-1">Não Aferidos:</strong> <?= $totais['nao_aferidos'] ?></span>
        <span
          class="badge bg-danger bg-opacity-50 text-danger border border-danger-subtle px-3 py-1 rounded-pill shadow-sm text-white"
          style="font-size: 0.75rem;"><strong class="me-1">OS Abertas:</strong> <?= $totais['os_abertas'] ?></span>
      </div>
    </div>

    <div class="container">
      <img src="MEDIA/logo.png" class="logo-left" alt="Logo">
      <h4 class="mb-4">ABERTURA OS</h4>

      <form method="POST" action="inicio.php" class="row g-2 tipo-os-nav mx-auto mb-3" style="max-width: 1050px;">
        <div class="col-md-4"><button type="submit" name="tipo" value="afericao"
            class="btn w-100 d-flex align-items-center justify-content-center <?= $tipoOS === 'afericao' ? 'active' : '' ?>">OS
            DE AFERIÇÃO</button></div>
        <div class="col-md-4"><button type="submit" name="tipo" value="preventiva"
            class="btn w-100 d-flex align-items-center justify-content-center <?= $tipoOS === 'preventiva' ? 'active' : '' ?>">OS
            PREVENTIVA</button></div>
        <div class="col-md-4"><button type="submit" name="tipo" value="revisao"
            class="btn w-100 d-flex align-items-center justify-content-center <?= $tipoOS === 'revisao' ? 'active' : '' ?>">REVISÃO
            INTERNA</button></div>
      </form>

      <form action="inicio.php" method="POST" class="mx-auto bg-white p-3 rounded-4 shadow-sm text-start"
        style="max-width: 1050px;">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipoOS) ?>">
        <div class="row g-3 align-items-end">
          <?php if ($tipoOS !== 'revisao'): ?>
            <div class="col-md-2"><label for="mesFiltro" class="form-label text-muted small fw-bold mb-1">Mês de
                Referência</label><input type="text" class="form-control bg-light border-0 py-2 text-center"
                id="mesFiltro" name="mesFiltro" value="<?= htmlspecialchars($mesFiltro) ?>" placeholder="MM/AAAA"
                required></div>
            <div class="col-md-2"><label for="cidadeFiltro" class="form-label text-muted small fw-bold mb-1">Filtrar por
                Cidade</label><input type="text" class="form-control bg-light border-0 py-2" id="cidadeFiltro"
                name="cidadeFiltro" value="<?= htmlspecialchars($cidadeFiltro) ?>" placeholder="Digite a cidade..."
                autocomplete="off"></div>
            <div class="col-md-4"><label for="contratoFiltro" class="form-label text-muted small fw-bold mb-1">Filtrar por
                Contrato</label><input type="text" class="form-control bg-light border-0 py-2" id="contratoFiltro"
                name="contratoFiltro" value="<?= htmlspecialchars($contratoFiltro) ?>" placeholder="Digite o contrato..."
                autocomplete="off"></div>
            <div class="col-md-3"><label for="patFiltro" class="form-label text-muted small fw-bold mb-1">Filtrar por
                Patrimônio ou série</label><input type="text" class="form-control bg-light border-0 py-2" id="patFiltro"
                name="patFiltro" value="<?= htmlspecialchars($patFiltro) ?>" placeholder="Digite o Pat. ou série..."
                autocomplete="off"></div>
            <div class="col-md-3"><label class="form-label text-muted small fw-bold mb-1">Bairro</label><input
                class="form-control bg-light border-0 py-2" id="bairroFiltro" name="bairroFiltro"
                value="<?= htmlspecialchars($bairroFiltro) ?>"></div>
            <div class="col-md-2"><label class="form-label text-muted small fw-bold mb-1">Estado</label><input
                class="form-control bg-light border-0 py-2" id="estadoFiltro" name="estadoFiltro"
                value="<?= htmlspecialchars($estadoFiltro) ?>"></div>
            <div class="col-md-3"><label class="form-label text-muted small fw-bold mb-1">Área de
                intervenção</label><input class="form-control bg-light border-0 py-2" id="areaFiltro" name="areaFiltro"
                value="<?= htmlspecialchars($areaFiltro) ?>"></div>
            <div class="col-md-2"><label class="form-label text-muted small fw-bold mb-1">Série</label><input
                class="form-control bg-light border-0 py-2" id="serieFiltro" name="serieFiltro"
                value="<?= htmlspecialchars($serieFiltro) ?>"></div>
            <?php if ($tipoOS === 'preventiva'): ?>
              <div class="col-md-2"><label class="form-label text-muted small fw-bold mb-1">Modelo</label><input
                  class="form-control bg-light border-0 py-2" id="modeloFiltro" name="modeloFiltro"
                  value="<?= htmlspecialchars($modeloFiltro) ?>"></div>
              <div class="col-md-3"><label class="form-label text-muted small fw-bold mb-1">Páginas mín. desde
                  preventiva</label><input type="number" min="0" step="1" class="form-control bg-light border-0 py-2"
                  id="paginasDesdePreventivaFiltro" name="paginasDesdePreventivaFiltro"
                  value="<?= htmlspecialchars($paginasDesdePreventivaFiltro) ?>" placeholder="Ex.: 1000"></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="col-md-3"><label class="form-label text-muted small fw-bold mb-1">Situação</label><input
                class="form-control bg-light border-0 py-2" name="situacaoFiltro"
                value="<?= htmlspecialchars($situacaoFiltro) ?>"></div>
            <div class="col-md-4"><label class="form-label text-muted small fw-bold mb-1">Modelo</label><input
                class="form-control bg-light border-0 py-2" name="modeloFiltro"
                value="<?= htmlspecialchars($modeloFiltro) ?>"></div>
            <div class="col-md-4"><label class="form-label text-muted small fw-bold mb-1">Série</label><input
                class="form-control bg-light border-0 py-2" name="serieFiltro"
                value="<?= htmlspecialchars($serieFiltro) ?>"></div>
          <?php endif; ?>
          <div class="col-md-1"><button class="btn w-100 py-2 rounded-3 shadow-sm" type="submit"
              style="background-color: #fff; color: white; border: none; transition: 0.3s;"><svg
                xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-search"
                viewBox="0 0 16 16">
                <path
                  d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 1-1.415 1.414l-3.85-3.85a1.007 1.007 0 0 1-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
              </svg></button></div>
        </div>
      </form>
    </div>
  </header>

  <main class="container-fluid mb-5">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card card-table overflow-hidden">
          <div class="table-responsive">
            <table class="table table-hover text-center align-middle">
              <thead>
                <tr>
                  <th><?= $tipoOS === 'revisao' ? 'Código modelo' : 'Contrato' ?></th>
                  <th><?= $tipoOS === 'revisao' ? 'Modelo' : 'Cliente' ?></th>
                  <th>Equipamentos</th>
                  <th><?= $tipoOS === 'afericao' ? 'Aferidos' : 'Disponíveis' ?></th>
                  <th><?= $tipoOS === 'afericao' ? 'Não aferidos' : 'Bloqueados' ?></th>
                  <th><?= $tipoOS === 'afericao' ? 'Qtd OS Aferição' : 'Qtd OS' ?></th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($resumos as $rowL):
                  $totalEquipamentos = (int) $rowL['Equipamentos'];
                  $totalDisponiveis = (int) $rowL['EquipamentosNaoAferidos'];
                  $totalOSAbertas = (int) $rowL['TotalOSAberta'];
                  $finalizado = $totalEquipamentos > 0 && ($totalDisponiveis === 0 || $totalOSAbertas >= $totalEquipamentos);
                  ?>
                  <tr style="cursor:pointer"
                    onclick="abrirSeries(event, '<?= htmlspecialchars($rowL['Contrato'], ENT_QUOTES) ?>', this, '<?= htmlspecialchars($mesFiltro, ENT_QUOTES) ?>', '<?= htmlspecialchars($patFiltro, ENT_QUOTES) ?>')">
                    <td><span class="status-badge"><?= htmlspecialchars($rowL['Contrato']) ?></span></td>
                    <td class="text-start"><?= htmlspecialchars($rowL['Cliente']) ?></td>
                    <td><?= $totalEquipamentos ?></td>
                    <td><?= $tipoOS === 'afericao' ? (int) $rowL['EquipamentosAferidos'] : $totalDisponiveis ?></td>
                    <td><?= $tipoOS === 'afericao' ? $totalDisponiveis : $totalEquipamentos - $totalDisponiveis ?></td>
                    <td><?= $totalOSAbertas ?></td>
                    <td><?php if ($finalizado): ?><button
                          class="btn btn-sm btn-danger rounded-pill shadow-sm">X</button><?php else: ?><button
                          class="btn btn-sm px-4 rounded-pill shadow-sm"
                          onclick="gerarOS('<?= htmlspecialchars($rowL['Contrato'], ENT_QUOTES) ?>', '', event)">ABRIR
                          OS</button><?php endif; ?></td>
                  </tr>
                  <tr style="display:none;background-color:#f8f9fa;">
                    <td colspan="7" class="p-0 border-0 w-100">
                      <div class="series-content w-100 px-4 py-2 mx-auto"></div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$resumos): ?>
                  <tr>
                    <td colspan="7" class="py-5 text-muted">Nenhum registro encontrado.</td>
                  </tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="modalTecnico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Selecionar Técnico</h5><button type="button" class="btn-close btn-close-white"
            data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <p id="textoModalTecnico" class="text-muted small mb-4"></p>
          <div class="mb-3"><label for="selectTecnico" class="form-label fw-bold text-dark">Técnico
              Responsável:</label><select class="form-select" id="selectTecnico">
              <option value="" disabled selected>Selecione um técnico...</option><?= $optionsTecnicos ?>
            </select></div>
        </div>
        <div class="modal-footer bg-light border-0"><button type="button" class="btn rounded-pill px-4"
            data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn rounded-pill px-4"
            onclick="confirmarGerarOS()">Confirmar e Abrir OS</button></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/inicio.js"></script>
</body>

</html>
