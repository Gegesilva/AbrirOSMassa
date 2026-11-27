<?php
session_start();
include_once '../conexaoSQL.php';

if (empty($_SESSION['login']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Sessão expirada.</div>';
    exit;
}
session_write_close();

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$grupo = isset($_POST['grupo']) ? trim($_POST['grupo']) : '';
$mes = isset($_POST['mes']) ? trim($_POST['mes']) : '';
$cidadeFiltro = isset($_POST['cidade']) ? trim($_POST['cidade']) : '';
$patFiltro = isset($_POST['patFiltro']) ? trim($_POST['patFiltro']) : '';
$bairroFiltro = isset($_POST['bairroFiltro']) ? trim($_POST['bairroFiltro']) : '';
$estadoFiltro = isset($_POST['estadoFiltro']) ? trim($_POST['estadoFiltro']) : '';
$areaFiltro = isset($_POST['areaFiltro']) ? trim($_POST['areaFiltro']) : '';
$serieFiltro = isset($_POST['serieFiltro']) ? trim($_POST['serieFiltro']) : '';
$modeloFiltro = isset($_POST['modeloFiltro']) ? trim($_POST['modeloFiltro']) : '';
$situacaoFiltro = isset($_POST['situacaoFiltro']) ? trim($_POST['situacaoFiltro']) : '';
$paginasDesdePreventivaFiltro = isset($_POST['paginasDesdePreventivaFiltro'])
    ? trim($_POST['paginasDesdePreventivaFiltro'])
    : '';

if ($paginasDesdePreventivaFiltro !== '' && !ctype_digit($paginasDesdePreventivaFiltro)) {
    $paginasDesdePreventivaFiltro = '';
}

if ($tipo === 'preventiva') {
    $sql = "SELECT TOP (1000)
            B.Serie,
            B.Pat AS Patrimonio,
            B.Equipamento AS Modelo,
            B.Cidade,
            B.Estado,
            B.Bairro,
            L.DataLeitura,
            L.A4Mono,
            L.A4Color,
            L.Scanner,
            L.A3Mono,
            L.A3Color,
            P.TB02115_CODIGO AS UltimaPreventiva,
            P.TB02115_DTCAD AS DataPreventiva,
            P.TB02115_DTFECHA AS FechamentoPreventiva,
            P.TB02115_CONTTOTAL AS ContadorPreventiva,
            TP.TB01024_NOME AS Tecnico,
            SP.TB01073_NOME AS StatusOS,
            DATEDIFF(DAY, P.TB02115_DTCAD, GETDATE()) AS DiasPreventiva,
            CASE
                WHEN P.TB02115_CODIGO IS NULL THEN NULL
                WHEN ISNULL(L2254.Contador2254, 0) >= ISNULL(L2117.Contador2117, 0)
                    THEN ISNULL(L2254.Contador2254, 0) - ISNULL(P.TB02115_CONTTOTAL, 0)
                ELSE ISNULL(L2117.Contador2117, 0) - ISNULL(P.TB02115_CONTTOTAL, 0)
            END AS PaginasDesdePreventiva,
            C.TB02115_CODIGO AS UltimaCorretiva,
            C.DataIntervencao,
            C.Contador
        FROM BM_CONTRLEITURA_ABRIROS B
        OUTER APPLY (
            SELECT TOP 1
                X.DataLeitura,
                X.A4Mono,
                X.A4Color,
                X.Scanner,
                X.A3Mono,
                X.A3Color
            FROM (
                SELECT
                    O.TB02254_DATEREADING AS DataLeitura,
                    O.TB02254_A4MONO AS A4Mono,
                    O.TB02254_A4COLOR AS A4Color,
                    O.TB02254_SCANNER AS Scanner,
                    O.TB02254_A3MONO AS A3Mono,
                    O.TB02254_A3COLOR AS A3Color
                FROM TB02254 O
                WHERE O.TB02254_SERIALNUMBER = B.Serie
                    AND O.TB02254_CONTRACT = B.Contrato
                UNION ALL
                SELECT
                    R.TB02117_DATA,
                    R.TB02117_MEDATUAL,
                    R.TB02117_MEDATUALC,
                    R.TB02117_MEDATUALDG,
                    R.TB02117_MEDATUALGF,
                    R.TB02117_MEDATUALGFC
                FROM TB02117 R
                WHERE R.TB02117_NUMSERIE = B.Serie
                    AND R.TB02117_CODIGO = B.Contrato
            ) X
            ORDER BY X.DataLeitura DESC
        ) L
        OUTER APPLY (
            SELECT TOP 1
                ISNULL(O.TB02254_A4MONO, 0)
                + ISNULL(O.TB02254_A4COLOR, 0)
                + ISNULL(O.TB02254_SCANNER, 0)
                + ISNULL(O.TB02254_A3MONO, 0)
                + ISNULL(O.TB02254_A3COLOR, 0) AS Contador2254
            FROM TB02254 O
            WHERE O.TB02254_SERIALNUMBER = B.Serie
                AND O.TB02254_CONTRACT = B.Contrato
            ORDER BY O.TB02254_DATEREADING DESC
        ) L2254
        OUTER APPLY (
            SELECT TOP 1
                ISNULL(R.TB02117_MEDATUAL, 0)
                + ISNULL(R.TB02117_MEDATUALC, 0)
                + ISNULL(R.TB02117_MEDATUALDG, 0)
                + ISNULL(R.TB02117_MEDATUALGF, 0)
                + ISNULL(R.TB02117_MEDATUALGFC, 0) AS Contador2117
            FROM TB02117 R
            WHERE R.TB02117_NUMSERIE = B.Serie
                AND R.TB02117_CODIGO = B.Contrato
            ORDER BY R.TB02117_DATA DESC
        ) L2117
        OUTER APPLY (
            SELECT TOP 1
                O.TB02115_CODIGO,
                O.TB02115_DTCAD,
                O.TB02115_DTFECHA,
                O.TB02115_CODTEC,
                O.TB02115_STATUS,
                O.TB02115_CONTTOTAL
            FROM TB02115 O
            WHERE O.TB02115_NUMSERIE = B.Serie
                AND O.TB02115_PREVENTIVA = 'S'
            ORDER BY O.TB02115_DTCAD DESC
        ) P
        LEFT JOIN TB01024 TP ON TP.TB01024_CODIGO = P.TB02115_CODTEC
        LEFT JOIN TB01073 SP ON SP.TB01073_CODIGO = P.TB02115_STATUS
        OUTER APPLY (
            SELECT TOP 1
                O.TB02115_CODIGO,
                I.TB02122_DATA AS DataIntervencao,
                I.TB02122_CONTADOR AS Contador
            FROM TB02115 O
            LEFT JOIN TB02122 I ON I.TB02122_NUMOS = O.TB02115_CODIGO
            WHERE O.TB02115_NUMSERIE = B.Serie
                AND O.TB02115_PREVENTIVA IN ('I', 'N')
            ORDER BY COALESCE(I.TB02122_DATA, O.TB02115_DTCAD) DESC
        ) C
        WHERE B.Contrato = ?
            AND B.MesFechamento = ?
            AND B.situacao <> 'I'
            AND B.libleitura = 'N'
            AND (L.DataLeitura IS NULL OR L.DataLeitura <= CAST(GETDATE() AS date))
            AND (? = '' OR B.Cidade LIKE '%' + ? + '%')
            AND (? = '' OR B.Pat LIKE '%' + ? + '%' OR B.Serie LIKE '%' + ? + '%')
            AND (? = '' OR B.Bairro LIKE '%' + ? + '%')
            AND (? = '' OR B.Estado LIKE '%' + ? + '%')
            AND (? = '' OR B.Serie LIKE '%' + ? + '%')
            AND (? = '' OR B.Equipamento LIKE '%' + ? + '%')
            AND (? = '' OR EXISTS (
                SELECT 1
                FROM TB02112 EQ
                WHERE EQ.TB02112_NUMSERIE = B.Serie
                    AND EQ.TB02112_TIPOINTERV LIKE '%' + ? + '%'
            ))
            AND (
                ? = ''
                OR (
                    P.TB02115_CONTTOTAL IS NOT NULL
                    AND (
                        CASE
                            WHEN ISNULL(L2254.Contador2254, 0) >= ISNULL(L2117.Contador2117, 0)
                                THEN ISNULL(L2254.Contador2254, 0)
                            ELSE ISNULL(L2117.Contador2117, 0)
                        END
                    ) - P.TB02115_CONTTOTAL >= ?
                )
            )
        ORDER BY
            L.DataLeitura DESC,
            B.Serie ASC";
    $stmt = sqlsrv_query($conn, $sql, [
        $grupo,
        $mes,
        $cidadeFiltro,
        $cidadeFiltro,
        $patFiltro,
        $patFiltro,
        $patFiltro,
        $bairroFiltro,
        $bairroFiltro,
        $estadoFiltro,
        $estadoFiltro,
        $serieFiltro,
        $serieFiltro,
        $modeloFiltro,
        $modeloFiltro,
        $areaFiltro,
        $areaFiltro,
        $paginasDesdePreventivaFiltro,
        $paginasDesdePreventivaFiltro
    ]);
} elseif ($tipo === 'revisao') {
    $sql = "SELECT
            E.TB02054_NUMSERIE AS Serie,
            E.TB02054_PAT AS Patrimonio,
            P.TB01010_NOME AS Modelo,
            S.TB01096_NOME AS Situacao,
            OS.TB02115_CODIGO AS OSAberta,
            OS.TB02115_DTCAD AS DataOS,
            T.TB01024_NOME AS Tecnico,
            ST.TB01073_NOME AS StatusOS
        FROM TB02054 E
        LEFT JOIN TB01010 P ON P.TB01010_CODIGO = E.TB02054_PRODUTO
        LEFT JOIN TB01096 S ON S.TB01096_CODIGO = E.TB02054_STATUS
        OUTER APPLY (
            SELECT TOP 1
                O.TB02115_CODIGO,
                O.TB02115_DTCAD,
                O.TB02115_CODTEC,
                O.TB02115_STATUS
            FROM TB02115 O
            WHERE O.TB02115_NUMSERIE = E.TB02054_NUMSERIE
                AND O.TB02115_PREVENTIVA = 'I'
                AND O.TB02115_DTFECHA IS NULL
            ORDER BY O.TB02115_DTCAD DESC
        ) OS
        LEFT JOIN TB01024 T ON T.TB01024_CODIGO = OS.TB02115_CODTEC
        LEFT JOIN TB01073 ST ON ST.TB01073_CODIGO = OS.TB02115_STATUS
        WHERE E.TB02054_PRODUTO = ?
            AND (? = '' OR E.TB02054_STATUS = ? OR S.TB01096_NOME LIKE '%' + ? + '%')
            AND (? = '' OR E.TB02054_NUMSERIE LIKE '%' + ? + '%')
        ORDER BY E.TB02054_NUMSERIE";
    $stmt = sqlsrv_query($conn, $sql, [
        $grupo,
        $situacaoFiltro,
        $situacaoFiltro,
        $situacaoFiltro,
        $serieFiltro,
        $serieFiltro
    ]);
} else {
    echo '<div class="alert alert-danger">Tipo de OS inválido.</div>';
    exit;
}

function h($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dataBr($valor)
{
    return $valor instanceof DateTime ? $valor->format('d/m/Y') : '';
}

function numero($valor)
{
    return $valor === null || $valor === '' ? '0' : h($valor);
}

if ($stmt === false) {
    echo '<div class="alert alert-danger">' . htmlspecialchars(exibirErroSql('Consulta de séries ' . $tipo), ENT_QUOTES, 'UTF-8') . '</div>';
    exit;
}

echo '<div class="p-3 bg-white border rounded shadow-sm">';
echo '<div class="d-flex justify-content-end mb-2 px-3"><div class="form-check">';
echo '<input class="form-check-input" type="checkbox" onchange="toggleAll(this, \'' . h($grupo) . '\')" checked>';
echo '<label class="form-check-label fw-bold text-primary ms-2">Marcar/Desmarcar Todos</label></div></div>';
echo '<ul class="list-group list-group-flush">';
$temDados = false;

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $temDados = true;
    $aberta = $tipo === 'preventiva'
        ? (!empty($row['UltimaPreventiva']) && $row['FechamentoPreventiva'] === null)
        : !empty($row['OSAberta']);
    echo '<li class="list-group-item py-3 mb-2 rounded border-top ' . ($aberta ? 'bg-light border-danger border-start' : 'border-primary border-start') . '"><div class="row align-items-center">';
    echo '<div class="col-md-2 border-end"><h6 class="mb-1 text-primary fw-bold">Série: ' . h($row['Serie']) . '</h6><p class="mb-0 text-muted small"><strong>Modelo:</strong> ' . h($row['Modelo']) . '</p><p class="mb-0 text-muted small"><strong>PAT:</strong> ' . h($row['Patrimonio']) . '</p></div>';

    if ($tipo === 'preventiva') {
        echo '<div class="col-md-2 border-end"><p class="mb-1 fw-semibold small text-dark">Localização</p><p class="mb-0 text-muted small">' . h($row['Cidade']) . ' - ' . h($row['Estado']) . '</p><p class="mb-0 text-muted small">' . h($row['Bairro']) . '</p></div>';
        echo '<div class="col-md-2 border-end"><p class="mb-1 fw-semibold small text-dark">Última leitura - ' . dataBr($row['DataLeitura']) . '</p><div class="d-flex flex-wrap gap-1"><span class="badge bg-primary">A4: ' . numero($row['A4Mono']) . '</span><span class="badge bg-primary">A4C: ' . numero($row['A4Color']) . '</span><span class="badge bg-primary">DG: ' . numero($row['Scanner']) . '</span><span class="badge bg-primary">A3: ' . numero($row['A3Mono']) . '</span><span class="badge bg-primary">A3C: ' . numero($row['A3Color']) . '</span></div></div>';
        echo '<div class="col-md-2 border-end"><p class="mb-1 fw-semibold small text-dark">Última preventiva</p><p class="mb-0 text-muted small">OS: ' . h($row['UltimaPreventiva']) . '</p><p class="mb-0 text-muted small">Data: ' . dataBr($row['DataPreventiva']) . '</p><p class="mb-0 text-muted small">Dias: ' . h($row['DiasPreventiva']) . '</p><p class="mb-0 text-muted small">Páginas: ' . numero($row['PaginasDesdePreventiva']) . '</p></div>';
        echo '<div class="col-md-2 border-end"><p class="mb-1 fw-semibold small text-dark">Corretiva/instalação</p><p class="mb-0 text-muted small">OS: ' . h($row['UltimaCorretiva']) . '</p><p class="mb-0 text-muted small">Data: ' . dataBr($row['DataIntervencao']) . '</p><p class="mb-0 text-muted small">Contador: ' . numero($row['Contador']) . '</p></div>';
    } else {
        echo '<div class="col-md-5 border-end"><p class="mb-1 fw-semibold small text-dark">Situação na TB02054</p><p class="mb-0 text-muted">' . h($row['Situacao']) . '</p></div>';
        echo '<div class="col-md-3 border-end"><p class="mb-1 fw-semibold small text-dark">OS de revisão</p><p class="mb-0 text-muted small">OS: ' . h($row['OSAberta']) . '</p><p class="mb-0 text-muted small">Técnico: ' . h($row['Tecnico']) . '</p><p class="mb-0 text-muted small">Status: ' . h($row['StatusOS']) . '</p></div>';
    }

    echo '<div class="col-md-2 text-center">';
    if ($aberta) {
        $codigo = $tipo === 'preventiva' ? $row['UltimaPreventiva'] : $row['OSAberta'];
        echo '<button class="btn btn-danger disabled w-100 rounded-pill" style="font-size:.75rem">POSSUI OS ' . h($codigo) . '</button>';
    } else {
        echo '<button class="btn btn-sm w-100 rounded-pill shadow-sm fw-bold mb-3" style="font-size:.72rem" onclick="gerarOS(\'' . h($grupo) . '\', \'' . h($row['Serie']) . '\', event)">ABRIR OS INDIVIDUAL</button>';
        echo '<div class="form-check d-flex justify-content-center gap-2">';
        echo '<input class="form-check-input check-serie" type="checkbox" value="' . h($row['Serie']) . '" data-contrato="' . h($grupo) . '" checked style="transform:scale(1.25)">';
        echo '<label class="form-check-label text-muted small">Selecionar</label>';
        echo '</div>';
    }
    echo '</div></div></li>';
}

echo '</ul>';
if (!$temDados) {
    echo '<div class="text-center text-muted py-4">Nenhum equipamento encontrado.</div>';
} else {
    echo '<div class="text-end mt-3"><button class="btn btn-sm px-4 rounded-pill shadow-sm" onclick="gerarOS(\'' . h($grupo) . '\', \'\', event)">ABRIR OS SELECIONADAS</button></div>';
}
echo '</div>';
