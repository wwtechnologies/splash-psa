<?php

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_support');

// Get list of users for assignment
$sql_users = mysqli_query($mysqli, "SELECT * FROM users ORDER BY user_name ASC");

// Get todos
$sql = mysqli_query($mysqli, "SELECT DISTINCT todos.*,
                             CONCAT(users.user_name) AS created_by_name,
                             CONCAT(completed_users.user_name) AS completed_by_name,
                             GROUP_CONCAT(DISTINCT assigned_users.user_name SEPARATOR ', ') as assigned_users
                             FROM todos
                             LEFT JOIN users ON todos.todo_created_by = users.user_id
                             LEFT JOIN users AS completed_users ON todos.todo_completed_by = completed_users.user_id
                             LEFT JOIN todo_assignments ta ON todos.todo_id = ta.todo_id
                             LEFT JOIN users AS assigned_users ON ta.user_id = assigned_users.user_id
                             WHERE todos.todo_created_by = $session_user_id
                                OR ta.user_id = $session_user_id
                             GROUP BY todos.todo_id
                             ORDER BY todos.todo_completed_at ASC,
                                      CASE 
                                        WHEN todos.todo_priority = 'High' THEN 1
                                        WHEN todos.todo_priority = 'Medium' THEN 2
                                        WHEN todos.todo_priority = 'Low' THEN 3
                                        ELSE 4
                                      END ASC,
                                      CASE 
                                        WHEN todos.todo_due_date IS NULL THEN 1
                                        ELSE 0
                                      END ASC,
                                      todos.todo_due_date ASC,
                                      todos.todo_created_at DESC");

?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="index.php">Home</a>
    </li>
    <li class="breadcrumb-item active">To-Do List</li>
</ol>

<div class="card mb-3">
    <div class="card-header">
        <div class="row">
            <div class="col-md-8">
                <i class="fas fa-check-square"></i>
                To-Do List
                <button type="button" class="btn btn-primary ml-2" data-toggle="modal" data-target="#addTodoModal">
                    <i class="fas fa-plus"></i> Add To-Do
                </button>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="searchButton">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="statusFilter">Filter by Status:</label>
                    <select class="form-control" id="statusFilter">
                        <option value="">All Items</option>
                        <option value="incomplete">Incomplete Items</option>
                        <option value="complete">Completed Items</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="priorityFilter">Filter by Priority:</label>
                    <select class="form-control" id="priorityFilter">
                        <option value="">All Priorities</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="todosTable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Created By</th>
                        <th>Assigned To</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql)) {
                        $todo_id = intval($row['todo_id']);
                        $todo_name = nullable_htmlentities($row['todo_name']);
                        $todo_description = nullable_htmlentities($row['todo_description']);
                        $todo_priority = nullable_htmlentities($row['todo_priority']);
                        $todo_due_date = nullable_htmlentities($row['todo_due_date']);
                        $todo_created_by = intval($row['todo_created_by']);
                        $todo_created_by_name = nullable_htmlentities($row['created_by_name']);
                        $todo_created_at = nullable_htmlentities($row['todo_created_at']);
                        $todo_completed_at = nullable_htmlentities($row['todo_completed_at']);
                        $todo_completed_by = intval($row['todo_completed_by']);
                        $todo_completed_by_name = nullable_htmlentities($row['completed_by_name']);
                        
                        // Format dates
                        $created_at_formatted = date('M j, Y g:i A', strtotime($todo_created_at));
                        $due_date_formatted = $todo_due_date ? date('M j, Y', strtotime($todo_due_date)) : '';
                        
                        // Determine if due date is past
                        $due_date_class = '';
                        if ($todo_due_date && !$todo_completed_at) {
                            $today = new DateTime();
                            $due = new DateTime($todo_due_date);
                            if ($due < $today) {
                                $due_date_class = 'text-danger font-weight-bold';
                            }
                        }
                        
                        // Determine task status
                        $todo_status = $todo_completed_at ? 'complete' : 'incomplete';
                        $todo_status_icon = $todo_completed_at ? 
                            '<i class="far fa-fw fa-check-square text-success"></i>' : 
                            '<i class="far fa-fw fa-square text-secondary"></i>';
                        
                        // Determine priority badge
                        $priority_badge = '';
                        if ($todo_priority == 'High') {
                            $priority_badge = '<span class="badge badge-danger">High</span>';
                        } elseif ($todo_priority == 'Medium') {
                            $priority_badge = '<span class="badge badge-warning">Medium</span>';
                        } elseif ($todo_priority == 'Low') {
                            $priority_badge = '<span class="badge badge-info">Low</span>';
                        }
                        
                        // Determine task action buttons
                        $todo_actions = '';
                        if (lookupUserPermission("module_support") >= 1) {
                            if ($todo_completed_at) {
                                $todo_actions .= "<a href='post.php?undo_complete_todo=$todo_id' class='btn btn-sm btn-secondary mr-1' title='Mark Incomplete'><i class='fas fa-undo'></i></a>";
                            } else {
                                $todo_actions .= "<a href='post.php?complete_todo=$todo_id' class='btn btn-sm btn-success mr-1' title='Mark Complete'><i class='fas fa-check'></i></a>";
                            }
                            $todo_actions .= "<a href='#' data-toggle='ajax-modal' data-ajax-url='ajax/ajax_todo_edit.php' data-ajax-id='$todo_id' class='btn btn-sm btn-primary mr-1' title='Edit To-Do'><i class='fas fa-edit'></i></a>";
                            $todo_actions .= "<a href='post.php?delete_todo=$todo_id' class='btn btn-sm btn-danger confirm-link' title='Delete To-Do'><i class='fas fa-trash'></i></a>";
                        }
                        
                        // Completed info
                        $completed_info = '';
                        if ($todo_completed_at) {
                            $completed_at_formatted = date('M j, Y g:i A', strtotime($todo_completed_at));
                            $completed_info = "<br><small class='text-muted'>Completed by $todo_completed_by_name on $completed_at_formatted</small>";
                        }
                        
                        echo "<tr class='todo-row' data-status='$todo_status' data-priority='$todo_priority'>";
                        echo "<td>$todo_status_icon</td>";
                        echo "<td>$todo_name</td>";
                        echo "<td>$todo_description</td>";
                        echo "<td>$priority_badge</td>";
                        echo "<td class='$due_date_class'>$due_date_formatted</td>";
                        echo "<td>$todo_created_by_name</td>";
                        echo "<td>" . ($row['assigned_users'] ? $row['assigned_users'] : '-') . "</td>";
                        echo "<td>$created_at_formatted</td>";
                        echo "<td>$todo_actions</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Todo Modal -->
<div class="modal fade" id="addTodoModal" tabindex="-1" role="dialog" aria-labelledby="addTodoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTodoModalLabel">Add New To-Do Item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <div class="form-group">
                        <label for="name">Name <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select class="form-control" name="priority" id="priority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" class="form-control" name="due_date" id="due_date">
                    </div>
                    <div class="form-group">
                        <label>Assign To</label>
                        <select class="form-control select2" name="assigned_to[]" multiple>
                            <?php
                            while ($row = mysqli_fetch_array($sql_users)) {
                                $user_id = intval($row['user_id']);
                                $user_name = nullable_htmlentities($row['user_name']);
                                echo "<option value='$user_id'>$user_name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="add_todo" class="btn btn-primary">Add To-Do</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#todosTable').DataTable({
        "order": [[0, "asc"], [3, "asc"], [4, "asc"]],
        "pageLength": 25,
        "searching": true,
        "dom": 'Bfrtip',
        "buttons": [
            {
                text: 'Add To-Do',
                className: 'btn btn-primary',
                action: function(e, dt, node, config) {
                    $('#addTodoModal').modal('show');
                }
            }
        ]
    });

    // Status filter
    $('#statusFilter').change(function() {
        filterTodos();
    });

    // Priority filter
    $('#priorityFilter').change(function() {
        filterTodos();
    });

    // Search functionality
    $('#searchButton').click(function() {
        filterTodos();
    });

    $('#searchInput').keyup(function(e) {
        if (e.keyCode === 13) {
            filterTodos();
        }
    });

    // Function to filter todos
    function filterTodos() {
        var status = $('#statusFilter').val();
        var priority = $('#priorityFilter').val();
        var searchText = $('#searchInput').val().toLowerCase();

        $('.todo-row').each(function() {
            var row = $(this);
            var rowStatus = row.data('status');
            var rowPriority = row.data('priority');
            var rowText = row.text().toLowerCase();
            
            var statusMatch = status === '' || rowStatus === status;
            var priorityMatch = priority === '' || rowPriority === priority;
            var textMatch = searchText === '' || rowText.includes(searchText);
            
            if (statusMatch && priorityMatch && textMatch) {
                row.show();
            } else {
                row.hide();
            }
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>