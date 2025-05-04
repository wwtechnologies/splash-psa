<?php

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_support');

// Get tasks with their associated tickets
$sql = mysqli_query($mysqli, "SELECT tasks.*, tickets.ticket_prefix, tickets.ticket_number, tickets.ticket_subject,
    tickets.ticket_client_id, clients.client_name,
    GROUP_CONCAT(DISTINCT users.user_name ORDER BY users.user_name ASC SEPARATOR ', ') as assignee_names
    FROM tasks
    LEFT JOIN tickets ON tasks.task_ticket_id = tickets.ticket_id
    LEFT JOIN clients ON tickets.ticket_client_id = clients.client_id
    LEFT JOIN task_assignees ON tasks.task_id = task_assignees.todo_id
    LEFT JOIN users ON task_assignees.user_id = users.user_id
    GROUP BY tasks.task_id
    ORDER BY tasks.task_completed_at ASC, tasks.task_order ASC, tasks.task_id ASC");

// Get clients for filter dropdown
$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");

?>

<!-- Breadcrumbs-->
<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item">
        <a href="index.php">Home</a>
    </li>
    <li class="breadcrumb-item active">Tasks</li>
</ol>

<div class="card mb-3">
    <div class="card-header">
        <div class="row">
            <div class="col-md-8">
                <i class="fas fa-tasks"></i>
                Tasks
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
                    <label for="clientFilter">Filter by Client:</label>
                    <select class="form-control" id="clientFilter">
                        <option value="">All Clients</option>
                        <?php
                        while ($client_row = mysqli_fetch_array($sql_clients)) {
                            $client_id = intval($client_row['client_id']);
                            $client_name = nullable_htmlentities($client_row['client_name']);
                            echo "<option value='$client_id'>$client_name</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="statusFilter">Filter by Status:</label>
                    <select class="form-control" id="statusFilter">
                        <option value="">All Tasks</option>
                        <option value="incomplete">Incomplete Tasks</option>
                        <option value="complete">Completed Tasks</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="tasksTable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Task</th>
                        <th>Ticket</th>
                        <th>Client</th>
                        <th>Assignees</th>
                        <th>Est. Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql)) {
                        $task_id = intval($row['task_id']);
                        $task_name = nullable_htmlentities($row['task_name']);
                        $task_completion_estimate = intval($row['task_completion_estimate']);
                        $task_completed_at = nullable_htmlentities($row['task_completed_at']);
                        $task_ticket_id = intval($row['task_ticket_id']);
                        $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                        $ticket_number = intval($row['ticket_number']);
                        $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                        $client_id = intval($row['ticket_client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        $assignee_names = nullable_htmlentities($row['assignee_names']);
                        
                        $ticket_link = "ticket.php?ticket_id=$task_ticket_id";
                        $ticket_display = "$ticket_prefix$ticket_number - $ticket_subject";
                        
                        // Determine task status
                        $task_status = $task_completed_at ? 'complete' : 'incomplete';
                        $task_status_icon = $task_completed_at ? 
                            '<i class="far fa-fw fa-check-square text-primary"></i>' : 
                            '<i class="far fa-fw fa-square text-secondary"></i>';
                        
                        // Determine task action buttons
                        $task_actions = '';
                        if (lookupUserPermission("module_support") >= 2) {
                            if ($task_completed_at) {
                                $task_actions .= "<a href='post.php?undo_complete_task=$task_id' class='btn btn-sm btn-secondary mr-1' title='Mark Incomplete'><i class='fas fa-undo'></i></a>";
                            } else {
                                $task_actions .= "<a href='post.php?complete_task=$task_id' class='btn btn-sm btn-success mr-1' title='Mark Complete'><i class='fas fa-check'></i></a>";
                            }
                            $task_actions .= "<a href='#' data-toggle='ajax-modal' data-ajax-url='ajax/ajax_task_assign.php' data-ajax-id='$task_id' class='btn btn-sm btn-info mr-1' title='Assign Task'><i class='fas fa-user-check'></i></a>";
                            $task_actions .= "<a href='#' data-toggle='ajax-modal' data-ajax-url='ajax/ajax_ticket_task_edit.php' data-ajax-id='$task_id' class='btn btn-sm btn-primary mr-1' title='Edit Task'><i class='fas fa-edit'></i></a>";
                            $task_actions .= "<a href='post.php?delete_task=$task_id&csrf_token=" . $_SESSION['csrf_token'] . "' class='btn btn-sm btn-danger confirm-link' title='Delete Task'><i class='fas fa-trash'></i></a>";
                        }
                        
                        echo "<tr class='task-row' data-client='$client_id' data-status='$task_status'>";
                        echo "<td>$task_status_icon</td>";
                        echo "<td>$task_name</td>";
                        echo "<td><a href='$ticket_link'>$ticket_display</a></td>";
                        echo "<td>$client_name</td>";
                        echo "<td>$assignee_names</td>";
                        echo "<td>$task_completion_estimate min</td>";
                        echo "<td>$task_actions</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <div class="form-group">
                        <label for="ticket_id">Ticket</label>
                        <select class="form-control select2" name="ticket_id" id="ticket_id" required>
                            <?php
                            $sql_tickets = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_status != 5 ORDER BY ticket_number DESC");
                            while ($ticket_row = mysqli_fetch_array($sql_tickets)) {
                                $ticket_id = intval($ticket_row['ticket_id']);
                                $ticket_prefix = nullable_htmlentities($ticket_row['ticket_prefix']);
                                $ticket_number = intval($ticket_row['ticket_number']);
                                $ticket_subject = nullable_htmlentities($ticket_row['ticket_subject']);
                                echo "<option value='$ticket_id'>$ticket_prefix$ticket_number - $ticket_subject</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="name">Task Name</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    <div class="form-group">
                        <label for="estimate">Estimated Completion Time (minutes)</label>
                        <input type="number" class="form-control" name="estimate" id="estimate" value="15" min="1">
                    </div>
                    <div class="form-group">
                        <label>Assign To</label>
                        <select class="form-control select2" name="assignees[]" multiple data-placeholder="- Select Assignees -">
                            <?php
                            $sql_users = mysqli_query($mysqli, "SELECT users.user_id, user_name FROM users
                                LEFT JOIN user_settings on users.user_id = user_settings.user_id
                                WHERE user_type = 1
                                AND user_archived_at IS NULL
                                ORDER BY user_name ASC");
                            while ($user_row = mysqli_fetch_array($sql_users)) {
                                $user_id = intval($user_row['user_id']);
                                $user_name = nullable_htmlentities($user_row['user_name']);
                                echo "<option value='$user_id'>$user_name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="add_task" class="btn btn-primary">Add Task</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#tasksTable').DataTable({
        "order": [[0, "asc"], [1, "asc"]],
        "pageLength": 25,
        "searching": true,
        "dom": 'Bfrtip',
        "buttons": [
            {
                text: 'Add Task',
                className: 'btn btn-primary',
                action: function(e, dt, node, config) {
                    $('#addTaskModal').modal('show');
                }
            }
        ]
    });

    // Client filter
    $('#clientFilter').change(function() {
        filterTasks();
    });

    // Status filter
    $('#statusFilter').change(function() {
        filterTasks();
    });

    // Search functionality
    $('#searchButton').click(function() {
        filterTasks();
    });

    $('#searchInput').keyup(function(e) {
        if (e.keyCode === 13) {
            filterTasks();
        }
    });

    // Function to filter tasks
    function filterTasks() {
        var clientId = $('#clientFilter').val();
        var status = $('#statusFilter').val();
        var searchText = $('#searchInput').val().toLowerCase();

        $('.task-row').each(function() {
            var row = $(this);
            var rowClientId = row.data('client');
            var rowStatus = row.data('status');
            var rowText = row.text().toLowerCase();
            
            var clientMatch = clientId === '' || rowClientId == clientId;
            var statusMatch = status === '' || rowStatus === status;
            var textMatch = searchText === '' || rowText.includes(searchText);
            
            if (clientMatch && statusMatch && textMatch) {
                row.show();
            } else {
                row.hide();
            }
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>