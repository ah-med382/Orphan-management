<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin only
checkRole(['admin']);

$report_type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$orphan_status = $_GET['orphan_status'] ?? '';

$report_data = [];
$report_title = "Report Details";

try {
    if ($report_type === 'donations') {
        $report_title = "Donation Receipts Summary (" . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date)) . ")";
        $stmt = $pdo->prepare("
            SELECT d.*, dn.full_name AS donor_name, dn.email AS donor_email 
            FROM donations d 
            JOIN donors dn ON d.donor_id = dn.donor_id 
            WHERE d.donation_date BETWEEN ? AND ?
            ORDER BY d.donation_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll();
    } elseif ($report_type === 'orphans') {
        $status_label = !empty($orphan_status) ? $orphan_status : "All";
        $report_title = "Orphans Registry Summary (Status: " . $status_label . ")";
        
        $query = "SELECT * FROM orphans WHERE 1=1";
        $params = [];
        if (!empty($orphan_status)) {
            $query .= " AND status = ?";
            $params[] = $orphan_status;
        }
        $query .= " ORDER BY full_name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll();
    } elseif ($report_type === 'sponsorships') {
        $report_title = "Active Sponsorships Distribution Summary";
        $report_data = $pdo->query("
            SELECT s.*, o.full_name AS orphan_name, dn.full_name AS donor_name 
            FROM sponsorships s 
            JOIN orphans o ON s.orphan_id = o.orphan_id 
            JOIN donors dn ON s.donor_id = dn.donor_id 
            WHERE s.status = 'Active'
            ORDER BY s.start_date DESC
        ")->fetchAll();
    } elseif ($report_type === 'staff') {
        $report_title = "Orphanage Staff & Caretakers Directory";
        $report_data = $pdo->query("SELECT * FROM staff ORDER BY full_name ASC")->fetchAll();
    } elseif ($report_type === 'adoptions') {
        $report_title = "Adoption Requests Summary Report";
        $report_data = $pdo->query("
            SELECT ar.*, o.full_name AS orphan_name 
            FROM adoption_requests ar 
            JOIN orphans o ON ar.orphan_id = o.orphan_id 
            ORDER BY ar.request_date DESC
        ")->fetchAll();
    } else {
        die("Invalid report selection.");
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Print Report | OrphanCare</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      color: #333;
      margin: 0;
      padding: 20px;
      font-size: 12px;
    }
    .print-header {
      border-bottom: 2px solid #333;
      padding-bottom: 15px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }
    .print-header h1 {
      margin: 0;
      font-size: 20px;
      color: #1a202c;
    }
    .print-header span {
      font-size: 10px;
      color: #718096;
    }
    .report-title {
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 20px;
      color: #2d3748;
    }
    .report-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }
    .report-table th {
      background-color: #edf2f7;
      border: 1px solid #cbd5e1;
      padding: 8px;
      text-align: left;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 10px;
    }
    .report-table td {
      border: 1px solid #cbd5e1;
      padding: 8px;
    }
    .amount-cell {
      font-weight: 700;
      color: #2f855a;
    }
    .footer-bar {
      margin-top: 40px;
      display: flex;
      justify-content: space-between;
      font-size: 10px;
      color: #718096;
      border-top: 1px solid #edf2f7;
      padding-top: 15px;
    }
    .btn-bar {
      margin-bottom: 20px;
      text-align: right;
    }
    .print-btn {
      background-color: #2b6cb0;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
    }
    @media print {
      .btn-bar {
        display: none;
      }
      body {
        padding: 0;
      }
    }
  </style>
</head>
<body>

  <div class="btn-bar">
    <button class="print-btn" onclick="window.print();">Print Report</button>
  </div>

  <div class="print-header">
    <div>
      <h1>OrphanCare Administration</h1>
      <span>Online Orphan Management System Reports</span>
    </div>
    <div style="text-align: right;">
      <strong>Generated:</strong> <?php echo date('F d, Y H:i'); ?><br>
      <strong>Issuer:</strong> <?php echo escape($_SESSION['full_name']); ?>
    </div>
  </div>

  <div class="report-title">
    <?php echo escape($report_title); ?>
  </div>

  <table class="report-table">
    
    <!-- 1. DONATIONS -->
    <?php if ($report_type === 'donations'): ?>
      <thead>
        <tr>
          <th>TXN ID</th>
          <th>Donor Name</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Date</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($report_data) > 0): $total = 0; ?>
          <?php foreach ($report_data as $row): $total += $row['amount']; ?>
            <tr>
              <td>#TXN-<?php echo str_pad($row['donation_id'], 5, '0', STR_PAD_LEFT); ?></td>
              <td><strong><?php echo escape($row['donor_name']); ?></strong></td>
              <td class="amount-cell">$<?php echo number_format($row['amount'], 2); ?></td>
              <td><?php echo escape($row['payment_method']); ?></td>
              <td><?php echo date('M d, Y', strtotime($row['donation_date'])); ?></td>
              <td><?php echo escape($row['notes']); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr style="background-color: #f7fafc; font-weight: bold;">
            <td colspan="2" style="text-align: right;">Grand Total:</td>
            <td class="amount-cell">$<?php echo number_format($total, 2); ?></td>
            <td colspan="3"></td>
          </tr>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center;">No donations found.</td>
          </tr>
        <?php endif; ?>
      </tbody>

    <!-- 2. ORPHANS -->
    <?php elseif ($report_type === 'orphans'): ?>
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Gender</th>
          <th>DOB</th>
          <th>Admission Date</th>
          <th>Health Status</th>
          <th>Education Level</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($report_data) > 0): ?>
          <?php foreach ($report_data as $row): ?>
            <tr>
              <td>#ORP-<?php echo str_pad($row['orphan_id'], 4, '0', STR_PAD_LEFT); ?></td>
              <td><strong><?php echo escape($row['full_name']); ?></strong></td>
              <td><?php echo escape($row['gender']); ?></td>
              <td><?php echo date('M d, Y', strtotime($row['date_of_birth'])); ?></td>
              <td><?php echo date('M d, Y', strtotime($row['admission_date'])); ?></td>
              <td><?php echo escape($row['health_status']); ?></td>
              <td><?php echo escape($row['education_level']); ?></td>
              <td><?php echo escape($row['status']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" style="text-align: center;">No orphans listed.</td>
          </tr>
        <?php endif; ?>
      </tbody>

    <!-- 3. SPONSORSHIPS -->
    <?php elseif ($report_type === 'sponsorships'): ?>
      <thead>
        <tr>
          <th>Sponsorship ID</th>
          <th>Orphan Name</th>
          <th>Donor Name</th>
          <th>Monthly Pledge</th>
          <th>Start Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($report_data) > 0): $total_monthly = 0; ?>
          <?php foreach ($report_data as $row): $total_monthly += $row['sponsorship_amount']; ?>
            <tr>
              <td>#SPO-<?php echo str_pad($row['sponsorship_id'], 5, '0', STR_PAD_LEFT); ?></td>
              <td><strong><?php echo escape($row['orphan_name']); ?></strong></td>
              <td><?php echo escape($row['donor_name']); ?></td>
              <td class="amount-cell">$<?php echo number_format($row['sponsorship_amount'], 2); ?></td>
              <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
              <td><?php echo escape($row['status']); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr style="background-color: #f7fafc; font-weight: bold;">
            <td colspan="3" style="text-align: right;">Combined Monthly Commitments:</td>
            <td class="amount-cell">$<?php echo number_format($total_monthly, 2); ?>/mo</td>
            <td colspan="2"></td>
          </tr>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center;">No active sponsorships.</td>
          </tr>
        <?php endif; ?>
      </tbody>

    <!-- 4. STAFF -->
    <?php elseif ($report_type === 'staff'): ?>
      <thead>
        <tr>
          <th>Staff ID</th>
          <th>Full Name</th>
          <th>Position</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Monthly Salary</th>
          <th>Joining Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($report_data) > 0): $total_sal = 0; ?>
          <?php foreach ($report_data as $row): $total_sal += $row['salary']; ?>
            <tr>
              <td>#STF-<?php echo str_pad($row['staff_id'], 3, '0', STR_PAD_LEFT); ?></td>
              <td><strong><?php echo escape($row['full_name']); ?></strong></td>
              <td><?php echo escape($row['position']); ?></td>
              <td><?php echo escape($row['phone']); ?></td>
              <td><?php echo escape($row['email']); ?></td>
              <td style="font-weight: 500;">$<?php echo number_format($row['salary'], 2); ?></td>
              <td><?php echo date('M d, Y', strtotime($row['joining_date'])); ?></td>
            </tr>
          <?php endforeach; ?>
          <tr style="background-color: #f7fafc; font-weight: bold;">
            <td colspan="5" style="text-align: right;">Total Payroll Commitment:</td>
            <td style="color: #c53030;">$<?php echo number_format($total_sal, 2); ?>/mo</td>
            <td></td>
          </tr>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center;">No staff registered.</td>
          </tr>
        <?php endif; ?>
      </tbody>

    <!-- 5. ADOPTIONS -->
    <?php elseif ($report_type === 'adoptions'): ?>
      <thead>
        <tr>
          <th>Request ID</th>
          <th>Applicant Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Orphan Name</th>
          <th>Request Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($report_data) > 0): ?>
          <?php foreach ($report_data as $row): ?>
            <tr>
              <td>#REQ-<?php echo str_pad($row['request_id'], 5, '0', STR_PAD_LEFT); ?></td>
              <td><strong><?php echo escape($row['applicant_name']); ?></strong></td>
              <td><?php echo escape($row['phone']); ?></td>
              <td><?php echo escape($row['email']); ?></td>
              <td><strong><?php echo escape($row['orphan_name']); ?></strong></td>
              <td><?php echo date('M d, Y', strtotime($row['request_date'])); ?></td>
              <td><?php echo escape($row['status']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="7" style="text-align: center;">No adoption records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    <?php endif; ?>

  </table>

  <div class="footer-bar">
    <span>Online Orphan Management System</span>
    <span>Page 1 of 1</span>
  </div>

  <script>
    window.addEventListener('load', () => {
      setTimeout(() => {
        window.print();
      }, 500);
    });
  </script>

</body>
</html>
