<?php

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


$origem = filter_input(INPUT_GET, 'origem', FILTER_VALIDATE_INT);
$destino = filter_input(INPUT_GET, 'destino', FILTER_VALIDATE_INT);
$passageiros = filter_input(INPUT_GET, 'passageiros', FILTER_VALIDATE_INT);

$dataIda = $_GET['data'] ?? '';
$dataVolta = $_GET['volta'] ?? '';
$tipoViagem = $_GET['tipo_viagem'] ?? 'ida_volta';
$vooIdaSelecionado = filter_input(INPUT_GET, 'voo_ida', FILTER_VALIDATE_INT);

if (!$passageiros || $passageiros < 1 || $passageiros > 6) {
  $passageiros = 1;
}

function dataValida($data)
{
  $objeto = DateTime::createFromFormat('!Y-m-d', $data);
  return $objeto && $objeto->format('Y-m-d') === $data;
}

function buscarVoos($pdo, $origem, $destino, $data, $passageiros)
{

  $sql = "
        SELECT
            v.id,
            v.preco,
            v.duracao_total_minutos,
            v.quantidade_escalas,
            v.assentos_disponiveis,

            ca.nome AS companhia,
            ca.logo,

            aeroporto_origem.cidade AS origem,
            aeroporto_origem.codigo_iata AS origem_iata,

            aeroporto_destino.cidade AS destino,
            aeroporto_destino.codigo_iata AS destino_iata,

            primeiro.horario_partida AS partida,
            ultimo.horario_chegada AS chegada

        FROM voos v

        INNER JOIN trechos_voos primeiro
            ON primeiro.voo_id = v.id
            AND primeiro.ordem = (
                SELECT MIN(t1.ordem)
                FROM trechos_voos t1
                WHERE t1.voo_id = v.id
            )

        INNER JOIN trechos_voos ultimo
            ON ultimo.voo_id = v.id
            AND ultimo.ordem = (
                SELECT MAX(t2.ordem)
                FROM trechos_voos t2
                WHERE t2.voo_id = v.id
            )

        INNER JOIN companhias_aereas ca
            ON ca.id = primeiro.companhia_id

        INNER JOIN aeroportos aeroporto_origem
            ON aeroporto_origem.id = primeiro.aeroporto_origem_id

        INNER JOIN aeroportos aeroporto_destino
            ON aeroporto_destino.id = ultimo.aeroporto_destino_id

        WHERE v.ativo = 1
          AND v.assentos_disponiveis >= :passageiros
          AND ca.ativo = 1
          AND aeroporto_origem.ativo = 1
          AND aeroporto_destino.ativo = 1
          AND primeiro.aeroporto_origem_id = :origem
          AND ultimo.aeroporto_destino_id = :destino
          AND primeiro.aeroporto_origem_id <> ultimo.aeroporto_destino_id
          AND DATE(primeiro.horario_partida) = :data

        ORDER BY primeiro.horario_partida ASC
    ";

  $stmt = $pdo->prepare($sql);

  $stmt->execute([
    'passageiros' => $passageiros,
    'origem' => $origem,
    'destino' => $destino,
    'data' => $data
  ]);

  return $stmt->fetchAll();
}

$erro = '';
$voos = [];
$voosVolta = [];
$vooIdaEscolhido = null;

if (
  !$origem ||
  !$destino ||
  $origem === $destino ||
  !dataValida($dataIda) ||
  !in_array($tipoViagem, ['ida', 'ida_volta'], true)
) {
  $erro = 'Preencha corretamente os dados da sua viagem.';
} elseif ($dataIda < date('Y-m-d')) {
  $erro = 'A data de ida não pode ser anterior a hoje.';
} elseif (
  $tipoViagem === 'ida_volta' &&
  (!dataValida($dataVolta) || $dataVolta <= $dataIda)
) {
  $erro = 'A data de volta deve ser posterior à data de ida.';
} else {

  $voos = buscarVoos($pdo, $origem, $destino, $dataIda, $passageiros);

  if ($tipoViagem === 'ida_volta') {

    if ($vooIdaSelecionado) {

      foreach ($voos as $voo) {
        if ((int) $voo['id'] === $vooIdaSelecionado) {
          $vooIdaEscolhido = $voo;
          break;
        }
      }

      if (!$vooIdaEscolhido) {
        $erro = 'O voo de ida selecionado não está disponível.';
      } else {
        $voosVolta = buscarVoos(
          $pdo,
          $destino,
          $origem,
          $dataVolta,
          $passageiros
        );
      }
    }
  }
}
function formatarDataBr($data)
{
  return dataValida($data) ? date('d/m/y', strtotime($data)) : '';
}

