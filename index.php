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

<section class="secao">
  <div class="container">
    <h2>Destinos populares</h2>
    <?php if (!$destaques): ?>
      <p class="vazio">Nenhum destino cadastrado ainda. Importe o arquivo moovplus_dados.sql.</p>
    <?php else: ?>
      <div class="grade">
        <?php foreach ($destaques as $d): ?>
          <a class="cartao" href="destino.php?id=<?= (int) $d['id'] ?>">
            <?php if (imagem_existe($d['imagem'])): ?>
              <img src="<?= e($d['imagem']) ?>" alt="Foto de <?= e($d['nome']) ?>" loading="lazy">
            <?php else: ?>
              <div class="sem-foto"><?= e($d['nome']) ?></div>
            <?php endif; ?>
            <div class="cartao-texto">
              <h3><?= e($d['nome']) ?></h3>
              <?php if ($d['preco_inicial']): ?>
                <p>Voos a partir de <?= moeda($d['preco_inicial']) ?></p>
              <?php endif; ?>
            </div>
          </a>
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
          <a class="cartao" href="<?= e(link_oferta($o)) ?>">
            <?php if (imagem_existe($o['imagem'])): ?>
              <img src="<?= e($o['imagem']) ?>" alt="<?= e($o['titulo']) ?>" loading="lazy">
            <?php else: ?>
              <div class="sem-foto"><?= e($o['titulo']) ?></div>
            <?php endif; ?>
            <div class="cartao-texto">
              <h3><?= e($o['titulo']) ?></h3>
              <p><?= e($o['descricao']) ?></p>
              <?php if ($o['preco_promocional']): ?>
                <p class="preco"><?= moeda($o['preco_promocional']) ?></p>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
