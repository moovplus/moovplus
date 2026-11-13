
<?php

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$destino = filter_input(INPUT_GET, 'destino', FILTER_VALIDATE_INT);
$hospedes = filter_input(INPUT_GET, 'hospedes', FILTER_VALIDATE_INT);

$checkin = $_GET['checkin'] ?? '';
$checkout = $_GET['checkout'] ?? '';

if (!$hospedes || $hospedes < 1 || $hospedes > 6) {
    $hospedes = 1;
}

function dataValidaHotel($data)
{
    $objeto = DateTime::createFromFormat('!Y-m-d', $data);

    return $objeto && $objeto->format('Y-m-d') === $data;
}

function formatarDataHotel($data)
{
    return dataValidaHotel($data)
        ? date('d/m/Y', strtotime($data))
        : '';
}

$erro = '';
$hoteis = [];

if (
    !$destino ||
    !dataValidaHotel($checkin) ||
    !dataValidaHotel($checkout)
) {
    $erro = 'Preencha corretamente os dados da sua hospedagem.';

} elseif ($checkin < date('Y-m-d')) {
    $erro = 'O check-in não pode ser anterior a hoje.';

} elseif ($checkout <= $checkin) {
    $erro = 'O check-out deve ser posterior ao check-in.';

} else {

    $sql = "
        SELECT
            h.id,
            h.nome,
            h.descricao,
            h.endereco,
            h.estrelas,
            h.preco_diaria,

            d.nome AS destino_nome,

            (
                SELECT hi.caminho_imagem
                FROM hotel_imagens hi
                WHERE hi.hotel_id = h.id
                ORDER BY hi.imagem_principal DESC, hi.ordem ASC
                LIMIT 1
            ) AS imagem,

            MIN(q.preco_diaria) AS preco_quarto

        FROM hoteis h

        INNER JOIN destinos d
            ON d.id = h.destino_id

        INNER JOIN quartos q
            ON q.hotel_id = h.id
            AND q.ativo = 1
            AND q.capacidade >= :hospedes
            AND q.quantidade > COALESCE(
                (
                    SELECT SUM(rh.quantidade_quartos)
                    FROM reserva_hoteis rh

                    INNER JOIN reservas r
                        ON r.id = rh.reserva_id

                    WHERE rh.quarto_id = q.id
                      AND r.status IN (
                          'pendente',
                          'aguardando_pagamento',
                          'confirmada'
                      )
                      AND rh.checkin < :checkout
                      AND rh.checkout > :checkin
                ),
                0
            )

        WHERE h.ativo = 1
          AND d.ativo = 1
          AND h.destino_id = :destino

        GROUP BY
            h.id,
            h.nome,
            h.descricao,
            h.endereco,
            h.estrelas,
            h.preco_diaria,
            d.nome

        ORDER BY preco_quarto ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'hospedes' => $hospedes,
        'checkin' => $checkin,
        'checkout' => $checkout,
        'destino' => $destino
    ]);

    $hoteis = $stmt->fetchAll();
}

$destinoNome = '';

if ($destino) {
    $stmt = $pdo->prepare("
        SELECT nome
        FROM destinos
        WHERE id = :id
    ");

    $stmt->execute(['id' => $destino]);
    $destinoNome = (string) $stmt->fetchColumn();
}

$periodo = formatarDataHotel($checkin);

if (formatarDataHotel($checkout)) {
    $periodo .= ' - ' . formatarDataHotel($checkout);
}

$titulo = 'Resultados de hotéis | Moovplus';

include __DIR__ . '/includes/header.php';
?>

<div class="container">

    <div class="banner-busca">
        <div class="container">

            <nav class="breadcrumb-etapas" aria-label="Etapas da compra">
                <strong>Hotéis</strong>
                <span>›</span><span>Hóspedes</span>
                <span>›</span><span>Responsável</span>
                <span>›</span><span>Resumo</span>
                <span>›</span><span>Pagamento</span>
                <span>›</span><span>Confirmação</span>
            </nav>

            <div class="banner-busca-linha">

                <div class="banner-busca-info">
                    <h1>Esse é o resultado de sua busca</h1>

                    <div class="banner-busca-dados">
                        <span>
                            <strong><?= e($destinoNome ?: 'Destino') ?></strong>
                        </span>

                        <span>
                            <?= str_pad((int) $hospedes, 2, '0', STR_PAD_LEFT) ?>
                            <?= $hospedes === 1 ? 'hóspede' : 'hóspedes' ?>
                        </span>

                        <?php if ($periodo): ?>
                            <span><?= e($periodo) ?></span>
                        <?php endif; ?>

                        <span>R$ Reais</span>

                        <a class="botao-alterar" href="index.php">
                            Alterar busca
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <div class="resultados-cabecalho">
        <div>
            <span class="voo-detalhe-etiqueta">SUA HOSPEDAGEM</span>

            <h1>Hotéis disponíveis</h1>

            <p>
                <?= e($destinoNome) ?>
                · <?= (int) $hospedes ?>
                <?= $hospedes === 1 ? 'hóspede' : 'hóspedes' ?>
            </p>
        </div>
    </div>

    <?php if ($erro): ?>

        <div class="mensagem-voos">
            <?= e($erro) ?>
        </div>

    <?php elseif (!$hoteis): ?>

        <p class="vazio">
            Não encontramos hotéis com quartos disponíveis para os dados selecionados.
        </p>

    <?php else: ?>

        <div class="grade grade-3">

            <?php foreach ($hoteis as $h): ?>

                <article class="cartao-voo">

                    <div class="destino-foto">

                        <?php if (imagem_existe($h['imagem'])): ?>

                            <img
                                src="<?= e($h['imagem']) ?>"
                                alt="<?= e($h['nome']) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="sem-foto">
                                <?= e($h['nome']) ?>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="destino-info">

                        <span class="voo-detalhe-etiqueta">
                            <?= e($h['destino_nome']) ?>
                        </span>

                        <h3><?= e($h['nome']) ?></h3>

                        <?php if ((int) $h['estrelas'] > 0): ?>
                            <p>
                                <?= str_repeat('★', min((int) $h['estrelas'], 5)) ?>
                                <?= (int) $h['estrelas'] ?> estrelas
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($h['endereco'])): ?>
                            <p><?= e($h['endereco']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($h['descricao'])): ?>
                            <p><?= e($h['descricao']) ?></p>
                        <?php endif; ?>

                    </div>

                    <div class="voo-rodape">

                        <div>
                            <small>Diária a partir de</small>

                            <strong>
                                <?= moeda($h['preco_quarto']) ?>
                            </strong>
                        </div>

                        <a href="hotel.php?id=<?= (int) $h['id'] ?>&quartos=<?= (int) $hospedes ?>&checkin=<?= e($checkin) ?>&checkout=<?= e($checkout) ?>&hospedes=<?= (int) $hospedes ?>">
                            Ver hotel ›
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
