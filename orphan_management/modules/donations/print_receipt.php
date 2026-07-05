<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

// Restricted to Admin and Donor
checkLogin();

$donation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Fetch donation info
    $stmt = $pdo->prepare("
        SELECT d.*, dn.full_name AS donor_name, dn.phone AS donor_phone, dn.email AS donor_email, dn.address AS donor_address, fa.account_name
        FROM donations d 
        JOIN donors dn ON d.donor_id = dn.donor_id 
        LEFT JOIN finance_accounts fa ON d.account_id = fa.account_id
        WHERE d.donation_id = ? LIMIT 1
    ");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch();

    if (!$donation) {
        die("Donation transaction record not found.");
    }

    // Access protection: Donor can only print their own receipts
    if ($_SESSION['role'] === 'donor' && $donation['donor_id'] != $_SESSION['donor_id']) {
        die("Access denied. You can only view your own receipts.");
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Donation Receipt | Zamzam KidsCare</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      color: #333;
      margin: 0;
      padding: 30px;
      background-color: #fff;
    }
    .receipt-container {
      max-width: 700px;
      margin: 0 auto;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 40px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .receipt-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 2px solid #edf2f7;
      padding-bottom: 20px;
      margin-bottom: 30px;
    }
    .logo-section h2 {
      margin: 0;
      color: #ef4444;
      font-weight: 700;
      font-size: 1.6rem;
    }
    .logo-section span {
      color: #4a5568;
      font-size: 0.85rem;
    }
    .receipt-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #2d3748;
      margin: 0;
    }
    .receipt-meta {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
      font-size: 0.9rem;
    }
    .meta-col {
      line-height: 1.5;
    }
    .meta-col strong {
      color: #2d3748;
    }
    .billing-details {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 40px;
      font-size: 0.9rem;
      background-color: #f7fafc;
      padding: 20px;
      border-radius: 8px;
    }
    .billing-col h4 {
      margin-top: 0;
      margin-bottom: 10px;
      color: #4a5568;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    .receipt-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 40px;
    }
    .receipt-table th {
      text-align: left;
      background-color: #edf2f7;
      padding: 12px;
      font-size: 0.85rem;
      text-transform: uppercase;
      color: #4a5568;
      font-weight: 600;
    }
    .receipt-table td {
      padding: 15px 12px;
      border-bottom: 1px solid #edf2f7;
      font-size: 0.9rem;
    }
    .receipt-table td.amount-cell {
      font-size: 1.1rem;
      font-weight: 700;
      color: #2f855a;
    }
    .receipt-footer {
      border-top: 2px solid #edf2f7;
      padding-top: 20px;
      text-align: center;
      font-size: 0.8rem;
      color: #a0aec0;
      margin-top: 40px;
    }
    .print-btn-bar {
      max-width: 700px;
      margin: 20px auto 0 auto;
      text-align: right;
    }
    .print-btn {
      background-color: #3182ce;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      font-size: 0.9rem;
      cursor: pointer;
      font-weight: 500;
      box-shadow: 0 4px 6px -1px rgba(49, 130, 206, 0.3);
      transition: background-color 0.2s;
    }
    .print-btn:hover {
      background-color: #2b6cb0;
    }

    @media print {
      .print-btn-bar {
        display: none;
      }
      body {
        padding: 0;
      }
      .receipt-container {
        border: none;
        box-shadow: none;
        padding: 0;
      }
    }
  </style>
</head>
<body>

  <div class="receipt-container">
    
    <div class="receipt-header">
      <div class="logo-section d-flex align-items-center">
        <img src="/orphan_management/assets/images/zamzam_logo.jpg" alt="Logo" class="img-circle" style="width: 45px; height: 45px; object-fit: cover; margin-right: 10px; border: 1px solid #cbd5e1;">
        <div>
          <h2 style="margin: 0; color: #1e293b; font-weight: 700; font-size: 1.4rem;">Zamzam KidsCare</h2>
          <span style="color: #64748b; font-size: 0.8rem;">KidsCare Foundation Portal</span>
        </div>
      </div>
      <div>
        <h3 class="receipt-title">Official Donation Receipt</h3>
      </div>
    </div>

    <div class="receipt-meta">
      <div class="meta-col">
        <strong>Receipt Number:</strong> #REC-<?php echo str_pad($donation['donation_id'], 5, '0', STR_PAD_LEFT); ?><br>
        <strong>Payment Date:</strong> <?php echo date('F d, Y', strtotime($donation['donation_date'])); ?>
      </div>
      <div class="meta-col" style="text-align: right;">
        <strong>Status:</strong> COMPLETED<br>
        <strong>Method:</strong> <?php echo escape($donation['payment_method']); ?>
      </div>
    </div>

    <div class="billing-details">
      <div class="billing-col">
        <h4>Foundation Details</h4>
        <strong>Zamzam KidsCare Foundation</strong><br>
        100 Hopeful Way<br>
        Springfield, OR 97477<br>
        finance@zamzamkidscare.org
      </div>
      <div class="billing-col">
        <h4>Received From (Donor)</h4>
        <strong><?php echo escape($donation['donor_name']); ?></strong><br>
        <?php echo !empty($donation['donor_address']) ? escape($donation['donor_address']) : 'Address not listed'; ?><br>
        Phone: <?php echo escape($donation['donor_phone']); ?><br>
        Email: <?php echo escape($donation['donor_email']); ?>
      </div>
    </div>

    <table class="receipt-table">
      <thead>
        <tr>
          <th>Description</th>
          <th style="text-align: right;">Total Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong><?php echo escape($donation['account_name'] ?? 'General Donation Fund'); ?></strong><br>
            <span style="color: #718096; font-size: 0.8rem;"><?php echo !empty($donation['notes']) ? escape($donation['notes']) : 'Monthly orphanage facility support contribution.'; ?></span>
          </td>
          <td style="text-align: right;" class="amount-cell">
            $<?php echo number_format($donation['amount'], 2); ?> USD
          </td>
        </tr>
      </tbody>
    </table>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 50px;">
      <div style="font-size: 0.8rem; color: #718096; line-height: 1.4;">
        Thank you for your generous support!<br>
        This receipt acts as an official endorsement of your contributions.
      </div>
      <div style="text-align: center; border-top: 1px solid #cbd5e1; width: 180px; padding-top: 8px; font-size: 0.8rem;">
        Caretaker Representative
      </div>
    </div>

    <div class="receipt-footer">
      Generated automatically by the Online Orphan Management System.
    </div>

  </div>

  <div class="print-btn-bar">
    <button class="print-btn" onclick="window.print();"><i class="fas fa-print"></i> Click to Print Receipt</button>
  </div>

  <!-- Auto-print trigger for user helper -->
  <script>
    window.addEventListener('load', () => {
      // Small timeout to ensure styles loaded
      setTimeout(() => {
        window.print();
      }, 500);
    });
  </script>

</body>
</html>
