<?php

require_once '../includes/ajax_header.php';

$task_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT tasks.*, tickets.ticket_client_id, clients.client_name 
    FROM tasks 
    LEFT JOIN tickets ON tickets.ticket_id = tasks.task_ticket_id
    LEFT JOIN clients ON clients.client_id = tickets.ticket_client_id
    WHERE task_id = $task_id
    LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$task_name = nullable_htmlentities($row['task_name']);
$client_name = nullable_htmlentities($row['client_name']);

// Get current assignees
$assignees = array();
$sql_assignees = mysqli_query($mysqli, "SELECT user_id FROM task_assignees WHERE todo_id = $task_id");
while ($assignee_row = mysqli_fetch_array($sql_assignees)) {
    $assignees[] = intval($assignee_row['user_id']);
}

// Generate the HTML form content using output buffering.
ob_start();

?>

<div class="modal-header">
    <h5 class="modal-title"><i class='fa fa-fw fa-user-check mr-2'></i>Assigning Task: <strong><?php echo $task_name; ?></strong><?php echo $client_name ? " - $client_name" : ""; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
    <div class="modal-body bg-white">
        <div class="form-group">
            <label>Assign To</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                </div>
                <select class="form-control select2" name="assignees[]" multiple data-placeholder="- Select Assignees -">
                    <?php
                    $sql_users_select = mysqli_query($mysqli, "SELECT users.user_id, user_name FROM users
                        LEFT JOIN user_settings on users.user_id = user_settings.user_id
                        WHERE user_type = 1
                        AND user_archived_at IS NULL
                        ORDER BY user_name ASC"
                    );
                    while ($row = mysqli_fetch_array($sql_users_select)) {
                        $user_id_select = intval($row['user_id']);
                        $user_name_select = nullable_htmlentities($row['user_name']);
                        ?>
                        <option value="<?php echo $user_id_select; ?>" <?php if (in_array($user_id_select, $assignees)) { echo "selected"; } ?>><?php echo $user_name_select; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>

    <div class="modal-footer bg-white">
        <button type="submit" name="assign_task" class="btn btn-primary text-bold">
            <i class="fa fa-check mr-2"></i>Assign
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal">
            <i class="fa fa-times mr-2"></i>Cancel
        </button>
    </div>
</form>

<?php

require_once "../includes/ajax_footer.php";