-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30-Jan-2026 às 12:57
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `mini_forms`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `forms`
--

CREATE TABLE `forms` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `theme_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_settings`)),
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `forms`
--

INSERT INTO `forms` (`id`, `user_id`, `title`, `description`, `slug`, `theme_settings`, `is_published`, `created_at`, `updated_at`, `is_archived`) VALUES
(1, 1, 'nnn', 'hhh', 'f5ac5fd7c0afd528', '[]', 0, '2026-01-26 11:14:54', '2026-01-26 16:10:01', 1),
(2, 1, 'testo novo', 'descrição do testo novo', '256cd8822edd3dbc', '[]', 0, '2026-01-26 11:54:14', '2026-01-30 11:50:12', 0),
(3, 1, 'Inscrição do curso', 'teste de formulario', '9f6b20020821e417', '{\"primaryColor\":\"#3b82f6\",\"background\":\"#ffffff\",\"radius\":8,\"font\":\"Inter\"}', 0, '2026-01-28 10:16:56', '2026-01-28 11:58:01', 1),
(4, 1, 'iof', 'teste de formulario para iof', 'e483a9b7e472197f', '{\"primaryColor\":\"#f59e0b\",\"background\":\"#f8fafc\",\"radius\":8,\"font\":\"Inter\"}', 0, '2026-01-30 09:52:13', '2026-01-30 11:49:36', 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `form_questions`
--

CREATE TABLE `form_questions` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `type` enum('text','long_text','checkbox','radio','upload') NOT NULL,
  `label` varchar(255) NOT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `position` int(11) DEFAULT 0,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estrutura da tabela `form_responses`
--

CREATE TABLE `form_responses` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Estrutura da tabela `question_options`
--

CREATE TABLE `question_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `position` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------

--
-- Estrutura da tabela `response_answers`
--

CREATE TABLE `response_answers` (
  `id` int(11) NOT NULL,
  `response_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_value` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Estrutura da tabela `uploads`
--

CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `storage_key` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size_bytes` int(11) NOT NULL,
  `status` enum('TEMP','ATTACHED') NOT NULL DEFAULT 'TEMP',
  `expires_at` datetime DEFAULT NULL,
  `response_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Índices para tabela `forms`
--
ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `form_questions`
--
ALTER TABLE `form_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_id` (`form_id`);

--
-- Índices para tabela `form_responses`
--
ALTER TABLE `form_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_id` (`form_id`);

--
-- Índices para tabela `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Índices para tabela `response_answers`
--
ALTER TABLE `response_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `response_id` (`response_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Índices para tabela `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `response_id` (`response_id`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `forms`
--
ALTER TABLE `forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `form_questions`
--
ALTER TABLE `form_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `form_responses`
--
ALTER TABLE `form_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `response_answers`
--
ALTER TABLE `response_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `forms`
--
ALTER TABLE `forms`
  ADD CONSTRAINT `forms_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `form_questions`
--
ALTER TABLE `form_questions`
  ADD CONSTRAINT `form_questions_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `form_responses`
--
ALTER TABLE `form_responses`
  ADD CONSTRAINT `form_responses_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `form_questions` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `response_answers`
--
ALTER TABLE `response_answers`
  ADD CONSTRAINT `response_answers_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `form_responses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `response_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `form_questions` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `uploads_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `form_responses` (`id`) ON DELETE SET NULL;
