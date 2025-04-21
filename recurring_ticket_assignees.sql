-- Table structure for table `recurring_ticket_assignees`

CREATE TABLE `recurring_ticket_assignees` (
  `recurring_ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_ticket_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `recurring_ticket_assignees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `recurring_ticket_assignees_ibfk_2` FOREIGN KEY (`recurring_ticket_id`) REFERENCES `recurring_tickets` (`recurring_ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;