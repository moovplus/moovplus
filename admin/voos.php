
<?php

require_once "../config/conexao.php";
require_once "proteger.php";

function h($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

$erro = "";
$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = $_POST["acao"] ?? "";

    try {
        if ($acao === "salvar") {
            $id = (int) ($_POST["id"] ?? 0);
            $codigo = trim($_POST["codigo_referencia"] ?? "");
            $preco = $_POST["preco"] ?? "";
            $assentos = (int) ($_POST["assentos_disponiveis"] ?? 0);
            $ativo = isset($_POST["ativo"]) ? 1 : 0;

            $companhias = $_POST["companhia_id"] ?? [];
            $origens = $_POST["aeroporto_origem_id"] ?? [];
            $destinos = $_POST["aeroporto_destino_id"] ?? [];
            $numeros = $_POST["numero_voo"] ?? [];
            $partidas = $_POST["horario_partida"] ?? [];
            $chegadas = $_POST["horario_chegada"] ?? [];
            $terminaisOrigem = $_POST["terminal_origem"] ?? [];
            $terminaisDestino = $_POST["terminal_destino"] ?? [];

            if ($preco === "" || !is_numeric($preco) || $preco < 0) {
                throw new Exception("Informe um preço válido para o voo.");
            }

            if ($assentos < 0) {
                throw new Exception("A quantidade de assentos não pode ser negativa.");
            }

            $totalTrechos = count($companhias);

            if ($totalTrechos < 1) {
                throw new Exception("Adicione pelo menos um trecho ao voo.");
            }

            if (
                count($origens) !== $totalTrechos ||
                count($destinos) !== $totalTrechos ||
                count($numeros) !== $totalTrechos ||
                count($partidas) !== $totalTrechos ||
                count($chegadas) !== $totalTrechos ||
                count($terminaisOrigem) !== $totalTrechos ||
                count($terminaisDestino) !== $totalTrechos
            ) {
                throw new Exception("Os dados dos trechos estão incompletos.");
            }

            $trechos = [];
            $primeiraPartida = null;
            $ultimaChegada = null;
            $aeroportoAnterior = null;

            for ($i = 0; $i < $totalTrechos; $i++) {
                $companhiaId = (int) $companhias[$i];
                $origemId = (int) $origens[$i];
                $destinoId = (int) $destinos[$i];
                $numero = trim($numeros[$i]);
                $partida = trim($partidas[$i]);
                $chegada = trim($chegadas[$i]);

                if (!$companhiaId || !$origemId || !$destinoId || $numero === "" || $partida === "" || $chegada === "") {
                    throw new Exception("Preencha todos os campos obrigatórios do trecho " . ($i + 1) . ".");
                }

                if ($origemId === $destinoId) {
                    throw new Exception("A origem e o destino do trecho " . ($i + 1) . " não podem ser iguais.");
                }

                if ($i > 0 && $origemId !== $aeroportoAnterior) {
                    throw new Exception("O aeroporto de origem do trecho " . ($i + 1) . " deve ser o destino do trecho anterior.");
                }

                $dataPartida = DateTime::createFromFormat("Y-m-d\TH:i", $partida);
                $dataChegada = DateTime::createFromFormat("Y-m-d\TH:i", $chegada);

                if (!$dataPartida || !$dataChegada || $dataChegada <= $dataPartida) {
                    throw new Exception("Confira os horários do trecho " . ($i + 1) . ". A chegada deve ser posterior à partida.");
                }

                if ($i > 0 && $dataPartida < $ultimaChegada) {
                    throw new Exception("A partida do trecho " . ($i + 1) . " não pode acontecer antes da chegada do trecho anterior.");
                }

                $trechos[] = [
                    "companhia_id" => $companhiaId,
                    "origem_id" => $origemId,
                    "destino_id" => $destinoId,
                    "numero" => $numero,
                    "partida" => str_replace("T", " ", $partida) . ":00",
                    "chegada" => str_replace("T", " ", $chegada) . ":00",
                    "terminal_origem" => trim($terminaisOrigem[$i] ?? ""),
                    "terminal_destino" => trim($terminaisDestino[$i] ?? "")
                ];

                if ($i === 0) {
                    $primeiraPartida = $dataPartida;
                }

                $ultimaChegada = $dataChegada;
                $aeroportoAnterior = $destinoId;
            }

            $duracao = (int) floor(
                ($ultimaChegada->getTimestamp() - $primeiraPartida->getTimestamp()) / 60
            );

            $escalas = $totalTrechos - 1;

            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE voos SET
                        codigo_referencia = ?, preco = ?,
                        duracao_total_minutos = ?, quantidade_escalas = ?,
                        assentos_disponiveis = ?, ativo = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $codigo ?: null, $preco, $duracao,
                    $escalas, $assentos, $ativo, $id
                ]);

                $pdo->prepare("DELETE FROM trechos_voos WHERE voo_id = ?")->execute([$id]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO voos
                     (codigo_referencia, preco, duracao_total_minutos,
                      quantidade_escalas, assentos_disponiveis, ativo)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $codigo ?: null, $preco, $duracao,
                    $escalas, $assentos, $ativo
                ]);

                $id = (int) $pdo->lastInsertId();
            }

            $stmtTrecho = $pdo->prepare(
                "INSERT INTO trechos_voos
                 (voo_id, companhia_id, aeroporto_origem_id,
                  aeroporto_destino_id, numero_voo, horario_partida,
                  horario_chegada, ordem, codigo_terminal_origem,
                  codigo_terminal_destino)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($trechos as $indice => $trecho) {
                $stmtTrecho->execute([
                    $id,
                    $trecho["companhia_id"],
                    $trecho["origem_id"],
                    $trecho["destino_id"],
                    $trecho["numero"],
                    $trecho["partida"],
                    $trecho["chegada"],
                    $indice + 1,
                    $trecho["terminal_origem"] ?: null,
                    $trecho["terminal_destino"] ?: null
                ]);
            }

            $pdo->commit();

            header("Location: voos.php?sucesso=salvo");
            exit;
        }

        if ($acao === "excluir") {
            $stmt = $pdo->prepare("DELETE FROM voos WHERE id = ?");
            $stmt->execute([(int) ($_POST["id"] ?? 0)]);

            header("Location: voos.php?sucesso=excluido");
            exit;
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = "Não foi possível concluir a operação. Verifique se o código de referência já existe ou se o voo está relacionado a uma reserva ou pacote.";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = $e->getMessage();
    }
}

