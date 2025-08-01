<?php
require 'vendor/autoload.php';

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;

function o(array $arr): stdClass {
    return (object) $arr;
}

// Lê configuração
$configJson = file_get_contents(__DIR__ . '/nfe-config.json');
$config = json_decode($configJson);

// Carrega certificado digital
$certificado = file_get_contents(__DIR__ . '/' . $config->certPfx);
$certificate = Certificate::readPfx($certificado, $config->certPassword);

// Instancia Tools (responsável por comunicação com SEFAZ)
$tools = new Tools($configJson, $certificate);
$tools->model('55');

// Recebe dados do pedido via POST JSON
$body = json_decode(file_get_contents("php://input"), true);

// Exemplo de produto vindo do frontend
$produto = $body['produto'] ?? [
    'cProd' => '001',
    'xProd' => 'Produto Teste',
    'NCM'   => '61091000',
    'CFOP'  => '5102',
    'uCom'  => 'UN',
    'qCom'  => '1.0000',
    'vUnCom'=> '100.00'
];

// =====================
// Montagem da NF-e
// =====================
$nfe = new Make();

// Cabeçalho
$nfe->taginfNFe(o([
    'versao' => '4.00'
]));

// Identificação
$nfe->tagide(o([
    'cUF' => 35,
    'cNF' => str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
    'natOp' => 'VENDA DE MERCADORIA',
    'mod' => 55,
    'serie' => 1,
    'nNF' => mt_rand(1, 99999),
    'dhEmi' => date('c'),
    'tpNF' => 1,
    'idDest' => 1,
    'cMunFG' => '3550308',
    'tpImp' => 1,
    'tpEmis' => 1,
    'cDV' => 0,
    'tpAmb' => 2, // 2 = Homologação
    'finNFe' => 1,
    'indFinal' => 1,
    'indPres' => 1,
    'procEmi' => 0,
    'verProc' => '1.0'
]));

// Emitente
$nfe->tagemit(o([
    'CNPJ' => $config->cnpj,
    'xNome' => $config->razaosocial,
    'xFant' => $config->fantasia,
    'IE' => $config->ie,
    'CRT' => 3
]));

// Endereço emitente
$nfe->tagenderEmit(o([
    'xLgr' => $config->endereco,
    'nro' => $config->numero,
    'xBairro' => $config->bairro,
    'cMun' => $config->cMun,
    'xMun' => $config->xMun,
    'UF' => $config->UF,
    'CEP' => $config->CEP,
    'cPais' => '1058',
    'xPais' => 'Brasil',
    'fone' => $config->fone
]));

// Destinatário
$nfe->tagdest(o([
    'CPF' => $body['cliente']['cpf'] ?? '12345678909',
    'xNome' => $body['cliente']['nome'] ?? 'NF-E HOMOLOGAÇÃO',
    'indIEDest' => 9
]));

// Endereço destinatário
$nfe->tagenderDest(o([
    'xLgr' => $body['cliente']['endereco']['rua'] ?? 'Rua Teste',
    'nro' => $body['cliente']['endereco']['numero'] ?? '123',
    'xBairro' => $body['cliente']['endereco']['bairro'] ?? 'Centro',
    'cMun' => $body['cliente']['endereco']['cMun'] ?? '3550308',
    'xMun' => $body['cliente']['endereco']['cidade'] ?? 'São Paulo',
    'UF' => $body['cliente']['endereco']['UF'] ?? 'SP',
    'CEP' => $body['cliente']['endereco']['CEP'] ?? '01001000',
    'cPais' => '1058',
    'xPais' => 'Brasil',
    'fone' => $body['cliente']['endereco']['fone'] ?? '11999999999'
]));

// Produto
$nfe->tagprod(o([
    'item' => 1,
    'cProd' => $produto['cProd'],
    'cEAN' => 'SEM GTIN',
    'xProd' => $produto['xProd'],
    'NCM' => $produto['NCM'],
    'CFOP' => $produto['CFOP'],
    'uCom' => $produto['uCom'],
    'qCom' => $produto['qCom'],
    'vUnCom' => $produto['vUnCom'],
    'vProd' => bcmul($produto['qCom'], $produto['vUnCom'], 2),
    'cEANTrib' => 'SEM GTIN',
    'uTrib' => $produto['uCom'],
    'qTrib' => $produto['qCom'],
    'vUnTrib' => $produto['vUnCom'],
    'indTot' => 1
]));

// Impostos
$nfe->tagimposto(o(['item' => 1]));
$nfe->tagICMS(o([
    'item' => 1,
    'orig' => 0,
    'CST' => '00',
    'modBC' => 0,
    'vBC' => bcmul($produto['qCom'], $produto['vUnCom'], 2),
    'pICMS' => '18.00',
    'vICMS' => bcmul($produto['qCom'], $produto['vUnCom'], 2) * 0.18
]));
$nfe->tagPIS(o([
    'item' => 1,
    'CST' => '01',
    'vBC' => bcmul($produto['qCom'], $produto['vUnCom'], 2),
    'pPIS' => '1.65',
    'vPIS' => bcmul($produto['qCom'], $produto['vUnCom'], 2) * 0.0165
]));
$nfe->tagCOFINS(o([
    'item' => 1,
    'CST' => '01',
    'vBC' => bcmul($produto['qCom'], $produto['vUnCom'], 2),
    'pCOFINS' => '7.60',
    'vCOFINS' => bcmul($produto['qCom'], $produto['vUnCom'], 2) * 0.076
]));

// Totais
$valorTotal = bcmul($produto['qCom'], $produto['vUnCom'], 2);
$nfe->tagICMSTot(o([
    'vBC' => $valorTotal,
    'vICMS' => $valorTotal * 0.18,
    'vICMSDeson' => '0.00',
    'vFCP' => '0.00',
    'vBCST' => '0.00',
    'vST' => '0.00',
    'vProd' => $valorTotal,
    'vFrete' => '0.00',
    'vSeg' => '0.00',
    'vDesc' => '0.00',
    'vII' => '0.00',
    'vIPI' => '0.00',
    'vPIS' => $valorTotal * 0.0165,
    'vCOFINS' => $valorTotal * 0.076,
    'vOutro' => '0.00',
    'vNF' => $valorTotal,
    'vTotTrib' => '0.00' // se não calcular tributos aproximados
]));

// Transporte
$nfe->tagtransp(o(['modFrete' => 9]));

// Pagamento
$nfe->tagpag(o(['vTroco' => '0.00']));
$nfe->tagdetPag(o([
    'indPag' => 0,
    'tPag' => '01',
    'vPag' => $valorTotal
]));

// Informações adicionais
$nfe->taginfAdic(o([
    'infCpl' => 'NF-e emitida em ambiente de homologação. Sem valor fiscal.'
]));

// =====================
// Finalização
// =====================

// Gera XML e assina
$xml = $nfe->getXML();
$xmlAssinado = $tools->signNFe($xml);

// Envia para SEFAZ
$idLote = str_pad(mt_rand(1, 999999999999999), 15, '0', STR_PAD_LEFT);
$retorno = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);

// Junta protocolo
$xmlAutorizado = $tools->addProt($xmlAssinado, $retorno);

// Salva no servidor
file_put_contents(__DIR__ . '/nfe-autorizada.xml', $xmlAutorizado);

// Retorna resposta JSON para frontend
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'retorno' => $retorno,
    'xmlAutorizado' => base64_encode($xmlAutorizado)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
