<?php

require_once '../includes/ajax_header.php';

$todo_id = intval($_GET['id']);

// Get current todo details
$sql = mysqli_query($mysqli, "SELECT * FROM todos WHERE todo_id = $todo_id LIMIT 1");
$row = mysqli_fetch_array($sql);
$todo_name = nullable_htmlentities($row['todo_name']);
$todo_description = nullable_htmlentities($row['todo_description']);
$todo_priority = nullable_htmlentities($row['todo_priority']);
$todo_due_date = nullable_htmlentities($row['todo_due_date']);

// Get current assignments
$sql_assignments = mysqli_query($mysqli, "SELECT user_id FROM todo_assignments WHERE todo_id = $todo_id");
$assigned_users = array();
while ($assignment = mysqli_fetch_array($sql_assignments)) {
    $assigned_users[] = $assignment['user_id'];
}

// Get list of all users
$sql_users = mysqli_query($mysqli, "SELECT * FROM users ORDER BY user_name ASC");

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-check-square mr-2"></i>Edit To-Do Item</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="todo_id" value="<?php echo $todo_id; ?>">
    
    <div class="modal-body bg-white">
        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Name the to-do item" maxlength="255" value="<?php echo $todo_name; ?>" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                </div>
                <textarea class="form-control" name="description" placeholder="Describe the to-do item"><?php echo $todo_description; ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <label>Priority</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                </div>
                <select class="form-control" name="priority">
                    <option value="Low" <?php if ($todo_priority == "Low") echo "selected"; ?>>Low</option>
                    <option value="Medium" <?php if ($todo_priority == "Medium") echo "selected"; ?>>Medium</option>
                    <option value="High" <?php if ($todo_priority == "High") echo "selected"; ?>>High</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Due Date</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="due_date" value="<?php echo $todo_due_date; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Assign To</label>
            <select class="form-control select2" name="assigned_to[]" multiple>
                <?php
                while ($row = mysqli_fetch_array($sql_users)) {
                    $user_id = intval($row['user_id']);
                    $user_name = nullable_htmlentities($row['user_name']);
                    $selected = in_array($user_id, $assigned_users) ? "selected" : "";
                    echo "<option value='$user_id' $selected>$user_name</option>";
                }
                ?>
            </select>
        </div>
    </div>

    <div class="modal-footer bg-white">
        <button type="submit" name="edit_todo" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";