if (isset($_GET["sucesso"])) {
    $mensagem = $_GET["sucesso"] === "excluido"
        ? "Voo excluído com sucesso!"
        : "Voo salvo com sucesso!";
}

$editando = null;
$trechosEditando = [];

if (isset($_GET["editar"])) {
    $stmt = $pdo->prepare("SELECT * FROM voos WHERE id = ?");
    $stmt->execute([(int) $_GET["editar"]]);
    $editando = $stmt->fetch();

    if ($editando) {
        $stmt = $pdo->prepare(
            "SELECT * FROM trechos_voos WHERE voo_id = ? ORDER BY ordem"
        );
        $stmt->execute([$editando["id"]]);
        $trechosEditando = $stmt->fetchAll();
    } else {
        $erro = "Voo não encontrado.";
    }
}

$companhias = $pdo->query(
    "SELECT id, nome FROM companhias_aereas WHERE ativo = 1 ORDER BY nome"
)->fetchAll();

$aeroportos = $pdo->query(
    "SELECT id, nome, codigo_iata, cidade FROM aeroportos WHERE ativo = 1 ORDER BY cidade"
)->fetchAll();

$voos = $pdo->query(
    "SELECT v.*,
        (SELECT COUNT(*) FROM trechos_voos t WHERE t.voo_id = v.id) AS total_trechos,
        (SELECT a.codigo_iata
         FROM trechos_voos t
         INNER JOIN aeroportos a ON a.id = t.aeroporto_origem_id
         WHERE t.voo_id = v.id
         ORDER BY t.ordem LIMIT 1) AS origem,
        (SELECT a.codigo_iata
         FROM trechos_voos t
         INNER JOIN aeroportos a ON a.id = t.aeroporto_destino_id
         WHERE t.voo_id = v.id
         ORDER BY t.ordem DESC LIMIT 1) AS destino,
        (SELECT t.horario_partida
         FROM trechos_voos t
         WHERE t.voo_id = v.id
         ORDER BY t.ordem LIMIT 1) AS partida
     FROM voos v
     ORDER BY v.criado_em DESC"
)->fetchAll();

if (!$trechosEditando) {
    $trechosEditando = [[
        "companhia_id" => "",
        "aeroporto_origem_id" => "",
        "aeroporto_destino_id" => "",
        "numero_voo" => "",
        "horario_partida" => "",
        "horario_chegada" => "",
        "codigo_terminal_origem" => "",
        "codigo_terminal_destino" => ""
    ]];
}

