<?php

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

function dataValida($data)
{
    $objeto = DateTime::createFromFormat('!Y-m-d', $data);
    return $objeto && $objeto->format('Y-m-d') === $data;
}

/* "Qui 25 Mar" */
function dataCurta($data)
{
    if (!$data) {
        return '';
    }

    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $t = strtotime($data);

    return $dias[(int) date('w', $t)] . ' ' . date('d', $t) . ' ' . $meses[(int) date('n', $t) - 1];
}

/* Destinos (SELECT * para achar a coluna da foto sem depender do nome exato) */
$destinos = $pdo->query("
    SELECT *
    FROM destinos
    WHERE ativo = 1
    ORDER BY nome
")->fetchAll();

$fotoDestino = [];
$colunasFoto = ['imagem', 'foto', 'caminho_imagem', 'imagem_url', 'url_imagem', 'imagem_principal', 'capa'];

foreach ($destinos as $d) {
    foreach ($colunasFoto as $coluna) {
        if (!empty($d[$coluna])) {
            $fotoDestino[(int) $d['id']] = $d[$coluna];
            break;
        }
    }
}

$destino = filter_input(INPUT_GET, 'destino', FILTER_VALIDATE_INT);
$passageiros = filter_input(INPUT_GET, 'passageiros', FILTER_VALIDATE_INT);

$dataIda = $_GET['data'] ?? '';
$dataVolta = $_GET['volta'] ?? '';

$passageiros = (!$passageiros || $passageiros < 1 || $passageiros > 6)
    ? 1
    : $passageiros;

$erro = '';
$pacotes = [];

if ($destino && !in_array(
    (int) $destino,
    array_map('intval', array_column($destinos, 'id')),
    true
)) {
    $erro = 'Selecione um destino válido.';
} elseif ($dataIda !== '' && !dataValida($dataIda)) {
    $erro = 'A data de ida é inválida.';
} elseif ($dataVolta !== '' && !dataValida($dataVolta)) {
    $erro = 'A data de volta é inválida.';
} elseif ($dataIda !== '' && $dataIda < date('Y-m-d')) {
    $erro = 'A data de ida não pode ser anterior a hoje.';
} elseif (
    $dataVolta !== '' &&
    ($dataIda === '' || $dataVolta <= $dataIda)
) {
    $erro = 'A volta deve ser posterior à ida.';
} else {

    $sql = "
        SELECT
            p.id,
            p.nome,
            p.descricao,
            p.quantidade_noites,
            p.preco,
            p.destino_id,

            d.nome AS destino_nome,
            h.nome AS hotel_nome,
            h.estrelas AS hotel_estrelas,

            (
                SELECT hi.caminho_imagem
                FROM hotel_imagens hi
                WHERE hi.hotel_id = h.id
                ORDER BY hi.imagem_principal DESC, hi.ordem ASC
                LIMIT 1
            ) AS imagem_hotel,

            ida.id AS voo_ida,
            volta.id AS voo_volta,

            (
                SELECT tv.horario_partida
                FROM trechos_voos tv
                WHERE tv.voo_id = ida.id
                ORDER BY tv.ordem ASC
                LIMIT 1
            ) AS partida_ida,

            (
                SELECT tv.horario_partida
                FROM trechos_voos tv
                WHERE tv.voo_id = volta.id
                ORDER BY tv.ordem ASC
                LIMIT 1
            ) AS partida_volta

        FROM pacotes p

        INNER JOIN voos ida
            ON ida.id = p.voo_ida_id

        LEFT JOIN voos volta
            ON volta.id = p.voo_volta_id

        LEFT JOIN destinos d
            ON d.id = p.destino_id

        LEFT JOIN hoteis h
            ON h.id = p.hotel_id

        WHERE p.ativo = 1
          AND ida.ativo = 1
          AND ida.assentos_disponiveis >= :passageiros_ida

          AND (
              volta.id IS NULL
              OR (
                  volta.ativo = 1
                  AND volta.assentos_disponiveis >= :passageiros_volta
              )
          )

          AND (p.hotel_id IS NULL OR h.ativo = 1)
    ";

    $params = [
        'passageiros_ida' => $passageiros,
        'passageiros_volta' => $passageiros
    ];

    if ($destino) {
        $sql .= " AND p.destino_id = :destino";
        $params['destino'] = $destino;
    }

    if ($dataIda !== '') {
        $sql .= "
            AND DATE((
                SELECT tv.horario_partida
                FROM trechos_voos tv
                WHERE tv.voo_id = ida.id
                ORDER BY tv.ordem ASC
                LIMIT 1
            )) = :data_ida
        ";

        $params['data_ida'] = $dataIda;
    }

    if ($dataVolta !== '') {
        $sql .= "
            AND volta.id IS NOT NULL

            AND DATE((
                SELECT tv.horario_partida
                FROM trechos_voos tv
                WHERE tv.voo_id = volta.id
                ORDER BY tv.ordem ASC
                LIMIT 1
            )) = :data_volta
        ";

        $params['data_volta'] = $dataVolta;
    }

    $sql .= " ORDER BY p.preco ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $pacotes = $stmt->fetchAll();
}

/* Textos do topo */
$destinoNome = '';
foreach ($destinos as $d) {
    if ((int) $d['id'] === (int) $destino) {
        $destinoNome = $d['nome'];
        break;
    }
}

$tituloPagina = $destinoNome
    ? 'Pacotes de viagem para ' . $destinoNome . ' imbatíveis'
    : 'Pacotes de viagem imbatíveis';

$subtituloPagina = $destinoNome
    ? 'Hospedagens e voos pré-selecionados por nós para ' . $destinoNome . '. Aproveite!'
    : 'Hospedagens e voos pré-selecionados por nós. Aproveite!';

$porPagina = 8;

/* Dados do banner */
$periodo = dataValida($dataIda) ? date('d/m/y', strtotime($dataIda)) : '';
if (dataValida($dataVolta)) {
    $periodo .= ($periodo ? ' - ' : '') . date('d/m/y', strtotime($dataVolta));
}

$titulo = 'Pacotes | Moov+';

include __DIR__ . '/includes/header.php';

?>

<section class="secao pagina-pacotes">
    <div class="container">

        <div class="banner-busca">
            <div class="container">

                <nav class="breadcrumb-etapas" aria-label="Etapas da compra">
                    <strong>Pacotes</strong>
                    <span>›</span><span>Viajantes</span>
                    <span>›</span><span>Responsável</span>
                    <span>›</span><span>Resumo</span>
                    <span>›</span><span>Pagamento</span>
                    <span>›</span><span>Confirmação</span>
                </nav>

                <div class="banner-busca-linha">
                    <div class="banner-busca-info">
                        <h1>Esse é o resultado de sua busca</h1>

                        <div class="banner-busca-dados">
                            <span class="dado-rota">
                                <strong><?= e($destinoNome ?: 'Qualquer destino') ?></strong>
                            </span>
                            <span><?= str_pad((string) $passageiros, 2, '0', STR_PAD_LEFT) ?></span>
                            <?php if ($periodo): ?>
                                <span><?= e($periodo) ?></span>
                            <?php endif; ?>
                            <span>R$ Reais</span>
                            <a class="botao-alterar" href="#form-pacotes">Alterar busca</a>
                        </div>
                    </div>

                    <div class="banner-busca-total">
                        <div>
                            <small>
                                Total <strong><?= str_pad((string) $passageiros, 2, '0', STR_PAD_LEFT) ?></strong>
                                <?= $passageiros === 1 ? 'viajante' : 'viajantes' ?>
                            </small>
                            <strong><?= moeda(0) ?></strong>
                        </div>
                        <button type="button" class="botao-informar" disabled>Informar<br>viajantes</button>
                    </div>
                </div>

            </div>
        </div>


        <div class="pacotes-cabecalho">
            <h1><?= e($tituloPagina) ?></h1>
            <p><?= e($subtituloPagina) ?></p>
        </div>

        <?php if ($erro): ?>

            <div class="mensagem-voos">
                <?= e($erro) ?>
            </div>

        <?php elseif (!$pacotes): ?>

            <p class="vazio">
                Não encontramos pacotes para os dados selecionados.
            </p>

        <?php else: ?>

            <div class="pacotes-grade" id="pacotes-grade">

                <?php foreach ($pacotes as $i => $p): ?>

                    <?php
                    $foto = $fotoDestino[(int) $p['destino_id']] ?? ($p['imagem_hotel'] ?? '');
                    $noites = (int) $p['quantidade_noites'];
                    $estrelas = (int) ($p['hotel_estrelas'] ?? 0);
                    $nomeCartao = $p['hotel_nome'] ?: $p['nome'];

                    $tipo = $p['hotel_nome'] ? 'Hotel + Aéreo' : 'Aéreo';

                    $datas = dataCurta($p['partida_ida']);
                    if ($p['voo_volta'] && $p['partida_volta']) {
                        $datas .= ' - ' . dataCurta($p['partida_volta']);
                    }
                    ?>

                    <a
                        class="pacote-card"
                        href="pacote.php?id=<?= (int) $p['id'] ?>&passageiros=<?= (int) $passageiros ?>"
                        <?= $i >= $porPagina ? 'hidden' : '' ?>
                    >

                        <div class="pacote-card-foto">

                            <?php if ($foto && imagem_existe($foto)): ?>
                                <img
                                    src="<?= e($foto) ?>"
                                    alt="<?= e($p['destino_nome'] ?? $p['nome']) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="sem-foto">
                                    <?= e($p['destino_nome'] ?? $p['nome']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($noites > 0): ?>
                                <span class="pacote-card-badge">
                                    <?= $noites + 1 ?> Dias / <?= $noites ?> <?= $noites === 1 ? 'Noite' : 'Noites' ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <div class="pacote-card-corpo">

                            <h2 class="pacote-card-titulo">
                                <?php if ($estrelas > 0): ?>
                                    <span class="pacote-card-nota">★ <?= min(5, $estrelas) ?></span>
                                <?php endif; ?>
                                <span class="pacote-card-nome"><?= e($nomeCartao) ?></span>
                            </h2>

                            <p class="pacote-card-detalhe">
                                <strong><?= e($tipo) ?></strong>
                                <?php if ($datas): ?>
                                    | <?= e($datas) ?>
                                <?php endif; ?>
                            </p>

                        </div>

                        <div class="pacote-card-preco">
                            <small>Preço por pessoa</small>
                            <strong><?= moeda($p['preco']) ?></strong>
                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

            <?php if (count($pacotes) > $porPagina): ?>
                <div class="pacotes-mais">
                    <button type="button" class="botao-mais-ofertas" id="mais-ofertas">
                        Ver mais ofertas
                    </button>
                </div>

                <script>
                    (function () {
                        var botao = document.getElementById('mais-ofertas');
                        var grade = document.getElementById('pacotes-grade');
                        var passo = <?= (int) $porPagina ?>;

                        botao.addEventListener('click', function () {
                            var ocultos = grade.querySelectorAll('.pacote-card[hidden]');

                            for (var i = 0; i < ocultos.length && i < passo; i++) {
                                ocultos[i].hidden = false;
                            }

                            if (grade.querySelectorAll('.pacote-card[hidden]').length === 0) {
                                botao.parentNode.hidden = true;
                            }
                        });
                    })();
                </script>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>