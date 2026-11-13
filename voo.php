<?php

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ida = filter_input(INPUT_GET, 'ida', FILTER_VALIDATE_INT);
$volta = filter_input(INPUT_GET, 'volta', FILTER_VALIDATE_INT);

$passageiros = filter_input(INPUT_GET, 'passageiros', FILTER_VALIDATE_INT);

if (!$passageiros || $passageiros < 1 || $passageiros > 6) {
  $passageiros = 1;
}

/*
|--------------------------------------------------------------------------
| Busca um voo e seus trechos
|--------------------------------------------------------------------------
*/

function carregarVoo($pdo, $id, $passageiros)
{
  $stmt = $pdo->prepare("
        SELECT *
        FROM voos
        WHERE id = :id
          AND ativo = 1
          AND assentos_disponiveis >= :passageiros
        LIMIT 1
    ");

  $stmt->execute([
    'id' => $id,
    'passageiros' => $passageiros
  ]);

  $voo = $stmt->fetch();

  if (!$voo) {
    return null;
  }

  $stmt = $pdo->prepare("
        SELECT
            tv.*,
            ca.nome AS companhia,
            ca.logo,
            origem.cidade AS cidade_origem,
            origem.codigo_iata AS origem_iata,
            destino.cidade AS cidade_destino,
            destino.codigo_iata AS destino_iata
        FROM trechos_voos tv
        INNER JOIN companhias_aereas ca
            ON ca.id = tv.companhia_id
        INNER JOIN aeroportos origem
            ON origem.id = tv.aeroporto_origem_id
        INNER JOIN aeroportos destino
            ON destino.id = tv.aeroporto_destino_id
        WHERE tv.voo_id = :id
          AND ca.ativo = 1
          AND origem.ativo = 1
          AND destino.ativo = 1
        ORDER BY tv.ordem ASC
    ");

  $stmt->execute(['id' => $id]);

  $trechos = $stmt->fetchAll();

  if (!$trechos) {
    return null;
  }

  return [
    'voo' => $voo,
    'trechos' => $trechos
  ];
}

/*
|--------------------------------------------------------------------------
| Define se é somente ida ou ida e volta
|--------------------------------------------------------------------------
*/

$voosSelecionados = [];
$erro = '';

if ($id && !$ida && !$volta) {

  // Somente ida

  $resultado = carregarVoo($pdo, $id, $passageiros);

  if (!$resultado) {
    $erro = 'Esse voo não está mais disponível ou não possui assentos suficientes.';
  } else {
    $voosSelecionados[] = [
      'tipo' => 'Ida',
      'voo' => $resultado['voo'],
      'trechos' => $resultado['trechos']
    ];
  }
} elseif ($ida && $volta && !$id && $ida !== $volta) {

  // Ida e volta

  $resultadoIda = carregarVoo($pdo, $ida, $passageiros);
  $resultadoVolta = carregarVoo($pdo, $volta, $passageiros);

  if (!$resultadoIda || !$resultadoVolta) {
    $erro = 'Um dos voos selecionados não está mais disponível ou não possui assentos suficientes.';
  } else {

    $primeiroIda = $resultadoIda['trechos'][0];
    $ultimoIda = $resultadoIda['trechos'][count($resultadoIda['trechos']) - 1];

    $primeiroVolta = $resultadoVolta['trechos'][0];
    $ultimoVolta = $resultadoVolta['trechos'][count($resultadoVolta['trechos']) - 1];

    // Confere se a volta faz o caminho inverso da ida

    if (
      (int) $ultimoIda['aeroporto_destino_id'] !== (int) $primeiroVolta['aeroporto_origem_id'] ||
      (int) $primeiroIda['aeroporto_origem_id'] !== (int) $ultimoVolta['aeroporto_destino_id']
    ) {
      $erro = 'Os voos selecionados não correspondem à mesma viagem.';
    } elseif (
      strtotime($primeiroVolta['horario_partida']) <=
      strtotime($primeiroIda['horario_partida'])
    ) {
      $erro = 'A data do voo de volta precisa ser posterior à data do voo de ida.';
    } else {

      $voosSelecionados[] = [
        'tipo' => 'Ida',
        'voo' => $resultadoIda['voo'],
        'trechos' => $resultadoIda['trechos']
      ];

      $voosSelecionados[] = [
        'tipo' => 'Volta',
        'voo' => $resultadoVolta['voo'],
        'trechos' => $resultadoVolta['trechos']
      ];
    }
  }
} else {
  $erro = 'Não foi possível identificar os voos selecionados.';
}

/*
|--------------------------------------------------------------------------
| Calcula os valores
|--------------------------------------------------------------------------
*/

$totalPorPessoa = 0;

foreach ($voosSelecionados as $viagem) {
  $totalPorPessoa += (float) $viagem['voo']['preco'];
}

$totalViagem = $totalPorPessoa * $passageiros;

$titulo = 'Detalhes da viagem | Moovplus';

include __DIR__ . '/includes/header.php';

?>

<section class="secao pagina-voo">
  <div class="container">

    <?php if ($erro): ?>

      <div class="mensagem-voos">
        <?= e($erro) ?>
      </div>

      <a href="index.php" class="botao">
        Fazer nova busca
      </a>

    <?php else: ?>

      <?php
      $primeiroVoo = $voosSelecionados[0];
      $ultimoVoo = $voosSelecionados[count($voosSelecionados) - 1];

      $primeiroTrecho = $primeiroVoo['trechos'][0];
      $ultimoTrecho = $primeiroVoo['trechos'][count($primeiroVoo['trechos']) - 1];

      if (count($voosSelecionados) === 2) {
        $destinoFinal = $ultimoVoo['trechos'][count($ultimoVoo['trechos']) - 1];
      } else {
        $destinoFinal = $ultimoTrecho;
      }
      ?>

      <div class="voo-detalhe-cabecalho">
        <div>
          <span class="voo-detalhe-etiqueta">
            DETALHES DA VIAGEM
          </span>

          <h1>
            <?= e($primeiroTrecho['cidade_origem']) ?>
            para
            <?= e($destinoFinal['cidade_destino']) ?>
          </h1>

          <p>
            <?= count($voosSelecionados) === 2
              ? 'Viagem de ida e volta'
              : 'Viagem somente de ida' ?>
            ·
            <?= (int) $passageiros ?>
            <?= $passageiros === 1 ? 'passageiro' : 'passageiros' ?>
          </p>
        </div>
      </div>

      <div class="voo-detalhe-layout">

        <div class="voo-detalhe-principal">

          <?php foreach ($voosSelecionados as $viagem): ?>

            <?php
            $voo = $viagem['voo'];
            $trechos = $viagem['trechos'];

            $primeiroTrecho = $trechos[0];
            $ultimoTrecho = $trechos[count($trechos) - 1];

            $duracao = (int) $voo['duracao_total_minutos'];
            $horas = intdiv($duracao, 60);
            $minutos = $duracao % 60;
            ?>

            <div class="card-detalhe">

              <h2>Voo de <?= e($viagem['tipo']) ?></h2>

              <div class="resumo-rota">

                <div>
                  <span>ORIGEM</span>

                  <strong>
                    <?= e($primeiroTrecho['origem_iata']) ?>
                  </strong>

                  <p>
                    <?= e($primeiroTrecho['cidade_origem']) ?>
                  </p>
                </div>

                <div class="resumo-rota-icone">
                  <span>✈</span>
                  <div></div>
                </div>

                <div>
                  <span>DESTINO</span>

                  <strong>
                    <?= e($ultimoTrecho['destino_iata']) ?>
                  </strong>

                  <p>
                    <?= e($ultimoTrecho['cidade_destino']) ?>
                  </p>
                </div>

              </div>

              <div class="detalhe-informacoes">

                <div>
                  <span>Duração total</span>

                  <strong>
                    <?= $horas ?>h <?= $minutos ?>min
                  </strong>
                </div>

                <div>
                  <span>Escalas</span>

                  <strong>
                    <?= (int) $voo['quantidade_escalas'] === 0
                      ? 'Sem escalas'
                      : (int) $voo['quantidade_escalas'] ?>
                  </strong>
                </div>

                <div>
                  <span>Assentos disponíveis</span>

                  <strong>
                    <?= (int) $voo['assentos_disponiveis'] ?>
                  </strong>
                </div>

              </div>

            </div>

            <div class="card-detalhe">

              <h2>Itinerário do voo de <?= e($viagem['tipo']) ?></h2>

              <div class="lista-trechos">

                <?php foreach ($trechos as $indice => $trecho): ?>

                  <div class="trecho-item">

                    <div class="trecho-numero">
                      <?= $indice + 1 ?>
                    </div>

                    <div class="trecho-conteudo">

                      <div class="trecho-companhia">

                        <?php if (!empty($trecho['logo'])): ?>

                          <img
                            src="<?= e($trecho['logo']) ?>"
                            alt="<?= e($trecho['companhia']) ?>">

                        <?php endif; ?>

                        <div>
                          <strong>
                            <?= e($trecho['companhia']) ?>
                          </strong>

                          <small>
                            Voo <?= e($trecho['numero_voo']) ?>
                          </small>
                        </div>

                      </div>

                      <div class="trecho-horarios">

                        <div>

                          <strong>
                            <?= date('H:i', strtotime($trecho['horario_partida'])) ?>
                          </strong>

                          <span>
                            <?= e($trecho['origem_iata']) ?>
                          </span>

                          <small>
                            <?= e($trecho['cidade_origem']) ?>
                          </small>

                          <?php if (!empty($trecho['codigo_terminal_origem'])): ?>

                            <small>
                              Terminal <?= e($trecho['codigo_terminal_origem']) ?>
                            </small>

                          <?php endif; ?>

                          <small>
                            <?= date('d/m/Y', strtotime($trecho['horario_partida'])) ?>
                          </small>

                        </div>

                        <div class="trecho-linha">
                          <span>✈</span>
                          <div></div>
                        </div>

                        <div>

                          <strong>
                            <?= date('H:i', strtotime($trecho['horario_chegada'])) ?>
                          </strong>

                          <span>
                            <?= e($trecho['destino_iata']) ?>
                          </span>

                          <small>
                            <?= e($trecho['cidade_destino']) ?>
                          </small>

                          <?php if (!empty($trecho['codigo_terminal_destino'])): ?>

                            <small>
                              Terminal <?= e($trecho['codigo_terminal_destino']) ?>
                            </small>

                          <?php endif; ?>

                          <small>
                            <?= date('d/m/Y', strtotime($trecho['horario_chegada'])) ?>
                          </small>

                        </div>

                      </div>

                    </div>

                  </div>

                  <?php if ($indice < count($trechos) - 1): ?>

                    <div class="trecho-conexao">
                      <span>Conexão</span>
                    </div>

                  <?php endif; ?>

                <?php endforeach; ?>

              </div>

            </div>

          <?php endforeach; ?>

        </div>

        <aside class="card-preco-voo">

          <span class="voo-detalhe-etiqueta">
            VALOR DA VIAGEM
          </span>

          <p>
            Preço por pessoa
          </p>

          <strong class="preco-detalhe">
            <?= moeda($totalPorPessoa) ?>
          </strong>

          <div class="preco-divisor"></div>

          <?php foreach ($voosSelecionados as $viagem): ?>

            <div class="preco-linha">
              <span>
                Voo de <?= e($viagem['tipo']) ?>
              </span>

              <strong>
                <?= moeda($viagem['voo']['preco']) ?>
              </strong>
            </div>

          <?php endforeach; ?>

          <div class="preco-linha">
            <span>Passageiros</span>

            <strong>
              <?= (int) $passageiros ?>
            </strong>
          </div>

          <div class="preco-divisor"></div>

          <div class="preco-linha">
            <strong>Total da viagem</strong>

            <strong>
              <?= moeda($totalViagem) ?>
            </strong>
          </div>

          <?php if (count($voosSelecionados) === 2): ?>

            <a
              href="reservar.php?ida=<?= (int) $ida ?>&volta=<?= (int) $volta ?>&passageiros=<?= (int) $passageiros ?>"
              class="botao botao-reservar">
              Continuar para reserva
            </a>

          <?php else: ?>

            <a
              href="reservar.php?voo=<?= (int) $id ?>&passageiros=<?= (int) $passageiros ?>"
              class="botao botao-reservar">
              Continuar para reserva
            </a>

          <?php endif; ?>

          <small class="aviso-preco">
            O valor total considera todos os passageiros e os voos selecionados.
          </small>

        </aside>

      </div>

    <?php endif; ?>

  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>