function dataLocal($data) {
    if (!$data) return "";
    return date("Y-m-d\TH:i", strtotime($data));
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voos | Moovplus</title>
    <link rel="stylesheet" href="assets/css/cadastros.css">
</head>
<body>

<div class="admin-layout">

    <aside class="sidebar">
        <h2>Moov+</h2>
        <p class="sidebar-descricao">Painel administrativo</p>

        <nav>
            <a href="index.php">Dashboard</a>
            <a href="destinos.php">Destinos</a>
            <a href="aeroportos.php">Aeroportos</a>
            <a href="companhias.php">Companhias aéreas</a>
            <a href="voos.php" class="ativo">Voos</a>
            <a href="hoteis.php">Hotéis</a>
            <a href="comodidades.php">Comodidades</a>
            <a href="quartos.php">Quartos</a>
            <a href="pacotes.php">Pacotes</a>
            <a href="ofertas.php">Ofertas</a>
            <a href="cupons.php">Cupons</a>
            <a href="reservas.php">Reservas</a>
            <a href="pagamentos.php">Pagamentos</a>
            <a href="usuarios.php">Usuários</a>

        </nav>

        <a href="sair.php" class="sair">Sair do painel</a>
    </aside>

    <main class="conteudo">

        <header class="topo">
            <div>
                <h1>Voos</h1>
                <p>Cadastre voos e seus respectivos trechos.</p>
            </div>
        </header>

        <?php if ($mensagem): ?>
            <div class="mensagem"><?= h($mensagem) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="mensagem erro"><?= h($erro) ?></div>
        <?php endif; ?>

        <?php if (!$companhias || !$aeroportos): ?>
            <div class="mensagem erro">
                Antes de cadastrar voos, cadastre pelo menos uma companhia aérea e dois aeroportos ativos.
            </div>
        <?php endif; ?>

        <section class="card-form">
            <h2><?= $editando ? "Editar voo" : "Cadastrar voo" ?></h2>

            <form method="POST" id="formVoo">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($editando["id"] ?? 0) ?>">

                <div class="form-grid">

                    <div class="campo">
                        <label>Código de referência</label>
                        <input type="text" name="codigo_referencia"
                            value="<?= h($editando["codigo_referencia"] ?? "") ?>"
                            placeholder="Ex.: VOO-001">
                    </div>

                    <div class="campo">
                        <label>Preço total (R$) *</label>
                        <input type="number" name="preco" min="0" step="0.01" required
                            value="<?= h($editando["preco"] ?? "") ?>">
                    </div>

                    <div class="campo">
                        <label>Assentos disponíveis *</label>
                        <input type="number" name="assentos_disponiveis" min="0" required
                            value="<?= h($editando["assentos_disponiveis"] ?? "0") ?>">
                    </div>

                    <div class="campo-checkbox">
                        <input type="checkbox" name="ativo" id="ativo"
                            <?= !$editando || !empty($editando["ativo"]) ? "checked" : "" ?>>
                        <label for="ativo">Voo ativo</label>
                    </div>

                </div>

                <div style="margin-top: 28px;">
                    <h2>Trechos do voo</h2>
                    <p class="vazio">
                        Para voos com escala, adicione um trecho para cada parte da viagem.
                        O destino de um trecho deve ser a origem do próximo.
                    </p>
                </div>

                <div id="listaTrechos">

                    <?php foreach ($trechosEditando as $indice => $t): ?>
                        <div class="card-form trecho-item" style="border: 1px solid #dce2eb; box-shadow: none; margin-top: 15px;">

                            <div class="topo">
                                <h3>Trecho <?= $indice + 1 ?></h3>
                                <button type="button" class="botao botao-excluir remover-trecho"
                                    <?= count($trechosEditando) === 1 ? "disabled" : "" ?>>
                                    Remover
                                </button>
                            </div>

                            <div class="form-grid">

                                <div class="campo">
                                    <label>Companhia aérea *</label>
                                    <select name="companhia_id[]" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($companhias as $c): ?>
                                            <option value="<?= (int) $c["id"] ?>"
                                                <?= (int) ($t["companhia_id"] ?? 0) === (int) $c["id"] ? "selected" : "" ?>>
                                                <?= h($c["nome"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="campo">
                                    <label>Número do voo *</label>
                                    <input type="text" name="numero_voo[]" required
                                        value="<?= h($t["numero_voo"] ?? "") ?>"
                                        placeholder="Ex.: LA3456">
                                </div>

                                <div class="campo">
                                    <label>Aeroporto de origem *</label>
                                    <select name="aeroporto_origem_id[]" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($aeroportos as $a): ?>
                                            <option value="<?= (int) $a["id"] ?>"
                                                <?= (int) ($t["aeroporto_origem_id"] ?? 0) === (int) $a["id"] ? "selected" : "" ?>>
                                                <?= h($a["codigo_iata"] . " - " . $a["cidade"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="campo">
                                    <label>Aeroporto de destino *</label>
                                    <select name="aeroporto_destino_id[]" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($aeroportos as $a): ?>
                                            <option value="<?= (int) $a["id"] ?>"
                                                <?= (int) ($t["aeroporto_destino_id"] ?? 0) === (int) $a["id"] ? "selected" : "" ?>>
                                                <?= h($a["codigo_iata"] . " - " . $a["cidade"]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="campo">
                                    <label>Data e horário de partida *</label>
                                    <input type="datetime-local" name="horario_partida[]" required
                                        value="<?= h(dataLocal($t["horario_partida"] ?? "")) ?>">
                                </div>

                                <div class="campo">
                                    <label>Data e horário de chegada *</label>
                                    <input type="datetime-local" name="horario_chegada[]" required
                                        value="<?= h(dataLocal($t["horario_chegada"] ?? "")) ?>">
                                </div>

                                <div class="campo">
                                    <label>Terminal de origem</label>
                                    <input type="text" name="terminal_origem[]"
                                        value="<?= h($t["codigo_terminal_origem"] ?? "") ?>">
                                </div>

                                <div class="campo">
                                    <label>Terminal de destino</label>
                                    <input type="text" name="terminal_destino[]"
                                        value="<?= h($t["codigo_terminal_destino"] ?? "") ?>">
                                </div>

                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <div class="acoes-form">
                    <button type="button" class="botao botao-secundario" id="adicionarTrecho">
                        + Adicionar trecho
                    </button>
                </div>

                <div class="acoes-form">
                    <button type="submit" class="botao">Salvar voo</button>

                    <?php if ($editando): ?>
                        <a href="voos.php" class="botao botao-secundario">Cancelar</a>
                    <?php endif; ?>
                </div>

            </form>
        </section>

        <section class="card-tabela">
            <h2>Voos cadastrados (<?= count($voos) ?>)</h2>

            <div class="tabela-responsiva">
                <table>
                    <thead>
                        <tr>
                            <th>Referência</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Partida</th>
                            <th>Trechos</th>
                            <th>Escalas</th>
                            <th>Preço</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($voos as $v): ?>
                        <tr>
                            <td><?= h($v["codigo_referencia"] ?? "—") ?></td>
                            <td><?= h($v["origem"] ?? "—") ?></td>
                            <td><?= h($v["destino"] ?? "—") ?></td>
                            <td>
                                <?= $v["partida"]
                                    ? date("d/m/Y H:i", strtotime($v["partida"]))
                                    : "—" ?>
                            </td>
                            <td><?= (int) $v["total_trechos"] ?></td>
                            <td><?= (int) $v["quantidade_escalas"] ?></td>
                            <td>R$ <?= number_format((float) $v["preco"], 2, ",", ".") ?></td>
                            <td><?= $v["ativo"] ? "Ativo" : "Inativo" ?></td>
                            <td>
                                <div class="acoes-tabela">
                                    <a class="botao botao-secundario"
                                       href="voos.php?editar=<?= (int) $v["id"] ?>">Editar</a>

                                    <form method="POST" onsubmit="return confirm('Deseja excluir este voo?')">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $v["id"] ?>">
                                        <button type="submit" class="botao botao-excluir">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$voos): ?>
                        <tr><td colspan="9" class="vazio">Nenhum voo cadastrado.</td></tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<script>
const listaTrechos = document.getElementById("listaTrechos");
const botaoAdicionar = document.getElementById("adicionarTrecho");

function atualizarTrechos() {
    const trechos = listaTrechos.querySelectorAll(".trecho-item");

    trechos.forEach((trecho, indice) => {
        trecho.querySelector("h3").textContent = `Trecho ${indice + 1}`;
        trecho.querySelector(".remover-trecho").disabled = trechos.length === 1;
    });
}

botaoAdicionar.addEventListener("click", () => {
    const primeiro = listaTrechos.querySelector(".trecho-item");
    const novo = primeiro.cloneNode(true);

    novo.querySelectorAll("input").forEach(input => {
        input.value = "";
    });

    novo.querySelectorAll("select").forEach(select => {
        select.selectedIndex = 0;
    });

    listaTrechos.appendChild(novo);
    atualizarTrechos();
});

listaTrechos.addEventListener("click", event => {
    if (event.target.classList.contains("remover-trecho")) {
        const trechos = listaTrechos.querySelectorAll(".trecho-item");

        if (trechos.length > 1) {
            event.target.closest(".trecho-item").remove();
            atualizarTrechos();
        }
    }
});

atualizarTrechos();
</script>

</body>
</html>
