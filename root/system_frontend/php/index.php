<?php
    // ================================================
    // ⚙️ FLASK SERVER STARTER (Local Integration)
    // ================================================

    // 🧩 Configuration
    $flaskPort = 5000;
    $flaskHost = "http://127.0.0.1:$flaskPort";
    $pythonExePath = "C:\\Program Files\\Python313\\python.exe"; // Path to your Python executable
    $pythonAppPath = "C:\\xampp\\htdocs\\LitterLensThesis2\\root\\system_backend\\python\\app.py"; // Path to Flask app

    // ================================================
    // 🔍 STEP 1: Check if Flask is already running
    // ================================================
    $ch = curl_init($flaskHost);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $running = $response !== false;
    curl_close($ch);

    // ================================================
    // 🚀 STEP 2: If Flask is NOT running, start it
    // ================================================
    if (!$running) {
        $logPath = __DIR__ . "\\debugfiles\\flask_error_log.txt";

        // 🧠 Explanation:
        // 'start /b' runs a command in the background (no CMD popup)
        // '>>' appends output and errors to flask_error_log.txt
        $command = "start /b \"\" \"$pythonExePath\" \"$pythonAppPath\" >> \"$logPath\" 2>&1";
        pclose(popen($command, "r"));

        // 🕒 Give Flask time to initialize before rechecking
        sleep(3);

        // ✅ Re-check if Flask started successfully
        $ch = curl_init($flaskHost);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $running = $response !== false;
        curl_close($ch);
    }

    // ================================================
    // 🧾 STEP 3: Log the Flask status
    // ================================================
    $debugMessage = $running
        ? "🟢 Flask is already running on port $flaskPort"
        : "❌ Flask failed to start — check flask_error_log.txt";
    $debugStatus = $running ? "running" : "error";

    // Append status updates to a log file for debugging
    file_put_contents(
        __DIR__ . "/debugfiles/flask_debug_log.txt",
        date('Y-m-d H:i:s') . " - " . $debugMessage . "\n",
        FILE_APPEND
    );
?>
<script>
    // ================================================
    // 📍 GEOLOCATION CAPTURE
    // ================================================
    document.addEventListener("DOMContentLoaded", () => {
      if (navigator.geolocation) {
        // Attempt to get user's current position
        navigator.geolocation.getCurrentPosition(
          (position) => {
            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;
            console.log(`📍 Latitude: ${latitude}, Longitude: ${longitude}`);

            // 💾 Store location in localStorage for access on other pages
            localStorage.setItem("user_latitude", latitude);
            localStorage.setItem("user_longitude", longitude);
          },
          (error) => {
            console.error("❌ Geolocation error:", error.message);
          }
        );
      } else {
        console.warn("⚠️ Geolocation not supported by this browser.");
      }
    });

    // ================================================
    // 🧠 FLASK STATUS LOG IN BROWSER CONSOLE
    // ================================================
    (() => {
      const status = "<?php echo $debugStatus; ?>";
      const msg = "<?php echo addslashes($debugMessage); ?>";

      // Define colors based on Flask status
      const color = status === "running" ? "#2e6cff" : "#b91c1c";
      const bg = status === "running" ? "#e6ecff" : "#ffe6e6";

      // Print colored status message in the browser console
      console.log(
        `%c[Flask] ${msg}`,
        `color:${color}; font-weight:bold; background:${bg}; padding:4px; border-radius:4px;`
      );
    })();
</script>


<!DOCTYPE html>
<html lang="en">

            <head>
                <!-- ================================================
                    🌿 LitterLens Web Application
                    🧠 HEAD SECTION — Meta, Fonts, and Styles
                    ================================================ -->

                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>LitterLens</title>

                <!-- ================================================
                    🎨 THIRD-PARTY STYLESHEETS
                    ================================================ -->

                <!-- 📤 Dropzone (drag & drop file upload styling) -->
                <link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" />

                <!-- 🔤 Font Awesome (icon library for buttons, UI, etc.) -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">

                <!-- 🧩 Line Awesome (alternative lightweight icon set) -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">

                <!-- 🏠 Main landing page & navigation styles -->
                <link rel="stylesheet" href="../css/index.css">

                <!-- 📸 Upload page (Dropzone + file upload UI) -->
                <link rel="stylesheet" href="../css/index_upload.css">

                <!-- 🌱 Environmental initiatives / community section -->
                <link rel="stylesheet" href="../css/index_initiativesPage.css">

                <!-- ℹ️ About page (mission, team, purpose) -->
                <link rel="stylesheet" href="../css/index_aboutPage.css">

                <!-- 📘 Guide or tutorial section (how-to-use instructions) -->
                <link rel="stylesheet" href="../css/index_guidePage.css">

                <!-- ⚓ Footer section styling -->
                <link rel="stylesheet" href="../css/index_footer.css">

                <!-- ☎️ Contact or feedback page styling -->
              <link rel="stylesheet" href="../css/index_contactPage.css">
            </head>



