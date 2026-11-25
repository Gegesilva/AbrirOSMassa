<?php
include_once 'models/filtrosIni.php';

$tipoOS = isset($_GET['tipo']) && in_array($_GET['tipo'], ['afericao', 'preventiva', 'revisao'], true)
    ? $_GET['tipo']
    : 'afericao';

$resumos = [];
$totais = [
    'equipamentos' => 0,
    'aferidos' => 0,
    'nao_aferidos' => 0,
    'os_abertas' => 0
];

if ($tipoOS !== 'revisao') {
    $somentePendentes = $tipoOS === 'afericao' ? "AND Possui = 'Não'" : '';
    $origemCompetencia = $tipoOS === 'afericao'
        ? "BM_CONTRLEITURA_ABRIROS B
            INNER JOIN TB02112 EQ ON EQ.TB02112_NUMSERIE = B.Serie
                AND EQ.TB02112_CODIGO = B.Contrato"
        : 'BM_CONTRLEITURA_ABRIROS B';
    $regraEquipamento = $tipoOS === 'afericao'
        ? "EQ.TB02112_SITUACAO <> 'I' AND EQ.TB02112_LIBLEITURA = 'N'"
        : "B.situacao <> 'I' AND B.libleitura = 'N'";
    $dadosCorrespondentes = '';
    $baseCompetencia = "FROM $origemCompetencia
        WHERE $regraEquipamento
            $somentePendentes
            $dadosCorrespondentes
            $condicaoContrato
            $condicaoCidade
            $condicaoPatFiltro
            $condicaoNovosFiltros";
    if ($tipoOS === 'preventiva') {
        $consultarCompetenciaPreventiva = function ($competencia) use ($conn, $baseCompetencia) {
            $dataCompetencia = DateTime::createFromFormat('!m/Y', $competencia);

            if (!$dataCompetencia || $dataCompetencia->format('m/Y') !== $competencia) {
                return false;
            }

            $inicio = $dataCompetencia->format('Y-m-d');
            $sqlCompetenciaPreventiva = "WITH LEITURAS_COMPETENCIA AS (
                    SELECT
                        R.TB02117_NUMSERIE AS Serie,
                        R.TB02117_CODIGO AS Contrato
                    FROM TB02117 R
                    WHERE R.TB02117_DATA >= CONVERT(date, ?, 23)
                        AND R.TB02117_DATA < DATEADD(month, 1, CONVERT(date, ?, 23))
                    UNION
                    SELECT
                        O.TB02254_SERIALNUMBER AS Serie,
                        O.TB02254_CONTRACT AS Contrato
                    FROM TB02254 O
                    WHERE O.TB02254_DATEREADING >= CONVERT(date, ?, 23)
                        AND O.TB02254_DATEREADING < DATEADD(month, 1, CONVERT(date, ?, 23))
                )
                SELECT TOP 1 MesFechamento
                $baseCompetencia
                    AND MesFechamento = ?
                    AND EXISTS (
                        SELECT 1
                        FROM LEITURAS_COMPETENCIA L
                        WHERE L.Serie = B.Serie
                            AND L.Contrato = B.Contrato
                    )
                OPTION (RECOMPILE)";
            $stmt = sqlsrv_query(
                $conn,
                $sqlCompetenciaPreventiva,
                [$inicio, $inicio, $inicio, $inicio, $competencia],
                ['QueryTimeout' => 20]
            );

            if ($stmt === false) {
                die('<pre>' . htmlspecialchars(exibirErroSql('Consulta da competência preventiva'), ENT_QUOTES, 'UTF-8') . '</pre>');
            }

            return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        };

        $rowCompetencia = $consultarCompetenciaPreventiva($mesFiltro);

        if (!$rowCompetencia) {
            $sqlCompetenciasAnteriores = "SELECT DISTINCT
                    MesFechamento,
                    TRY_CONVERT(date, '01/' + MesFechamento, 103) AS DataCompetencia
                $baseCompetencia
                    AND TRY_CONVERT(date, '01/' + MesFechamento, 103) < TRY_CONVERT(date, '01/' + ?, 103)
                ORDER BY DataCompetencia DESC
                OPTION (RECOMPILE)";
            $stmtCompetenciasAnteriores = sqlsrv_query(
                $conn,
                $sqlCompetenciasAnteriores,
                [$mesFiltro],
                ['QueryTimeout' => 20]
            );

            if ($stmtCompetenciasAnteriores === false) {
                die('<pre>' . htmlspecialchars(exibirErroSql('Retrocesso de competência preventiva'), ENT_QUOTES, 'UTF-8') . '</pre>');
            }

            while ($competenciaAnterior = sqlsrv_fetch_array($stmtCompetenciasAnteriores, SQLSRV_FETCH_ASSOC)) {
                $rowCompetencia = $consultarCompetenciaPreventiva($competenciaAnterior['MesFechamento']);

                if ($rowCompetencia) {
                    break;
                }
            }
        }
    } else {
        $sqlCompetenciaAtual = "SELECT TOP 1 MesFechamento
            $baseCompetencia
                AND MesFechamento = ?
            OPTION (RECOMPILE)";
        $stmtCompetencia = sqlsrv_query(
            $conn,
            $sqlCompetenciaAtual,
            [$mesFiltro],
            ['QueryTimeout' => 10]
        );
        if ($stmtCompetencia === false) {
            die('<pre>' . htmlspecialchars(exibirErroSql('Consulta da competência selecionada'), ENT_QUOTES, 'UTF-8') . '</pre>');
        }
        $rowCompetencia = $stmtCompetencia !== false
            ? sqlsrv_fetch_array($stmtCompetencia, SQLSRV_FETCH_ASSOC)
            : false;

        if (!$rowCompetencia) {
            $sqlCompetencia = "SELECT TOP 1 MesFechamento
                $baseCompetencia
                    AND TRY_CONVERT(date, '01/' + MesFechamento, 103) <= TRY_CONVERT(date, '01/' + ?, 103)
                ORDER BY TRY_CONVERT(date, '01/' + MesFechamento, 103) DESC
                OPTION (RECOMPILE)";
            $stmtCompetencia = sqlsrv_query(
                $conn,
                $sqlCompetencia,
                [$mesFiltro],
                ['QueryTimeout' => 15]
            );
            if ($stmtCompetencia === false) {
                die('<pre>' . htmlspecialchars(exibirErroSql('Retrocesso de competência'), ENT_QUOTES, 'UTF-8') . '</pre>');
            }
            $rowCompetencia = $stmtCompetencia !== false
                ? sqlsrv_fetch_array($stmtCompetencia, SQLSRV_FETCH_ASSOC)
                : false;
        }
    }

    if ($rowCompetencia) {
        $mesFiltro = $rowCompetencia['MesFechamento'];
    }
}

