<?php
include_once 'models/filtrosIni.php';

$tipoOS = isset($_POST['tipo']) && in_array($_POST['tipo'], ['afericao', 'preventiva', 'revisao'], true)
    ? $_POST['tipo']
    : 'afericao';
$situacaoFiltro = isset($_POST['situacaoFiltro']) ? trim($_POST['situacaoFiltro']) : '';
$equipamentos = [];
$mensagemLista = 'Informe os filtros obrigatórios para consultar os equipamentos.';

$consultaSolicitada = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($tipoOS === 'preventiva') {
    $consultaSolicitada = $consultaSolicitada && ($modeloFiltro !== '' || $contratoFiltro !== '');
    $mensagemLista = 'Informe o modelo ou o contrato para consultar.';
} elseif ($tipoOS === 'revisao') {
    $consultaSolicitada = $consultaSolicitada && ($modeloFiltro !== '' || $situacaoFiltro !== '');
    $mensagemLista = 'Informe o modelo ou a situação para consultar.';
} else {
    $consultaSolicitada = $consultaSolicitada && ($contratoFiltro !== '' || $situacaoFiltro !== '');
    $mensagemLista = 'Informe o contrato ou a situação para consultar.';
}

if ($consultaSolicitada && $tipoOS !== 'revisao') {
    $params = [];
    $where = "B.situacao <> 'I' AND B.libleitura = 'N'";
    if ($contratoFiltro !== '') { $where .= " AND B.Contrato LIKE '%' + ? + '%'"; $params[] = $contratoFiltro; }
    if ($cidadeFiltro !== '') { $where .= " AND B.Cidade LIKE '%' + ? + '%'"; $params[] = $cidadeFiltro; }
    if ($patFiltro !== '') { $where .= " AND (B.Pat LIKE '%' + ? + '%' OR B.Serie LIKE '%' + ? + '%')"; array_push($params, $patFiltro, $patFiltro); }
    if ($bairroFiltro !== '') { $where .= " AND B.Bairro LIKE '%' + ? + '%'"; $params[] = $bairroFiltro; }
    if ($estadoFiltro !== '') { $where .= " AND B.Estado LIKE '%' + ? + '%'"; $params[] = $estadoFiltro; }
    if ($serieFiltro !== '') { $where .= " AND B.Serie LIKE '%' + ? + '%'"; $params[] = $serieFiltro; }
    if ($modeloFiltro !== '') { $where .= " AND B.Equipamento LIKE '%' + ? + '%'"; $params[] = $modeloFiltro; }
    if ($areaFiltro !== '') { $where .= " AND EQ.TB02112_TIPOINTERV LIKE '%' + ? + '%'"; $params[] = $areaFiltro; }
    if ($situacaoFiltro !== '') { $where .= " AND EQ.TB02112_SITUACAO LIKE '%' + ? + '%'"; $params[] = $situacaoFiltro; }

    if ($tipoOS === 'afericao') {
        $sqlCompetencia = "SELECT TOP 1 B.MesFechamento
            FROM BM_CONTRLEITURA_ABRIROS B
            INNER JOIN TB02112 EQ ON EQ.TB02112_NUMSERIE=B.Serie AND EQ.TB02112_CODIGO=B.Contrato
            WHERE $where
                AND TRY_CONVERT(date, '01/' + B.MesFechamento, 103)
                    <= TRY_CONVERT(date, '01/' + ?, 103)
            ORDER BY TRY_CONVERT(date, '01/' + B.MesFechamento, 103) DESC";
        $stmtCompetencia = sqlsrv_query($conn, $sqlCompetencia, array_merge($params, [$mesFiltro]), ['QueryTimeout' => 30]);
        if ($stmtCompetencia === false) die('<pre>'.htmlspecialchars(exibirErroSql('Competência da aferição'), ENT_QUOTES, 'UTF-8').'</pre>');
        $rowCompetencia = sqlsrv_fetch_array($stmtCompetencia, SQLSRV_FETCH_ASSOC);
        if ($rowCompetencia) $mesFiltro = $rowCompetencia['MesFechamento'];
        $where .= " AND B.MesFechamento = ?";
        $params[] = $mesFiltro;
    }

    $whereTotais = $where;
    $paramsTotais = $params;
    if ($tipoOS === 'afericao') $where .= " AND B.Possui = 'Não'";

    $codigoTipo = $tipoOS === 'afericao' ? 'A' : 'S';
    $sql = "WITH BASE AS (
            SELECT B.*,
                ROW_NUMBER() OVER (PARTITION BY B.Serie ORDER BY TRY_CONVERT(date, '01/' + B.MesFechamento, 103) DESC) AS Ordem
            FROM BM_CONTRLEITURA_ABRIROS B
            INNER JOIN TB02112 EQ ON EQ.TB02112_NUMSERIE = B.Serie AND EQ.TB02112_CODIGO = B.Contrato
            WHERE $where
        )
        SELECT TOP (1000)
            B.Serie, B.Contrato, B.Cliente, B.Pat AS Patrimonio, B.Equipamento AS Modelo,
            B.situacao AS Situacao, B.Cidade, B.Estado, B.Bairro, B.MesFechamento,
            ORB.DataLeitura AS OrbixData, ORB.A4Mono AS OrbixA4, ORB.A4Color AS OrbixA4C,
            ORB.Scanner AS OrbixDG, ORB.A3Mono AS OrbixA3, ORB.A3Color AS OrbixA3C,
            LEI.DataLeitura, LEI.A4Mono AS LeituraA4, LEI.A4Color AS LeituraA4C,
            LEI.Scanner AS LeituraDG, LEI.A3Mono AS LeituraA3, LEI.A3Color AS LeituraA3C,
            CASE WHEN ORB.DataLeitura IS NOT NULL AND (LEI.DataLeitura IS NULL OR ORB.DataLeitura>=LEI.DataLeitura) THEN ORB.DataLeitura ELSE LEI.DataLeitura END AS UltimaData,
            CASE WHEN ORB.DataLeitura IS NOT NULL AND (LEI.DataLeitura IS NULL OR ORB.DataLeitura>=LEI.DataLeitura) THEN ORB.A4Mono ELSE LEI.A4Mono END AS UltimaA4,
            CASE WHEN ORB.DataLeitura IS NOT NULL AND (LEI.DataLeitura IS NULL OR ORB.DataLeitura>=LEI.DataLeitura) THEN ORB.A4Color ELSE LEI.A4Color END AS UltimaA4C,
            CASE WHEN ORB.DataLeitura IS NOT NULL AND (LEI.DataLeitura IS NULL OR ORB.DataLeitura>=LEI.DataLeitura) THEN ORB.Scanner ELSE LEI.Scanner END AS UltimaDG,
            CASE WHEN ORB.DataLeitura IS NOT NULL AND (LEI.DataLeitura IS NULL OR ORB.DataLeitura>=LEI.DataLeitura) THEN ORB.A3Mono ELSE LEI.A3Mono END AS UltimaA3,
            CASE WHEN ORB.DataLeitura IS NOT NULL AND (LEI.DataLeitura IS NULL OR ORB.DataLeitura>=LEI.DataLeitura) THEN ORB.A3Color ELSE LEI.A3Color END AS UltimaA3C,
            OS.TB02115_CODIGO AS OsCodigo, OS.TB02115_DTCAD AS OsData,
            OS.TB02115_DTFECHA AS OsFechamento, OS.TB02115_CONTPB AS OsA4,
            OS.TB02115_CONTCOLOR AS OsA4C, TEC.TB01024_NOME AS Tecnico,
            STA.TB01073_NOME AS StatusOS,
            COR.TB02115_CODIGO AS CorretivaCodigo,
            COR.DataIntervencao AS CorretivaData,
            COR.Contador AS CorretivaContador,
            COR.A4 AS CorretivaA4,
            COR.A4C AS CorretivaA4C,
            COR.DG AS CorretivaDG,
            COR.A3 AS CorretivaA3,
            COR.A3C AS CorretivaA3C,
            DATEDIFF(DAY, OS.TB02115_DTCAD, GETDATE()) AS DiasPreventiva,
            CASE WHEN OS.TB02115_CODIGO IS NULL THEN NULL ELSE
                (CASE WHEN ISNULL(ORB.Total,0) >= ISNULL(LEI.Total,0) THEN ISNULL(ORB.Total,0) ELSE ISNULL(LEI.Total,0) END)
                - ISNULL(OS.TB02115_CONTTOTAL,0) END AS PaginasDesdePreventiva
        FROM BASE B
        OUTER APPLY (
            SELECT TOP 1 O.TB02254_DATEREADING AS DataLeitura,
                O.TB02254_A4MONO AS A4Mono, O.TB02254_A4COLOR AS A4Color,
                O.TB02254_SCANNER AS Scanner, O.TB02254_A3MONO AS A3Mono, O.TB02254_A3COLOR AS A3Color,
                ISNULL(O.TB02254_A4MONO,0)+ISNULL(O.TB02254_A4COLOR,0)+ISNULL(O.TB02254_SCANNER,0)+ISNULL(O.TB02254_A3MONO,0)+ISNULL(O.TB02254_A3COLOR,0) AS Total
            FROM TB02254 O WHERE O.TB02254_SERIALNUMBER=B.Serie AND O.TB02254_CONTRACT=B.Contrato
            ORDER BY O.TB02254_DATEREADING DESC
        ) ORB
        OUTER APPLY (
            SELECT TOP 1 R.TB02117_DATA AS DataLeitura,
                R.TB02117_MEDATUAL AS A4Mono, R.TB02117_MEDATUALC AS A4Color,
                R.TB02117_MEDATUALDG AS Scanner, R.TB02117_MEDATUALGF AS A3Mono, R.TB02117_MEDATUALGFC AS A3Color,
                ISNULL(R.TB02117_MEDATUAL,0)+ISNULL(R.TB02117_MEDATUALC,0)+ISNULL(R.TB02117_MEDATUALDG,0)+ISNULL(R.TB02117_MEDATUALGF,0)+ISNULL(R.TB02117_MEDATUALGFC,0) AS Total
            FROM TB02117 R WHERE R.TB02117_NUMSERIE=B.Serie AND R.TB02117_CODIGO=B.Contrato
            ORDER BY R.TB02117_DATA DESC
        ) LEI
        OUTER APPLY (
            SELECT TOP 1 P.TB02115_CODIGO, P.TB02115_DTCAD, P.TB02115_DTFECHA,
                P.TB02115_CONTPB, P.TB02115_CONTCOLOR, P.TB02115_CONTTOTAL,
                P.TB02115_CODTEC, P.TB02115_STATUS
            FROM TB02115 P WHERE P.TB02115_NUMSERIE=B.Serie AND P.TB02115_PREVENTIVA=?
            ORDER BY P.TB02115_DTCAD DESC
        ) OS
        OUTER APPLY (
            SELECT TOP 1 C.TB02115_CODIGO, I.TB02122_DATA AS DataIntervencao,
                I.TB02122_CONTADOR AS Contador,
                I.TB02122_CONTADOR AS A4,
                I.TB02122_CONTADORC AS A4C,
                I.TB02122_CONTADORGF AS A3,
                I.TB02122_CONTADORGFC AS A3C,
                I.TB02122_CONTADORDG AS DG
            FROM TB02115 C
            LEFT JOIN TB02122 I ON I.TB02122_NUMOS=C.TB02115_CODIGO
            WHERE C.TB02115_NUMSERIE=B.Serie AND C.TB02115_PREVENTIVA IN ('I','N')
            ORDER BY COALESCE(I.TB02122_DATA,C.TB02115_DTCAD) DESC
        ) COR
        LEFT JOIN TB01024 TEC ON TEC.TB01024_CODIGO=OS.TB02115_CODTEC
        LEFT JOIN TB01073 STA ON STA.TB01073_CODIGO=OS.TB02115_STATUS
        WHERE B.Ordem=1
            AND (?='' OR OS.TB02115_CONTTOTAL IS NOT NULL AND
                (CASE WHEN ISNULL(ORB.Total,0)>=ISNULL(LEI.Total,0) THEN ISNULL(ORB.Total,0) ELSE ISNULL(LEI.Total,0) END)-OS.TB02115_CONTTOTAL>=?)
        ORDER BY B.Contrato, B.Serie OPTION (RECOMPILE)";
    array_push($params, $codigoTipo, $tipoOS === 'preventiva' ? $paginasDesdePreventivaFiltro : '', $tipoOS === 'preventiva' && $paginasDesdePreventivaFiltro !== '' ? (int)$paginasDesdePreventivaFiltro : 0);
} elseif ($consultaSolicitada) {
    $sql = "SELECT TOP (1000)
            E.TB02054_NUMSERIE AS Serie, '' AS Contrato, '' AS Cliente,
            E.TB02054_PAT AS Patrimonio, P.TB01010_NOME AS Modelo, S.TB01096_NOME AS Situacao,
            '' AS Cidade, '' AS Estado, '' AS Bairro, '' AS MesFechamento,
            OS.TB02115_CODIGO AS OsCodigo, OS.TB02115_DTCAD AS OsData,
            OS.TB02115_DTFECHA AS OsFechamento, T.TB01024_NOME AS Tecnico, ST.TB01073_NOME AS StatusOS
        FROM TB02054 E
        LEFT JOIN TB01010 P ON P.TB01010_CODIGO=E.TB02054_PRODUTO
        LEFT JOIN TB01096 S ON S.TB01096_CODIGO=E.TB02054_STATUS
        OUTER APPLY (
            SELECT TOP 1 O.TB02115_CODIGO, O.TB02115_DTCAD, O.TB02115_DTFECHA, O.TB02115_CODTEC, O.TB02115_STATUS
            FROM TB02115 O WHERE O.TB02115_NUMSERIE=E.TB02054_NUMSERIE AND O.TB02115_PREVENTIVA='I' AND O.TB02115_DTFECHA IS NULL
            ORDER BY O.TB02115_DTCAD DESC
        ) OS
        LEFT JOIN TB01024 T ON T.TB01024_CODIGO=OS.TB02115_CODTEC
        LEFT JOIN TB01073 ST ON ST.TB01073_CODIGO=OS.TB02115_STATUS
        WHERE E.TB02054_NUMSERIE IS NOT NULL
            AND (?='' OR E.TB02054_STATUS=? OR S.TB01096_NOME LIKE '%'+?+'%')
            AND (?='' OR E.TB02054_PRODUTO=? OR P.TB01010_NOME LIKE '%'+?+'%')
            AND (?='' OR E.TB02054_NUMSERIE LIKE '%'+?+'%')
        ORDER BY P.TB01010_NOME, E.TB02054_NUMSERIE OPTION (RECOMPILE)";
    $params = [$situacaoFiltro,$situacaoFiltro,$situacaoFiltro,$modeloFiltro,$modeloFiltro,$modeloFiltro,$serieFiltro,$serieFiltro];
}

