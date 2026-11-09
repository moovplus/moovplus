-- Banco de dados MOOVPLUS (estrutura)
-- MySQL 8.0+ / MariaDB compatível em sua maior parte
-- Execute este arquivo primeiro; depois execute moovplus_dados.sql (dados de exemplo).
-- Para refazer do zero: DROP DATABASE moovplus; e execute de novo.

CREATE DATABASE IF NOT EXISTS moovplus
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE moovplus;

-- =========================================================
-- 1. USUÁRIOS E ADMINISTRADORES
-- =========================================================

CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  sobrenome VARCHAR(100) NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL, 
  cpf VARCHAR(14) NULL UNIQUE,
  telefone VARCHAR(30) NULL,
  data_nascimento DATE NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS administradores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL, 
  nivel ENUM('admin','editor') NOT NULL DEFAULT 'admin',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 2. DESTINOS E IMAGENS
-- =========================================================

CREATE TABLE IF NOT EXISTS destinos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  cidade VARCHAR(120) NOT NULL,
  estado VARCHAR(120) NULL,
  pais VARCHAR(120) NOT NULL,
  descricao TEXT NULL,
  melhor_epoca VARCHAR(190) NULL,
  como_chegar VARCHAR(255) NULL,
  preco_inicial DECIMAL(10,2) NULL,
  destaque TINYINT(1) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_destinos_local (cidade, estado, pais)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS destino_imagens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destino_id BIGINT UNSIGNED NOT NULL,
  caminho_imagem VARCHAR(500) NOT NULL,
  texto_alternativo VARCHAR(190) NULL,
  imagem_principal TINYINT(1) NOT NULL DEFAULT 0,
  ordem INT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_destino_imagens_destino
    FOREIGN KEY (destino_id) REFERENCES destinos(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_destino_imagens_ordem (destino_id, ordem)
) ENGINE=InnoDB;

-- Pontos turísticos (seção "O que fazer" da página de destino)
CREATE TABLE IF NOT EXISTS atracoes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destino_id BIGINT UNSIGNED NOT NULL,
  nome VARCHAR(150) NOT NULL,
  descricao VARCHAR(255) NULL,
  imagem VARCHAR(500) NULL,
  ordem INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_atracoes_destino
    FOREIGN KEY (destino_id) REFERENCES destinos(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_atracoes_destino (destino_id, ordem)
) ENGINE=InnoDB;

-- =========================================================
-- 3. COMPANHIAS, AEROPORTOS, VOOS E TRECHOS
-- =========================================================

CREATE TABLE IF NOT EXISTS companhias_aereas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  sigla VARCHAR(10) NULL,
  logo VARCHAR(500) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_companhia_sigla (sigla)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aeroportos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(190) NOT NULL,
  codigo_iata CHAR(3) NOT NULL UNIQUE,
  cidade VARCHAR(120) NOT NULL,
  estado VARCHAR(120) NULL,
  pais VARCHAR(120) NOT NULL,
  destino_id BIGINT UNSIGNED NULL, -- liga o aeroporto ao destino (voos para Salvador)
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_aeroportos_destino
    FOREIGN KEY (destino_id) REFERENCES destinos(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_aeroportos_cidade (cidade, pais)
) ENGINE=InnoDB;

