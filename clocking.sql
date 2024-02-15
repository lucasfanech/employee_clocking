-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 15 fév. 2024 à 13:12
-- Version du serveur : 8.0.31
-- Version de PHP : 8.1.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `clocking`
--

-- --------------------------------------------------------

--
-- Structure de la table `calendar`
--

DROP TABLE IF EXISTS `calendar`;
CREATE TABLE IF NOT EXISTS `calendar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL,
  `week_nb` int NOT NULL,
  `completed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clocking`
--

DROP TABLE IF EXISTS `clocking`;
CREATE TABLE IF NOT EXISTS `clocking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `week_ref` int NOT NULL,
  `day` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `part_of_day` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clocking_hour` time NOT NULL,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_D3E9DCCDA76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conf`
--

DROP TABLE IF EXISTS `conf`;
CREATE TABLE IF NOT EXISTS `conf` (
  `id` int NOT NULL AUTO_INCREMENT,
  `time_lunch_break` time NOT NULL,
  `time_exception` time DEFAULT NULL,
  `days_exception` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_hours_to_do_week` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_14F389A8A76ED395` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `conf`
--

INSERT INTO `conf` (`id`, `time_lunch_break`, `time_exception`, `days_exception`, `time_hours_to_do_week`, `user_id`) VALUES
(2, '01:00:00', '00:45:00', 'Fri;', '38:30:00', 1);

-- --------------------------------------------------------

--
-- Structure de la table `day_off`
--

DROP TABLE IF EXISTS `day_off`;
CREATE TABLE IF NOT EXISTS `day_off` (
  `id` int NOT NULL AUTO_INCREMENT,
  `week_ref` int NOT NULL,
  `day` int NOT NULL,
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_926C726CA76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20221012081106', '2022-10-12 08:11:24', 393),
('DoctrineMigrations\\Version20221012150442', '2022-10-12 15:05:18', 583),
('DoctrineMigrations\\Version20221018074129', '2022-10-18 07:43:03', 283),
('DoctrineMigrations\\Version20240215081719', '2024-02-15 08:17:31', 228),
('DoctrineMigrations\\Version20240215082116', '2024-02-15 08:21:22', 959);

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user`
--

INSERT INTO `user` (`id`, `email`, `roles`, `password`) VALUES
(1, 'test@test.com', '[\"ROLE_ADMIN\"]', '$2y$10$Xwa5JmlAWBKDfm5lbzklqe5Mjg/bStJjBFzx96oMjjhfEeTTN1MNu');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `clocking`
--
ALTER TABLE `clocking`
  ADD CONSTRAINT `FK_D3E9DCCDA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

--
-- Contraintes pour la table `conf`
--
ALTER TABLE `conf`
  ADD CONSTRAINT `FK_14F389A8A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

--
-- Contraintes pour la table `day_off`
--
ALTER TABLE `day_off`
  ADD CONSTRAINT `FK_926C726CA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
