
CREATE DATABASE farmacia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE farmacia_db;

-- usuários
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150),
  email VARCHAR(150) UNIQUE,
  senha VARCHAR(255), -- hash
  papel ENUM('admin','farmaceutico','atendente') DEFAULT 'atendente',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- produtos (info base)
CREATE TABLE produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  descricao TEXT,
  codigo_barra VARCHAR(100) NULL,
  unidade VARCHAR(50) DEFAULT 'un',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- lotes (cada lote tem quantidade, validade)
CREATE TABLE lotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produto_id INT NOT NULL,
  lote VARCHAR(100),
  qtd_total INT DEFAULT 0,
  qtd_disponivel INT DEFAULT 0,
  data_validade DATE NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- movimentos (entrada/saida) - registra histórico
CREATE TABLE movimentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('entrada','saida') NOT NULL,
  produto_id INT NOT NULL,
  lote_id INT NULL,
  qtd INT NOT NULL,
  usuario_id INT,
  descricao VARCHAR(255) NULL,
  data_mov DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
  FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