$iataOrigem = '';
$iataDestino = '';

if ($origem && $destino) {
  $stmtAero = $pdo->prepare("SELECT codigo_iata FROM aeroportos WHERE id = :id");

  $stmtAero->execute(['id' => $origem]);
  $iataOrigem = (string) $stmtAero->fetchColumn();

  $stmtAero->execute(['id' => $destino]);
  $iataDestino = (string) $stmtAero->fetchColumn();
}

$periodo = formatarDataBr($dataIda);
if ($tipoViagem === 'ida_volta' && formatarDataBr($dataVolta)) {
  $periodo .= ' - ' . formatarDataBr($dataVolta);
}

$totalViagem = $vooIdaEscolhido
  ? (float) $vooIdaEscolhido['preco'] * $passageiros
  : 0;
$titulo = 'Resultados de voos | Moovplus';


include __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="banner-busca">
    <div class="container">

      <nav class="breadcrumb-etapas" aria-label="Etapas da compra">
        <strong>Voos</strong>
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
               <strong><?= e($iataOrigem) ?></strong>
              <?= $tipoViagem === 'ida_volta' ? '⇄' : '→' ?>
              <strong><?= e($iataDestino) ?></strong>
            </span>
            <span> <?= str_pad((int) $passageiros, 2, '0', STR_PAD_LEFT) ?></span>
            <?php if ($periodo): ?>
              <span> <?= e($periodo) ?></span>
            <?php endif; ?>
            <span>R$ Reais</span>
            <a class="botao-alterar" href="index.php">Alterar busca</a>
          </div>
        </div>

        <div class="banner-busca-total">
          <div>
            <small>
              Total <strong><?= str_pad((int) $passageiros, 2, '0', STR_PAD_LEFT) ?></strong>
              <?= $passageiros === 1 ? 'viajante' : 'viajantes' ?>
            </small>
            <strong><?= moeda($totalViagem) ?></strong>
          </div>
          <button type="button" class="botao-informar" disabled>Informar<br>viajantes</button>
        </div>
      </div>

    </div>
  </div>

  <div class="resultados-cabecalho">
    <div>
      <span class="voo-detalhe-etiqueta">SUA VIAGEM</span>
      <h1>Voos disponíveis</h1>
      <p>
        <?= $tipoViagem === 'ida_volta' ? 'Ida e volta' : 'Somente ida' ?>
        · <?= (int) $passageiros ?>
        <?= $passageiros === 1 ? 'passageiro' : 'passageiros' ?>
      </p>
    </div>
  </div>

  <?php if ($erro): ?>

    <div class="mensagem-voos">
      <?= e($erro) ?>
    </div>

  <?php else: ?>

    <?php if ($tipoViagem === 'ida_volta' && $vooIdaEscolhido): ?>

      <div class="etapa-viagem">
        <span class="etapa-concluida">1. Ida selecionada</span>
        <span class="etapa-atual">2. Escolha a volta</span>
      </div>

      <div class="card-voo-selecionado">
        <strong>Sua ida</strong>
        <p>
          <?= e($vooIdaEscolhido['origem_iata']) ?>
          →
          <?= e($vooIdaEscolhido['destino_iata']) ?>
        </p>
        <span>
          <?= date('d/m/Y H:i', strtotime($vooIdaEscolhido['partida'])) ?>
        </span>
        <strong><?= moeda($vooIdaEscolhido['preco']) ?></strong>
        <a href="voos.php?origem=<?= (int) $origem ?>&destino=<?= (int) $destino ?>&data=<?= e($dataIda) ?>&volta=<?= e($dataVolta) ?>&tipo_viagem=ida_volta&passageiros=<?= (int) $passageiros ?>">
          Trocar ida
        </a>
      </div>

      <h2>Escolha seu voo de volta</h2>

      <?php if (!$voosVolta): ?>

        <p class="vazio">
          Não encontramos voos de volta para essa data.
        </p>

      <?php else: ?>

        <div class="grade grade-3">

          <?php foreach ($voosVolta as $v): ?>

            <article class="cartao-voo">

              <div class="voo-topo">
                <div class="voo-companhia">

                  <?php if (!empty($v['logo'])): ?>
                    <img src="<?= e($v['logo']) ?>" alt="<?= e($v['companhia']) ?>">
                  <?php endif; ?>

                  <span><?= e($v['companhia']) ?></span>
                </div>

                <span class="voo-tipo">
                  <?= (int) $v['quantidade_escalas'] === 0
                    ? 'Direto'
                    : (int) $v['quantidade_escalas'] . ' escalas' ?>
                </span>
              </div>

              <div class="voo-rotas">
                <div>
                  <strong><?= e($v['origem_iata']) ?></strong>
                  <p><?= e($v['origem']) ?></p>
                  <small><?= date('d/m/Y H:i', strtotime($v['partida'])) ?></small>
                </div>

                <div class="voo-destino">
                  <strong><?= e($v['destino_iata']) ?></strong>
                  <p><?= e($v['destino']) ?></p>
                </div>
              </div>

              <div class="voo-rodape">
                <div>
                  <small>Preço por pessoa</small>
                  <strong><?= moeda($v['preco']) ?></strong>
                </div>

                <a href="voo.php?ida=<?= (int) $vooIdaEscolhido['id'] ?>&volta=<?= (int) $v['id'] ?>&passageiros=<?= (int) $passageiros ?>">
                  Selecionar volta ›
                </a>
              </div>

            </article>

          <?php endforeach; ?>

        </div>

      <?php endif; ?>

    <?php else: ?>

      <div class="etapa-viagem">
        <span class="etapa-atual">1. Escolha seu voo de ida</span>
        <?php if ($tipoViagem === 'ida_volta'): ?>
          <span>2. Escolha a volta</span>
        <?php endif; ?>
      </div>

      <h2>Voos de ida</h2>

      <?php if (!$voos): ?>

        <p class="vazio">
          Não encontramos voos disponíveis para os dados selecionados.
        </p>

      <?php else: ?>

        <div class="grade grade-3">

          <?php foreach ($voos as $v): ?>

            <article class="cartao-voo">

              <div class="voo-topo">
                <div class="voo-companhia">

                  <?php if (!empty($v['logo'])): ?>
                    <img src="<?= e($v['logo']) ?>" alt="<?= e($v['companhia']) ?>">
                  <?php endif; ?>

                  <span><?= e($v['companhia']) ?></span>
                </div>

                <span class="voo-tipo">
                  <?= (int) $v['quantidade_escalas'] === 0
                    ? 'Direto'
                    : (int) $v['quantidade_escalas'] . ' escalas' ?>
                </span>
              </div>

              <div class="voo-rotas">
                <div>
                  <strong><?= e($v['origem_iata']) ?></strong>
                  <p><?= e($v['origem']) ?></p>
                  <small><?= date('d/m/Y H:i', strtotime($v['partida'])) ?></small>
                </div>

                <div class="voo-destino">
                  <strong><?= e($v['destino_iata']) ?></strong>
                  <p><?= e($v['destino']) ?></p>
                </div>
              </div>

              <div class="voo-rodape">
                <div>
                  <small>Preço por pessoa</small>
                  <strong><?= moeda($v['preco']) ?></strong>
                </div>

                <?php if ($tipoViagem === 'ida_volta'): ?>

                  <a href="voos.php?origem=<?= (int) $origem ?>&destino=<?= (int) $destino ?>&data=<?= e($dataIda) ?>&volta=<?= e($dataVolta) ?>&tipo_viagem=ida_volta&passageiros=<?= (int) $passageiros ?>&voo_ida=<?= (int) $v['id'] ?>">
                    Selecionar ida ›
                  </a>

                <?php else: ?>

                  <a href="voo.php?id=<?= (int) $v['id'] ?>&passageiros=<?= (int) $passageiros ?>">
                    Seguinte ›
                  </a>

                <?php endif; ?>

              </div>

            </article>

          <?php endforeach; ?>

        </div>

      <?php endif; ?>

    <?php endif; ?>

  <?php endif; ?>

</div>
</section>
<script src="js/busca-voos.js" defer></script>
<?php include __DIR__ . '/includes/footer.php'; ?>