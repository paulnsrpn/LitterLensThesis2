<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LitterLens</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/uploadPage.css">
    <link rel="stylesheet" href="../css/initiativesPage.css">
    <link rel="stylesheet" href="../css/aboutPage.css">
    <link rel="stylesheet" href="../css/guidePage.css">
    <link rel="stylesheet" href="../css/contactPage.css">
    <link rel="stylesheet" href="../css/footer.css">
</head>

<body>
  <!--                                        MAIN PAGE                                   -->
  <div class="nav">
    <div class="navbar">
        <a href="../php/index.php">
          <img src="../imgs/logo.png" alt="LitterLens logo">
        </a>
      <div class="r-nav">
        <a href="#"> Home </a>
        <a href="#about-page"> About </a>
        <a href="#contact-page"> Contacts </a>
        <button> Upload </button>
        <a href="../php/index_login.php">
          <i class="fa-solid fa-user" id="user-icon"></i>
        </a>
      </div>
    </div>
  </div>

  <div class="background">
    <img src="../imgs/pasigRiver.png" alt="pasig river">
    <div class="blue-overlay"></div>
  </div>

  <div class="main-content">
    <p> Detect and Measure Water Litter </p>
    <a href="#upload-page">
      <button> Get Started </button>
    </a>
  </div>

  <p class="h-title"> Quick Highlights </p>

  <div class="highlights">
    <div class="h-card">
      <img src="../imgs/highlight1.jpg" alt="card 1">
      <p> Monitors Pasig River</p>
    </div>
    <div class="h-card">
      <img src="../imgs/highlight2.jpg" alt="card 2">
      <p> AI-Powered Detection </p>
    </div>
    <div class="h-card">
      <img src="../imgs/highlight3.jpg" alt="card 3">
      <p> Real-time Litter Data </p>
    </div>
    <div class="h-card">
      <img src="../imgs/highlight4.jpg" alt="card 4">
      <p> For a Greener Future </p>
    </div>
  </div>



  <!--                                                   UPLOAD PAGE                                          -->

  <section id="upload-page">
    <div class="prim-content" id="upload-sec">
      <div class="title-text">
        <h1> Detect and Measure Litter </h1>
      </div>

      <div class="p-text">
        <p>Take or upload a clear image of any river, canal, estero, or creek in Pasig.</p>
      </div>
      <div class="photo-container">
        <div class="drop-zone">
          <div class="drop-content">
            <p class="main-text">Drop, Upload, or Paste Image</p>
            <p class="sub-text">Supported formats: JPG, PNG, WEBP</p>
            <label for="file-upload" class="select-btn">Choose File</label>
            <input type="file" id="file-upload" hidden accept="image/*" />
          </div>
        </div>

        <div class="preview-box" id="preview-box" style="display: none;">
          <img src="css/pasig.jpg" id="preview-image" alt="Preview" />
          <p id="file-name"></p>
        </div>
        <a href="../php/index_results.php">
          <button class="upload-photo-btn">Analyze Photo</button>
        </a>
      </div>
    </div>
  </section>










  <!--                                                   INITIATIVES PAGE                                          -->
    <section id="initiatives-page">
      <h2 class="section-title">Pasig River Initiatives</h2>

      <div class="initiatives">
        <div class="i-card">
          <img src="../imgs/init1.jpg" alt="Initiative 1">
        </div>
        <div class="i-card">
          <img src="../imgs/init2.jpg" alt="Initiative 2">
        </div>
        <div class="i-card">
          <img src="../imgs/init3.jpg" alt="Initiative 3">
        </div>
        <div class="i-card">
          <img src="../imgs/init4.jpg" alt="Initiative 4">
        </div>
      </div>

    </section>

    <!--                                                 INITIATIVES PAGE                                          -->
    <!--                                                     About Page                                            -->
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
          LitterLens is an AI-powered system that detects and counts visible litter in waterways, starting with Pasig
          City. Using computer vision, it provides real-time data to support smarter waste management, cleaner rivers,
          and evidence-based environmental action.
        </div>

      </div>



      <div class="card-container">
        <div class="card">
          <div class="scrolling-content">
            <h3>Empower Users</h3>
            <p>Enables individuals to contribute directly to environmental monitoring by simply taking photos of
              waterborne waste with their smartphones.</p>
          </div>
        </div>
        <div class="card">
          <div class="scrolling-content">
            <h3>Automates Detection</h3>
            <p>Utilizes advanced deep learning (YOLOv8) to automatically identify and classify various types of plastic
              macrolitter present on the water's surface from uploaded images.</p>
          </div>
        </div>
        <div class="card">
          <div class="scrolling-content">
            <h3>Quantifies Pollution</h3>
            <p>Provides quantitative data by counting detected waste items and potentially estimating their density
              within the captured area.</p>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!--                                                        Guide Page                                           -->
  <section id="guidePage" data-aos="fade-up">
    <div class="guide-box">
      <div class="guideNav">
        <div class="guideTitle">
          <h1>How to use LitterLens</h1>
          <p>Simple steps to identify and quantify litter</p>
        </div>
        <div class="navParts">
          <p><a href="#step1"> Log In or Register </a></p>
          <p><a href="#step2"> Uploading a Photo </a></p>
          <p><a href="#step3"> Analysis </a></p>
          <p><a href="#step4"> Results </a></p>
          <p><a href="#step5"> Downloading Reports </a></p>
        </div>
      </div>

      <div class="stepsCard">
        <div class="step" id="step1">
          <div class="stepTitle">
            <div class="circleNum">1</div>
            <h2>Step 1: Log In or Register (Optional)</h2>
          </div>
          <div class="stepContent">
            <img src="../imgs/step1.png" alt="">
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
            <img src="../imgs/step2.png" alt="">
          </div>
        </div>

        <div class="step" id="step3">
          <div class="stepTitle">
            <div class="circleNum">3</div>
            <h2>Step 3: Analysis</h2>
          </div>
          <div class="stepContent">
            <div class="textContent">
              <p>Once uploaded, the system uses AI to scan the image and identify macrolitter. Sit tight—processing
                takes just a few seconds.</p>
            </div>
            <img src="../imgs/step3.png" alt="">
          </div>
        </div>

        <div class="step" id="step4">
          <div class="stepTitle">
            <div class="circleNum">4</div>
            <h2>Step 4: Results</h2>
          </div>
          <div class="stepContent">
            <p>View the image with detection boxes and a summary of litter types and quantities found.</p>
            <img src="../imgs/step4.png" alt="">
          </div>
        </div>

        <div class="step" id="step5">
          <div class="stepTitle">
            <div class="circleNum">5</div>
            <h2>Step 5: Downloading Reports</h2>
          </div>
          <p class="addText">Download a detailed report (PDF or CSV) containing the detection summary and image data.
          </p>
          <div class="stepContent">
            <div class="textContent">
              <p>If signed in, download the detailed report as a PDF for documentation.</p>
              <p>Use reports for LGU collaboration or tracking progress.</p>
            </div>
            <img src="../imgs/step5.png" alt="">
          </div>
        </div>
      </div>
    </div>
    <div class="bot-text">
      <p>LitterLens 2025</p>
    </div>
  </section>

  <!--                                                                       Contact Page                                    -->
  <section id="contact-page">
    <div class="upper-sec">
      <img src="../imgs/pasig-river.jpg" alt="">
      <div class="green-overlay"></div>
    </div>

    <div class="contact-header" data-aos="fade-up" data-aos-duration="800">
      <h1>Contact Us</h1>
      <p>Have questions, feedback, or want to partner with us? Reach out—we’d love to hear from you.</p>
    </div>

    <div class="contact-content">
      <div class="con-description">
        <p>You can contact us through several ways.</p>
        <p>Choose the one more convenient for you.</p>
      </div>

      <div class="con" data-aos="fade-right" data-aos-delay="200">
        <i class="fa-regular fa-envelope"></i>
        <span>records.ncr@denr.gov.ph</span>
      </div>

      <div class="con" data-aos="fade-right" data-aos-delay="400">
        <i class="fa-brands fa-facebook-f"></i>
        <span>Pasig River Coordinating and Management Office - PRCMO</span>
      </div>
    </div>

    <!-- ✅ Contact Form Start -->
    <form id="contactForm" class="contact-form" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="800">
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

        <textarea class="message" name="message" placeholder="How can we help?" rows="5" required></textarea>

        <button type="submit" class="submit-btn" data-aos="fade-up" data-aos-delay="500">Send Message</button>

        <p class="disclaimer" data-aos="fade-up" data-aos-delay="600">
          By contacting us, you agree to our
          <a href="#" class="link">Terms of Service</a> and
          <a href="#" class="link">Privacy Policy</a>.
        </p>
      </div>
    </form>
    <!-- ✅ Contact Form End -->
  </section>

  <!--                                                FOOTER                                          -->

  <footer class="footer">
    <div class="wave-container">
      <svg class="wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 150" preserveAspectRatio="none">
        <path fill="#FFFDF6" d="
                    M0,80 
                    C 240,30 480,130 720,80 
                    C 960,30 1200,130 1440,80 
                    L1440,0 
                    L0,0 
                    Z
                " />
      </svg>
    </div>

    <div class="footer-content">

      <div class="footer-left">
        <div class="logo2">
          <img src="../imgs/logo2.png" alt="LitterLens Logo">
        </div>
        <p class="copyright">&copy; LitterLens 2025. All rights reserved</p>
      </div>

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
          <a href="#">Cookie Settigs</a>
        </div>
      </div>

    </div>


  </footer>


  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="../js/main.js"></script>

  <script>
      document.getElementById("userIcon").addEventListener("click", (e) => {
        e.preventDefault(); // prevent instant jump
        document.body.classList.add("fade-out");

        setTimeout(() => {
          window.location.href = "../php/index_login.php";
        }, 1000);
      });

  </script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const ctx1 = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        datasets: [{ 
          label: 'Detections',
          data: [120, 190, 150, 200, 250, 220, 300],
          borderColor: '#346347',
          backgroundColor: 'rgba(52, 99, 71, 0.2)',
          fill: true
        }]
      }
    });

    const ctx2 = document.getElementById('objectChart').getContext('2d');
    new Chart(ctx2, {
      type: 'pie',
      data: {
        labels: ['Plastic', 'Metal', 'Styrofoam', 'Glass'],
        datasets: [{
          data: [40, 25, 20, 15],
          backgroundColor: ['#346347','#4caf50','#81c784','#c8e6c9']
        }]
      }
    });
  </script>

  </body>
</html>