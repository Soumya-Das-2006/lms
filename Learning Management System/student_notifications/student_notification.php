<?php include('header_dashboard.php'); ?>
<?php include('session.php'); ?>
<body>
    <?php include('navbar_student.php'); ?>
    <div class="container-fluid">
        <div class="row-fluid">
            <?php include('student_notification_sidebar.php'); ?>
            <div class="span9" id="content">
                <div class="row-fluid">
                    <!-- breadcrumb -->
                    <ul class="breadcrumb">
                        <?php
                        $school_year_query = mysqli_query($conn, "SELECT * FROM school_year ORDER BY school_year DESC") or die(mysqli_error($conn));
                        $school_year_query_row = mysqli_fetch_array($school_year_query);
                        $school_year = $school_year_query_row ? $school_year_query_row['school_year'] : '';
                        ?>
                        <li><a href="#"><b>My Class</b></a><span class="divider">/</span></li>
                        <li><a href="#">School Year: <?php echo htmlspecialchars($school_year); ?></a></li>
                    </ul>
                    <!-- end breadcrumb -->

                    <!-- block -->
                    <div id="block_bg" class="block">
                        <div class="navbar navbar-inner block-header">
                            <div id="" class="muted pull-left">
                                <h4><i class="icon-bell"></i> 
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
                                // Count total notifications - UPDATED WITH DISTINCT
                                $total_query = mysqli_query(
                                    $conn,
                                    "SELECT COUNT(DISTINCT n.notification_id) as total 
                                    FROM teacher_class_student tcs
                                    LEFT JOIN teacher_class tc ON tcs.teacher_class_id = tc.teacher_class_id 
                                    LEFT JOIN notification n ON n.teacher_class_id = tc.teacher_class_id 	
                                    WHERE tcs.student_id = '$session_id' 
                                    AND tc.school_year = '$school_year'"
                                ) or die(mysqli_error($conn));
                                $total_row = mysqli_fetch_array($total_query);
                                $total_notifications = $total_row['total'];
                                
                                // Count unread notifications - UPDATED WITH DISTINCT
                                $unread_query = mysqli_query(
                                    $conn,
                                    "SELECT COUNT(DISTINCT n.notification_id) as unread 
                                    FROM teacher_class_student tcs
                                    LEFT JOIN teacher_class tc ON tcs.teacher_class_id = tc.teacher_class_id 
                                    LEFT JOIN notification n ON n.teacher_class_id = tc.teacher_class_id 
                                    LEFT JOIN notification_read nr ON n.notification_id = nr.notification_id 
                                    AND nr.student_id = '$session_id'
                                    WHERE tcs.student_id = '$session_id' 
                                    AND tc.school_year = '$school_year'
                                    AND (nr.student_read IS NULL OR nr.student_read = 'no')"
                                ) or die(mysqli_error($conn));
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
                                        <a href="student_notification.php?view=all" class="btn btn-success">
                                            <i class="icon-list"></i> View All (<?php echo $total_notifications; ?>)
                                        </a>
                                    <?php } else { ?>
                                        <a href="student_notification.php" class="btn btn-warning">
                                            <i class="icon-arrow-left"></i> Back to Recent
                                        </a>
                                    <?php } ?>
                                </div>

                                <form action="read.php" method="post">
                                    <?php 
                                    // Determine limit based on view
                                    $limit = (isset($_GET['view']) && $_GET['view'] == 'all') ? "" : "LIMIT 6";
                                    
                                    // Count unread notifications for current view - UPDATED WITH DISTINCT
                                    $not_read_query = mysqli_query(
                                        $conn,
                                        "SELECT COUNT(DISTINCT n.notification_id) as not_read 
                                        FROM teacher_class_student tcs
                                        LEFT JOIN teacher_class tc ON tcs.teacher_class_id = tc.teacher_class_id 
                                        LEFT JOIN notification n ON n.teacher_class_id = tc.teacher_class_id 
                                        LEFT JOIN notification_read nr ON n.notification_id = nr.notification_id 
                                        AND nr.student_id = '$session_id'
                                        WHERE tcs.student_id = '$session_id' 
                                        AND tc.school_year = '$school_year'
                                        AND (nr.student_read IS NULL OR nr.student_read = 'no')
                                        ORDER BY n.date_of_notification DESC
                                        $limit"
                                    ) or die(mysqli_error($conn));
                                    $not_read_row = mysqli_fetch_array($not_read_query);
                                    $not_read = $not_read_row['not_read'];
                                    
                                    if ($not_read > 0) { 
                                    ?>
                                        <button id="delete" class="btn btn-info" name="read">
                                            <i class="icon-check"></i> Mark Selected as Read
                                        </button>
                                        <div class="pull-right">
                                            Check All <input type="checkbox" name="selectAll" id="checkAll" />
                                            <script>
                                                $("#checkAll").click(function() {
                                                    $('input:checkbox').not(this).prop('checked', this.checked);
                                                });
                                            </script>
                                        </div>
                                        <div class="clearfix" style="margin-bottom: 15px;"></div>
                                    <?php } ?>

                                    <?php
                                    // Get notifications with appropriate limit - UPDATED WITH DISTINCT
                                    $query = mysqli_query(
                                        $conn,
                                        "SELECT DISTINCT 
                                            n.notification_id,
                                            n.notification,
                                            n.date_of_notification,
                                            n.link,
                                            tc.teacher_class_id,
                                            c.class_name,
                                            s.subject_code,
                                            t.firstname,
                                            t.lastname
                                        FROM teacher_class_student tcs
                                        LEFT JOIN teacher_class tc ON tcs.teacher_class_id = tc.teacher_class_id 
                                        LEFT JOIN class c ON tc.class_id = c.class_id 
                                        LEFT JOIN subject s ON tc.subject_id = s.subject_id
                                        LEFT JOIN teacher t ON tc.teacher_id = t.teacher_id 
                                        JOIN notification n ON n.teacher_class_id = tc.teacher_class_id 	
                                        WHERE tcs.student_id = '$session_id' 
                                        AND tc.school_year = '$school_year'  
                                        ORDER BY n.date_of_notification DESC
                                        $limit"
                                    ) or die(mysqli_error($conn));

                                    $count = mysqli_num_rows($query);

                                    if ($count > 0) {
                                        while ($row = mysqli_fetch_array($query)) {
                                            $get_id = $row['teacher_class_id'];
                                            $id = $row['notification_id'];

                                            $query_yes_read = mysqli_query(
                                                $conn,
                                                "SELECT * FROM notification_read 
                                                 WHERE notification_id = '$id' 
                                                 AND student_id = '$session_id'"
                                            ) or die(mysqli_error($conn));

                                            $read_row = mysqli_fetch_array($query_yes_read);
                                            $yes = $read_row ? $read_row['student_read'] : null;
                                            ?>
                                            
                                            <div class="post" id="del<?php echo $id; ?>" style="padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; <?php echo ($yes !== 'yes') ? 'background-color: #f0f7ff; border-left: 4px solid #3498db;' : ''; ?>">
                                                <?php if ($yes !== 'yes') { ?>
                                                    <input name="selector[]" type="checkbox" value="<?php echo $id; ?>" style="margin-right: 10px;">	
                                                <?php } ?>	

                                                <strong><?php echo htmlspecialchars($row['firstname'] . " " . $row['lastname']); ?></strong>
                                                <?php echo htmlspecialchars($row['notification']); ?> In 
                                                <a href="<?php echo htmlspecialchars($row['link']) . '?id=' . $get_id; ?>" class="btn btn-small btn-primary">
                                                    <?php echo htmlspecialchars($row['class_name']); ?> 
                                                    <?php echo htmlspecialchars($row['subject_code']); ?> 
                                                </a>
                                                <hr style="margin: 10px 0;">
                                                <div class="pull-right">
                                                    <i class="icon-calendar"></i>&nbsp;
                                                    <?php 
                                                    $timestamp = strtotime($row['date_of_notification']);
                                                    echo date('M j, Y \a\t g:i A', $timestamp);
                                                    ?> 
                                                </div>
                                                <div class="clearfix"></div>
                                            </div>

                                            <?php
                                        }
                                    } else {
                                        ?>
                                        <div class="alert alert-info">
                                            <strong><i class="icon-info-sign"></i> No Notifications Found</strong>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </form>

                                <!-- Show View All button only when on recent view and there are more than 10 notifications -->
                                <?php if (!isset($_GET['view']) && $total_notifications > 10) { ?>
                                    <div class="text-center" style="margin-top: 20px;">
                                        <a href="student_notification.php?view=all" class="btn btn-success btn-large">
                                            <i class="icon-list"></i> View All Notifications (<?php echo $total_notifications; ?>)
                                        </a>
                                    </div>
                                <?php } ?>

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