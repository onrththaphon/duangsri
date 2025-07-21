<?php
session_start(); 
include 'conn.php';       // เชื่อมต่อฐานข้อมูล
include 'header.php';      // ส่วนหัวของหน้า
include 'navbar.php';      // แถบเมนูด้านบน
include 'sidebar_menu.php'; 
// Database connection parameters
$host = 'localhost';
$db   = 'isanfood';
$user = 'root'; // Your database username
$pass = '';     // Your database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null; // Initialize $pdo outside try-catch to ensure it's always defined

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Set default date range or get from GET parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-7 days')); // Default to 7 days ago
$toDate = $_GET['to_date'] ?? date('Y-m-d'); // Default to today

// Ensure dates are in YYYY-MM-DD format for database queries
$fromDateFormatted = date('Y-m-d', strtotime($fromDate));
$toDateFormatted = date('Y-m-d', strtotime($toDate));

// --- Fetch Summary Data ---

// Total Revenue
$stmt = $pdo->prepare("SELECT SUM(amount) AS total_revenue FROM payment WHERE status = 'completed' AND DATE(payment_item) BETWEEN ? AND ?");
$stmt->execute([$fromDateFormatted, $toDateFormatted]);
$totalRevenue = $stmt->fetchColumn() ?? 0.00; // Use ?? 0.00 for cleaner output if sum is null

// Total Orders
// Count orders that are not 'cancelled' within the date range
$stmt = $pdo->prepare("SELECT COUNT(Order_id) AS total_orders FROM `order` WHERE status != 'cancelled' AND DATE(order_time) BETWEEN ? AND ?");
$stmt->execute([$fromDateFormatted, $toDateFormatted]);
$totalOrders = $stmt->fetchColumn() ?? 0;

// Approx. Customers (distinct users who placed orders)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT User_id) AS approx_customers FROM `order` WHERE DATE(order_time) BETWEEN ? AND ?");
$stmt->execute([$fromDateFormatted, $toDateFormatted]);
$approxCustomers = $stmt->fetchColumn() ?? 0;

// --- Fetch Sales by Category (for Bar Chart) ---
// This data will be passed to JavaScript
$categorySalesData = [];
$categoryLabels = [];
$categoryBackgroundColors = [
    'อาหารคาว' => '#6c757d',    // Dark Gray
    'อาหารหวาน' => '#343a40',   // Even Darker Gray
    'เครื่องดื่ม' => '#495057', // Medium Gray
    // Add more colors if you have more categories
];

