<?php include('header_dashboard.php'); ?>
<?php include('session.php'); ?>
<body>
    <?php include('navbar_teacher.php'); ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <?php include('teacher_notification_sidebar.php'); ?>
            <div class="span9" id="content">
                <div class="row-fluid">
                    <!-- breadcrumb -->
                    <ul class="breadcrumb">
                        <?php
                        $school_year_query = mysqli_query($conn,"SELECT * FROM school_year ORDER BY school_year DESC") 
                            or die(mysqli_error($conn));
                        $school_year_query_row = mysqli_fetch_array($school_year_query);
                        $school_year = $school_year_query_row ? $school_year_query_row['school_year'] : "N/A";
                        ?>
                        <li><a href="#"><b>My Class</b></a><span class="divider">/</span></li>
                        <li><a href="#">School Year: <?php echo htmlspecialchars($school_year); ?></a><span class="divider">/</span></li>
                        <li><a href="#"><b>Notification</b></a></li>
                    </ul>
                    <!-- end breadcrumb -->

                    <!-- block -->
                    <div id="block_bg" class="block">
                        <div class="navbar navbar-inner block-header">
                            <div class="muted pull-left">
                                <h4>
                                    <?php 
                                    // Check if showing all or recent
                                    if (isset($_GET['view']) && $_GET['view'] == 'all') {
                                        echo "All Notifications";
                                    } else {
                                        echo "Recent Notifications";
                                    }
                                    ?>
                                </h4>
                            </div>
                            <div class="pull-right">
                                <?php
                                // Count total notifications
                                $total_query = mysqli_query($conn,"SELECT COUNT(*) as total FROM teacher_notification
                                    LEFT JOIN teacher_class ON teacher_class.teacher_class_id = teacher_notification.teacher_class_id
                                    WHERE teacher_class.teacher_id = '$session_id'") or die(mysqli_error($conn));
                                $total_row = mysqli_fetch_array($total_query);
                                $total_notifications = $total_row['total'];
                                
                                // Count unread notifications
                                $unread_query = mysqli_query($conn,"SELECT COUNT(*) as unread FROM teacher_notification
                                    LEFT JOIN teacher_class ON teacher_class.teacher_class_id = teacher_notification.teacher_class_id
                                    LEFT JOIN notification_read_teacher ON teacher_notification.teacher_notification_id = notification_read_teacher.notification_id 
                                    AND notification_read_teacher.teacher_id = '$session_id'
                                    WHERE teacher_class.teacher_id = '$session_id'
                                    AND (notification_read_teacher.student_read IS NULL OR notification_read_teacher.student_read = 'no')") 
                                    or die(mysqli_error($conn));
                                $unread_row = mysqli_fetch_array($unread_query);
                                $unread_count = $unread_row['unread'];
                                
                                if ($unread_count > 0) {
                                    echo '<span class="badge badge-important">' . $unread_count . ' unread</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="block-content collapse in">
                            <div class="span12">

                                <!-- View Toggle Buttons -->
                                <div class="btn-group" style="margin-bottom: 15px;">
                                    <?php if (!isset($_GET['view']) || $_GET['view'] != 'all') { ?>
                                        <?php if ($total_notifications > 10) { ?>
                                            <a href="teacher_notification.php?view=all" class="btn btn-success">
                                                <i class="icon-list"></i> View All (<?php echo $total_notifications; ?>)
                                            </a>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <a href="teacher_notification.php" class="btn btn-warning">
                                            <i class="icon-arrow-left"></i> Back to Recent
                                        </a>
                                    <?php } ?>
                                </div>

                                <!-- Check All -->
                                <?php 
                                // Determine limit based on view
                                $limit = (isset($_GET['view']) && $_GET['view'] == 'all') ? "" : "LIMIT 10";
                                
                                // Count unread notifications for current view
                                $not_read_query = mysqli_query($conn,"SELECT COUNT(*) as not_read FROM teacher_notification
                                    LEFT JOIN teacher_class ON teacher_class.teacher_class_id = teacher_notification.teacher_class_id
                                    LEFT JOIN notification_read_teacher ON teacher_notification.teacher_notification_id = notification_read_teacher.notification_id 
                                    AND notification_read_teacher.teacher_id = '$session_id'
                                    WHERE teacher_class.teacher_id = '$session_id'
                                    AND (notification_read_teacher.student_read IS NULL OR notification_read_teacher.student_read = 'no')
                                    ORDER BY teacher_notification.date_of_notification DESC
                                    $limit") or die(mysqli_error($conn));
                                $not_read_row = mysqli_fetch_array($not_read_query);
                                $not_read = $not_read_row['not_read'];
                                ?>

                                <?php if ($not_read > 0) { ?>
                                    <div class="pull-right" style="margin-bottom:10px;">
                                        <label>
                                            Check All <input type="checkbox" name="selectAll" id="checkAll" />
                                        </label>
                                    </div>
                                <?php } ?>

                                <form id="domainTable" action="read_teacher.php" method="post">
                                    <?php if ($not_read > 0) { ?>
                                        <button id="delete" class="btn btn-info" name="read">
                                            <i class="icon-check"></i> Mark Selected as Read
                                        </button>
                                        <div class="clearfix" style="margin-bottom: 15px;"></div>
                                    <?php } ?>

                                    <?php
                                    $query = mysqli_query($conn,"SELECT * FROM teacher_notification
                                        LEFT JOIN teacher_class ON teacher_class.teacher_class_id = teacher_notification.teacher_class_id
                                        LEFT JOIN student ON student.student_id = teacher_notification.student_id
                                        LEFT JOIN assignment ON assignment.assignment_id = teacher_notification.assignment_id 
                                        LEFT JOIN class ON teacher_class.class_id = class.class_id
                                        LEFT JOIN subject ON teacher_class.subject_id = subject.subject_id
                                        WHERE teacher_class.teacher_id = '$session_id'
                                        ORDER BY teacher_notification.date_of_notification DESC
                                        $limit
                                    ") or die(mysqli_error($conn));

                                    $count = mysqli_num_rows($query);

                                    if ($count == 0) {
                                        echo "<div class='alert alert-info'>No notifications available.</div>";
                                    }

                                    while ($row = mysqli_fetch_array($query)) {
                                        $assignment_id = $row['assignment_id'];
                                        $get_id        = $row['teacher_class_id'];
                                        $id            = $row['teacher_notification_id'];

                                        // ✅ Check if notification is already marked as read
                                        $query_yes_read = mysqli_query($conn,"SELECT * FROM notification_read_teacher 
                                            WHERE notification_id = '$id' AND teacher_id = '$session_id'") 
                                            or die(mysqli_error($conn));

                                        $read_row = mysqli_fetch_array($query_yes_read);
                                        $yes = $read_row ? $read_row['student_read'] : null;
                                        ?>
                                        
                                        <div class="post well" id="del<?php echo $id; ?>" style="<?php echo ($yes !== 'yes') ? 'background-color: #f0f7ff; border-left: 4px solid #3498db;' : ''; ?>">
                                            <?php if ($yes !== 'yes') { ?>
                                                <input name="selector[]" type="checkbox" value="<?php echo $id; ?>" style="margin-right: 10px;">
                                            <?php } ?>

                                            <strong><?php echo htmlspecialchars($row['firstname']." ".$row['lastname']); ?></strong>
                                            <?php echo htmlspecialchars($row['notification']); ?> 
                                            
                                            <?php if (!empty($row['fname'])) { ?>
                                                in <strong><?php echo htmlspecialchars($row['fname']); ?></strong>
                                            <?php } ?>
                                            
                                            <a href="<?php echo $row['link'].'?id='.$get_id.'&post_id='.$assignment_id; ?>" class="btn btn-small btn-primary">
                                                <?php echo htmlspecialchars($row['class_name']); ?> 
                                                <?php echo htmlspecialchars($row['subject_code']); ?>
                                            </a>

                                            <hr style="margin: 10px 0;">
                                            <div class="pull-right text-muted">
                                                <i class="icon-calendar"></i> 
                                                <?php 
                                                $timestamp = strtotime($row['date_of_notification']);
                                                echo date('M j, Y \a\t g:i A', $timestamp);
                                                ?>
                                            </div>
                                            <div class="clearfix"></div>
                                        </div>

                                        <?php
                                    }
                                    ?>
                                </form>

                                <!-- Show View All button only when on recent view and there are more than 10 notifications -->
                                <?php if (!isset($_GET['view']) && $total_notifications > 10) { ?>
                                    <div class="text-center" style="margin-top: 20px;">
                                        <a href="teacher_notification.php?view=all" class="btn btn-success btn-large">
                                            <i class="icon-list"></i> View All Notifications (<?php echo $total_notifications; ?>)
                                        </a>
                                    </div>
                                <?php } ?>

                                <!-- Script for Check All -->
                                <script>
                                document.getElementById("checkAll")?.addEventListener("click", function () {
                                    const checkboxes = document.querySelectorAll("#domainTable input[type=checkbox]");
                                    checkboxes.forEach(cb => cb.checked = this.checked);
                                });
                                </script>

                            </div>
                        </div>
                    </div>
                    <!-- /block -->
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
    <?php include('script.php'); ?>
</body>
</html>