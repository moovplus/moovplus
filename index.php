<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

// A conexão já está disponível na variável $pdo.

$aeroportos = $pdo->query(
    "SELECT id, codigo_iata, cidade FROM aeroportos WHERE ativo = 1 ORDER BY cidade, codigo_iata"
)->fetchAll();

$destinos = $pdo->query(
    "SELECT id, nome FROM destinos WHERE ativo = 1 ORDER BY nome"
)->fetchAll();

$destaques = $pdo->query(
    "SELECT d.id, d.nome, d.estado, d.preco_inicial,
            (SELECT i.caminho_imagem FROM destino_imagens i
              WHERE i.destino_id = d.id
              ORDER BY i.imagem_principal DESC, i.ordem LIMIT 1) AS imagem
       FROM destinos d
      WHERE d.ativo = 1 AND d.destaque = 1
      ORDER BY d.nome
      LIMIT 4"
)->fetchAll();

$ofertas = $pdo->query(
    "SELECT * FROM ofertas
      WHERE ativo = 1
        AND (validade_inicio IS NULL OR validade_inicio <= NOW())
        AND (validade_fim IS NULL OR validade_fim >= NOW())
      ORDER BY criado_em DESC
      LIMIT 3"
)->fetchAll();
$voos = $pdo->query("
    SELECT
        v.id,
        v.preco,
        v.duracao_total_minutos,
        v.quantidade_escalas,
        v.assentos_disponiveis,

        (
            SELECT ca.nome
            FROM trechos_voos tv
            INNER JOIN companhias_aereas ca ON ca.id = tv.companhia_id
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS companhia,

        (
            SELECT ca.logo
            FROM trechos_voos tv
            INNER JOIN companhias_aereas ca ON ca.id = tv.companhia_id
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS logo,

        (
            SELECT a.cidade
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_origem_id
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS origem,

        (
            SELECT a.codigo_iata
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_origem_id
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS origem_iata,

        (
            SELECT a.cidade
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_destino_id
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS destino,

        (
            SELECT a.codigo_iata
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_destino_id
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS destino_iata,

        (
            SELECT tv.horario_partida
            FROM trechos_voos tv
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS partida,

        (
            SELECT tv.horario_chegada
            FROM trechos_voos tv
            WHERE tv.voo_id = v.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS chegada

    FROM voos v
    WHERE v.ativo = 1
      AND v.assentos_disponiveis > 0
      AND EXISTS (
          SELECT 1
          FROM trechos_voos tv
          INNER JOIN companhias_aereas ca ON ca.id = tv.companhia_id
          WHERE tv.voo_id = v.id
            AND ca.ativo = 1
      )
    ORDER BY v.criado_em DESC
    LIMIT 6
")->fetchAll();


$pacotesDestaque = $pdo->query("
    SELECT
        p.id,
        p.nome,
        p.preco,

        d.nome AS destino_nome,
        h.nome AS hotel_nome,

        (
            SELECT ca.nome
            FROM trechos_voos tv
            INNER JOIN companhias_aereas ca ON ca.id = tv.companhia_id
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS companhia,

        (
            SELECT ca.logo
            FROM trechos_voos tv
            INNER JOIN companhias_aereas ca ON ca.id = tv.companhia_id
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS logo,

        (
            SELECT a.cidade
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_origem_id
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS origem_ida,

        (
            SELECT a.codigo_iata
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_origem_id
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS origem_ida_iata,

        (
            SELECT a.cidade
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_destino_id
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS destino_ida,

        (
            SELECT a.codigo_iata
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_destino_id
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS destino_ida_iata,

        (
            SELECT tv.horario_partida
            FROM trechos_voos tv
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS partida_ida,

        (
            SELECT tv.horario_chegada
            FROM trechos_voos tv
            WHERE tv.voo_id = ida.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS chegada_ida,

        (
            SELECT a.cidade
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_origem_id
            WHERE tv.voo_id = volta.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS origem_volta,

        (
            SELECT a.codigo_iata
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_origem_id
            WHERE tv.voo_id = volta.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS origem_volta_iata,

        (
            SELECT a.cidade
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_destino_id
            WHERE tv.voo_id = volta.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS destino_volta,

        (
            SELECT a.codigo_iata
            FROM trechos_voos tv
            INNER JOIN aeroportos a ON a.id = tv.aeroporto_destino_id
            WHERE tv.voo_id = volta.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS destino_volta_iata,

        (
            SELECT tv.horario_partida
            FROM trechos_voos tv
            WHERE tv.voo_id = volta.id
            ORDER BY tv.ordem ASC
            LIMIT 1
        ) AS partida_volta,

        (
            SELECT tv.horario_chegada
            FROM trechos_voos tv
            WHERE tv.voo_id = volta.id
            ORDER BY tv.ordem DESC
            LIMIT 1
        ) AS chegada_volta

    FROM pacotes p
    INNER JOIN voos ida ON ida.id = p.voo_ida_id
    LEFT JOIN voos volta ON volta.id = p.voo_volta_id
    LEFT JOIN destinos d ON d.id = p.destino_id
    LEFT JOIN hoteis h ON h.id = p.hotel_id

    WHERE p.ativo = 1
      AND ida.ativo = 1
      AND (volta.id IS NULL OR volta.ativo = 1)

    ORDER BY p.criado_em DESC
    LIMIT 6
")->fetchAll();

$hoje   = date('Y-m-d');
$titulo = 'Moov+ | Voos, hotéis e pacotes';
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <h1>Para onde você quer ir?</h1>
    <p class="hero-texto">Compare voos, hotéis e pacotes em um só lugar.</p>

    <div class="busca">
      <div class="abas" role="tablist">
        <button type="button" class="aba ativa" role="tab" aria-selected="true"  data-alvo="form-voos">Voos</button>
        <button type="button" class="aba"        role="tab" aria-selected="false" data-alvo="form-hoteis">Hotéis</button>
        <button type="button" class="aba"        role="tab" aria-selected="false" data-alvo="form-pacotes">Pacotes</button>
      </div>

      <form id="form-voos" class="form-busca" action="voos.php" method="get">
        <label>Origem
          <select name="origem" required>
            <option value="">Cidade ou aeroporto</option>
            <?php foreach ($aeroportos as $a): ?>
              <option value="<?= (int) $a['id'] ?>"<?= $a['codigo_iata'] === 'GRU' ? ' selected' : '' ?>><?= e($a['cidade']) ?> (<?= e($a['codigo_iata']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Destino
          <select name="destino" required>
            <option value="">Cidade ou aeroporto</option>
            <?php foreach ($aeroportos as $a): ?>
              <option value="<?= (int) $a['id'] ?>"><?= e($a['cidade']) ?> (<?= e($a['codigo_iata']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Ida
          <input type="date" id="ida" name="data" min="<?= $hoje ?>" required>
        </label>
        <label>Volta
          <input type="date" id="volta" name="volta" min="<?= $hoje ?>">
        </label>
        <label>Passageiros
          <select name="passageiros">
            <?php for ($n = 1; $n <= 6; $n++): ?>
              <option value="<?= $n ?>"><?= $n ?> <?= $n === 1 ? 'adulto' : 'adultos' ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <button class="botao" type="submit">Buscar</button>
      </form>

      <form id="form-hoteis" class="form-busca" action="hoteis.php" method="get" hidden>
        <label>Destino
          <select name="destino" required>
            <option value="">Selecione</option>
            <?php foreach ($destinos as $d): ?>
              <option value="<?= (int) $d['id'] ?>"><?= e($d['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Check-in
          <input type="date" id="checkin" name="checkin" min="<?= $hoje ?>" required>
        </label>
        <label>Check-out
          <input type="date" id="checkout" name="checkout" min="<?= $hoje ?>" required>
        </label>
        <label>Hóspedes
          <select name="hospedes">
            <?php for ($n = 1; $n <= 6; $n++): ?>
              <option value="<?= $n ?>"><?= $n ?> <?= $n === 1 ? 'hóspede' : 'hóspedes' ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <button class="botao" type="submit">Buscar</button>
      </form>

      <form id="form-pacotes" class="form-busca" action="pacotes.php" method="get" hidden>
        <label>Destino
          <select name="destino">
            <option value="">Qualquer destino</option>
            <?php foreach ($destinos as $d): ?>
              <option value="<?= (int) $d['id'] ?>"><?= e($d['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="botao" type="submit">Ver pacotes</button>
      </form>
    </div>
  </div>
</section>

<section class="secao" id="destinos">
  <div class="container">
    <h2>Destinos populares</h2>
    <?php if (!$destaques): ?>
      <p class="vazio">Nenhum destino cadastrado ainda. Importe o arquivo moovplus_dados.sql.</p>
    <?php else: ?>
      <div class="grade">
        <?php foreach ($destaques as $d): ?>
          <a class="destino-cartao" href="destino.php?id=<?= (int) $d['id'] ?>">
            <div class="destino-foto">
              <?php if (imagem_existe($d['imagem'])): ?>
                <img src="<?= e($d['imagem']) ?>" alt="Foto de <?= e($d['nome']) ?>" loading="lazy">
              <?php else: ?>
                <div class="sem-foto"><?= e($d['nome']) ?></div>
              <?php endif; ?>

              <?php if (!empty($d['estado'])): ?>
                <span class="destino-badge"><?= e($d['estado']) ?></span>
              <?php endif; ?>
            </div>

            <div class="destino-info">
              <h3><?= e($d['nome']) ?></h3>
              <?php if ($d['preco_inicial']): ?>
                <small>Voos a partir de</small>
                <strong><?= moeda($d['preco_inicial']) ?></strong>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<section class="secao" id="voos">
    <div class="container">
        <h2>Voos disponíveis</h2>
        <?php if (!$voos): ?>
            <p class="vazio">Nenhum voo disponível no momento.</p>
        <?php else: ?>
            <div class="grade grade-3">
                <?php foreach ($voos as $v): ?>
                    <article class="cartao-voo">
                        <div class="voo-topo">
                            <div class="voo-companhia">
                                <?php if (!empty($v['logo'])): ?>
                                    <img src="<?= e($v['logo']) ?>" alt="<?= e($v['companhia'] ?? 'Companhia aérea') ?>">
                                <?php endif; ?>
                                <span><?= e($v['companhia'] ?? 'Companhia aérea') ?></span>
                            </div>
                            <span class="voo-tipo">
                                <?= (int) $v['quantidade_escalas'] === 0 ? 'Direto' : (int) $v['quantidade_escalas'] . ' escalas' ?>
                            </span>
                        </div>

                        <div class="voo-rotas">
                            <div>
                                <strong>✈ IDA</strong>
                                <p><?= e($v['origem_iata']) ?> - <?= e($v['destino_iata']) ?></p>
                                <small><?= date('d/m/Y H:i', strtotime($v['partida'])) ?></small>
                            </div>
                            <div class="voo-destino">
                                <strong><?= e($v['origem']) ?></strong>
                                <p><?= e($v['destino']) ?></p>
                            </div>
                        </div>

                        <div class="voo-rodape">
                            <div>
                                <small>Preço por pessoa a partir de</small>
                                <strong><?= moeda($v['preco']) ?></strong>
                            </div>
                            <a href="voos.php?id=<?= (int) $v['id'] ?>">Seguinte <span>›</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="secao" id="ofertas">
  <div class="container">
    <h2>Ofertas do mês</h2>
    <?php if (!$ofertas): ?>
      <p class="vazio">Nenhuma oferta no momento.</p>
    <?php else: ?>
      <div class="grade grade-3">
        <?php foreach ($ofertas as $o): ?>
          <?php
            $dias   = (int) ($o['pacote_dias'] ?? 0);
            $noites = (int) ($o['pacote_noites'] ?? max($dias - 1, 0));
            $promo  = (float) ($o['preco_promocional'] ?? 0);
            $antigo = (float) ($o['preco_original'] ?? 0);
            $economia = ($antigo > $promo && $promo > 0) ? $antigo - $promo : 0;
          ?>
          <a class="destino-cartao" href="<?= e(link_oferta($o)) ?>">
            <div class="destino-foto">
              <?php if (imagem_existe($o['imagem'])): ?>
                <img src="<?= e($o['imagem']) ?>" alt="<?= e($o['titulo']) ?>" loading="lazy">
              <?php else: ?>
                <div class="sem-foto"><?= e($o['titulo']) ?></div>
              <?php endif; ?>

              <?php if ($dias > 0): ?>
                <span class="destino-badge"><?= $dias ?> Dias / <?= $noites ?> Noites</span>
              <?php endif; ?>
            </div>

            <div class="destino-info">
              <h3><?= e($o['titulo']) ?></h3>
              <?php if (!empty($o['descricao'])): ?>
                <p class="oferta-desc"><?= e($o['descricao']) ?></p>
              <?php endif; ?>

              <?php if ($economia > 0): ?>
                <span class="oferta-economia">Economize <?= moeda($economia) ?></span>
              <?php endif; ?>

              <?php if ($promo > 0): ?>
                <small>Preço por pessoa</small>
                <div class="oferta-precos">
                  <strong><?= moeda($promo) ?></strong>
                  <?php if ($antigo > $promo): ?>
                    <s><?= moeda($antigo) ?></s>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
