<?php
require 'vendor/autoload.php';

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\DA\NFe\Danfe; // <- para o PDF DANFE

function o(array $arr): stdClass {
    return (object) $arr;
}

// =====================
// CONFIGURAÇÕES
// =====================
$config = [
    "atualizar_automaticamente" => true,
    "tpAmb" => (int) getenv('NFE_TPAMB') ?: 2, // forçar int
    "razaosocial" => getenv('NFE_RAZAOSOCIAL') ?: "EMPRESA DE TESTE LTDA",
    "cnpj" => getenv('NFE_CNPJ') ?: "99999999000199",
    "siglaUF" => getenv('NFE_SIGLA_UF') ?: "SP",
    "schemes" => getenv('NFE_SCHEMA') ?: "PL_009_V4",
    "versao" => getenv('NFE_VERSION') ?: "4.00",
    "tokenIBPT" => "",
    "certPfx" => "certs/certificado-cantina.pfx",
    "certPassword" => getenv('NFE_CERT_PASSWORD') ?: "12345678Mc"
];
$configJson = json_encode($config);

// =====================
// CERTIFICADO
// =====================
$certificado = file_get_contents(__DIR__ . "/" . $config['certPfx']);
$certificate = Certificate::readPfx($certificado, $config['certPassword']);

// =====================
// TOOLS (comunicação SEFAZ)
// =====================
$tools = new Tools($configJson, $certificate);
$tools->model('55'); // NF-e modelo 55

// =====================
// RECEBE DADOS DO FRONTEND (JSON)
// =====================
$body = json_decode(file_get_contents("php://input"), true);

// Produto de exemplo
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
// MONTAGEM DA NF-E
// =====================
$nfe = new Make();

// Cabeçalho
$nfe->taginfNFe(o(['versao' => '4.00']));

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
    'cMunFG' => getenv('NFE_COD_MUNICIPIO') ?: '3550308',
    'tpImp' => 1,
    'tpEmis' => 1,
    'cDV' => 0,
    'tpAmb' => $config['tpAmb'],
    'finNFe' => 1,
    'indFinal' => 1,
    'indPres' => 1,
    'procEmi' => 0,
    'verProc' => '1.0'
]));

// Emitente
$nfe->tagemit(o([
    'CNPJ' => $config['cnpj'],
    'xNome' => $config['razaosocial'],
    'xFant' => getenv('NFE_FANTASIA') ?: 'EMPRESA TESTE',
    'IE' => getenv('NFE_IE') ?: '123456789',
    'CRT' => 3
]));

// Endereço emitente
$nfe->tagenderEmit(o([
    'xLgr' => getenv('NFE_ENDERECO') ?: 'Rua Exemplo',
    'nro' => getenv('NFE_NUMERO') ?: '1000',
    'xBairro' => getenv('NFE_BAIRRO') ?: 'Centro',
    'cMun' => getenv('NFE_COD_MUNICIPIO') ?: '3550308',
    'xMun' => getenv('NFE_CIDADE') ?: 'São Paulo',
    'UF' => $config['siglaUF'],
    'CEP' => getenv('NFE_CEP') ?: '01001000',
    'cPais' => '1058',
    'xPais' => 'Brasil',
    'fone' => getenv('NFE_FONE') ?: '11999999999'
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
$valorTotal = bcmul($produto['qCom'], $produto['vUnCom'], 2);
$nfe->tagimposto(o(['item' => 1]));
$nfe->tagICMS(o([
    'item' => 1,
    'orig' => 0,
    'CST' => '00',
    'modBC' => 0,
    'vBC' => $valorTotal,
    'pICMS' => '18.00',
    'vICMS' => $valorTotal * 0.18
]));
$nfe->tagPIS(o([
    'item' => 1,
    'CST' => '01',
    'vBC' => $valorTotal,
    'pPIS' => '1.65',
    'vPIS' => $valorTotal * 0.0165
]));
$nfe->tagCOFINS(o([
    'item' => 1,
    'CST' => '01',
    'vBC' => $valorTotal,
    'pCOFINS' => '7.60',
    'vCOFINS' => $valorTotal * 0.076
]));

// Totais
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
    'vTotTrib' => '0.00'
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
// FINALIZAÇÃO
// =====================
$xml = $nfe->getXML();
$xmlAssinado = $tools->signNFe($xml);

$idLote = str_pad(mt_rand(1, 999999999999999), 15, '0', STR_PAD_LEFT);
$retorno = $tools->sefazEnviaLote([$xmlAssinado], $idLote, 1);

$xmlProc = montaProcNFe($xmlAssinado, $retorno);

// Salva XML autorizado
$chave = substr(md5($xmlProc), 0, 10);
if (!is_dir(__DIR__ . "/notas")) {
    mkdir(__DIR__ . "/notas", 0777, true);
}
file_put_contents(__DIR__ . "/notas/nfe-autorizada-$chave.xml", $xmlProc);

// === Gerar DANFE PDF ===
try {
    $danfe = new Danfe($xmlProc);
    $pdf = $danfe->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="danfe.pdf"');
    echo $pdf;
    exit;

} catch (\Exception $e) {
    echo json_encode([
        "status" => "erro",
        "mensagem" => $e->getMessage()
    ]);
}

// --- Junta XML assinado + protocolo em um nfeProc ---
function montaProcNFe(string $xmlAssinado, string $retorno): string
{
    $domRet = new DOMDocument();
    $domRet->loadXML($retorno);
    $protNFe = $domRet->getElementsByTagName("protNFe")->item(0);

    if (!$protNFe) {
        throw new Exception("Protocolo de autorização não encontrado.");
    }

    $domNFe = new DOMDocument();
    $domNFe->loadXML($xmlAssinado);
    $nfe = $domNFe->getElementsByTagName("NFe")->item(0);

    $domProc = new DOMDocument("1.0", "UTF-8");
    $nfeProc = $domProc->createElement("nfeProc");
    $nfeProc->setAttribute("xmlns", "http://www.portalfiscal.inf.br/nfe");
    $nfeProc->setAttribute("versao", "4.00");

    $nfeProc->appendChild($domProc->importNode($nfe, true));
    $nfeProc->appendChild($domProc->importNode($protNFe, true));
    $domProc->appendChild($nfeProc);

    return $domProc->saveXML();
}
