<?php

$sqlTotais = "SELECT 
    SUM(Equipamentos) AS TotalEquipamentos,
    SUM(EquipamentosAferidos) AS TotalAferidos,
    SUM(EquipamentosNaoAferidos) AS TotalNaoAferidos,
    SUM(TotalOSAberta) AS TotalOS
FROM (
    SELECT DISTINCT
        Contrato,
        (SELECT COUNT(LEIT2.Serie)
            FROM BM_CONTRLEITURA_ABRIROS AS LEIT2
            WHERE LEIT2.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
            AND LEIT2.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
            AND LEIT2.situacao = 'A' 
            AND LEIT2.libleitura = 'N'
            $condicaoCidadeInt) AS Equipamentos,

        (SELECT COUNT(LEIT2.Serie)
            FROM BM_CONTRLEITURA_ABRIROS AS LEIT2
            WHERE LEIT2.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
            AND LEIT2.Possui = 'Sim'
            AND LEIT2.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
            AND LEIT2.situacao = 'A' 
            AND LEIT2.libleitura = 'N'
            $condicaoCidadeInt) AS EquipamentosAferidos,

        (SELECT COUNT(LEIT2.Serie)
            FROM BM_CONTRLEITURA_ABRIROS AS LEIT2
            WHERE LEIT2.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
            AND LEIT2.Possui = 'Não'
            AND LEIT2.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
            AND LEIT2.situacao = 'A' 
            AND LEIT2.libleitura = 'N'
            $condicaoCidadeInt) AS EquipamentosNaoAferidos,

        (SELECT COUNT(DISTINCT OS.TB02115_NUMSERIE)
            FROM TB02115 OS
            INNER JOIN BM_CONTRLEITURA_ABRIROS L3 ON OS.TB02115_NUMSERIE = L3.Serie
            WHERE L3.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
            AND L3.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
            AND L3.situacao = 'A' 
            AND L3.libleitura = 'N'
            AND L3.Possui = 'Não'
            AND OS.TB02115_PREVENTIVA = 'A'
            AND OS.TB02115_DTFECHA IS NULL
            $condicaoCidadeIntOS
        ) AS TotalOSAberta
        
    FROM BM_CONTRLEITURA_ABRIROS
    WHERE situacao = 'A' 
        AND libleitura = 'N'
        AND MesFechamento = ?
        AND Possui = 'Não'
        $condicaoContrato
        $condicaoCidade
        $condicaoPatFiltro
) AS TabelaBase";

// Passamos o $mesFiltro APENAS UMA VEZ agora, pois a query base só exige ele uma vez
$paramsTotais = array($mesFiltro);
$stmtTotais = sqlsrv_query($conn, $sqlTotais, $paramsTotais);

$totais = [
    'equipamentos' => 0,
    'aferidos' => 0,
    'nao_aferidos' => 0,
    'os_abertas' => 0
];

if ($stmtTotais !== false && $rowT = sqlsrv_fetch_array($stmtTotais, SQLSRV_FETCH_ASSOC)) {
    $totais['equipamentos'] = $rowT['TotalEquipamentos'];
    $totais['aferidos'] = $rowT['TotalAferidos'];
    $totais['nao_aferidos'] = $rowT['TotalNaoAferidos'];
    $totais['os_abertas'] = $rowT['TotalOS'];
}