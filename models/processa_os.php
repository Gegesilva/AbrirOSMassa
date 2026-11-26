<?php
session_start();
include '../conexaoSQL.php';
include 'gerarOS.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['login']) || !isset($_SESSION['password'])) {
    echo 'Sessão expirada.';
    exit;
}
$stmtUsuario = sqlsrv_query($conn, 'SELECT 1 AS Autorizado FROM TB01066 WHERE TB01066_USUARIO=? AND TB01066_SENHA=?', [$_SESSION['login'], $_SESSION['password']]);
if ($stmtUsuario === false) {
    echo exibirErroSql('Validação da sessão');
    exit;
}
if (!sqlsrv_fetch_array($stmtUsuario, SQLSRV_FETCH_ASSOC)) {
    echo 'Sessão inválida.';
    exit;
}
session_write_close();
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$series = isset($_POST['series']) ? json_decode($_POST['series'], true) : [];
$mes = isset($_POST['mes']) ? trim($_POST['mes']) : null;
if (!in_array($tipo, ['afericao', 'preventiva', 'revisao'], true)) {
    echo 'Tipo de OS inválido.';
    exit;
}
if (!is_array($series)) {
    $series = [];
}
$series = array_values(array_unique(array_filter(array_map('trim', $series))));
$contrato = isset($_POST['contrato']) ? trim($_POST['contrato']) : '';
$cidadeFiltro = isset($_POST['cidadeFiltro']) ? trim($_POST['cidadeFiltro']) : '';
$patFiltro = isset($_POST['patFiltro']) ? trim($_POST['patFiltro']) : '';
$bairroFiltro = isset($_POST['bairroFiltro']) ? trim($_POST['bairroFiltro']) : '';
$estadoFiltro = isset($_POST['estadoFiltro']) ? trim($_POST['estadoFiltro']) : '';
$areaFiltro = isset($_POST['areaFiltro']) ? trim($_POST['areaFiltro']) : '';
$serieFiltro = isset($_POST['serieFiltro']) ? trim($_POST['serieFiltro']) : '';
$modeloFiltro = isset($_POST['modeloFiltro']) ? trim($_POST['modeloFiltro']) : '';
$situacaoFiltro = isset($_POST['situacaoFiltro']) ? trim($_POST['situacaoFiltro']) : '';
$paginasFiltro = isset($_POST['paginasDesdePreventivaFiltro']) ? trim($_POST['paginasDesdePreventivaFiltro']) : '';

if ($paginasFiltro !== '' && !ctype_digit($paginasFiltro)) {
    $paginasFiltro = '';
}

