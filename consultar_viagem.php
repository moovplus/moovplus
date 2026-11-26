<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$titulo = 'Consultar viagem | Moovplus';

function escaparConsulta($valor)
{
  return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dinheiroConsulta($valor)
{
  return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
$email = strtolower(trim($_POST['email'] ?? ''));

$erro = '';
$reserva = null;
$detalhes = [];
$pagamento = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($codigo === '' || $email === '') {
    $erro = 'Informe o código da reserva e o e-mail utilizado na compra.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erro = 'Informe um endereço de e-mail válido.';
  } else {
    $consulta = $pdo->prepare("
          SELECT
          r.*,
          u.nome,
          u.sobrenome,
          u.email
          FROM reservas r
          INNER JOIN usuarios u ON u.id = r.usuario_id
          INNER JOIN pagamentos pg ON pg.reserva_id = r.id
          WHERE r.codigo = :codigo
          AND LOWER(u.email) = :email
          AND r.status = 'confirmada'
          AND pg.status = 'aprovado'
          LIMIT 1
        ");

    $consulta->execute([
      ':codigo' => $codigo,
      ':email' => $email
    ]);

    $reserva = $consulta->fetch();

    if (!$reserva) {
      $erro = 'Não encontramos uma reserva com esses dados. Confira o código e o e-mail.';
    } else {
      $consulta = $pdo->prepare("
                SELECT *
                FROM pagamentos
                WHERE reserva_id = :reserva_id
                ORDER BY criado_em DESC
                LIMIT 1
            ");

      $consulta->execute([
        ':reserva_id' => $reserva['id']
      ]);

      $pagamento = $consulta->fetch();

      if ($reserva['tipo'] === 'voo') {
        $consulta = $pdo->prepare("
                    SELECT
                        rv.sentido,
                        rv.preco,
                        v.id AS voo_id,
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

        $consulta->execute([
          ':reserva_id' => $reserva['id']
        ]);

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

        $consulta->execute([
          ':reserva_id' => $reserva['id']
        ]);

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
    }
  }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container consulta-container">

  <section class="consulta-cabecalho">
    <span class="consulta-etapa">MOOVPLUS</span>
    <h1>Encontre sua viagem</h1>
    <p>Informe os dados da sua reserva para consultar os detalhes da viagem.</p>
  </section>

  <?php if (!$reserva): ?>
    <section class="consulta-card">
      <h1>Consulte sua viagem</h1>
      <p>Informe o código da reserva e o e-mail utilizado na compra.</p>

      <form method="POST" action="consultar_viagem.php" class="consulta-form">
        <div class="consulta-campo">
          <label for="codigo">Código da reserva</label>
          <input
            type="text"
            id="codigo"
            name="codigo"
            value="<?= escaparConsulta($codigo) ?>"
            placeholder="Ex: MOOV123456"
            required>
        </div>

        <div class="consulta-campo">
          <label for="email">E-mail da reserva</label>
          <input
            type="email"
            id="email"
            name="email"
            autocomplete="email"
            list="emails-salvos"
            aria-describedby="email-ajuda"
            value="<?= escaparConsulta($email) ?>"
            placeholder="seuemail@email.com"
            required>
          <datalist id="emails-salvos"></datalist>
          <small class="consulta-ajuda" id="email-ajuda">
            Seus e-mails ficam salvos somente neste navegador.
          </small>
        </div>

        <button type="submit" class="consulta-botao">
          Buscar viagem
        </button>
      </form>

      <?php if ($erro): ?>
        <div class="consulta-erro">
          <?= escaparConsulta($erro) ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($reserva): ?>

    <section class="consulta-resultado">

      <div class="consulta-resultado-topo">
        <div>
          <span class="consulta-legenda">RESERVA ENCONTRADA</span>
          <h2>Detalhes da sua viagem</h2>
        </div>

        <span class="consulta-status status-<?= escaparConsulta($reserva['status']) ?>">
          <?= escaparConsulta(ucfirst(str_replace('_', ' ', $reserva['status']))) ?>
        </span>
      </div>

      <div class="consulta-codigo">
        <span>Código da reserva</span>
        <strong><?= escaparConsulta($reserva['codigo']) ?></strong>
      </div>

      <div class="consulta-informacoes">
        <div>
          <span>Passageiro responsável</span>
          <strong>
            <?= escaparConsulta(trim($reserva['nome'] . ' ' . ($reserva['sobrenome'] ?? ''))) ?>
          </strong>
        </div>

        <div>
          <span>Data da reserva</span>
          <strong><?= date('d/m/Y', strtotime($reserva['data_criacao'])) ?></strong>
        </div>

        <div>
          <span>Tipo de reserva</span>
          <strong><?= escaparConsulta(ucfirst($reserva['tipo'])) ?></strong>
        </div>

        <div>
          <span>Valor total</span>
          <strong><?= dinheiroConsulta($reserva['valor_total']) ?></strong>
        </div>
      </div>

      <div class="consulta-detalhes">
        <h3>Informações da viagem</h3>

        <?php foreach ($detalhes as $item): ?>

          <?php if ($reserva['tipo'] === 'voo'): ?>

            <div class="consulta-item">
              <strong>
                <?= $item['sentido'] === 'ida' ? 'Voo de ida' : 'Voo de volta' ?>
              </strong>

              <div class="consulta-rota">
                <div>
                  <strong><?= escaparConsulta($item['codigo_origem']) ?></strong>
                  <span><?= escaparConsulta($item['cidade_origem']) ?></span>
                  <small><?= date('d/m/Y H:i', strtotime($item['horario_partida'])) ?></small>
                </div>

                <span class="consulta-seta">→</span>

                <div>
                  <strong><?= escaparConsulta($item['codigo_destino']) ?></strong>
                  <span><?= escaparConsulta($item['cidade_destino']) ?></span>
                  <small><?= date('d/m/Y H:i', strtotime($item['horario_chegada'])) ?></small>
                </div>
              </div>

              <strong><?= dinheiroConsulta($item['preco']) ?></strong>
            </div>

          <?php elseif ($reserva['tipo'] === 'hotel'): ?>

            <div class="consulta-item">
              <strong><?= escaparConsulta($item['hotel']) ?></strong>
              <p><?= escaparConsulta($item['cidade']) ?>, <?= escaparConsulta($item['pais']) ?></p>
              <p>Quarto: <?= escaparConsulta($item['quarto']) ?></p>
              <p>Check-in: <?= date('d/m/Y', strtotime($item['checkin'])) ?></p>
              <p>Check-out: <?= date('d/m/Y', strtotime($item['checkout'])) ?></p>
              <p>Quantidade de quartos: <?= (int) $item['quantidade_quartos'] ?></p>
              <strong><?= dinheiroConsulta($item['preco_total']) ?></strong>
            </div>

          <?php else: ?>

            <div class="consulta-item">
              <strong><?= escaparConsulta($item['pacote']) ?></strong>
              <p>Destino: <?= escaparConsulta($item['destino']) ?></p>
              <p><?= escaparConsulta($item['cidade']) ?>, <?= escaparConsulta($item['pais']) ?></p>
              <p>Hotel: <?= escaparConsulta($item['hotel']) ?></p>
              <p><?= (int) $item['quantidade_noites'] ?> noites</p>
            </div>

          <?php endif; ?>

        <?php endforeach; ?>

        <?php if (!$detalhes): ?>
          <p>Não há detalhes disponíveis para esta reserva.</p>
        <?php endif; ?>
      </div>

      <div class="consulta-pagamento">
        <h3>Informações do pagamento</h3>

        <?php if ($pagamento): ?>

          <div>
            <span>Forma de pagamento</span>
            <strong>
              <?= $pagamento['metodo'] === 'cartao_credito' ? 'Cartão de crédito' : strtoupper($pagamento['metodo']) ?>
            </strong>
          </div>

          <div>
            <span>Status do pagamento</span>
            <strong><?= escaparConsulta(ucfirst($pagamento['status'])) ?></strong>
          </div>

          <?php if ($pagamento['pago_em']): ?>
            <div>
              <span>Data do pagamento</span>
              <strong><?= date('d/m/Y H:i', strtotime($pagamento['pago_em'])) ?></strong>
            </div>
          <?php endif; ?>

        <?php else: ?>

          <p>Esta reserva ainda não possui pagamento registrado.</p>

        <?php endif; ?>
      </div>

    </section>

    <a href="index.php" class="consulta-botao consulta-retorno">
      Voltar ao início
    </a>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>