-- Um voo representa uma opção/itinerário completo de UM sentido (ida OU volta).
-- Ida e volta = dois voos (veja pacotes.voo_ida_id / voo_volta_id e reserva_voos.sentido).
-- Cada etapa (inclusive escalas) fica em trechos_voos.
CREATE TABLE IF NOT EXISTS voos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo_referencia VARCHAR(60) NULL UNIQUE,
  preco DECIMAL(10,2) NOT NULL,
  duracao_total_minutos INT UNSIGNED NULL,
  quantidade_escalas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  assentos_disponiveis INT UNSIGNED NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_voos_preco (preco),
  INDEX idx_voos_ativo (ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trechos_voos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voo_id BIGINT UNSIGNED NOT NULL,
  companhia_id BIGINT UNSIGNED NOT NULL,
  aeroporto_origem_id BIGINT UNSIGNED NOT NULL,
  aeroporto_destino_id BIGINT UNSIGNED NOT NULL,
  numero_voo VARCHAR(30) NULL,
  horario_partida DATETIME NOT NULL,
  horario_chegada DATETIME NOT NULL,
  ordem SMALLINT UNSIGNED NOT NULL,
  codigo_terminal_origem VARCHAR(20) NULL,
  codigo_terminal_destino VARCHAR(20) NULL,
  CONSTRAINT fk_trechos_voo
    FOREIGN KEY (voo_id) REFERENCES voos(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_trechos_companhia
    FOREIGN KEY (companhia_id) REFERENCES companhias_aereas(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_trechos_origem
    FOREIGN KEY (aeroporto_origem_id) REFERENCES aeroportos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_trechos_destino
    FOREIGN KEY (aeroporto_destino_id) REFERENCES aeroportos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_trecho_horarios CHECK (horario_chegada > horario_partida),
  UNIQUE KEY uk_voo_ordem_trecho (voo_id, ordem),
  INDEX idx_trechos_horarios (horario_partida, horario_chegada)
) ENGINE=InnoDB;

-- =========================================================
-- 4. HOTÉIS, IMAGENS, COMODIDADES, QUARTOS E AVALIAÇÕES
-- =========================================================

CREATE TABLE IF NOT EXISTS hoteis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destino_id BIGINT UNSIGNED NOT NULL,
  nome VARCHAR(190) NOT NULL,
  descricao TEXT NULL,
  endereco VARCHAR(255) NULL,
  estrelas TINYINT UNSIGNED NULL,
  preco_diaria DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'preco a partir de',
  latitude DECIMAL(9,6) NULL,
  longitude DECIMAL(9,6) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_hoteis_destino
    FOREIGN KEY (destino_id) REFERENCES destinos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_hoteis_destino_preco (destino_id, preco_diaria),
  INDEX idx_hoteis_estrelas (estrelas)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hotel_imagens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id BIGINT UNSIGNED NOT NULL,
  caminho_imagem VARCHAR(500) NOT NULL,
  texto_alternativo VARCHAR(190) NULL,
  imagem_principal TINYINT(1) NOT NULL DEFAULT 0,
  ordem INT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hotel_imagens_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_hotel_imagens_ordem (hotel_id, ordem)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comodidades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL UNIQUE,
  icone VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hotel_comodidades (
  hotel_id BIGINT UNSIGNED NOT NULL,
  comodidade_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (hotel_id, comodidade_id),
  CONSTRAINT fk_hotel_comodidades_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_hotel_comodidades_comodidade
    FOREIGN KEY (comodidade_id) REFERENCES comodidades(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quartos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id BIGINT UNSIGNED NOT NULL,
  nome VARCHAR(150) NOT NULL,
  descricao TEXT NULL,
  capacidade TINYINT UNSIGNED NOT NULL DEFAULT 2,
  quantidade INT UNSIGNED NOT NULL DEFAULT 1,
  preco_diaria DECIMAL(10,2) NOT NULL,
  cancelamento_gratis TINYINT(1) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_quartos_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_quartos_hotel_preco (hotel_id, preco_diaria)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS avaliacoes_hoteis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  nota TINYINT UNSIGNED NOT NULL,
  comentario TEXT NULL,
  data_avaliacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_avaliacao_nota CHECK (nota BETWEEN 1 AND 5),
  CONSTRAINT fk_avaliacoes_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_avaliacoes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_avaliacoes_hotel_data (hotel_id, data_avaliacao)
) ENGINE=InnoDB;

-- =========================================================
-- 5. PACOTES E OFERTAS
-- =========================================================

CREATE TABLE IF NOT EXISTS pacotes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destino_id BIGINT UNSIGNED NOT NULL,
  hotel_id BIGINT UNSIGNED NOT NULL,
  voo_ida_id BIGINT UNSIGNED NOT NULL,
  voo_volta_id BIGINT UNSIGNED NULL,
  nome VARCHAR(190) NOT NULL,
  descricao TEXT NULL,
  quantidade_noites SMALLINT UNSIGNED NOT NULL,
  preco DECIMAL(10,2) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pacotes_destino
    FOREIGN KEY (destino_id) REFERENCES destinos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pacotes_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pacotes_voo_ida
    FOREIGN KEY (voo_ida_id) REFERENCES voos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_pacotes_voo_volta
    FOREIGN KEY (voo_volta_id) REFERENCES voos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_pacotes_destino_preco (destino_id, preco)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ofertas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(190) NOT NULL,
  descricao TEXT NULL,
  destino_id BIGINT UNSIGNED NULL,
  hotel_id BIGINT UNSIGNED NULL,
  pacote_id BIGINT UNSIGNED NULL,
  preco_promocional DECIMAL(10,2) NULL,
  validade_inicio DATETIME NULL,
  validade_fim DATETIME NULL,
  imagem VARCHAR(500) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ofertas_destino
    FOREIGN KEY (destino_id) REFERENCES destinos(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ofertas_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_ofertas_pacote
    FOREIGN KEY (pacote_id) REFERENCES pacotes(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_ofertas_periodo (ativo, validade_inicio, validade_fim)
) ENGINE=InnoDB;

-- =========================================================
-- 6. RESERVAS, PASSAGEIROS E ITENS RESERVADOS
-- =========================================================

CREATE TABLE IF NOT EXISTS reservas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  pacote_id BIGINT UNSIGNED NULL,
  codigo VARCHAR(40) NOT NULL UNIQUE,
  tipo ENUM('voo','hotel','pacote') NOT NULL,
  valor_total DECIMAL(10,2) NOT NULL,
  status ENUM('pendente','aguardando_pagamento','confirmada','cancelada','reembolsada') NOT NULL DEFAULT 'pendente',
  data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_reservas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reservas_pacote
    FOREIGN KEY (pacote_id) REFERENCES pacotes(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_reservas_usuario_data (usuario_id, data_criacao),
  INDEX idx_reservas_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passageiros (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  sobrenome VARCHAR(100) NOT NULL,
  cpf VARCHAR(14) NULL,
  data_nascimento DATE NOT NULL,
  email VARCHAR(190) NULL,
  telefone VARCHAR(30) NULL,
  INDEX idx_passageiros_cpf (cpf)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reserva_passageiros (
  reserva_id BIGINT UNSIGNED NOT NULL,
  passageiro_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (reserva_id, passageiro_id),
  CONSTRAINT fk_reserva_passageiros_reserva
    FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_passageiros_passageiro
    FOREIGN KEY (passageiro_id) REFERENCES passageiros(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reserva_voos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reserva_id BIGINT UNSIGNED NOT NULL,
  voo_id BIGINT UNSIGNED NOT NULL,
  sentido ENUM('ida','volta') NOT NULL DEFAULT 'ida',
  preco DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_reserva_voos_reserva
    FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_voos_voo
    FOREIGN KEY (voo_id) REFERENCES voos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_reserva_voos_reserva (reserva_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reserva_hoteis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reserva_id BIGINT UNSIGNED NOT NULL,
  hotel_id BIGINT UNSIGNED NOT NULL,
  quarto_id BIGINT UNSIGNED NOT NULL,
  checkin DATE NOT NULL,
  checkout DATE NOT NULL,
  quantidade_quartos SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  preco_total DECIMAL(10,2) NOT NULL,
  CONSTRAINT chk_reserva_hotel_datas CHECK (checkout > checkin),
  CONSTRAINT fk_reserva_hoteis_reserva
    FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_hoteis_hotel
    FOREIGN KEY (hotel_id) REFERENCES hoteis(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_hoteis_quarto
    FOREIGN KEY (quarto_id) REFERENCES quartos(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_reserva_hoteis_datas (hotel_id, checkin, checkout)
) ENGINE=InnoDB;

-- =========================================================
-- 7. PAGAMENTOS E CUPONS
-- =========================================================

CREATE TABLE IF NOT EXISTS pagamentos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reserva_id BIGINT UNSIGNED NOT NULL,
  metodo ENUM('pix','cartao_credito','cartao_debito','boleto','outro') NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  parcelas TINYINT UNSIGNED NOT NULL DEFAULT 1,
  valor_parcela DECIMAL(10,2) NULL,
  status ENUM('pendente','aprovado','recusado','estornado','cancelado') NOT NULL DEFAULT 'pendente',
  identificador_transacao VARCHAR(190) NULL,
  pago_em DATETIME NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_pagamento_parcelas CHECK (parcelas BETWEEN 1 AND 12),
  CONSTRAINT fk_pagamentos_reserva
    FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_pagamentos_reserva (reserva_id),
  INDEX idx_pagamentos_status (status),
  UNIQUE KEY uk_pagamento_transacao (identificador_transacao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(60) NOT NULL UNIQUE,
  tipo_desconto ENUM('percentual','valor_fixo') NOT NULL,
  valor_desconto DECIMAL(10,2) NOT NULL,
  validade_inicio DATETIME NULL,
  validade_fim DATETIME NULL,
  limite_uso INT UNSIGNED NULL,
  usos_realizados INT UNSIGNED NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reserva_cupons (
  reserva_id BIGINT UNSIGNED NOT NULL,
  cupom_id BIGINT UNSIGNED NOT NULL,
  desconto_aplicado DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (reserva_id, cupom_id),
  CONSTRAINT fk_reserva_cupons_reserva
    FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reserva_cupons_cupom
    FOREIGN KEY (cupom_id) REFERENCES cupons(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 8. VIEWS (consultas prontas para as telas)
-- =========================================================

-- Hotéis com nota média e quantidade de avaliações calculadas na hora
CREATE OR REPLACE VIEW vw_hoteis_avaliacao AS
SELECT h.*,
       ROUND(AVG(a.nota), 1) AS nota_media,
       COUNT(a.id) AS quantidade_avaliacoes
FROM hoteis h
LEFT JOIN avaliacoes_hoteis a ON a.hotel_id = h.id
GROUP BY h.id;

-- Voos com origem, destino e horários (primeiro e último trecho): usar na busca de voos
CREATE OR REPLACE VIEW vw_voos_busca AS
SELECT v.id AS voo_id, v.preco, v.duracao_total_minutos, v.quantidade_escalas,
       v.assentos_disponiveis,
       t1.companhia_id, t1.aeroporto_origem_id, t1.horario_partida,
       t2.aeroporto_destino_id, t2.horario_chegada
FROM voos v
JOIN trechos_voos t1 ON t1.voo_id = v.id AND t1.ordem = 1
JOIN trechos_voos t2 ON t2.voo_id = v.id
 AND t2.ordem = (SELECT MAX(x.ordem) FROM trechos_voos x WHERE x.voo_id = v.id)
WHERE v.ativo = 1;

