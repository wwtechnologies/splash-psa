<?php

/*
 * ITFlow - GET/POST request handler for todos
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_todo'])) {

    enforceUserPermission('module_support', 1);

    $todo_name = sanitizeInput($_POST['name']);
    $todo_description = mysqli_real_escape_string($mysqli, $_POST['description']);
    $todo_priority = sanitizeInput($_POST['priority']);
    $todo_due_date = sanitizeInput($_POST['due_date']);
    
    if (empty($todo_due_date)) {
        $due_date_sql = "NULL";
    } else {
        $due_date_sql = "'$todo_due_date'";
    }

    mysqli_query($mysqli, "INSERT INTO todos SET 
        todo_name = '$todo_name', 
        todo_description = '$todo_description', 
        todo_priority = '$todo_priority', 
        todo_due_date = $due_date_sql, 
        todo_created_by = $session_user_id");

    $todo_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Todo", "Create", "$session_name created todo $todo_name");

    $_SESSION['alert_message'] = "Todo <strong>$todo_name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['edit_todo'])) {

    enforceUserPermission('module_support', 1);

    $todo_id = intval($_POST['todo_id']);
    $todo_name = sanitizeInput($_POST['name']);
    $todo_description = mysqli_real_escape_string($mysqli, $_POST['description']);
    $todo_priority = sanitizeInput($_POST['priority']);
    $todo_due_date = sanitizeInput($_POST['due_date']);
    
    if (empty($todo_due_date)) {
        $due_date_sql = "NULL";
    } else {
        $due_date_sql = "'$todo_due_date'";
    }

    mysqli_query($mysqli, "UPDATE todos SET 
        todo_name = '$todo_name', 
        todo_description = '$todo_description', 
        todo_priority = '$todo_priority', 
        todo_due_date = $due_date_sql 
        WHERE todo_id = $todo_id");

    // Logging
    logAction("Todo", "Edit", "$session_name edited todo $todo_name");

    $_SESSION['alert_message'] = "Todo <strong>$todo_name</strong> updated";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['delete_todo'])) {

    enforceUserPermission('module_support', 1);

    $todo_id = intval($_GET['delete_todo']);

    // Get todo name for logging
    $sql = mysqli_query($mysqli, "SELECT todo_name FROM todos WHERE todo_id = $todo_id");
    $row = mysqli_fetch_array($sql);
    $todo_name = sanitizeInput($row['todo_name']);

    mysqli_query($mysqli, "DELETE FROM todos WHERE todo_id = $todo_id");

    // Logging
    logAction("Todo", "Delete", "$session_name deleted todo $todo_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Todo <strong>$todo_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['complete_todo'])) {

    enforceUserPermission('module_support', 1);

    $todo_id = intval($_GET['complete_todo']);

    // Get todo name for logging
    $sql = mysqli_query($mysqli, "SELECT todo_name FROM todos WHERE todo_id = $todo_id");
    $row = mysqli_fetch_array($sql);
    $todo_name = sanitizeInput($row['todo_name']);

    mysqli_query($mysqli, "UPDATE todos SET todo_completed_at = NOW(), todo_completed_by = $session_user_id WHERE todo_id = $todo_id");

    // Logging
    logAction("Todo", "Complete", "$session_name completed todo $todo_name");

    $_SESSION['alert_message'] = "Todo <strong>$todo_name</strong> completed";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_GET['undo_complete_todo'])) {

    enforceUserPermission('module_support', 1);

    $todo_id = intval($_GET['undo_complete_todo']);

    // Get todo name for logging
    $sql = mysqli_query($mysqli, "SELECT todo_name FROM todos WHERE todo_id = $todo_id");
    $row = mysqli_fetch_array($sql);
    $todo_name = sanitizeInput($row['todo_name']);

    mysqli_query($mysqli, "UPDATE todos SET todo_completed_at = NULL, todo_completed_by = NULL WHERE todo_id = $todo_id");

    // Logging
    logAction("Todo", "Undo Complete", "$session_name marked todo $todo_name as incomplete");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Todo <strong>$todo_name</strong> marked as incomplete";

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}