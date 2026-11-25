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