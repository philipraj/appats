<?php
require_once 'config.php';
require_once 'auth.php';
include 'header.php';

$user_id = $_SESSION['user_id'];

// Check if the user has an open check-in (checked in but not checked out)
$today_start = date('Y-m-d 00:00:00');
$checkin_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND check_in_time >= ? AND check_out_time IS NULL");
$checkin_stmt->bind_param("is", $user_id, $today_start);
$checkin_stmt->execute();
$open_checkin = $checkin_stmt->get_result()->fetch_assoc();

// Fetch this user's attendance and leave records
$attendance_records = $conn->query("SELECT * FROM attendance WHERE user_id = $user_id ORDER BY check_in_time DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$leave_records = $conn->query("SELECT * FROM leaves WHERE user_id = $user_id ORDER BY start_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>

<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />

<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><h4 class="mb-0">My Attendance</h4></div>
            <div class="card-body text-center">
                <p>Please check in when you start your day and check out when you finish.</p>
                <form action="process_attendance.php" method="POST">
                    <?php if ($open_checkin): ?>
                        <button type="submit" name="check_out" class="btn btn-danger btn-lg">Check Out</button>
                    <?php else: ?>
                        <button type="submit" name="check_in" class="btn btn-success btn-lg">Check In</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header"><h4 class="mb-0">Apply for Leave</h4></div>
            <div class="card-body">
                <form action="process_leave.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Reason for Leave</label><textarea name="reason" class="form-control" rows="1"></textarea></div>
                    <button type="submit" name="apply_leave" class="btn btn-primary w-100">Submit Leave Request</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header"><h5>My Attendance History (Last 30 Days)</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Duration</th></tr></thead>
                    <tbody>
                        <?php foreach($attendance_records as $rec): ?>
                            <tr>
                                <td><?php echo date('d-M-Y', strtotime($rec['check_in_time'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($rec['check_in_time'])); ?></td>
                                <td><?php echo $rec['check_out_time'] ? date('h:i A', strtotime($rec['check_out_time'])) : '<i>Still logged in</i>'; ?></td>
                                <td><?php if($rec['check_out_time']) { $duration = (new DateTime($rec['check_in_time']))->diff(new DateTime($rec['check_out_time'])); echo $duration->format('%h hrs %i mins'); } ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header"><h5>My Leave Requests</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>From</th><th>To</th><th>Reason</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($leave_records as $leave): ?>
                            <tr>
                                <td><?php echo date('d-M-Y', strtotime($leave['start_date'])); ?></td>
                                <td><?php echo date('d-M-Y', strtotime($leave['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                <td><span class="badge bg-<?php echo ($leave['status'] == 'Approved' ? 'success' : ($leave['status'] == 'Rejected' ? 'danger' : 'warning')); ?>"><?php echo $leave['status']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header"><h5><i class="bi bi-calendar3"></i> Company Calendar (Holidays & Events)</h5></div>
            <div class="card-body">
                <div id='calendar'></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="eventModalLabel">Manage Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" id="eventId">
                <div class="mb-3"><label for="eventTitle" class="form-label">Title</label><input type="text" class="form-control" id="eventTitle" required></div>
                <div class="mb-3"><label for="eventType" class="form-label">Type</label><select id="eventType" class="form-select"><option value="Event">Event</option><option value="Holiday">Official Holiday</option></select></div>
                <div class="mb-3"><label for="eventStart" class="form-label">Start Date</label><input type="date" class="form-control" id="eventStart" required></div>
                <div class="mb-3"><label for="eventEnd" class="form-label">End Date (optional)</label><input type="date" class="form-control" id="eventEnd"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <div><button type="button" class="btn btn-danger" id="deleteEventBtn">Delete</button></div>
                <div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" id="saveEventBtn">Save</button></div>
            </div>
        </div>
    </div>
</div>


<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
    
    // THIS IS THE KEY SECURITY CHECK
    // The hasPermission() function is called here to decide if editing is enabled.
    var hasEditPermission = <?php echo hasPermission('attendance', 'edit') ? 'true' : 'false'; ?>;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        events: 'fetch_events.php',
        
        // Editing features are enabled/disabled based on user permission
        editable: hasEditPermission,      
        selectable: hasEditPermission,    
        
        // Functions for adding/editing events will only trigger if selectable/editable is true
        select: function(info) {
            $('#eventModalLabel').text('Add Event');
            $('#eventId').val('');
            $('#eventTitle').val('');
            $('#eventType').val('Event');
            $('#eventStart').val(info.startStr);
            $('#eventEnd').val('');
            $('#deleteEventBtn').hide();
            eventModal.show();
        },
        eventClick: function(info) {
            if (!hasEditPermission) return;
            $('#eventModalLabel').text('Edit Event');
            $('#eventId').val(info.event.id);
            $('#eventTitle').val(info.event.title);
            $('#eventType').val(info.event.extendedProps.type || 'Event');
            $('#eventStart').val(info.event.startStr);
            var endDate = info.event.end ? new Date(info.event.endStr) : null;
            if (endDate) {
                endDate.setDate(endDate.getDate() - 1);
                $('#eventEnd').val(endDate.toISOString().split('T')[0]);
            } else {
                $('#eventEnd').val('');
            }
            $('#deleteEventBtn').show();
            eventModal.show();
        },
        eventDrop: function(info) {
            var eventData = { id: info.event.id, start: info.event.startStr, end: info.event.endStr || null, action: 'drag_update' };
            $.post('manage_events.php', eventData, (response) => {
                if(response.status != 'success') { alert('Error: ' + response.message); info.revert(); }
            });
        }
    });
    calendar.render();

    // The modal buttons will only be used if the modal can be opened (i.e., by an admin)
    $('#saveEventBtn').on('click', function() {
        var endDateVal = $('#eventEnd').val();
        var eventData = {
            id: $('#eventId').val(),
            title: $('#eventTitle').val(),
            start: $('#eventStart').val(),
            end: endDateVal || null,
            type: $('#eventType').val(),
            action: $('#eventId').val() ? 'update' : 'add'
        };
        if (eventData.end) {
             var endDate = new Date(eventData.end);
             endDate.setDate(endDate.getDate() + 1);
             eventData.end = endDate.toISOString().split('T')[0];
        }
        $.post('manage_events.php', eventData, (response) => {
            if(response.status == 'success') { eventModal.hide(); calendar.refetchEvents(); } 
            else { alert('Error: ' + response.message); }
        });
    });
    $('#deleteEventBtn').on('click', function() {
        if (!confirm('Are you sure?')) return;
        $.post('manage_events.php', { id: $('#eventId').val(), action: 'delete' }, (response) => {
            if(response.status == 'success') { eventModal.hide(); calendar.refetchEvents(); } 
            else { alert('Error: ' + response.message); }
        });
    });
});
</script>

<?php include 'footer.php'; ?>