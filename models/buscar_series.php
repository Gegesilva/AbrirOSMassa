<?php
session_start();
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');
include_once "../conexaoSQL.php";

if (!isset($_SESSION["login"])) {
    echo "Sessão expirada.";
    exit;
}

if (isset($_POST['contrato']) && isset($_POST['mes'])) {
    $contrato = $_POST['contrato'];
    $mes = $_POST['mes'];
    $patFiltro = $_POST['patFiltro'];

    $cidade = $_POST['cidade'];

    $condicaoCidade = "";
    if ($cidade != "") {
        $condicaoCidade = " AND Cidade LIKE '%$cidade%' ";
    }

    $condicaoPatFiltro = "";
    if ($patFiltro != "") {
        $condicaoPatFiltro = " AND (PAT LIKE '%$patFiltro%' OR Serie LIKE '%$patFiltro%')";
    }

    $sql = "SELECT
                Serie AS SERAL,
                Pat AS PAT,
                Equipamento AS MODELO,
                subgrupo AS SUBGRUPO,
                marca AS MARCA,
                Estado AS ESTADO,
                Cidade AS CIDADE,
                Bairro AS BAIRRO,

                UltimaLeitOrbix.DTLEIT AS ULTIMA_CAPTURA_ORBIX,
                UltimaLeitOrbix.A4 AS CONT_A4_MONO,
                UltimaLeitOrbix.A4C AS CONT_A4_COLOR,
                UltimaLeitOrbix.SCANNER AS CONT_SCANNER,
                UltimaLeitOrbix.A3 AS CONT_A3_MONO,
                UltimaLeitOrbix.A3C AS CONT_A3_COLOR,

                CASE
                    WHEN UltimaLeitOrbix.DTLEIT IS NOT NULL
                     AND MONTH(UltimaLeitOrbix.DTLEIT) = MONTH(GETDATE())
                     AND YEAR(UltimaLeitOrbix.DTLEIT) = YEAR(GETDATE())
                        THEN 'Sim'
                    ELSE 'Não'
                END AS LEITURA_MES_ORBIX,

                CASE
                    WHEN UltimaLeit.DTCAD IS NOT NULL
                     AND MONTH(UltimaLeit.DTCAD) = MONTH(GETDATE())
                     AND YEAR(UltimaLeit.DTCAD) = YEAR(GETDATE())
                        THEN 'Sim'
                    ELSE 'Não'
                END AS LEITURA_MES_TB02117,

                CASE
                    WHEN OSAFERICAO.DTCAD IS NOT NULL
                     AND MONTH(OSAFERICAO.DTCAD) = MONTH(GETDATE())
                     AND YEAR(OSAFERICAO.DTCAD) = YEAR(GETDATE())
                        THEN 'Sim'
                    ELSE 'Não'
                END AS LEITURA_MES_OS,

                UltimaLeit.DTCAD AS DATA_LEITURA,
                UltimaLeit.PB AS LEITOR_PB,
                UltimaLeit.COR AS LEITOR_COR,
                UltimaLeit.DIGITALIZACAO AS LEITOR_DIGITALIZACAO,
                UltimaLeit.A3 AS LEITOR_A3,
                UltimaLeit.A3COR AS LEITOR_A3_COR,
                OSAFERICAO.DTCAD AS DATA_OS_AFERICAO,
                OSAFERICAO.STATUS AS STATUS_OS_AFERICAO,
                OSAFERICAO.OS AS OS_AFERICAO,
                OSAFERICAO.PB AS LEITURA_OS_PB,
                OSAFERICAO.COR AS LEITURA_OS_COR,

                CASE
                    WHEN OSAFERICAO.DTCAD IS NOT NULL THEN 'TEMOS'
                    ELSE 'NTEMOS'
                END AS TEMOS
            FROM BM_CONTRLEITURA_ABRIROS
            OUTER APPLY (
		       SELECT TOP 1 
                    TB02115_DTCAD DTCAD,
                    TB02115_CODIGO OS,
                    TB01073_NOME STATUS,
                    TB02115_CONTPB PB,
                    TB02115_CONTCOLOR COR
                FROM TB02115 
                LEFT JOIN TB01073 ON TB01073_CODIGO = TB02115_STATUS
                WHERE TB02115_NUMSERIE = BM_CONTRLEITURA_ABRIROS.Serie
                    AND TB02115_PREVENTIVA IN ('I','N')
                   -- AND TB02115_DTFECHA IS NULL
                ORDER BY TB02115_DTCAD DESC
	        ) OSAFERICAO

            OUTER APPLY (
                SELECT TOP 1
                    TB02117_DATA DTCAD,
                    TB02117_MEDATUAL PB,
                    TB02117_MEDATUALC COR,
                    TB02117_MEDATUALDG DIGITALIZACAO,
                    TB02117_MEDATUALGF A3,
                    TB02117_MEDATUALGFC A3COR
                FROM TB02117
                WHERE TB02117_NUMSERIE = BM_CONTRLEITURA_ABRIROS.Serie
                  AND TB02117_CODIGO = BM_CONTRLEITURA_ABRIROS.Contrato
                ORDER BY TB02117_DATA DESC
            ) UltimaLeit

            OUTER APPLY (
                SELECT TOP 1 
                    TB02254_DATEREADING DTLEIT,
                    TB02254_A4MONO A4,
                    TB02254_A4COLOR A4C,
                    TB02254_SCANNER SCANNER,
                    TB02254_A3MONO A3,
                    TB02254_A3COLOR A3C,
                    TB02254_DATEREADING
                FROM tb02254
                WHERE TB02254_SERIALNUMBER = BM_CONTRLEITURA_ABRIROS.Serie
                    AND TB02254_CONTRACT = BM_CONTRLEITURA_ABRIROS.Contrato
                ORDER BY TB02254_DATEREADING DESC
            ) UltimaLeitOrbix

            WHERE situacao = 'A' 
                AND libleitura = 'N'
                AND MesFechamento = ?
                AND Contrato = ?
                AND Possui = 'Não'
                $condicaoCidade
                $condicaoPatFiltro";

    $params = array($mes, $contrato);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo "<div class='alert alert-danger'>Erro ao buscar dados.</div>";
        exit;
    }

    echo '<div class="p-3 bg-white border rounded shadow-sm">';

    echo '<div class="d-flex justify-content-end mb-2 px-3">';
    echo '  <div class="form-check">';
    echo '      <input class="form-check-input" type="checkbox" id="checkAll_' . $contrato . '" onchange="toggleAll(this, \'' . $contrato . '\')" checked style="transform: scale(1.2); cursor:pointer;">';
    echo '      <label class="form-check-label fw-bold text-primary" for="checkAll_' . $contrato . '" style="cursor:pointer;">Marcar/Desmarcar Todos</label>';
    echo '  </div>';
    echo '</div>';

    // Função auxiliar para tratar dados do SQL Server antes de imprimir
    function formataDado($valor)
    {
        if ($valor === null) {
            return ''; // Se for nulo no banco, retorna vazio para não dar erro
        }
        if ($valor instanceof DateTime) {
            return $valor->format('d/m/Y'); // Se for data, formata para dd/mm/aaaa
        }
        return htmlspecialchars((string) $valor); // Se for texto ou número, converte normal
    }

    function formataContador($valor)
    {
        return ($valor === null || $valor === '') ? '0' : htmlspecialchars((string) $valor);
    }

    $hasData = false;

    // Lista estilo "Cards" horizontais
    echo '<ul class="list-group list-group-flush">';

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $hasData = true;
        $classeTemos = $row['TEMOS'];

        // Fundo levemente colorido se já tiver OS
        $bgClass = ($classeTemos == 'TEMOS') ? 'bg-light border-danger border-start border-1' : 'border-primary border-start border-1';

        echo '<li class="list-group-item py-3 mb-2 rounded border-top ' . $bgClass . '">';
        echo '  <div class="row align-items-center">';

        // --- COLUNA 1: Destaque principal (Série e Modelo) ---
        echo '<div class="col-md-2 border-end">';
        echo ' <h6 class="mb-1 text-primary fw-bold">Série: ' . formataDado($row['SERAL']) . '</h6>';
        echo ' <p class="mb-0 text-muted small"><strong>Modelo:</strong> ' . formataDado($row['MODELO']) . ' | <strong>Marca:</strong> ' . formataDado($row['MARCA']) . '</p>';
        echo ' <p class="mb-0 text-muted small"><strong>PAT:</strong> ' . formataDado($row['PAT']) . ' | <strong>Subgrupo:</strong> ' . formataDado($row['SUBGRUPO']) . '</p>';
        echo '</div>';

        // --- COLUNA 2: Localização ---
        echo '<div class="col-md-2 border-end">';
        echo ' <p class="mb-1 fw-semibold small text-dark"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-geo-alt-fill me-1" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>Localização</p>';
        echo ' <p class="mb-0 text-muted small">' . formataDado($row['CIDADE']) . ' - ' . formataDado($row['ESTADO']) . '</p>';
        echo ' <p class="mb-0 text-muted small"><strong>Bairro:</strong> ' . formataDado($row['BAIRRO']) . '</p>';
        echo '</div>';

        // --- COLUNA 3: Dados Técnicos (Orbix/Leitura e CONTADORES) ---
        echo '<div class="col-md-2 border-end">';
        echo ' <p class="mb-1 fw-semibold small text-dark"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-bar-chart-fill me-1" viewBox="0 0 16 16"><path d="M1 11a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1zm5-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1z"/></svg>Orbix/Leitura</p>';

        // ADICIONADO: Exibindo os contadores em uma pequena caixa com badges para não poluir muito
        echo ' <div class="d-flex flex-wrap gap-1 mb-2 justify-content-center">';
        echo '  <span class="badge bg-primary" title="A4 Mono" style="font-size: 0.65rem;">A4: ' . formataContador($row['CONT_A4_MONO']) . '</span>';
        echo '  <span class="badge bg-primary" title="A4 Color" style="font-size: 0.65rem;">A4C: ' . formataContador($row['CONT_A4_COLOR']) . '</span>';
        echo '  <span class="badge bg-primary" title="Scanner" style="font-size: 0.65rem;">DG: ' . formataContador($row['CONT_SCANNER']) . '</span>';
        echo '  <span class="badge bg-primary" title="A3 Mono" style="font-size: 0.65rem;">A3: ' . formataContador($row['CONT_A3_MONO']) . '</span>';
        echo '  <span class="badge bg-primary" title="A3 Color" style="font-size: 0.65rem;">A3C: ' . formataContador($row['CONT_A3_COLOR']) . '</span>';

        echo ' </div>';

        echo ' <p class="mb-0 text-muted small"><strong>Data:</strong> ' . formataDado($row['ULTIMA_CAPTURA_ORBIX']) . '</p>';
        echo ' <p class="mb-0 text-muted small"><strong>Leitura Mês:</strong> ' . formataDado($row['LEITURA_MES_ORBIX']) . '</p>';
        echo '</div>';

        // --- COLUNA 4: Leitores registrados na TB02117 ---
        echo '<div class="col-md-2 border-end text-center">';
        echo ' <p class="mb-1 fw-semibold small text-dark"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-speedometer2 me-1" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5v4.293l2.854 2.853a.5.5 0 0 1-.708.708l-3-3A.5.5 0 0 1 7.5 9V4.5A.5.5 0 0 1 8 4"/><path d="M3.732 4.732a6 6 0 1 1-1.386 6.11.5.5 0 0 1 .976-.218 5 5 0 1 0 1.157-5.096.5.5 0 0 1-.747-.666z"/></svg>Leituras Contrato</p>';
        echo ' <div class="d-flex flex-wrap gap-1 justify-content-center">';
        echo '  <span class="badge bg-primary" title="A4 preto e branco" style="font-size: 0.65rem;">A4: ' . formataContador($row['LEITOR_PB']) . '</span>';
        echo '  <span class="badge bg-primary" title="A4 colorido" style="font-size: 0.65rem;">A4C: ' . formataContador($row['LEITOR_COR']) . '</span>';
        echo '  <span class="badge bg-primary" title="Digitalização" style="font-size: 0.65rem;">DG: ' . formataContador($row['LEITOR_DIGITALIZACAO']) . '</span>';
        echo '  <span class="badge bg-primary" title="A3 preto e branco" style="font-size: 0.65rem;">A3: ' . formataContador($row['LEITOR_A3']) . '</span>';
        echo '  <span class="badge bg-primary" title="A3 colorido" style="font-size: 0.65rem;">A3C: ' . formataContador($row['LEITOR_A3_COR']) . '</span>';
        echo ' </div>';
        echo ' <p class="mb-0 text-muted small"><strong>Data:</strong> ' . formataDado($row['DATA_LEITURA']) . '</p>';
        echo ' <p class="mb-0 text-muted small"><strong>Leitura Mês:</strong> ' . formataDado($row['LEITURA_MES_TB02117']) . '</p>';
        echo '</div>';

        // --- COLUNA 5: Leituras registradas na TB02115 ---
        echo '<div class="col-md-2 text-center border-end">';
        echo ' <p class="mb-1 fw-semibold small text-dark"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-clipboard-check me-1" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M10.854 6.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 8.793l2.646-2.647a.5.5 0 0 1 .708 0"/><path d="M4 1.5H3a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-11a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h1z"/><path d="M9.5 1h-3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5m-3-1h3A1.5 1.5 0 0 1 11 1.5v1A1.5 1.5 0 0 1 9.5 4h-3A1.5 1.5 0 0 1 5 2.5v-1A1.5 1.5 0 0 1 6.5 0"/></svg>Leituras OS</p>';
        echo ' <p class="mb-1 text-muted small"><strong>OS:</strong> ' . formataContador($row['OS_AFERICAO']) . '</p>';
        echo ' <div class="d-flex flex-wrap gap-1 mb-2 justify-content-center">';
        echo '  <span class="badge bg-primary" title="Contador A4 preto e branco da OS" style="font-size: 0.65rem;">A4: ' . formataContador($row['LEITURA_OS_PB']) . '</span>';
        echo '  <span class="badge bg-primary" title="Contador A4 colorido da OS" style="font-size: 0.65rem;">A4C: ' . formataContador($row['LEITURA_OS_COR']) . '</span>';
        echo ' </div>';
        echo ' <p class="mb-0 text-muted small"><strong>Data:</strong> ' . formataDado($row['DATA_OS_AFERICAO']) . '</p>';
        echo ' <p class="mb-0 text-muted small"><strong>Leitura Mês:</strong> ' . formataDado($row['LEITURA_MES_OS']) . '</p>';
        echo '</div>';

        // --- COLUNA 6: Ação e Status OS ---
        echo '<div class="col-md-1 text-center border-end">';
        $passouDe5Dias = false;
        if ($row['ULTIMA_CAPTURA_ORBIX'] instanceof DateTime) {
            $hoje = new DateTime();
            $hoje->setTime(0, 0, 0);

            $dataOrbix = clone $row['ULTIMA_CAPTURA_ORBIX'];
            $dataOrbix->setTime(0, 0, 0);

            $diff = $hoje->diff($dataOrbix);

            if ($diff->days <= 5) {
                $passouDe5Dias = true;
            }
        }

        $flagatv = false;

        if ($classeTemos == 'NTEMOS') {
            if ($passouDe5Dias) {
                echo ' <button class="btn btn-sm btn-danger disabled w-100 rounded-pill mb-2" style="cursor: not-allowed; font-size: 0.85rem;">';
                echo '  ORBIX ATUALIZADO (<5 DIAS)';
                echo ' </button>';
            } else {
                echo ' <button class="btn btn-sm w-100 rounded-pill shadow-sm fw-bold mb-2" onclick="gerarOS(\'' . $contrato . '\', \'' . $row['SERAL'] . '\', event)">';
                echo '  ABRIR OS INDIVIDUAL';
                echo ' </button>';
                $flagatv = true;
            }
        } else {
            echo ' <button class="btn btn-danger disabled w-100 rounded-pill mb-2" style="cursor: not-allowed; font-size: 0.85rem;">';
            echo '  POSSUI OS ABERTA - ' . formataDado($row['OS_AFERICAO']) . '';
            echo ' </button>';
            echo ' <div class="text-start bg-light p-2 rounded border">';
            echo '  <p class="mb-0 text-muted" style="font-size: 10px;"><strong>Status:</strong> ' . formataDado($row['STATUS_OS_AFERICAO']) . '</p>';
            echo '  <p class="mb-0 text-muted" style="font-size: 10px;"><strong>Data OS:</strong> ' . formataDado($row['DATA_OS_AFERICAO']) . '</p>';
            echo ' </div>';
        }

        echo '</div>';

        if ($flagatv) {
            // --- COLUNA 7: CHECKBOX ---
            echo '<div class="col-md-1 d-flex justify-content-center align-items-center">';
            echo '  <input 
                        class="form-check-input check-serie" 
                        type="checkbox" 
                        value="' . formataDado($row['SERAL']) . '" 
                        data-contrato="' . $contrato . '" 
                        style="transform: scale(1.4); cursor:pointer;"
                        checked>';
            echo '</div>';
        }

        echo '  </div>'; // fim row
        echo '</li>';

    }

    echo '</ul>';

    if (!$hasData) {
        echo '<div class="text-center text-muted py-4">Nenhum equipamento pendente encontrado para este contrato no mês selecionado.</div>';
    }

    echo '</div>';
}
