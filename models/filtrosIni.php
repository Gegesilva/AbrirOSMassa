<?php

include_once "conexaoSQL.php";
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');

$mesAtual = date('m/Y');

$mesFiltro = !empty($_POST['mesFiltro']) ? $_POST['mesFiltro'] : $mesAtual;
$contratoFiltro = isset($_POST['contratoFiltro']) ? trim($_POST['contratoFiltro']) : '';
$cidadeFiltro = isset($_POST['cidadeFiltro']) ? trim($_POST['cidadeFiltro']) : '';
$patFiltro = isset($_POST['patFiltro']) ? trim($_POST['patFiltro']) : '';
$bairroFiltro = isset($_POST['bairroFiltro']) ? trim($_POST['bairroFiltro']) : '';
$estadoFiltro = isset($_POST['estadoFiltro']) ? trim($_POST['estadoFiltro']) : '';
$areaFiltro = isset($_POST['areaFiltro']) ? trim($_POST['areaFiltro']) : '';
$serieFiltro = isset($_POST['serieFiltro']) ? trim($_POST['serieFiltro']) : '';
$modeloFiltro = isset($_POST['modeloFiltro']) ? trim($_POST['modeloFiltro']) : '';
$paginasDesdePreventivaFiltro = isset($_POST['paginasDesdePreventivaFiltro'])
  ? trim($_POST['paginasDesdePreventivaFiltro'])
  : '';

if ($paginasDesdePreventivaFiltro !== '' && !ctype_digit($paginasDesdePreventivaFiltro)) {
  $paginasDesdePreventivaFiltro = '';
}

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
