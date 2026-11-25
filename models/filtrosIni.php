<?php

include_once "conexaoSQL.php";
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');

$mesAtual = date('m/Y');

$mesFiltro = !empty($_GET['mesFiltro']) ? $_GET['mesFiltro'] : $mesAtual;
$contratoFiltro = isset($_GET['contratoFiltro']) ? trim($_GET['contratoFiltro']) : '';
$cidadeFiltro = isset($_GET['cidadeFiltro']) ? trim($_GET['cidadeFiltro']) : '';
$patFiltro = isset($_GET['patFiltro']) ? trim($_GET['patFiltro']) : '';
$bairroFiltro = isset($_GET['bairroFiltro']) ? trim($_GET['bairroFiltro']) : '';
$estadoFiltro = isset($_GET['estadoFiltro']) ? trim($_GET['estadoFiltro']) : '';
$areaFiltro = isset($_GET['areaFiltro']) ? trim($_GET['areaFiltro']) : '';
$serieFiltro = isset($_GET['serieFiltro']) ? trim($_GET['serieFiltro']) : '';
$modeloFiltro = isset($_GET['modeloFiltro']) ? trim($_GET['modeloFiltro']) : '';

$condicaoContrato = "";
if ($contratoFiltro != "") {
  $condicaoContrato = " AND Contrato LIKE '%$contratoFiltro%' ";
}

$condicaoPatFiltro = "";
$condicaoPatFiltroInt = "";
$condicaoPatFiltroIntOS = "";
if ($patFiltro != "") {
  $condicaoPatFiltro = " AND (Pat LIKE '%$patFiltro%' OR Serie LIKE '%$patFiltro%' )";
  $condicaoPatFiltroInt = " AND (LEIT2.Pat LIKE '%$patFiltro%' OR LEIT2.Serie LIKE '%$patFiltro%')";
  $condicaoPatFiltroIntOS = " AND (L3.Pat LIKE '%$patFiltro%' OR L3.Serie LIKE '%$patFiltro%')";
}

$condicaoCidade = "";
$condicaoCidadeInt = "";
$condicaoCidadeIntOS = "";
if ($cidadeFiltro != "") {
  $condicaoCidade = " AND Cidade LIKE '%$cidadeFiltro%' ";
  $condicaoCidadeInt = " AND LEIT2.Cidade LIKE '%$cidadeFiltro%' ";
  $condicaoCidadeIntOS = " AND L3.Cidade LIKE '%$cidadeFiltro%' ";
}

$condicaoNovosFiltros = '';
$condicaoNovosFiltrosInt = '';
$condicaoNovosFiltrosIntOS = '';
if ($bairroFiltro !== '') {
  $condicaoNovosFiltros .= " AND Bairro LIKE '%$bairroFiltro%' ";
  $condicaoNovosFiltrosInt .= " AND LEIT2.Bairro LIKE '%$bairroFiltro%' ";
  $condicaoNovosFiltrosIntOS .= " AND L3.Bairro LIKE '%$bairroFiltro%' ";
}
if ($estadoFiltro !== '') {
  $condicaoNovosFiltros .= " AND Estado LIKE '%$estadoFiltro%' ";
  $condicaoNovosFiltrosInt .= " AND LEIT2.Estado LIKE '%$estadoFiltro%' ";
  $condicaoNovosFiltrosIntOS .= " AND L3.Estado LIKE '%$estadoFiltro%' ";
}
if ($serieFiltro !== '') {
  $condicaoNovosFiltros .= " AND Serie LIKE '%$serieFiltro%' ";
  $condicaoNovosFiltrosInt .= " AND LEIT2.Serie LIKE '%$serieFiltro%' ";
  $condicaoNovosFiltrosIntOS .= " AND L3.Serie LIKE '%$serieFiltro%' ";
}
if ($modeloFiltro !== '') {
  $condicaoNovosFiltros .= " AND Equipamento LIKE '%$modeloFiltro%' ";
  $condicaoNovosFiltrosInt .= " AND LEIT2.Equipamento LIKE '%$modeloFiltro%' ";
  $condicaoNovosFiltrosIntOS .= " AND L3.Equipamento LIKE '%$modeloFiltro%' ";
}
if ($areaFiltro !== '') {
  $condicaoNovosFiltros .= " AND EXISTS (SELECT 1 FROM TB02112 EQ WHERE EQ.TB02112_NUMSERIE = Serie AND EQ.TB02112_TIPOINTERV LIKE '%$areaFiltro%') ";
  $condicaoNovosFiltrosInt .= " AND EXISTS (SELECT 1 FROM TB02112 EQ WHERE EQ.TB02112_NUMSERIE = LEIT2.Serie AND EQ.TB02112_TIPOINTERV LIKE '%$areaFiltro%') ";
  $condicaoNovosFiltrosIntOS .= " AND EXISTS (SELECT 1 FROM TB02112 EQ WHERE EQ.TB02112_NUMSERIE = L3.Serie AND EQ.TB02112_TIPOINTERV LIKE '%$areaFiltro%') ";
}
