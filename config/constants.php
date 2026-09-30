<?php
// config/constants.php

define('BASE_URL',    'http://localhost/civictrack/public/');
define('UPLOADS_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOADS_URL', 'http://localhost/civictrack/uploads/');

define('STATUS_LABELS', [
    'pending'     => ['label' => 'Pending',     'class' => 'badge-pending'],
    'verified'    => ['label' => 'Verified',    'class' => 'badge-verified'],
    'assigned'    => ['label' => 'Assigned',    'class' => 'badge-assigned'],
    'in_progress' => ['label' => 'In Progress', 'class' => 'badge-inprogress'],
    'resolved'    => ['label' => 'Resolved',    'class' => 'badge-resolved'],
    'closed'      => ['label' => 'Closed',      'class' => 'badge-closed'],
    'rejected'    => ['label' => 'Rejected',    'class' => 'badge-rejected'],
]);

define('CATEGORIES', [
    'Road Damage', 'Street Light', 'Garbage',
    'Water Leakage', 'Drainage', 'Public Park',
    'Footpath', 'Public Safety', 'Other'
]);