<body>
  <!--                                        MAIN PAGE                                   -->

  <input type="hidden" id="latField" name="latitude">
  <input type="hidden" id="lonField" name="longitude">

              <!-- ================================================
                  🧭 NAVIGATION BAR SECTION
                  ================================================ -->
              <div class="nav">
                <div class="navbar">
                  
                  <!-- 🏠 Logo (links to homepage) -->
                  <a href="../php/index.php">
                    <img src="../imgs/logo.png" alt="LitterLens logo">
                  </a>

                  <!-- 📌 Right-side navigation links -->
                  <div class="r-nav">
                    <a href="#">Home</a>
                    <a href="#about-page">About</a>
                    <a href="#contact-page">Contacts</a>

                    <button class="upload-btn" onclick="location.href='#upload-page'">
                      Upload
                    </button>

                    <a href="../php/index_login.php">
                      <i class="fa-solid fa-user" id="user-icon"></i>
                    </a>
                  </div>

                </div>
              </div>

              
              <!-- 🌊 Background Section -->
              <div class="background">
                <img src="../imgs/pasigRiver.png" alt="Pasig River">
                <div class="blue-overlay"></div>
              </div>

              <!-- 🧠 Main Hero Content -->
              <div class="main-content">
                <p>Detect and Measure Water Litter</p>
                <a href="#upload-page">
                  <button>Get Started</button>
                </a>
              </div>

              <!-- ⚡ Quick Highlights Section -->
              <p class="h-title">Quick Highlights</p>

              <div class="highlights">
                <div class="h-card">
                  <img src="../imgs/highlight1.jpg" alt="card 1">
                  <p>Monitors Pasig River</p>
                </div>
                <div class="h-card">
                  <img src="../imgs/highlight2.jpg" alt="card 2">
                  <p>AI-Powered Detection</p>
                </div>
                <div class="h-card">
                  <img src="../imgs/highlight3.jpg" alt="card 3">
                  <p>Real-time Litter Data</p>
                </div>
                <div class="h-card">
                  <img src="../imgs/highlight4.jpg" alt="card 4">
                  <p>For a Greener Future</p>
                </div>
              </div>



              <!-- ================================================
                  📤 UPLOAD PAGE SECTION
                  ================================================ -->
              <section id="upload-page">
                <div class="prim-content" id="upload-sec">

                  <div class="title-text">
                    <h1>Detect and Measure Litter</h1>
                  </div>

                  <div class="p-text">
                    <p>Take or upload a clear image of any river, canal, estero, or creek in Pasig.</p>
                  </div>

                  <!-- 📸 Dropzone Upload Form -->
                  <form
                    action="../php/index_result.php"
                    class="dropzone"
                    id="my-dropzone"
                    enctype="multipart/form-data">

                    <div class="dz-message">
                      <p class="main-text">Drop, Upload, or Paste Image</p>
                      <p class="sub-text">Supported formats: JPG, PNG, WEBP</p>
                      <button type="button" class="select-btn">Choose File</button>
                    </div>
                  </form>

                  <!-- 🧠 Analyze Button -->
                  <button type="button" id="analyze-btn" class="upload-photo-btn">
                    Analyze Photo
                  </button>

                </div>
              </section>



              <!-- ================================================
                  🌱 INITIATIVES PAGE SECTION
                  ================================================ -->
              <section id="initiatives-page">
                <h2 class="section-title">Pasig River Initiatives</h2>

                <div class="initiatives">
                  <div class="i-card">
                    <img src="" alt="Initiative 1">
                  </div>
                  <div class="i-card">
                    <img src="" alt="Initiative 2">
                  </div>
                  <div class="i-card">
                    <img src="" alt="Initiative 3">
                  </div>
                  <div class="i-card">
                    <img src="" alt="Initiative 4">
                  </div>
                </div>
              </section>



              <!-- ================================================
                  ℹ️ ABOUT PAGE SECTION
                  ================================================ -->
              <section id="about-page" data-aos="fade-up">
                <div class="about-bg-box" data-aos="fade-in">

                  <div class="first-line">
                    <div class="about-header">
                      <h2 data-aos="fade-down">About</h2>
                      <h1 data-aos="zoom-in" data-aos-delay="100">LitterLens</h1>
                      <hr class="white-line" data-aos="fade-right" data-aos-delay="200">
                      <div class="what-we-do" data-aos="fade-up" data-aos-delay="300">What We Do</div>
                    </div>

                    <div class="description" data-aos="fade-up" data-aos-delay="400">
                      LitterLens is an AI-powered system that detects and counts visible litter in waterways, starting with Pasig City.
                      Using computer vision, it provides real-time data to support smarter waste management, cleaner rivers,
                      and evidence-based environmental action.
                    </div>
                  </div>

                  <div class="card-container">
                    <div class="card">
                      <div class="scrolling-content">
                        <h3>Empower Users</h3>
                        <p>
                          Enables individuals to contribute directly to environmental monitoring by simply taking photos of
                          waterborne waste with their smartphones.
                        </p>
                      </div>
                    </div>

                    <div class="card">
                      <div class="scrolling-content">
                        <h3>Automates Detection</h3>
                        <p>
                          Utilizes advanced deep learning (YOLOv8) to automatically identify and classify various types of plastic
                          macrolitter present on the water's surface from uploaded images.
                        </p>
                      </div>
                    </div>

                    <div class="card">
                      <div class="scrolling-content">
                        <h3>Quantifies Pollution</h3>
                        <p>
                          Provides quantitative data by counting detected waste items and potentially estimating their density
                          within the captured area.
                        </p>
                      </div>
                    </div>
                  </div>

                </div>
              </section>



              <!-- ================================================
                  📘 GUIDE PAGE SECTION
                  ================================================ -->
              <section id="guidePage" data-aos="fade-up">
                <div class="guide-box">

                  <!-- 🧭 Navigation & Title -->
                  <div class="guideNav">
                    <div class="guideTitle">
                      <h1>How to use LitterLens</h1>
                      <p>Simple steps to identify and quantify litter</p>
                    </div>

                    <div class="navParts">
                      <p><a href="#step1">Log In or Register</a></p>
                      <p><a href="#step2">Uploading a Photo</a></p>
                      <p><a href="#step3">Analysis</a></p>
                      <p><a href="#step4">Results</a></p>
                      <p><a href="#step5">Downloading Reports</a></p>
                    </div>
                  </div>

                  <!-- 🪜 Step-by-Step Guide -->
                  <div class="stepsCard">

                    <div class="step" id="step1">
                      <div class="stepTitle">
                        <div class="circleNum">1</div>
                        <h2>Step 1: Log In or Register (Optional)</h2>
                      </div>
                      <div class="stepContent">
                        <img src="../imgs/step1.png" alt="Step 1">
                        <div class="textContent">
                          <p>Create an account or sign in to access advanced features like report downloads and image history.</p>
                          <p>Use email or Google account.</p>
                        </div>
                      </div>
                    </div>

                    <div class="step" id="step2">
                      <div class="stepTitle">
                        <div class="circleNum">2</div>
                        <h2>Step 2: Uploading a Photo</h2>
                      </div>
                      <div class="stepContent">
                        <p>Click “Upload” and select a clear image of a waterway. Supported formats: JPG or PNG.</p>
                        <img src="../imgs/step2.png" alt="Step 2">
                      </div>
                    </div>

                    <div class="step" id="step3">
                      <div class="stepTitle">
                        <div class="circleNum">3</div>
                        <h2>Step 3: Analysis</h2>
                      </div>
                      <div class="stepContent">
                        <div class="textContent">
                          <p>
                            Once uploaded, the system uses AI to scan the image and identify macrolitter.
                            Sit tight—processing takes just a few seconds.
                          </p>
                        </div>
                        <img src="../imgs/step3.png" alt="Step 3">
                      </div>
                    </div>

                    <div class="step" id="step4">
                      <div class="stepTitle">
                        <div class="circleNum">4</div>
                        <h2>Step 4: Results</h2>
                      </div>
                      <div class="stepContent">
                        <p>View the image with detection boxes and a summary of litter types and quantities found.</p>
                        <img src="../imgs/step4.png" alt="Step 4">
                      </div>
                    </div>

                    <div class="step" id="step5">
                      <div class="stepTitle">
                        <div class="circleNum">5</div>
                        <h2>Step 5: Downloading Reports</h2>
                      </div>
                      <p class="addText">
                        Download a detailed report (PDF or CSV) containing the detection summary and image data.
                      </p>
                      <div class="stepContent">
                        <div class="textContent">
                          <p>If signed in, download the detailed report as a PDF for documentation.</p>
                          <p>Use reports for LGU collaboration or tracking progress.</p>
                        </div>
                        <img src="../imgs/step5.png" alt="Step 5">
                      </div>
                    </div>

                  </div> <!-- End .stepsCard -->
                </div> <!-- End .guide-box -->

                <div class="bot-text">
                  <p>LitterLens 2025</p>
                </div>
              </section>



              <!-- ================================================
                  ☎️ CONTACT PAGE SECTION
                  ================================================ -->
              <section id="contact-page">
                <div class="upper-sec">
                  <img src="../imgs/pasig-river.jpg" alt="Pasig River">
                  <div class="green-overlay"></div>
                </div>

                <div class="contact-header" data-aos="fade-up" data-aos-duration="800">
                  <h1>Contact Us</h1>
                  <p>
                    Have questions, feedback, or want to partner with us?
                    Reach out—we’d love to hear from you.
                  </p>
                </div>

                <div class="contact-content">
                  <div class="con-description">
                    <p>You can contact us through several ways.</p>
                    <p>Choose the one more convenient for you.</p>
                  </div>

                  <div class="con" data-aos="fade-right" data-aos-delay="200">
                    <i class="fa-regular fa-envelope"></i>
                    <span>cenro@pasigcity.gov.ph</span>
                  </div>

                  <div class="con" data-aos="fade-right" data-aos-delay="400">
                    <i class="fa-brands fa-facebook-f"></i>
                    <span>Pasig CENRO</span>
                  </div>
                </div>

                <!-- 📨 Contact Form -->
                <form
                  id="contactForm"
                  class="contact-form"
                  data-aos="fade-left"
                  data-aos-duration="1000"
                  data-aos-delay="800"
                >
                  <div class="contact-form-header">
                    <h1>Get in touch!</h1>
                    <p>You can reach us anytime</p>
                  </div>

                  <div class="names" data-aos="fade-up" data-aos-delay="100">
                    <input type="text" name="firstName" placeholder="First Name" required>
                    <input type="text" name="lastName" placeholder="Last Name" required>
                  </div>

                  <div class="add-infos" data-aos="fade-up" data-aos-delay="200">
                    <input type="email" name="email" placeholder="Your Email" required>

                    <div class="phone-field">
                      <select class="country-code" name="countryCode" required>
                        <option value="+63">+63 (PH)</option>
                        <option value="+1">+1 (US)</option>
                        <option value="+44">+44 (UK)</option>
                        <option value="+91">+91 (IN)</option>
                      </select>
                      <input type="tel" name="phone" placeholder="Your Phone Number" maxlength="11" required>
                    </div>

                    <textarea
                      class="message"
                      name="message"
                      placeholder="How can we help?"
                      rows="5"
                      required
                    ></textarea>

                    <button type="submit" class="submit-btn" data-aos="fade-up" data-aos-delay="500">
                      Send Message
                    </button>

                    <p class="disclaimer" data-aos="fade-up" data-aos-delay="600">
                      By contacting us, you agree to our
                      <a href="#" class="link">Terms of Service</a> and
                      <a href="#" class="link">Privacy Policy</a>.
                    </p>
                  </div>
                </form>
              </section>


              <!-- ================================================
                  🌊 FOOTER SECTION
                  ================================================ -->
              <footer class="footer">
                <!-- 🌀 Decorative Wave Background -->
                <div class="wave-container">
                  <svg
                    class="wave"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 1440 150"
                    preserveAspectRatio="none"
                  >
                    <path
                      fill="#FFFDF6"
                      d="
                        M0,80 
                        C240,30 480,130 720,80 
                        C960,30 1200,130 1440,80 
                        L1440,0 
                        L0,0 
                        Z
                      "
                    />
                  </svg>
                </div>

                <!-- 🧩 Footer Content -->
                <div class="footer-content">

                  <!-- 🔹 Left Side: Logo and Copyright -->
                  <div class="footer-left">
                    <div class="logo2">
                      <img src="../imgs/logo2.png" alt="LitterLens Logo">
                    </div>
                    <p class="copyright">&copy; LitterLens 2025. All rights reserved.</p>
                  </div>

                  <!-- 🔹 Right Side: Socials and Links -->
                  <div class="footer-right">
                    <div class="footer-icons">
                      <a href="#" class="icon-circle" aria-label="Facebook">
                        <i class="lab la-facebook-f"></i>
                      </a>
                      <a href="#" class="icon-circle" aria-label="Instagram">
                        <i class="lab la-instagram"></i>
                      </a>
                      <a href="#" class="icon-circle" aria-label="Twitter">
                        <i class="lab la-twitter"></i>
                      </a>
                      <a href="#" class="icon-circle" aria-label="YouTube">
                        <i class="lab la-youtube"></i>
                      </a>
                      <a href="#" class="icon-circle" aria-label="LinkedIn">
                        <i class="lab la-linkedin-in"></i>
                      </a>
                    </div>

                    <div class="footer-links">
                      <a href="#">Terms of Service</a>
                      <a href="#">Privacy Policy</a>
                      <a href="#">Cookie Settings</a>
                    </div>
                  </div>

                </div>
              </footer>


    <!-- ================================================
        ⚙️ SCRIPT IMPORTS
        ================================================ -->
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script> <!-- AOS Animation Library -->
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script> <!-- Dropzone for File Upload -->
    <script src="../js/main.js"></script> <!-- Main Site Scripts -->
    <script src="../js/upload.js"></script> <!-- Upload Page Logic -->

  </body>
</html>