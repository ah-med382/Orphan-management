<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$orphan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orphan_id > 0) {
    try {
        // Fetch current photo
        $stmtPhoto = $pdo->prepare("SELECT photo FROM orphans WHERE orphan_id = ? LIMIT 1");
        $stmtPhoto->execute([$orphan_id]);
        $photo = $stmtPhoto->fetchColumn();

        // Delete photo from folder
        if (!empty($photo)) {
            $photo_path = __DIR__ . '/../../assets/images/' . $photo;
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
        }

        // Delete records
        $stmtDel = $pdo->prepare("DELETE FROM orphans WHERE orphan_id = ?");
        $stmtDel->execute([$orphan_id]);

        $_SESSION['success_message'] = "Orphan record deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Could not delete record: " . $e->getMessage();
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

header("Location: /orphan_management/modules/orphans/index.php");
exit;
?>
