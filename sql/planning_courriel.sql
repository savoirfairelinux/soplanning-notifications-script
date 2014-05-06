CREATE TABLE `planning_courriel` (
  `periode_id` int(11) NOT NULL,
  `ts_courriel` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`periode_id`),
  CONSTRAINT `planning_courriel_ibfk_1` FOREIGN KEY (`periode_id`) REFERENCES `planning_periode` (`periode_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;