if ($tipoOS === 'afericao') {
    $sqlResumo = "WITH OSABERTAS AS (
            SELECT DISTINCT
                TB02115_NUMSERIE AS Serie
            FROM TB02115
            WHERE TB02115_PREVENTIVA = 'A'
                AND TB02115_DTFECHA IS NULL
        )
        SELECT
            B.Contrato,
            B.Cliente,
            COUNT(*) AS Equipamentos,
            SUM(CASE WHEN B.Possui = 'Sim' THEN 1 ELSE 0 END) AS EquipamentosAferidos,
            SUM(CASE WHEN B.Possui = 'Não' THEN 1 ELSE 0 END) AS EquipamentosNaoAferidos,
            SUM(CASE WHEN B.Possui = 'Não' AND O.Serie IS NOT NULL THEN 1 ELSE 0 END) AS TotalOSAberta
        FROM BM_CONTRLEITURA_ABRIROS B
        INNER JOIN TB02112 EQ ON EQ.TB02112_NUMSERIE = B.Serie
            AND EQ.TB02112_CODIGO = B.Contrato
        LEFT JOIN OSABERTAS O ON O.Serie = B.Serie
        WHERE EQ.TB02112_SITUACAO <> 'I'
            AND EQ.TB02112_LIBLEITURA = 'N'
            AND B.MesFechamento = ?
            $condicaoContrato
            $condicaoCidade
            $condicaoPatFiltro
            $condicaoNovosFiltros
        GROUP BY B.Contrato, B.Cliente
        HAVING SUM(CASE WHEN B.Possui = 'Não' THEN 1 ELSE 0 END) > 0
        ORDER BY B.Contrato
        OPTION (RECOMPILE)";
    $paramsResumo = [$mesFiltro];
} elseif ($tipoOS === 'preventiva') {
    $juncaoProducao = '';
    $condicaoProducao = '';
    $paramsResumo = [$mesFiltro];

    if ($paginasDesdePreventivaFiltro !== '') {
        $juncaoProducao = "OUTER APPLY (
                SELECT TOP 1
                    P.TB02115_CONTTOTAL AS ContadorPreventiva
                FROM TB02115 P
                WHERE P.TB02115_NUMSERIE = B.Serie
                    AND P.TB02115_PREVENTIVA = 'S'
                ORDER BY P.TB02115_DTCAD DESC
            ) PPREVENTIVA
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
            ) LEITURA2117
            OUTER APPLY (
                SELECT TOP 1
                    ISNULL(L.TB02254_A4MONO, 0)
                    + ISNULL(L.TB02254_A4COLOR, 0)
                    + ISNULL(L.TB02254_SCANNER, 0)
                    + ISNULL(L.TB02254_A3MONO, 0)
                    + ISNULL(L.TB02254_A3COLOR, 0) AS Contador2254
                FROM TB02254 L
                WHERE L.TB02254_SERIALNUMBER = B.Serie
                    AND L.TB02254_CONTRACT = B.Contrato
                ORDER BY L.TB02254_DATEREADING DESC
            ) LEITURA2254";
        $condicaoProducao = "AND PPREVENTIVA.ContadorPreventiva IS NOT NULL
            AND (
                CASE
                    WHEN ISNULL(LEITURA2254.Contador2254, 0) >= ISNULL(LEITURA2117.Contador2117, 0)
                        THEN ISNULL(LEITURA2254.Contador2254, 0)
                    ELSE ISNULL(LEITURA2117.Contador2117, 0)
                END
            ) - PPREVENTIVA.ContadorPreventiva >= ?";
        $paramsResumo[] = (int) $paginasDesdePreventivaFiltro;
    }

    $sqlResumo = "WITH OSABERTAS AS (
            SELECT DISTINCT
                TB02115_NUMSERIE AS Serie
            FROM TB02115
            WHERE TB02115_PREVENTIVA = 'S'
                AND TB02115_DTFECHA IS NULL
        )
        SELECT
            B.Contrato,
            B.Cliente,
            COUNT(*) AS Equipamentos,
            0 AS EquipamentosAferidos,
            SUM(CASE WHEN O.Serie IS NULL THEN 1 ELSE 0 END) AS EquipamentosNaoAferidos,
            SUM(CASE WHEN O.Serie IS NOT NULL THEN 1 ELSE 0 END) AS TotalOSAberta
        FROM BM_CONTRLEITURA_ABRIROS B
        $juncaoProducao
        LEFT JOIN OSABERTAS O ON O.Serie = B.Serie
        WHERE B.situacao <> 'I'
            AND B.libleitura = 'N'
            AND B.MesFechamento = ?
            $condicaoContrato
            $condicaoCidade
            $condicaoPatFiltro
            $condicaoNovosFiltros
            $condicaoProducao
        GROUP BY B.Contrato, B.Cliente
        ORDER BY B.Contrato
        OPTION (RECOMPILE)";
} else {
    $situacaoFiltro = isset($_GET['situacaoFiltro']) ? trim($_GET['situacaoFiltro']) : '';
    $modeloFiltro = isset($_GET['modeloFiltro']) ? trim($_GET['modeloFiltro']) : '';
    $serieFiltro = isset($_GET['serieFiltro']) ? trim($_GET['serieFiltro']) : '';
    $sqlResumo = "WITH OSABERTAS AS (
            SELECT DISTINCT
                TB02115_NUMSERIE AS Serie
            FROM TB02115
            WHERE TB02115_PREVENTIVA = 'E'
                AND TB02115_DTFECHA IS NULL
        )
        SELECT
            E.TB02054_PRODUTO AS Contrato,
            P.TB01010_NOME AS Cliente,
            COUNT(*) AS Equipamentos,
            0 AS EquipamentosAferidos,
            SUM(CASE WHEN O.Serie IS NULL THEN 1 ELSE 0 END) AS EquipamentosNaoAferidos,
            SUM(CASE WHEN O.Serie IS NOT NULL THEN 1 ELSE 0 END) AS TotalOSAberta
        FROM TB02054 E
        LEFT JOIN TB01010 P ON P.TB01010_CODIGO = E.TB02054_PRODUTO
        LEFT JOIN TB01096 S ON S.TB01096_CODIGO = E.TB02054_STATUS
        LEFT JOIN OSABERTAS O ON O.Serie = E.TB02054_NUMSERIE
        WHERE E.TB02054_NUMSERIE IS NOT NULL
            AND (? = '' OR E.TB02054_STATUS = ? OR S.TB01096_NOME LIKE '%' + ? + '%')
            AND (? = '' OR E.TB02054_PRODUTO = ? OR P.TB01010_NOME LIKE '%' + ? + '%')
            AND (? = '' OR E.TB02054_NUMSERIE LIKE '%' + ? + '%')
        GROUP BY E.TB02054_PRODUTO, P.TB01010_NOME
        ORDER BY P.TB01010_NOME
        OPTION (RECOMPILE)";
    $paramsResumo = [
        $situacaoFiltro,
        $situacaoFiltro,
        $situacaoFiltro,
        $modeloFiltro,
        $modeloFiltro,
        $modeloFiltro,
        $serieFiltro,
        $serieFiltro
    ];
}

$stmtResumo = sqlsrv_query(
    $conn,
    $sqlResumo,
    $paramsResumo,
    ['QueryTimeout' => 30]
);

if ($stmtResumo === false) {
    die('<pre>' . htmlspecialchars(exibirErroSql('Resumo de ' . $tipoOS), ENT_QUOTES, 'UTF-8') . '</pre>');
}

while ($row = sqlsrv_fetch_array($stmtResumo, SQLSRV_FETCH_ASSOC)) {
    $resumos[] = $row;
    $totais['equipamentos'] += (int) $row['Equipamentos'];
    $totais['aferidos'] += (int) $row['EquipamentosAferidos'];
    $totais['nao_aferidos'] += (int) $row['EquipamentosNaoAferidos'];
    $totais['os_abertas'] += (int) $row['TotalOSAberta'];
}
