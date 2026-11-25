<?php

include_once "conexaoSQL.php";

$sqlLista = "SELECT DISTINCT
              Contrato,
              Cliente,

              (SELECT COUNT(LEIT2.Serie)
                  FROM BM_CONTRLEITURA_ABRIROS AS LEIT2
                WHERE LEIT2.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
                  AND LEIT2.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
                  AND LEIT2.situacao = 'A' 
                  AND LEIT2.libleitura = 'N'
                  $condicaoCidadeInt
                  $condicaoPatFiltroInt) Equipamentos,

              (SELECT COUNT(LEIT2.Serie)
                  FROM BM_CONTRLEITURA_ABRIROS AS LEIT2
                WHERE LEIT2.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
                  AND LEIT2.Possui = 'Sim'
                  AND LEIT2.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
                  AND LEIT2.situacao = 'A' 
                  AND LEIT2.libleitura = 'N'
                  $condicaoCidadeInt
                  $condicaoPatFiltroInt) EquipamentosAferidos,

              (SELECT COUNT(LEIT2.Serie)
                  FROM BM_CONTRLEITURA_ABRIROS AS LEIT2
                WHERE LEIT2.Contrato = BM_CONTRLEITURA_ABRIROS.Contrato
                  AND LEIT2.Possui = 'Não'
                  AND LEIT2.MesFechamento = dbo.BM_CONTRLEITURA_ABRIROS.MesFechamento
                  AND LEIT2.situacao = 'A' 
                  AND LEIT2.libleitura = 'N'
                  $condicaoCidadeInt
                  $condicaoPatFiltroInt) EquipamentosNaoAferidos,

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
                  $condicaoPatFiltroIntOS
              ) AS TotalOSAberta
          
            FROM BM_CONTRLEITURA_ABRIROS
            WHERE situacao = 'A' 
              AND libleitura = 'N'
              AND MesFechamento = ?
              AND Possui = 'Não'
              $condicaoContrato
              $condicaoCidade
              $condicaoPatFiltro
            ORDER BY Contrato asc";

$params = array($mesFiltro);
$stmtLista = sqlsrv_query($conn, $sqlLista, $params);
