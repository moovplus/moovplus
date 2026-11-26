
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

function escaparReserva($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dinheiroReserva($valor)
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

$usuarioId = filter_var($_SESSION['usuario_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$usuarioId) {
    header('Location: login.php');
    exit;
}

$usuarioQuery = $pdo->prepare("
    SELECT id, nome, sobrenome, email, cpf, telefone, data_nascimento
    FROM usuarios
    WHERE id = :id AND ativo = 1
    LIMIT 1
");

$usuarioQuery->execute(['id' => $usuarioId]);
$usuario = $usuarioQuery->fetch();

if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

function carregarVooReserva(PDO $pdo, int $id): ?array
{
    $query = $pdo->prepare("
        SELECT
            v.id,
            v.preco,
            v.duracao_total_minutos,
            v.quantidade_escalas,
            v.assentos_disponiveis,
            tv.numero_voo,
            tv.horario_partida,
            tv.horario_chegada,
            tv.ordem,
            tv.codigo_terminal_origem,
            tv.codigo_terminal_destino,
            ca.nome AS companhia,
            ca.sigla AS sigla_companhia,
            ca.logo AS logo_companhia,
            ao.nome AS aeroporto_origem,
            ao.codigo_iata AS origem_iata,
            ao.cidade AS origem_cidade,
            ad.nome AS aeroporto_destino,
            ad.codigo_iata AS destino_iata,
            ad.cidade AS destino_cidade
        FROM voos v
        INNER JOIN trechos_voos tv ON tv.voo_id = v.id
        INNER JOIN companhias_aereas ca ON ca.id = tv.companhia_id
        INNER JOIN aeroportos ao ON ao.id = tv.aeroporto_origem_id
        INNER JOIN aeroportos ad ON ad.id = tv.aeroporto_destino_id
        WHERE v.id = :id
          AND v.ativo = 1
          AND ca.ativo = 1
          AND ao.ativo = 1
          AND ad.ativo = 1
        ORDER BY tv.ordem ASC
    ");

    $query->execute(['id' => $id]);
    $trechos = $query->fetchAll();

    if (!$trechos) {
        return null;
    }

    return [
        'id' => (int) $trechos[0]['id'],
        'preco' => (float) $trechos[0]['preco'],
        'assentos_disponiveis' => (int) $trechos[0]['assentos_disponiveis'],
        'duracao_total_minutos' => (int) $trechos[0]['duracao_total_minutos'],
        'quantidade_escalas' => (int) $trechos[0]['quantidade_escalas'],
        'trechos' => $trechos
    ];
}

$erro = [];
$voos = [];

$metodo = $_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET;

$passageiros = filter_input($metodo, 'passageiros', FILTER_VALIDATE_INT);
$idaId = filter_input($metodo, 'ida', FILTER_VALIDATE_INT);
$voltaId = filter_input($metodo, 'volta', FILTER_VALIDATE_INT);
$vooId = filter_input($metodo, 'voo', FILTER_VALIDATE_INT);

if (!$passageiros || $passageiros < 1 || $passageiros > 6) {
    $passageiros = 1;
}

if (!$idaId && !$voltaId && $vooId) {
    $idaId = $vooId;
}

if (!$idaId || $idaId < 1 || ($voltaId !== null && $voltaId < 0)) {
    $erro[] = 'Não foi possível identificar os voos selecionados.';
} else {
    $vooIda = carregarVooReserva($pdo, (int) $idaId);

    if (!$vooIda) {
        $erro[] = 'O voo selecionado não foi encontrado ou não está disponível.';
    } else {
        $voos[] = [
            'sentido' => 'ida',
            'titulo' => 'Voo de ida',
            'dados' => $vooIda
        ];
    }

    if (!$erro && $voltaId) {
        if ($voltaId === $idaId) {
            $erro[] = 'Os voos de ida e volta não podem ser iguais.';
        } else {
            $vooVolta = carregarVooReserva($pdo, (int) $voltaId);

            if (!$vooVolta) {
                $erro[] = 'O voo de volta não foi encontrado ou não está disponível.';
            } else {
                $primeiroTrechoIda = $vooIda['trechos'][0];
                $ultimoTrechoIda = $vooIda['trechos'][count($vooIda['trechos']) - 1];
                $primeiroTrechoVolta = $vooVolta['trechos'][0];
                $ultimoTrechoVolta = $vooVolta['trechos'][count($vooVolta['trechos']) - 1];

                if (
                    $primeiroTrechoIda['origem_iata'] !== $ultimoTrechoVolta['destino_iata'] ||
                    $ultimoTrechoIda['destino_iata'] !== $primeiroTrechoVolta['origem_iata']
                ) {
                    $erro[] = 'O voo de volta não corresponde ao trajeto de ida.';
                } elseif (
                    strtotime($primeiroTrechoVolta['horario_partida']) <=
                    strtotime($primeiroTrechoIda['horario_partida'])
                ) {
                    $erro[] = 'A data do voo de volta precisa ser posterior à ida.';
                } else {
                    $voos[] = [
                        'sentido' => 'volta',
                        'titulo' => 'Voo de volta',
                        'dados' => $vooVolta
                    ];
                }
            }
        }
    }
}

$valorUnitario = 0;

foreach ($voos as $voo) {
    $valorUnitario += $voo['dados']['preco'];
}

$valorTotal = $valorUnitario * $passageiros;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erro) {
    $token = $_POST['token'] ?? '';

    if (
        empty($_SESSION['token_reserva']) ||
        !hash_equals($_SESSION['token_reserva'], $token)
    ) {
        $erro[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $nomes = $_POST['nome'] ?? [];
        $sobrenomes = $_POST['sobrenome'] ?? [];
        $cpfs = $_POST['cpf'] ?? [];
        $nascimentos = $_POST['data_nascimento'] ?? [];
        $emails = $_POST['email'] ?? [];
        $telefones = $_POST['telefone'] ?? [];

        $dadosPassageiros = [];

        for ($i = 0; $i < $passageiros; $i++) {
            $nome = trim($nomes[$i] ?? '');
            $sobrenome = trim($sobrenomes[$i] ?? '');
            $cpf = preg_replace('/\D/', '', $cpfs[$i] ?? '');
            $nascimento = trim($nascimentos[$i] ?? '');
            $email = trim($emails[$i] ?? '');
            $telefone = trim($telefones[$i] ?? '');

            if (
                !$nome ||
                !$sobrenome ||
                strlen($cpf) !== 11 ||
                !$nascimento ||
                !$email ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                !$telefone
            ) {
                $erro[] = 'Preencha corretamente todos os dados dos passageiros.';
                break;
            }

            $dataNascimento = DateTime::createFromFormat('Y-m-d', $nascimento);

            if (
                !$dataNascimento ||
                $dataNascimento->format('Y-m-d') !== $nascimento ||
                $dataNascimento > new DateTime('today')
            ) {
                $erro[] = 'Informe uma data de nascimento válida para todos os passageiros.';
                break;
            }

            $dadosPassageiros[] = [
                'nome' => $nome,
                'sobrenome' => $sobrenome,
                'cpf' => $cpf,
                'data_nascimento' => $nascimento,
                'email' => $email,
                'telefone' => $telefone
            ];
        }

        if (!$erro) {
            try {
                $pdo->beginTransaction();

                $idsVoos = array_map(
                    fn($voo) => $voo['dados']['id'],
                    $voos
                );

                $placeholders = implode(',', array_fill(0, count($idsVoos), '?'));

                $bloqueio = $pdo->prepare("
                    SELECT id, assentos_disponiveis
                    FROM voos
                    WHERE id IN ($placeholders)
                    FOR UPDATE
                ");

                $bloqueio->execute($idsVoos);
                $assentos = $bloqueio->fetchAll();

                if (count($assentos) !== count($idsVoos)) {
                    throw new Exception('Não foi possível confirmar a disponibilidade dos voos.');
                }

                foreach ($assentos as $assento) {
                    if ((int) $assento['assentos_disponiveis'] < $passageiros) {
                        throw new Exception('Não há assentos suficientes em um dos voos selecionados.');
                    }
                }

                $codigo = 'MOOV' . strtoupper(bin2hex(random_bytes(4)));

                $criarReserva = $pdo->prepare("
                    INSERT INTO reservas (
                        usuario_id,
                        codigo,
                        tipo,
                        valor_total,
                        status
                    ) VALUES (
                        :usuario_id,
                        :codigo,
                        'voo',
                        :valor_total,
                        'pendente'
                    )
                ");

                $criarReserva->execute([
                    'usuario_id' => $usuarioId,
                    'codigo' => $codigo,
                    'valor_total' => $valorTotal
                ]);

                $reservaId = (int) $pdo->lastInsertId();

                $inserirVoo = $pdo->prepare("
                    INSERT INTO reserva_voos (
                        reserva_id,
                        voo_id,
                        sentido,
                        preco
                    ) VALUES (
                        :reserva_id,
                        :voo_id,
                        :sentido,
                        :preco
                    )
                ");

                $atualizarAssentos = $pdo->prepare("
                    UPDATE voos
                    SET assentos_disponiveis = assentos_disponiveis - :quantidade
                    WHERE id = :id
                      AND assentos_disponiveis >= :quantidade
                ");

                foreach ($voos as $voo) {
                    $inserirVoo->execute([
                        'reserva_id' => $reservaId,
                        'voo_id' => $voo['dados']['id'],
                        'sentido' => $voo['sentido'],
                        'preco' => $voo['dados']['preco'] * $passageiros
                    ]);

                    $atualizarAssentos->execute([
                        'quantidade' => $passageiros,
                        'id' => $voo['dados']['id']
                    ]);

                    if ($atualizarAssentos->rowCount() !== 1) {
                        throw new Exception('Não foi possível reservar os assentos selecionados.');
                    }
                }

                $inserirPassageiro = $pdo->prepare("
                    INSERT INTO passageiros (
                        nome,
                        sobrenome,
                        cpf,
                        data_nascimento,
                        email,
                        telefone
                    ) VALUES (
                        :nome,
                        :sobrenome,
                        :cpf,
                        :data_nascimento,
                        :email,
                        :telefone
                    )
                ");

                $vincularPassageiro = $pdo->prepare("
                    INSERT INTO reserva_passageiros (
                        reserva_id,
                        passageiro_id
                    ) VALUES (
                        :reserva_id,
                        :passageiro_id
                    )
                ");

                foreach ($dadosPassageiros as $passageiro) {
                    $inserirPassageiro->execute($passageiro);
                    $passageiroId = (int) $pdo->lastInsertId();

                    $vincularPassageiro->execute([
                        'reserva_id' => $reservaId,
                        'passageiro_id' => $passageiroId
                    ]);
                }

                $pdo->commit();

                unset($_SESSION['token_reserva']);

                header('Location: pagamento.php?reserva=' . $reservaId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erro[] = $e instanceof PDOException
                    ? 'Não foi possível criar a reserva. Verifique os dados e tente novamente.'
                    : $e->getMessage();
            }
        }
    }
}

if (empty($_SESSION['token_reserva'])) {
    $_SESSION['token_reserva'] = bin2hex(random_bytes(32));
}

$tokenReserva = $_SESSION['token_reserva'];

$titulo = 'Finalizar reserva';

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="css/style.css">

<div class="reserva-container">

    <div class="reserva-cabecalho">
        <h1>Finalize sua reserva</h1>
        <p>Confira os voos e informe os dados dos passageiros.</p>
    </div>

    <?php if ($erro): ?>

        <?php foreach ($erro as $mensagem): ?>
            <div class="reserva-erro">
                <?= escaparReserva($mensagem) ?>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <?php if (!$erro && $voos): ?>

        <form method="POST" action="reservar.php">

            <input type="hidden" name="ida" value="<?= (int) $idaId ?>">
            <input type="hidden" name="volta" value="<?= (int) ($voltaId ?? 0) ?>">
            <input type="hidden" name="passageiros" value="<?= (int) $passageiros ?>">
            <input type="hidden" name="token" value="<?= escaparReserva($tokenReserva) ?>">

            <div class="reserva-grade">

                <div>

                    <section class="reserva-card">
                        <h2>Resumo dos voos</h2>

                        <?php foreach ($voos as $voo): ?>

                            <?php
                            $dados = $voo['dados'];
                            $primeiro = $dados['trechos'][0];
                            $ultimo = $dados['trechos'][count($dados['trechos']) - 1];
                            ?>

                            <div class="reserva-voo">

                                <div class="reserva-voo-titulo">
                                    <?= escaparReserva($voo['titulo']) ?>
                                </div>

                                <div class="reserva-companhia">
                                    <?php if (!empty($primeiro['logo_companhia'])): ?>
                                        <img src="<?= escaparReserva($primeiro['logo_companhia']) ?>" alt="">
                                    <?php endif; ?>

                                    <span>
                                        <?= escaparReserva($primeiro['companhia']) ?>
                                        (<?= escaparReserva($primeiro['sigla_companhia']) ?>)
                                    </span>
                                </div>

                                <div class="reserva-rota">

                                    <div>
                                        <strong><?= escaparReserva($primeiro['origem_iata']) ?></strong>
                                        <small><?= escaparReserva($primeiro['origem_cidade']) ?></small>
                                        <small><?= date('d/m/Y H:i', strtotime($primeiro['horario_partida'])) ?></small>
                                    </div>

                                    <div class="reserva-rota-centro">
                                        <span>✈</span>
                                        <div>
                                            <?= $dados['quantidade_escalas'] > 0
                                                ? (int) $dados['quantidade_escalas'] . ' escala(s)'
                                                : 'Direto' ?>
                                        </div>
                                    </div>

                                    <div class="reserva-rota-destino">
                                        <strong><?= escaparReserva($ultimo['destino_iata']) ?></strong>
                                        <small><?= escaparReserva($ultimo['destino_cidade']) ?></small>
                                        <small><?= date('d/m/Y H:i', strtotime($ultimo['horario_chegada'])) ?></small>
                                    </div>

                                </div>

                                <div class="reserva-detalhe">
                                    <?= count($dados['trechos']) ?> trecho(s) ·
                                    <?= (int) $dados['duracao_total_minutos'] ?> minutos
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </section>

                    <section class="reserva-card">

                        <h2>Dados dos passageiros</h2>

                        <?php for ($i = 0; $i < $passageiros; $i++): ?>

                            <div class="reserva-passageiro">

                                <h3>Passageiro <?= $i + 1 ?></h3>

                                <div class="reserva-form-grid">

                                    <div class="reserva-campo">
                                        <label>Nome</label>
                                        <input
                                            type="text"
                                            name="nome[]"
                                            value="<?= escaparReserva($i === 0 ? $usuario['nome'] : '') ?>"
                                            required
                                        >
                                    </div>

                                    <div class="reserva-campo">
                                        <label>Sobrenome</label>
                                        <input
                                            type="text"
                                            name="sobrenome[]"
                                            value="<?= escaparReserva($i === 0 ? $usuario['sobrenome'] : '') ?>"
                                            required
                                        >
                                    </div>

                                    <div class="reserva-campo">
                                        <label>CPF</label>
                                        <input
                                            type="text"
                                            name="cpf[]"
                                            maxlength="14"
                                            placeholder="000.000.000-00"
                                            value="<?= escaparReserva($i === 0 ? $usuario['cpf'] : '') ?>"
                                            required
                                        >
                                    </div>

                                    <div class="reserva-campo">
                                        <label>Data de nascimento</label>
                                        <input
                                            type="date"
                                            name="data_nascimento[]"
                                            value="<?= escaparReserva($i === 0 ? $usuario['data_nascimento'] : '') ?>"
                                            max="<?= date('Y-m-d') ?>"
                                            required
                                        >
                                    </div>

                                    <div class="reserva-campo">
                                        <label>E-mail</label>
                                        <input
                                            type="email"
                                            name="email[]"
                                            value="<?= escaparReserva($i === 0 ? $usuario['email'] : '') ?>"
                                            required
                                        >
                                    </div>

                                    <div class="reserva-campo">
                                        <label>Telefone</label>
                                        <input
                                            type="tel"
                                            name="telefone[]"
                                            value="<?= escaparReserva($i === 0 ? $usuario['telefone'] : '') ?>"
                                            required
                                        >
                                    </div>

                                </div>

                            </div>

                        <?php endfor; ?>

                    </section>

                </div>

                <aside class="reserva-resumo">

                    <section class="reserva-card">

                        <h2>Resumo da reserva</h2>

                        <div class="reserva-resumo-linha">
                            <span>Tipo de viagem</span>
                            <strong><?= $voltaId ? 'Ida e volta' : 'Somente ida' ?></strong>
                        </div>

                        <div class="reserva-resumo-linha">
                            <span>Passageiros</span>
                            <strong><?= (int) $passageiros ?></strong>
                        </div>

                        <div class="reserva-resumo-linha">
                            <span>Valor por passageiro</span>
                            <strong><?= dinheiroReserva($valorUnitario) ?></strong>
                        </div>

                        <div class="reserva-total">
                            <span>Total</span>
                            <strong><?= dinheiroReserva($valorTotal) ?></strong>
                        </div>

                        <button type="submit" class="reserva-botao">
                            Continuar para pagamento
                        </button>

                        <p class="reserva-aviso">
                            Ao continuar, sua reserva será registrada e você será encaminhado para a página de pagamento.
                        </p>

                    </section>

                </aside>

            </div>

        </form>

    <?php elseif (!$erro): ?>

        <div class="reserva-vazia">
            Não foi possível carregar os voos selecionados.
        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
