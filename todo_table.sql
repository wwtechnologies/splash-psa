CREATE TABLE IF NOT EXISTS todos (
    todo_id INT(11) NOT NULL AUTO_INCREMENT,
    todo_name VARCHAR(255) NOT NULL,
    todo_description TEXT,
    todo_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    todo_created_by INT(11) NOT NULL,
    todo_completed_at DATETIME DEFAULT NULL,
    todo_completed_by INT(11) DEFAULT NULL,
    todo_due_date DATE DEFAULT NULL,
    todo_priority VARCHAR(20) DEFAULT 'Medium',
    PRIMARY KEY (todo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;