if ($consultaSolicitada) {
    $stmtLista = sqlsrv_query($conn, $sql, $params, ['QueryTimeout' => 60]);
    if ($stmtLista === false) die('<pre>'.htmlspecialchars(exibirErroSql('Lista de equipamentos de '.$tipoOS), ENT_QUOTES, 'UTF-8').'</pre>');
    while ($row = sqlsrv_fetch_array($stmtLista, SQLSRV_FETCH_ASSOC)) $equipamentos[] = $row;
    $mensagemLista = 'Nenhum equipamento encontrado para os filtros informados.';
}

$totais = ['equipamentos' => count($equipamentos), 'aferidos' => 0, 'nao_aferidos' => count($equipamentos), 'os_abertas' => 0];
if ($consultaSolicitada && $tipoOS === 'afericao') {
    $sqlTotais = "SELECT COUNT(*) AS Equipamentos,
            SUM(CASE WHEN B.Possui='Sim' THEN 1 ELSE 0 END) AS Aferidos,
            SUM(CASE WHEN B.Possui='Não' THEN 1 ELSE 0 END) AS NaoAferidos,
            SUM(CASE WHEN B.Possui='Não' AND OA.Serie IS NOT NULL THEN 1 ELSE 0 END) AS OsAbertas
        FROM BM_CONTRLEITURA_ABRIROS B
        INNER JOIN TB02112 EQ ON EQ.TB02112_NUMSERIE=B.Serie AND EQ.TB02112_CODIGO=B.Contrato
        LEFT JOIN (
            SELECT DISTINCT TB02115_NUMSERIE AS Serie
            FROM TB02115
            WHERE TB02115_PREVENTIVA='A' AND TB02115_DTFECHA IS NULL
        ) OA ON OA.Serie=B.Serie
        WHERE $whereTotais";
    $stmtTotais = sqlsrv_query($conn, $sqlTotais, $paramsTotais, ['QueryTimeout' => 30]);
    if ($stmtTotais === false) die('<pre>'.htmlspecialchars(exibirErroSql('Totais de aferição'), ENT_QUOTES, 'UTF-8').'</pre>');
    $rowTotais = sqlsrv_fetch_array($stmtTotais, SQLSRV_FETCH_ASSOC);
    if ($rowTotais) $totais = [
        'equipamentos' => (int)$rowTotais['Equipamentos'],
        'aferidos' => (int)$rowTotais['Aferidos'],
        'nao_aferidos' => (int)$rowTotais['NaoAferidos'],
        'os_abertas' => (int)$rowTotais['OsAbertas']
    ];
}