$stmt = $pdo->prepare("
    SELECT tm.type_name_menu, SUM(od.Quantity * m.price) AS category_total_sales
    FROM order_detail od
    JOIN `order` o ON od.Order_id = o.Order_id
    JOIN menu m ON od.menu_id = m.menu_id
    JOIN type_menu tm ON m.type_menu_id = tm.Type_menu_id
    WHERE o.status = 'completed' AND DATE(o.order_time) BETWEEN ? AND ?
    GROUP BY tm.type_name_menu
    ORDER BY category_total_sales DESC
");
$stmt->execute([$fromDateFormatted, $toDateFormatted]);
$rawCategorySales = $stmt->fetchAll();

$categorySalesValues = [];
$categoryColors = [];
foreach ($rawCategorySales as $row) {
    $categoryLabels[] = $row['type_name_menu'];
    $categorySalesValues[] = (float)$row['category_total_sales'];
    $categoryColors[] = $categoryBackgroundColors[$row['type_name_menu']] ?? '#cccccc'; // Default color if not defined
}

// --- Fetch Sales over Time (for Line Chart) ---
// This data will be passed to JavaScript
$timeSalesLabels = [];
$timeSalesValues = [];

$stmt = $pdo->prepare("
    SELECT DATE(payment_item) AS sale_date, SUM(amount) AS daily_sales
    FROM payment
    WHERE status = 'completed' AND DATE(payment_item) BETWEEN ? AND ?
    GROUP BY DATE(payment_item)
    ORDER BY sale_date ASC
");
$stmt->execute([$fromDateFormatted, $toDateFormatted]);
$rawTimeSales = $stmt->fetchAll();

// Fill in missing dates with zero sales for a continuous line chart
$period = new DatePeriod(
    new DateTime($fromDateFormatted),
    new DateInterval('P1D'),
    new DateTime($toDateFormatted . ' +1 day') // Add 1 day to include the toDate
);

$dailySalesMap = [];
foreach ($rawTimeSales as $row) {
    $dailySalesMap[$row['sale_date']] = (float)$row['daily_sales'];
}

foreach ($period as $date) {
    $dateStr = $date->format('Y-m-d');
    $timeSalesLabels[] = $date->format('M d'); // Format for chart label
    $timeSalesValues[] = $dailySalesMap[$dateStr] ?? 0;
}

// --- Fetch Popular Dishes ---
$popularDishes = [];
$stmt = $pdo->prepare("
    SELECT
        m.name AS dish_name,
        tm.type_name_menu AS category_name,
        SUM(od.Quantity) AS total_quantity,
        SUM(od.Quantity * m.price) AS total_sales_amount
    FROM order_detail od
    JOIN `order` o ON od.Order_id = o.Order_id
    JOIN menu m ON od.menu_id = m.menu_id
    JOIN type_menu tm ON m.type_menu_id = tm.Type_menu_id
    WHERE o.status = 'completed' AND DATE(o.order_time) BETWEEN ? AND ?
    GROUP BY m.menu_id, m.name, tm.type_name_menu
    ORDER BY total_sales_amount DESC
    LIMIT 10
");
$stmt->execute([$fromDateFormatted, $toDateFormatted]);
$popularDishesRaw = $stmt->fetchAll();

$rank = 1;
foreach ($popularDishesRaw as $dish) {
    $popularDishes[] = [
        $rank++,
        $dish['dish_name'],
        $dish['category_name'],
        (int)$dish['total_quantity'],
        number_format((float)$dish['total_sales_amount'], 2) // Format sales amount
    ];
}

// Format dates for display in inputs
$displayFromDate = date('m/d/Y', strtotime($fromDate));
$displayToDate = date('m/d/Y', strtotime($toDate));

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap');

        body {
            font-family: 'Kanit', sans-serif;
            margin: 0;
            background-color: #f4f7fa;
            color: #333;
        }

        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header/Date Selector Section */
        .date-selector-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 20px;
        }

        .date-input-group {
            flex: 1;
        }

        .date-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .date-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 12px;
            background-color: #fff;
        }

        .date-input-wrapper input {
            border: none;
            outline: none;
            flex-grow: 1;
            font-size: 16px;
            padding: 0;
            color: #333;
        }

        .date-input-wrapper .icon {
            color: #888;
            margin-left: 10px;
            cursor: pointer;
        }

        .generate-report-btn {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }

        .generate-report-btn:hover {
            background-color: #0056b3;
        }

        /* Summary Cards Section */
        .summary-cards-section {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-card {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
        }

        .summary-card .value {
            font-size: 2.2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .summary-card .label {
            font-size: 1em;
            color: #777;
            margin-top: 5px;
        }

        .summary-card .icon-wrapper {
            margin-bottom: 10px;
            font-size: 2em;
        }
        .summary-card.revenue .icon-wrapper { color: #28a745; }
        .summary-card.orders .icon-wrapper { color: #ffc107; }
        .summary-card.customers .icon-wrapper { color: #17a2b8; }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .chart-container h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2em;
            color: #333;
            font-weight: 500;
        }

        .chart-legend {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 0.9em;
            color: #555;
        }

        .legend-color-box {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 5px;
        }

        .chart-canvas {
            width: 100%;
            max-height: 250px; /* Limit height for consistency */
        }


        /* Popular Dishes Table Section */
        .popular-dishes-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .popular-dishes-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2em;
            color: #333;
            font-weight: 500;
        }

        .popular-dishes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .popular-dishes-table th,
        .popular-dishes-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .popular-dishes-table th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: #555;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .popular-dishes-table tbody tr:last-child td {
            border-bottom: none;
        }

        .popular-dishes-table tbody tr:hover {
            background-color: #f0f4f7;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .date-selector-section {
                flex-direction: column;
                align-items: stretch;
            }
            .summary-cards-section {
                flex-direction: column;
            }
            .summary-card {
                align-items: center;
                text-align: center;
            }
            .date-input-group {
                flex: none;
                width: 100%;
            }
            .generate-report-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="" method="get" class="date-selector-section">
            <div class="date-input-group">
                <label for="from_date">จากวันที่</label>
                <div class="date-input-wrapper">
                    <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($fromDateFormatted) ?>">
                    <i class="fas fa-calendar-alt icon"></i>
                </div>
            </div>
            <div class="date-input-group">
                <label for="to_date">ถึงวันที่</label>
                <div class="date-input-wrapper">
                    <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($toDateFormatted) ?>">
                    <i class="fas fa-calendar-alt icon"></i>
                </div>
            </div>
            <button type="submit" class="generate-report-btn">สร้างรายงาน</button>
        </form>

        <div class="summary-cards-section">
            <div class="summary-card revenue">
                <div class="icon-wrapper"><i class="fas fa-chart-line"></i></div>
                <div class="value"><?= number_format($totalRevenue, 2) ?></div>
                <div class="label">รายรับรวม (บาท)</div>
            </div>
            <div class="summary-card orders">
                <div class="icon-wrapper"><i class="fas fa-utensils"></i></div>
                <div class="value"><?= htmlspecialchars($totalOrders) ?></div>
                <div class="label">ออเดอร์ทั้งหมด</div>
            </div>
            <div class="summary-card customers">
                <div class="icon-wrapper"><i class="fas fa-users"></i></div>
                <div class="value"><?= htmlspecialchars($approxCustomers) ?></div>
                <div class="label">ลูกค้า (โดยประมาณ)</div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-container">
                <h3>ยอดขายตามหมวดหมู่</h3>
                <div class="chart-legend">
                    <?php foreach ($categoryBackgroundColors as $name => $color): ?>
                        <div class="legend-item"><span class="legend-color-box" style="background-color: <?= $color ?>;"></span><?= htmlspecialchars($name) ?></div>
                    <?php endforeach; ?>
                </div>
                <canvas id="categorySalesChart" class="chart-canvas"></canvas>
            </div>

            <div class="chart-container">
                <h3>ยอดขายตามช่วงเวลา</h3>
                <div class="chart-legend">
                    <div class="legend-item"><span class="legend-color-box" style="background-color: #007bff;"></span>ยอดขายรายวัน</div>
                </div>
                <canvas id="timeSalesChart" class="chart-canvas"></canvas>
            </div>
        </div>

        <div class="popular-dishes-section">
            <h3>อาหารยอดนิยม</h3>
            <table class="popular-dishes-table">
                <thead>
                    <tr>
                        <th>อันดับ</th>
                        <th>ชื่ออาหาร</th>
                        <th>หมวดหมู่</th>
                        <th>จำนวน (จาน)</th>
                        <th>ยอดขายรวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($popularDishes)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: #888;">ไม่มีข้อมูลอาหารยอดนิยมในช่วงวันที่ที่เลือก</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($popularDishes as $dish): ?>
                            <tr>
                                <td><?= htmlspecialchars($dish[0]) ?></td>
                                <td><?= htmlspecialchars($dish[1]) ?></td>
                                <td><?= htmlspecialchars($dish[2]) ?></td>
                                <td><?= htmlspecialchars($dish[3]) ?></td>
                                <td><?= htmlspecialchars($dish[4]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Set input type to 'date' to enable native date pickers
        document.addEventListener('DOMContentLoaded', function() {
            const fromDateInput = document.getElementById('from_date');
            const toDateInput = document.getElementById('to_date');

            // Set type to 'date' if browser supports it
            if (fromDateInput.type !== 'date') {
                fromDateInput.type = 'date';
                toDateInput.type = 'date';
            }

            // Simple click handler for calendar icon to focus input
            document.querySelectorAll('.date-input-wrapper .icon').forEach(icon => {
                icon.addEventListener('click', function() {
                    this.previousElementSibling.focus(); // Focus the input field
                });
            });

            // --- Chart.js Integration ---
            // Data from PHP, converted to JavaScript
            const categoryLabels = <?= json_encode($categoryLabels) ?>;
            const categorySalesValues = <?= json_encode($categorySalesValues) ?>;
            const categoryColors = <?= json_encode($categoryColors) ?>;

            const timeSalesLabels = <?= json_encode($timeSalesLabels) ?>;
            const timeSalesValues = <?= json_encode($timeSalesValues) ?>;

            // Category Sales Chart (Bar Chart)
            const categoryCtx = document.getElementById('categorySalesChart');
            if (categoryCtx) {
                new Chart(categoryCtx, {
                    type: 'bar',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            label: 'ยอดขายตามหมวดหมู่',
                            data: categorySalesValues,
                            backgroundColor: categoryColors,
                            borderColor: categoryColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'ยอดขาย (บาท)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'หมวดหมู่'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false // We have a custom legend
                            }
                        }
                    }
                });
            }

            // Time Sales Chart (Line Chart)
            const timeCtx = document.getElementById('timeSalesChart');
            if (timeCtx) {
                new Chart(timeCtx, {
                    type: 'line',
                    data: {
                        labels: timeSalesLabels,
                        datasets: [{
                            label: 'ยอดขายรายวัน',
                            data: timeSalesValues,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                            pointBackgroundColor: '#007bff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'ยอดขาย (บาท)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'วันที่'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false // We have a custom legend
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>