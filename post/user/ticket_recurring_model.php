<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$client_id = intval($_POST['client']);
$subject = sanitizeInput($_POST['subject']);
$priority = sanitizeInput($_POST['priority']);
$details = mysqli_real_escape_string($mysqli, $_POST['details']);
$frequency = sanitizeInput($_POST['frequency']);
$billable = intval($_POST['billable'] ?? 0);

$asset_id = "0";
if (isset($_POST['asset'])) {
    $asset_id = intval($_POST['asset']);
}

$contact_id = "0";
if (isset($_POST['contact'])) {
    $contact_id = intval($_POST['contact']);
}

$assigned_to = "0";
if (isset($_POST['assigned_to'])) {
    $assigned_to = intval($_POST['assigned_to']);
}

// Process additional assignees
$additional_assignees = array();
if (isset($_POST['additional_assignees']) && is_array($_POST['additional_assignees'])) {
    foreach ($_POST['additional_assignees'] as $assignee) {
        $assignee_id = intval($assignee);
        if ($assignee_id > 0 && $assignee_id != $assigned_to) {
            $additional_assignees[] = $assignee_id;
        }
    }
}
