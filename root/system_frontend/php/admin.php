<!DOCTYPE html>
<html lang="en">

<?php
include 'php/config.php';


// require login (uses admin_id because your login sets $_SESSION['admin_id'])
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_reg.php");
    exit();
}

// canonical variables (use whichever session key exists)
$admin_name = $_SESSION['admin_name'] ?? null;   // what registration/login sets
$username   = $_SESSION['username']   ?? $admin_name ?? 'Unknown'; // fallback for old code
$fullname   = $_SESSION['fullname']   ?? $admin_name ?? 'Admin';
$email      = $_SESSION['email']      ?? 'Not set';
$role       = $_SESSION['role']       ?? 'Unknown';
?>



<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <title>admin</title>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
</head>

<body>
    <div class="a-nav">
        <div class="a-header">
            <img src="imgs/a-logo.png" alt="LitterLens logo">
            <p><?php echo htmlspecialchars($username); ?></p>
        </div>

        <div class="a-menu">
            <a class="tab-link active" data-tab="dashboard">Dashboard</a>
            <a class="tab-link" data-tab="image">Image and Detection</a>
            <a class="tab-link" data-tab="analytics">Analytics</a>
            <a class="tab-link" data-tab="users">Users</a>
            <a class="tab-link" data-tab="logs">Activity Logs</a>

            <div class="menu-bottom">
                <a href="#">Settings</a>
                <div class="log-out">
                    <a href="php/logout.php">Log out</a>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-button">
        <span class="admin-circle"></span>
        <span class="admin-divider"></span>
        <span class="admin-label">Admin</span>
    </div>

    <!--                                            DASHBOARD SECTION                                     -->
    <!-- DASHBOARD SECTION -->
    <div id="dashboard" class="tab-content active">
        <div class="dashboard-container">

            <!-- Top Stats -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Images analyzed</h3>
                    <p>1,069</p>
                </div>
                <div class="stat-card">
                    <h3>Litter Detected</h3>
                    <p>19,069</p>
                </div>
                <div class="stat-card">
                    <h3>Accuracy</h3>
                    <p>88%</p>
                </div>
            </div>

            <!-- Charts -->
            <div class="dashboard-charts">
                <div class="chart-card">
                    <h3>Litter Trends</h3>
                    <canvas id="trendChartDash"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Litter Hotspots</h3>
                    <canvas id="hotspotChartDash"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="dashboard-activity">
                <h3>Recent Activity</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Image</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>September 1</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>September 5</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>September 17</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>September 29</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>October 5</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>October 19</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                        <tr>
                            <td>October 30</td>
                            <td>Thumbnail</td>
                            <td>New Upload</td>
                            <td><a href="#">View Analytics</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>


    <!--                                            IMAGE AND DETECTION SECTION                                     -->
    <div id="image" class="tab-content image-detection">
        <!-- UPLOAD SECTION -->
        <div class="upload-card">
            <p>Upload Image</p>
            <p class="upload-desc">
                Choose an image to analyze litter presence in the selected photo.
            </p>
            <form>
                <input type="file" id="upload-file" accept="image/*" />
                <button type="submit">Upload & Detect</button>
            </form>
        </div>

        <!-- DETECTION HISTORY SECTION -->
        <div class="history-card">
            <p>Detection History</p>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Image</th>
                        <th>Litter Detected</th>
                        <th>Accuracy</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sept 29, 2025</td>
                        <td><img src="thumb1.jpg" alt="thumbnail" /></td>
                        <td>Plastic Bag</td>
                        <td>88%</td>
                        <td><a href="#">View</a></td>
                    </tr>
                    <tr>
                        <td>Sept 28, 2025</td>
                        <td><img src="thumb2.jpg" alt="thumbnail" /></td>
                        <td>Can, Bottle</td>
                        <td>91%</td>
                        <td><a href="#">View</a></td>
                    </tr>
                    <tr>
                        <td>Sept 27, 2025</td>
                        <td><img src="thumb3.jpg" alt="thumbnail" /></td>
                        <td>Styrofoam</td>
                        <td>86%</td>
                        <td><a href="#">View</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!--                                            ANALYTICS SECTION                                     -->
    <section id="analytics" class="tab-content">
        <h2 class="a-title">Analytics Overview</h2>

        <!-- KPI Cards -->
        <div class="a-overview">
            <div class="a-card">
                <h3>Total Detections</h3>
                <div class="a-value">1,000</div>
            </div>
            <div class="a-card">
                <h3>Users</h3>
                <div class="a-value">25</div>
            </div>
            <div class="a-card">
                <h3>Reports Today</h3>
                <div class="a-value">26</div>
            </div>
            <div class="a-card">
                <h3>Accuracy</h3>
                <div class="a-value">92%</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="a-grid">
            <!-- Pie Chart -->
            <div class="a-box">
                <h3>Detections by Category</h3>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>

            <!-- Map -->
            <div class="a-box">
                <h3>Geospatial Mapping</h3>
                <div id="a-map"></div>
            </div>

            <!-- Line Chart -->
            <div class="a-box" style="grid-column: span 2;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Litter Trends</h3>
                    <button class="a-dropdown">Monthly ⟳</button>
                </div>
                <div class="chart-container">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>
    </section>



    <!--                                            USER SECTION                                   -->
    <!-- Header -->
    <div id="users" class="tab-content">
        <div class="user-header">
            <h2>The Team</h2>

            <div class="user-controls">
                <div class="search-wrapper">
                    <input type="text" placeholder="Search users..." class="user-search">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                </div>
            </div>

        </div>


        <!-- User Grid -->
        <div class="user-grid">

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="imgs/avatar2.jpg" alt="User Avatar" class="user-avatar">
                <h3>Jervie Alentajan</h3>
                <p>jervie@gmail.com</p>
                <span class="role">User</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="imgs/avatar1.jpg" alt="User Avatar" class="user-avatar">
                <h3>Vico Sotto</h3>
                <p>sotto_vico@plpasig.edu.ph</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="imgs/avatar3.jpg" alt="User Avatar" class="user-avatar">
                <h3>Mark Sison</h3>
                <p>mars@gmail.com</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="imgs/avatar4.jpg" alt="User Avatar" class="user-avatar">
                <h3>Emanuel Florida</h3>
                <p>eman@gmail.com</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>

            <div class="user-card">
                <div class="user-menu">
                    <button class="menu-btn">⋮</button>
                    <div class="menu-options">
                        <button>View</button>
                        <button>Edit</button>
                        <button class="danger">Delete</button>
                    </div>
                </div>

                <img src="imgs/avatar5.jpg" alt="User Avatar" class="user-avatar">
                <h3>Pauline Serapion</h3>
                <p>pauln@gmail.com</p>
                <span class="role">Admin</span>

                <div class="user-contact">
                    <button class="contact-btn email-btn">
                        <i class="fa-solid fa-envelope"></i> Email
                    </button>
                    <button class="contact-btn call-btn">
                        <i class="fa-solid fa-phone"></i> Call
                    </button>
                </div>
            </div>


        </div>

    </div>

    <!--                                            USER SECTION                                   -->


    <!-- SETTINGS TAB -->
    <div id="settings" class="tab-content">
        <div class="settings-container">

            <!-- Account Settings -->
            <div class="settings-card">
                <h2>Account Settings</h2>
                <label>Email</label>
                <input type="email" placeholder="Enter new email">
                <label>Password</label>
                <input type="password" placeholder="Enter new password">
                <button>Update Account</button>
            </div>

            <!-- Preferences -->
            <div class="settings-card">
                <h2>System Preferences</h2>
                <label>Theme</label>
                <select>
                    <option>Light</option>
                    <option>Dark</option>
                </select>
                <label>Language</label>
                <select>
                    <option>English</option>
                    <option>Filipino</option>
                </select>
                <label>Timezone</label>
                <select>
                    <option>GMT+8 (Philippines)</option>
                    <option>GMT+9 (Japan)</option>
                    <option>GMT-5 (New York)</option>
                </select>
            </div>

            <!-- Notifications -->
            <div class="settings-card">
                <h2>Notifications</h2>
                <label><input type="checkbox" checked> Email Notifications</label>
                <label><input type="checkbox"> System Alerts</label>
                <label><input type="checkbox"> Weekly Summary Report</label>
            </div>

            <!-- Security -->
            <div class="settings-card">
                <h2>Privacy & Security</h2>
                <label><input type="checkbox"> Enable Two-Factor Authentication</label>
                <button>Manage Sessions</button>
                <button>Permissions</button>
            </div>

            <!-- Data Management -->
            <div class="settings-card">
                <h2>Data Management</h2>
                <button>Export Data</button>
                <button>Clear Cache</button>
                <button>Backup & Restore</button>
            </div>

            <!-- Support -->
            <div class="settings-card">
                <h2>Support</h2>
                <button>Help & FAQs</button>
                <button>Contact Support</button>
            </div>
        </div>
    </div>



    <!--                                            SETTING SECTION                                   -->



    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Category Chart
        const ctx1 = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: ['Plastic', 'Metal', 'Paper', 'Glass', 'Others'],
                datasets: [{
                    data: [40, 20, 15, 10, 15],
                    backgroundColor: ['#40916c', '#74c69d', '#95d5b2', '#d8f3dc', '#1b4332']
                }]
            }
        });

        // Trend Chart
        const ctx2 = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Detections',
                    data: [500, 700, 800, 650, 900, 1200],
                    borderColor: '#2d6a4f',
                    backgroundColor: 'rgba(45,106,79,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const links = document.querySelectorAll(".tab-link");
            const contents = document.querySelectorAll(".tab-content");
            const settingsLink = document.querySelector(".a-menu a[href='#']"); // the Settings link in bottom menu

            // Show only dashboard on page load
            contents.forEach(c => c.style.display = "none");
            document.getElementById("dashboard").style.display = "block";

            // Sidebar tab click handling
            links.forEach(link => {
                link.addEventListener("click", () => {
                    // Remove active states
                    links.forEach(l => l.classList.remove("active"));
                    contents.forEach(c => c.style.display = "none");

                    // Activate current tab
                    link.classList.add("active");
                    const target = link.getAttribute("data-tab");
                    const section = document.getElementById(target);
                    if (section) {
                        section.style.display = "block";
                    }
                });
            });

            // Settings link handling (bottom of sidebar)
            if (settingsLink) {
                settingsLink.addEventListener("click", (e) => {
                    e.preventDefault();
                    links.forEach(l => l.classList.remove("active"));
                    contents.forEach(c => c.style.display = "none");
                    document.getElementById("settings").style.display = "block";
                });
            }
        });
    </script>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Litter Trends Bar Chart
        const ctxT = document.getElementById('trendChartDash').getContext('2d');
        new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: ['September 1', 'September 5', 'September 17', 'September 29', 'October 5', 'October 19'],
                datasets: [{
                    label: 'Litter Count',
                    data: [20, 30, 10, 20, 25, 18],
                    backgroundColor: '#74c69d'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Litter Hotspots Line Chart
        const ctxH = document.getElementById('hotspotChartDash').getContext('2d');
        new Chart(ctxH, {
            type: 'line',
            data: {
                labels: ['P1', 'O1', 'R1', 'B1', 'M1', 'X1'],
                datasets: [{
                    label: 'Hotspot Activity',
                    data: [50, 20, 35, 18, 12, 17],
                    borderColor: '#40916c',
                    backgroundColor: 'rgba(64,145,108,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script>
        // === PIE CHART ===
        new Chart(document.getElementById("pieChart"), {
            type: "pie",
            data: {
                labels: [
                    "Biological Debris", "Electronic Waste", "Fabric and Textiles",
                    "Foamed Plastic", "Glass and Ceramic", "Metal", "Organic Debris",
                    "Paper and Cardboard", "Plastic", "Rubber", "Sanitary Waste"
                ],
                datasets: [{
                    data: [15, 12, 11, 10, 9, 10, 11, 10, 11, 10, 20],
                    backgroundColor: [
                        "#2d6a4f", "#40916c", "#52b788", "#74c69d",
                        "#95d5b2", "#b7e4c7", "#d8f3dc", "#74c69d",
                        "#52b788", "#40916c", "#2d6a4f"
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                plugins: {
                    legend: {
                        position: "right",
                        labels: {
                            color: "#1b4332",
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            }
        });

        // === LINE CHART ===
        new Chart(document.getElementById("lineChart"), {
            type: "line",
            data: {
                labels: ["Jan 2025", "Feb 2025", "Mar 2025", "Apr 2025", "May 2025", "Jun 2025"],
                datasets: [{
                        label: "Biological Debris",
                        data: [55, 45, 52, 40, 49, 47],
                        borderColor: "#2d6a4f",
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: "Plastic",
                        data: [50, 42, 48, 41, 45, 44],
                        borderColor: "#52b788",
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                plugins: {
                    legend: {
                        position: "right",
                        labels: {
                            color: "#1b4332",
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: "#eee"
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // === LEAFLET MAP ===
        const map = L.map("a-map").setView([14.5896, 121.0370], 13); // Center on Pasig River, Manila

        // Tile layer
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>'
        }).addTo(map);

        // Hardcoded sample detections (lat, lng)
        const detections = [
            [14.5995, 120.9842], // Manila
            [14.5800, 121.0000],
            [14.5830, 121.0200],
            [14.5900, 121.0300],
            [14.5950, 121.0400],
            [14.6000, 121.0500],
            [14.6050, 121.0600],
            [14.6100, 121.0700]
        ];

        detections.forEach(coords => {
            L.marker(coords, {
                icon: L.icon({
                    iconUrl: "https://cdn-icons-png.flaticon.com/512/484/484167.png",
                    iconSize: [25, 35],
                    iconAnchor: [12, 35]
                })
            }).addTo(map);
        });
    </script>

</body>

</html>