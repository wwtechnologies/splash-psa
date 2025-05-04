<?php

// Include database connection
require_once 'includes/config.php';

// Function to execute SQL and handle errors
function executeSql($mysqli, $sql, $description) {
    echo "<p>Executing: $description... ";
    if (mysqli_query($mysqli, $sql)) {
        echo "<span style='color: green;'>Success!</span></p>";
        return true;
    } else {
        echo "<span style='color: red;'>Failed: " . mysqli_error($mysqli) . "</span></p>";
        return false;
    }
}

// Set page header
echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Update</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Database Update for Multiple Assignees</h1>";

// Check if we have a database connection
if (!isset($mysqli)) {
    echo "<p class='error'>Error: Database connection not available. Please check your config.php file.</p>";
    exit;
}

echo "<h2>Creating new tables for multiple assignees</h2>";

// Create recurring_ticket_assignees table
$recurring_ticket_assignees_sql = "
CREATE TABLE IF NOT EXISTS `recurring_ticket_assignees` (
  `recurring_ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`recurring_ticket_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `recurring_ticket_assignees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `recurring_ticket_assignees_ibfk_2` FOREIGN KEY (`recurring_ticket_id`) REFERENCES `recurring_tickets` (`recurring_ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

executeSql($mysqli, $recurring_ticket_assignees_sql, "Creating recurring_ticket_assignees table");

// Create ticket_assignees table
$ticket_assignees_sql = "
CREATE TABLE IF NOT EXISTS `ticket_assignees` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`ticket_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ticket_assignees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_assignees_ibfk_2` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

executeSql($mysqli, $ticket_assignees_sql, "Creating ticket_assignees table");

// Create task_assignees table
$task_assignees_sql = "
CREATE TABLE IF NOT EXISTS `task_assignees` (
  `todo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`todo_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_assignees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignees_ibfk_2` FOREIGN KEY (`todo_id`) REFERENCES `todos` (`todo_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

executeSql($mysqli, $task_assignees_sql, "Creating task_assignees table");

// Migrate existing data
echo "<h2>Migrating existing data</h2>";

// Migrate primary assignees from recurring tickets to the new table
$migrate_recurring_sql = "
INSERT IGNORE INTO recurring_ticket_assignees (recurring_ticket_id, user_id)
SELECT recurring_ticket_id, recurring_ticket_assigned_to 
FROM recurring_tickets 
WHERE recurring_ticket_assigned_to > 0;
";

executeSql($mysqli, $migrate_recurring_sql, "Migrating existing recurring ticket assignees");

// Migrate primary assignees from tickets to the new table
$migrate_tickets_sql = "
INSERT IGNORE INTO ticket_assignees (ticket_id, user_id)
SELECT ticket_id, ticket_assigned_to 
FROM tickets 
WHERE ticket_assigned_to > 0;
";

executeSql($mysqli, $migrate_tickets_sql, "Migrating existing ticket assignees");

echo "<h2>Summary</h2>
<p>The database has been updated to support multiple assignees for tickets and recurring tickets.</p>
<p>The following files have been modified:</p>
<ul>
    <li>ajax/ajax_recurring_ticket_edit.php</li>
    <li>ajax/ajax_ticket_assign.php</li>
    <li>post/user/ticket.php</li>
    <li>post/user/ticket_recurring_model.php</li>
    <li>ticket.php</li>
    <li>tasks.php</li>
    <li>post/user/task.php</li>
    <li>ajax/ajax_task_assign.php</li>
</ul>
<p>The following tables have been added:</p>
<ul>
    <li>recurring_ticket_assignees</li>
    <li>ticket_assignees</li>
    <li>task_assignees</li>
</ul>
<p>You can now assign multiple people to recurring tickets, regular tickets, and tasks.</p>
</div>
</body>
</html>";
?>