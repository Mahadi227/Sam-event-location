<?php
// receptionist/calendar.php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireStaff();

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$first_day = "$year-$month-01";
$days_in_month = date('t', strtotime($first_day));
$start_day_of_week = date('w', strtotime($first_day));

// Get reservations for this month
$stmt = $pdo->prepare("SELECT id, event_date, customer_name, status FROM reservations WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?");
$stmt->execute([$month, $year]);
$res_data = [];
while ($row = $stmt->fetch()) {
    $res_data[$row['event_date']][] = $row;
}

$next_month = date('m', strtotime("+1 month", strtotime($first_day)));
$next_year = date('Y', strtotime("+1 month", strtotime($first_day)));
$prev_month = date('m', strtotime("-1 month", strtotime($first_day)));
$prev_year = date('Y', strtotime("-1 month", strtotime($first_day)));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier - Sam Reception</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #eee;
        gap: 1px;
        border: 1px solid #eee;
    }

    .calendar-day {
        background: white;
        min-height: 120px;
        padding: 10px;
    }

    .calendar-header {
        background: #1a1c23;
        color: white;
        padding: 10px;
        text-align: center;
        font-weight: bold;
    }

    .day-num {
        font-weight: 800;
        color: #ccc;
        margin-bottom: 5px;
    }

    .res-pill {
        font-size: 0.7rem;
        padding: 3px 6px;
        border-radius: 4px;
        margin-bottom: 3px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        color: #fff;
        display: block;
        text-decoration: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .res-pill:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .pending { background: #f59e0b; color: white; }
    .approved { background: #10b981; color: white; }
    .in_preparation { background: #3b82f6; color: white; }
    .completed { background: #6b7280; color: white; }
    .cancelled { background: #ef4444; color: white; }
    </style>
</head>

<body style="background: #f4f5f7;">

<div class="admin-mobile-header">
    <div style="font-weight: 800; color: white;">Sam Reception</div>
    <button class="admin-hamburger"><i class="fas fa-bars"></i></button>
</div>

<div class="admin-container">
    <div class="sidebar-overlay"></div>
    <div class="admin-sidebar">
        <h2 style="color: white; margin-bottom: 30px;">Reception Sam</h2>
        <a href="dashboard.php"><i class="fas fa-home"></i> &nbsp; Accueil</a>
        <a href="walk_in.php"><i class="fas fa-plus"></i> &nbsp; Nouveau Walk-in</a>
        <a href="reservations.php"><i class="fas fa-list"></i> &nbsp; Reservations</a>
        <a href="calendar.php" class="active"><i class="fas fa-calendar-alt"></i> &nbsp; Calendrier</a>
        <a href="caisse.php"><i class="fas fa-cash-register"></i> &nbsp; Caisse (Shift)</a>
        <a href="profile.php"><i class="fas fa-user"></i> &nbsp; Mon Profil</a>
        <a href="../logout.php" style="margin-top: 50px; color: #ef4444;"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
    </div>

    <div class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2>Planning des Réservations</h2>
            <div>
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="contact-btn"
                    style="padding: 5px 15px;"><i class="fas fa-chevron-left"></i></a>
                <span style="font-weight: 800; margin: 0 20px;"><?php echo date('F Y', strtotime($first_day)); ?></span>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="contact-btn"
                    style="padding: 5px 15px;"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>

        <div style="overflow-x: auto; background: white; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div class="calendar-grid" style="min-width: 800px;">
                <div class="calendar-header">Dim</div>
                <div class="calendar-header">Lun</div>
                <div class="calendar-header">Mar</div>
                <div class="calendar-header">Mer</div>
                <div class="calendar-header">Jeu</div>
                <div class="calendar-header">Ven</div>
                <div class="calendar-header">Sam</div>

            <?php
            // Blank cells
            for ($i = 0; $i < $start_day_of_week; $i++) {
                echo '<div class="calendar-day" style="background:#f9f9f9;"></div>';
            }

            // Days of month
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
                
                $style = '';
                if ($date_str == date('Y-m-d')) {
                    $style = 'border: 3px solid var(--accent-gold); background: #fffbf0; transform: scale(1.02); z-index: 10; padding: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 8px;';
                } elseif ($date_str < date('Y-m-d')) {
                    $style = 'background: #f1f5f9; opacity: 0.65;';
                } else {
                    $style = 'background: white;';
                }

                echo '<div class="calendar-day" style="' . $style . '">';
                echo '<div class="day-num">' . $day . '</div>';

                if (isset($res_data[$date_str])) {
                    foreach ($res_data[$date_str] as $r) {
                        echo '<a href="manage.php?id=' . $r['id'] . '" class="res-pill ' . $r['status'] . '" title="Voir la réservation">' . htmlspecialchars($r['customer_name']) . '</a>';
                    }
                }

                echo '</div>';
            }
            ?>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>

</html>