if (!$series && $contrato !== '') {
    if ($tipo === 'revisao') {
        $condicoes = '';
        $paramsSeries = [$contrato];

        if ($situacaoFiltro !== '') {
            $condicoes .= " AND (E.TB02054_STATUS = ? OR ST.TB01096_NOME LIKE '%' + ? + '%')";
            array_push($paramsSeries, $situacaoFiltro, $situacaoFiltro);
        }
        if ($modeloFiltro !== '') {
            $condicoes .= " AND (E.TB02054_PRODUTO = ? OR PR.TB01010_NOME LIKE '%' + ? + '%')";
            array_push($paramsSeries, $modeloFiltro, $modeloFiltro);
        }
        if ($serieFiltro !== '') {
            $condicoes .= " AND E.TB02054_NUMSERIE LIKE '%' + ? + '%'";
            $paramsSeries[] = $serieFiltro;
        }

        $sqlSeries = "SELECT E.TB02054_NUMSERIE AS Serie
            FROM TB02054 E
            LEFT JOIN TB01096 ST ON ST.TB01096_CODIGO = E.TB02054_STATUS
            LEFT JOIN TB01010 PR ON PR.TB01010_CODIGO = E.TB02054_PRODUTO
            WHERE E.TB02054_PRODUTO = ?
                $condicoes
                AND NOT EXISTS (
                    SELECT 1
                    FROM TB02115 O
                    WHERE O.TB02115_NUMSERIE = E.TB02054_NUMSERIE
                        AND O.TB02115_PREVENTIVA = 'I'
                        AND O.TB02115_DTFECHA IS NULL
                )";
        $stmtSeries = sqlsrv_query($conn, $sqlSeries, $paramsSeries);
    } else {
        $codigoTipo = $tipo === 'afericao' ? 'A' : 'S';
        $pendente = $tipo === 'afericao' ? "AND B.Possui = 'Não'" : '';
        $condicoes = '';
        $juncaoPaginas = '';
        $paramsSeries = [$contrato, $mes];

        if ($cidadeFiltro !== '') {
            $condicoes .= " AND B.Cidade LIKE '%' + ? + '%'";
            $paramsSeries[] = $cidadeFiltro;
        }
        if ($patFiltro !== '') {
            $condicoes .= " AND (B.Pat LIKE '%' + ? + '%' OR B.Serie LIKE '%' + ? + '%')";
            array_push($paramsSeries, $patFiltro, $patFiltro);
        }
        if ($bairroFiltro !== '') {
            $condicoes .= " AND B.Bairro LIKE '%' + ? + '%'";
            $paramsSeries[] = $bairroFiltro;
        }
        if ($estadoFiltro !== '') {
            $condicoes .= " AND B.Estado LIKE '%' + ? + '%'";
            $paramsSeries[] = $estadoFiltro;
        }
        if ($serieFiltro !== '') {
            $condicoes .= " AND B.Serie LIKE '%' + ? + '%'";
            $paramsSeries[] = $serieFiltro;
        }
        if ($modeloFiltro !== '') {
            $condicoes .= " AND B.Equipamento LIKE '%' + ? + '%'";
            $paramsSeries[] = $modeloFiltro;
        }
        if ($areaFiltro !== '') {
            $condicoes .= " AND EXISTS (
                SELECT 1 FROM TB02112 EQ
                WHERE EQ.TB02112_NUMSERIE = B.Serie
                    AND EQ.TB02112_TIPOINTERV LIKE '%' + ? + '%'
            )";
            $paramsSeries[] = $areaFiltro;
        }
        if ($tipo === 'preventiva' && $paginasFiltro !== '') {
            $juncaoPaginas = "OUTER APPLY (
                    SELECT TOP 1 P.TB02115_CONTTOTAL AS ContadorPreventiva
                    FROM TB02115 P
                    WHERE P.TB02115_NUMSERIE = B.Serie
                        AND P.TB02115_PREVENTIVA = 'S'
                    ORDER BY P.TB02115_DTCAD DESC, P.TB02115_CODIGO DESC
                ) PREV
                OUTER APPLY (
                    SELECT TOP 1
                        ISNULL(R.TB02117_MEDATUAL, 0) + ISNULL(R.TB02117_MEDATUALC, 0)
                        + ISNULL(R.TB02117_MEDATUALDG, 0) + ISNULL(R.TB02117_MEDATUALGF, 0)
                        + ISNULL(R.TB02117_MEDATUALGFC, 0) AS Contador
                    FROM TB02117 R
                    WHERE R.TB02117_NUMSERIE = B.Serie AND R.TB02117_CODIGO = B.Contrato
                    ORDER BY R.TB02117_DATA DESC
                ) L2117
                OUTER APPLY (
                    SELECT TOP 1
                        ISNULL(L.TB02254_A4MONO, 0) + ISNULL(L.TB02254_A4COLOR, 0)
                        + ISNULL(L.TB02254_SCANNER, 0) + ISNULL(L.TB02254_A3MONO, 0)
                        + ISNULL(L.TB02254_A3COLOR, 0) AS Contador
                    FROM TB02254 L
                    WHERE L.TB02254_SERIALNUMBER = B.Serie AND L.TB02254_CONTRACT = B.Contrato
                    ORDER BY L.TB02254_DATEREADING DESC
                ) L2254";
            $condicoes .= " AND PREV.ContadorPreventiva IS NOT NULL
                AND CASE WHEN ISNULL(L2254.Contador, 0) >= ISNULL(L2117.Contador, 0)
                    THEN ISNULL(L2254.Contador, 0) ELSE ISNULL(L2117.Contador, 0) END
                    - PREV.ContadorPreventiva >= ?";
            $paramsSeries[] = (int) $paginasFiltro;
        }

        $sqlSeries = "SELECT B.Serie
            FROM BM_CONTRLEITURA_ABRIROS B
            $juncaoPaginas
            WHERE B.Contrato = ?
                AND B.MesFechamento = ?
                AND B.situacao <> 'I'
                AND B.libleitura = 'N'
                $pendente
                $condicoes
                AND NOT EXISTS (
                    SELECT 1
                    FROM TB02115 O
                    WHERE O.TB02115_NUMSERIE = B.Serie
                        AND O.TB02115_PREVENTIVA = ?
                        AND O.TB02115_DTFECHA IS NULL
                )";
        $paramsSeries[] = $codigoTipo;
        $stmtSeries = sqlsrv_query($conn, $sqlSeries, $paramsSeries, ['QueryTimeout' => 60]);
    }

    if ($stmtSeries !== false) {
        while ($rowSerie = sqlsrv_fetch_array($stmtSeries, SQLSRV_FETCH_ASSOC)) {
            $series[] = $rowSerie['Serie'];
        }
    } else {
        echo exibirErroSql('Consulta de equipamentos para abertura em massa');
        exit;
    }
}

if (!$series) {
    echo 'Nenhum equipamento pendente encontrado.';
    exit;
}
$tecnico = isset($_POST['tecnico']) && $_POST['tecnico'] !== '' ? trim($_POST['tecnico']) : '0000';
echo gravaOS($conn, $tipo, $series, $mes, $tecnico);
