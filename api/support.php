<?php
// api/support.php — Toggle support for an issue (requires login)
session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

function respond($arr)
{
    echo json_encode($arr);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'message' => 'Please sign in to support issues.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Invalid request.']);
}

$userId  = (int)$_SESSION['user_id'];
$issueId = (int)($_POST['issue_id'] ?? 0);

if ($issueId <= 0) {
    respond(['success' => false, 'message' => 'Invalid issue.']);
}

$db = getDB();

// Verify issue exists and is supportable
$stmt = $db->prepare("SELECT id, citizen_id, status FROM issues WHERE id = ?");
$stmt->execute([$issueId]);
$issue = $stmt->fetch();

if (!$issue) {
    respond(['success' => false, 'message' => 'Issue not found.']);
}

if (in_array($issue['status'], ['closed', 'rejected'])) {
    respond(['success' => false, 'message' => 'This issue is already resolved/rejected.']);
}

if ((int)$issue['citizen_id'] === $userId) {
    respond(['success' => false, 'message' => 'You cannot support your own issue.']);
}

// Check if already supported
$chk = $db->prepare("SELECT id FROM supports WHERE issue_id = ? AND citizen_id = ?");
$chk->execute([$issueId, $userId]);

if ($chk->fetch()) {
    respond(['success' => false, 'message' => 'You have already supported this issue.']);
}

// Insert support + increment counter atomically
try {
    $db->beginTransaction();

    $db->prepare("INSERT INTO supports (issue_id, citizen_id) VALUES (?, ?)")
        ->execute([$issueId, $userId]);

    $db->prepare("UPDATE issues SET support_count = support_count + 1 WHERE id = ?")
        ->execute([$issueId]);

    // Recalculate priority score: base + support boost
    $db->prepare("
        UPDATE issues
        SET priority_score = priority_score + 2
        WHERE id = ?
    ")->execute([$issueId]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    respond(['success' => false, 'message' => 'Could not record your support.']);
}

// Get updated count
$cnt = $db->prepare("SELECT support_count FROM issues WHERE id = ?");
$cnt->execute([$issueId]);
$newCount = (int)$cnt->fetchColumn();

respond(['success' => true, 'count' => $newCount, 'message' => 'Thank you for your support!']);
