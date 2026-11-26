
<?php
session_start();

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$titulo = 'Pagamento | Moovplus';

$usuarioId = filter_var($_SESSION['usuario_id'] ?? 0, FILTER_VALIDATE_INT);
$reservaId = filter_input(INPUT_GET, 'reserva', FILTER_VALIDATE_INT);

$erro = '';
$sucesso = isset($_GET['sucesso']) && $_GET['sucesso'] === '1';

if (empty($_SESSION['token_pagamento'])) {
    $_SESSION['token_pagamento'] = bin2hex(random_bytes(32));
}

function escaparPagamento($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarDinheiroPagamento($valor)
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

$reserva = null;
$detalhes = [];

if (!$usuarioId) {
    $erro = 'Você precisa entrar na sua conta para acessar o pagamento.';
} elseif (!$reservaId) {
    $erro = 'Nenhuma reserva foi informada.';
} else {
    $consulta = $pdo->prepare("
        SELECT *
        FROM reservas
        WHERE id = :id AND usuario_id = :usuario_id
        LIMIT 1
    ");

    $consulta->execute([
        ':id' => $reservaId,
        ':usuario_id' => $usuarioId
    ]);

    $reserva = $consulta->fetch();

    if (!$reserva) {
        $erro = 'Reserva não encontrada.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erro) {
    $token = $_POST['token'] ?? '';
    $metodo = $_POST['metodo'] ?? '';
    $parcelas = filter_var($_POST['parcelas'] ?? 1, FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION['token_pagamento'], $token)) {
        $erro = 'Sessão inválida. Atualize a página e tente novamente.';
    } elseif (!in_array($metodo, ['pix', 'cartao_credito'], true)) {
        $erro = 'Selecione uma forma de pagamento válida.';
    } elseif (!$parcelas || $parcelas < 1 || $parcelas > 12) {
        $erro = 'Selecione uma quantidade válida de parcelas.';
    } elseif ($metodo === 'pix' && $parcelas !== 1) {
        $erro = 'O pagamento por PIX deve ser realizado em uma parcela.';
    }

    if (!$erro && $metodo === 'cartao_credito') {
        $nomeCartao = trim($_POST['nome_cartao'] ?? '');
        $numeroCartao = preg_replace('/\D/', '', $_POST['numero_cartao'] ?? '');
        $validade = trim($_POST['validade'] ?? '');
        $cvv = preg_replace('/\D/', '', $_POST['cvv'] ?? '');

        if (
            strlen($nomeCartao) < 3 ||
            strlen($numeroCartao) < 13 ||
            strlen($numeroCartao) > 19 ||
            !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $validade) ||
            !preg_match('/^\d{3,4}$/', $cvv)
        ) {
            $erro = 'Confira os dados fictícios do cartão.';
        }
    }

    if (!$erro) {
        try {
            $pdo->beginTransaction();

            $consulta = $pdo->prepare("
                SELECT *
                FROM reservas
                WHERE id = :id AND usuario_id = :usuario_id
                FOR UPDATE
            ");

            $consulta->execute([
                ':id' => $reservaId,
                ':usuario_id' => $usuarioId
            ]);

            $reservaAtual = $consulta->fetch();

            if (!$reservaAtual) {
                throw new Exception('Reserva não encontrada.');
            }

            if ($reservaAtual['status'] === 'confirmada') {
                throw new Exception('Esta reserva já foi confirmada.');
            }

            if (!in_array($reservaAtual['status'], ['pendente', 'aguardando_pagamento'], true)) {
                throw new Exception('Esta reserva não está disponível para pagamento.');
            }

            $consulta = $pdo->prepare("
                SELECT id
                FROM pagamentos
                WHERE reserva_id = :reserva_id
                AND status = 'aprovado'
                LIMIT 1
            ");

            $consulta->execute([
                ':reserva_id' => $reservaId
            ]);

            if ($consulta->fetch()) {
                throw new Exception('Esta reserva já possui um pagamento aprovado.');
            }

            $valorTotal = (float) $reservaAtual['valor_total'];
            $valorParcela = $valorTotal / $parcelas;
            $identificador = 'MOOV-' . strtoupper(bin2hex(random_bytes(8)));

            $consulta = $pdo->prepare("
                INSERT INTO pagamentos (
                    reserva_id,
                    metodo,
                    valor,
                    parcelas,
                    valor_parcela,
                    status,
                    identificador_transacao,
                    pago_em
                ) VALUES (
                    :reserva_id,
                    :metodo,
                    :valor,
                    :parcelas,
                    :valor_parcela,
                    'aprovado',
                    :identificador,
                    NOW()
                )
            ");

            $consulta->execute([
                ':reserva_id' => $reservaId,
                ':metodo' => $metodo,
                ':valor' => $valorTotal,
                ':parcelas' => $parcelas,
                ':valor_parcela' => $valorParcela,
                ':identificador' => $identificador
            ]);

            $consulta = $pdo->prepare("
                UPDATE reservas
                SET status = 'confirmada'
                WHERE id = :id
                AND usuario_id = :usuario_id
            ");

            $consulta->execute([
                ':id' => $reservaId,
                ':usuario_id' => $usuarioId
            ]);

            $pdo->commit();

            unset($_SESSION['token_pagamento']);

            header('Location: pagamento.php?reserva=' . $reservaId . '&sucesso=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = $e instanceof PDOException
                ? 'Não foi possível registrar o pagamento. Verifique os dados da reserva.'
                : $e->getMessage();
        }
    }
}

if ($reserva) {
    if ($reserva['tipo'] === 'voo') {
        $consulta = $pdo->prepare("
            SELECT
                rv.sentido,
                v.id AS voo_id,
                v.preco,
                t1.horario_partida,
                t2.horario_chegada,
                ao.cidade AS cidade_origem,
                ad.cidade AS cidade_destino,
                ao.codigo_iata AS codigo_origem,
                ad.codigo_iata AS codigo_destino
            FROM reserva_voos rv
            INNER JOIN voos v ON v.id = rv.voo_id
            INNER JOIN trechos_voos t1
                ON t1.voo_id = v.id AND t1.ordem = 1
            INNER JOIN trechos_voos t2
                ON t2.voo_id = v.id
                AND t2.ordem = (
                    SELECT MAX(t3.ordem)
                    FROM trechos_voos t3
                    WHERE t3.voo_id = v.id
                )
            INNER JOIN aeroportos ao ON ao.id = t1.aeroporto_origem_id
            INNER JOIN aeroportos ad ON ad.id = t2.aeroporto_destino_id
            WHERE rv.reserva_id = :reserva_id
            ORDER BY rv.sentido
        ");

        $consulta->execute([':reserva_id' => $reservaId]);
        $detalhes = $consulta->fetchAll();

    } elseif ($reserva['tipo'] === 'hotel') {
        $consulta = $pdo->prepare("
            SELECT
                rh.checkin,
                rh.checkout,
                rh.quantidade_quartos,
                rh.preco_total,
                h.nome AS hotel,
                h.endereco,
                d.cidade,
                d.pais,
                q.nome AS quarto
            FROM reserva_hoteis rh
            INNER JOIN hoteis h ON h.id = rh.hotel_id
            INNER JOIN destinos d ON d.id = h.destino_id
            INNER JOIN quartos q ON q.id = rh.quarto_id
            WHERE rh.reserva_id = :reserva_id
        ");

        $consulta->execute([':reserva_id' => $reservaId]);
        $detalhes = $consulta->fetchAll();

    } elseif ($reserva['tipo'] === 'pacote') {
        $consulta = $pdo->prepare("
            SELECT
                p.nome AS pacote,
                p.descricao,
                p.quantidade_noites,
                p.preco,
                d.nome AS destino,
                d.cidade,
                d.pais,
                h.nome AS hotel
            FROM pacotes p
            INNER JOIN destinos d ON d.id = p.destino_id
            INNER JOIN hoteis h ON h.id = p.hotel_id
            WHERE p.id = :pacote_id
        ");

        $consulta->execute([
            ':pacote_id' => $reserva['pacote_id']
        ]);

        $detalhes = $consulta->fetchAll();
    }

    if (!$detalhes && !$erro) {
        $erro = 'Não foi possível encontrar os detalhes dos itens desta reserva.';
    }
}

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="css/style.css">

<div class="container pagamento-container">

    <?php if ($erro): ?>

        <section class="pagamento-mensagem">
            <div class="pagamento-icone">!</div>
            <h1>Não foi possível continuar</h1>
            <p><?= escaparPagamento($erro) ?></p>
            <a href="index.php" class="pagamento-botao">Voltar para o início</a>
        </section>

    <?php elseif ($sucesso): ?>

        <section class="pagamento-mensagem pagamento-aprovado">
            <div class="pagamento-icone">✓</div>
            <h1>Pagamento aprovado!</h1>
            <p>Sua reserva foi confirmada com sucesso.</p>

            <div class="pagamento-protocolo">
                <span>Código da reserva</span>
                <strong><?= escaparPagamento($reserva['codigo']) ?></strong>
            </div>

            <p>Valor pago: <strong><?= formatarDinheiroPagamento($reserva['valor_total']) ?></strong></p>

            <a href="index.php" class="pagamento-botao">Voltar para o início</a>
        </section>

    <?php else: ?>

        <div class="pagamento-cabecalho">
            <span class="pagamento-etapa">ETAPA FINAL</span>
            <h1>Finalize sua reserva</h1>
            <p>Confira os detalhes e escolha a forma de pagamento.</p>
        </div>

        <div class="pagamento-layout">

            <section class="pagamento-formulario">

                <form method="POST" action="pagamento.php?reserva=<?= (int) $reservaId ?>" id="formPagamento">

                    <input
                        type="hidden"
                        name="token"
                        value="<?= escaparPagamento($_SESSION['token_pagamento']) ?>"
                    >

                    <div class="pagamento-secao">
                        <h2>Forma de pagamento</h2>

                        <label class="pagamento-opcao">
                            <input type="radio" name="metodo" value="pix" checked>
                            <span class="pagamento-opcao-conteudo">
                                <strong>PIX</strong>
                                <small>Pagamento simulado e aprovação imediata</small>
                            </span>
                            <span class="pagamento-simbolo">PIX</span>
                        </label>

                        <label class="pagamento-opcao">
                            <input type="radio" name="metodo" value="cartao_credito">
                            <span class="pagamento-opcao-conteudo">
                                <strong>Cartão de crédito</strong>
                                <small>Simulação de pagamento com cartão</small>
                            </span>
                            <span class="pagamento-simbolo">💳</span>
                        </label>
                    </div>

                    <div id="areaPix" class="pagamento-dados-pix">
                        <div class="pagamento-pix-aviso">
                            <strong>Pagamento demonstrativo</strong>
                            <p>Este PIX é ilustrativo. Nenhuma cobrança será realizada.</p>
                        </div>

                        <div class="pagamento-qr">
                            <div class="pagamento-qr-centro">PIX</div>
                        </div>

                        <p class="pagamento-codigo-pix">
                            MOOVPLUS-PIX-<?= str_pad((string) $reservaId, 8, '0', STR_PAD_LEFT) ?>
                        </p>

                        <p class="pagamento-ajuda">
                            Ao finalizar, o sistema registrará o pagamento como aprovado.
                        </p>

                        <input type="hidden" name="parcelas" id="parcelasPix" value="1">
                    </div>

                    <div id="areaCartao" class="pagamento-dados-cartao" hidden>
                        <h3>Dados do cartão</h3>

                        <label for="nome_cartao">Nome impresso no cartão</label>
                        <input
                            type="text"
                            id="nome_cartao"
                            name="nome_cartao"
                            placeholder="NOME COMPLETO"
                            autocomplete="off"
                            minlength="3"
                        >

                        <label for="numero_cartao">Número do cartão</label>
                        <input
                            type="text"
                            id="numero_cartao"
                            name="numero_cartao"
                            placeholder="0000 0000 0000 0000"
                            maxlength="23"
                            inputmode="numeric"
                            autocomplete="off"
                        >

                        <div class="pagamento-campos-duplos">
                            <div>
                                <label for="validade">Validade</label>
                                <input
                                    type="text"
                                    id="validade"
                                    name="validade"
                                    placeholder="MM/AA"
                                    maxlength="5"
                                    autocomplete="off"
                                >
                            </div>

                            <div>
                                <label for="cvv">CVV</label>
                                <input
                                    type="password"
                                    id="cvv"
                                    name="cvv"
                                    placeholder="123"
                                    maxlength="4"
                                    inputmode="numeric"
                                    autocomplete="off"
                                >
                            </div>
                        </div>

                        <label for="parcelas_cartao">Parcelamento</label>
                        <select id="parcelas_cartao" name="parcelas">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>">
                                    <?= $i ?>x de <?= formatarDinheiroPagamento((float) $reserva['valor_total'] / $i) ?>
                                </option>
                            <?php endfor; ?>
                        </select>

                        <p class="pagamento-ajuda">
                            Utilize dados fictícios. Nenhum dado do cartão será salvo.
                        </p>
                    </div>

                    <button type="submit" class="pagamento-botao pagamento-finalizar">
                        Finalizar pagamento
                    </button>

                    <p class="pagamento-seguranca">
                        Pagamento demonstrativo para fins acadêmicos.
                    </p>

                </form>
            </section>

            <aside class="pagamento-resumo">
                <h2>Resumo da reserva</h2>

                <div class="pagamento-reserva-codigo">
                    <span>Código da reserva</span>
                    <strong><?= escaparPagamento($reserva['codigo']) ?></strong>
                </div>

                <div class="pagamento-tipo">
                    <?php if ($reserva['tipo'] === 'voo'): ?>
                        <span>✈</span>
                        <strong>Passagem aérea</strong>
                    <?php elseif ($reserva['tipo'] === 'hotel'): ?>
                        <span>⌂</span>
                        <strong>Hospedagem</strong>
                    <?php else: ?>
                        <span>✦</span>
                        <strong>Pacote de viagem</strong>
                    <?php endif; ?>
                </div>

                <div class="pagamento-detalhes">

                    <?php foreach ($detalhes as $item): ?>

                        <?php if ($reserva['tipo'] === 'voo'): ?>

                            <div class="pagamento-item">
                                <strong>
                                    <?= $item['sentido'] === 'ida' ? 'Voo de ida' : 'Voo de volta' ?>
                                </strong>

                                <p>
                                    <?= escaparPagamento($item['cidade_origem']) ?>
                                    (<?= escaparPagamento($item['codigo_origem']) ?>)
                                    →
                                    <?= escaparPagamento($item['cidade_destino']) ?>
                                    (<?= escaparPagamento($item['codigo_destino']) ?>)
                                </p>

                                <small>
                                    <?= date('d/m/Y H:i', strtotime($item['horario_partida'])) ?>
                                    até
                                    <?= date('d/m/Y H:i', strtotime($item['horario_chegada'])) ?>
                                </small>

                                <strong><?= formatarDinheiroPagamento($item['preco']) ?></strong>
                            </div>

                        <?php elseif ($reserva['tipo'] === 'hotel'): ?>

                            <div class="pagamento-item">
                                <strong><?= escaparPagamento($item['hotel']) ?></strong>
                                <p><?= escaparPagamento($item['cidade']) ?>, <?= escaparPagamento($item['pais']) ?></p>
                                <p>Quarto: <?= escaparPagamento($item['quarto']) ?></p>
                                <p>Check-in: <?= date('d/m/Y', strtotime($item['checkin'])) ?></p>
                                <p>Check-out: <?= date('d/m/Y', strtotime($item['checkout'])) ?></p>
                                <p>Quantidade de quartos: <?= (int) $item['quantidade_quartos'] ?></p>
                                <strong><?= formatarDinheiroPagamento($item['preco_total']) ?></strong>
                            </div>

                        <?php else: ?>

                            <div class="pagamento-item">
                                <strong><?= escaparPagamento($item['pacote']) ?></strong>
                                <p><?= escaparPagamento($item['destino']) ?></p>
                                <p><?= escaparPagamento($item['cidade']) ?>, <?= escaparPagamento($item['pais']) ?></p>
                                <p>Hotel: <?= escaparPagamento($item['hotel']) ?></p>
                                <p><?= (int) $item['quantidade_noites'] ?> noites</p>
                            </div>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <div class="pagamento-total">
                    <span>Total da reserva</span>
                    <strong><?= formatarDinheiroPagamento($reserva['valor_total']) ?></strong>
                </div>
            </aside>

        </div>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const opcoes = document.querySelectorAll('input[name="metodo"]');
    const areaPix = document.getElementById('areaPix');
    const areaCartao = document.getElementById('areaCartao');

    const camposCartao = [
        document.getElementById('nome_cartao'),
        document.getElementById('numero_cartao'),
        document.getElementById('validade'),
        document.getElementById('cvv')
    ];

    const parcelasPix = document.getElementById('parcelasPix');
    const parcelasCartao = document.getElementById('parcelas_cartao');

    function atualizarPagamento() {
        const metodo = document.querySelector('input[name="metodo"]:checked')?.value;
        const cartaoSelecionado = metodo === 'cartao_credito';

        areaPix.hidden = cartaoSelecionado;
        areaCartao.hidden = !cartaoSelecionado;

        camposCartao.forEach(function (campo) {
            campo.required = cartaoSelecionado;
            campo.disabled = !cartaoSelecionado;
        });

        parcelasPix.disabled = cartaoSelecionado;
        parcelasCartao.disabled = !cartaoSelecionado;
    }

    opcoes.forEach(function (opcao) {
        opcao.addEventListener('change', atualizarPagamento);
    });

    document.getElementById('numero_cartao')?.addEventListener('input', function () {
        let valor = this.value.replace(/\D/g, '').slice(0, 19);
        this.value = valor.replace(/(.{4})/g, '$1 ').trim();
    });

    document.getElementById('validade')?.addEventListener('input', function () {
        let valor = this.value.replace(/\D/g, '').slice(0, 4);

        if (valor.length > 2) {
            valor = valor.slice(0, 2) + '/' + valor.slice(2);
        }

        this.value = valor;
    });

    document.getElementById('cvv')?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
    });

    atualizarPagamento();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
