<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pasig’s Comprehensive Waste Management Strategy</title>
    <link rel="stylesheet" href="../css/initiativespage.css" />
  </head>
  <body>
    <section class="header">
      <div class="header-overlay">
        <p class="breadcrumb">
          <a href="index.php">Home</a> > Pasig Environmental Initiatives
        </p>
        <h1>Pasig’s Comprehensive<br />Waste Management Strategy</h1>
      </div>
    </section>

    <section class="content">
      <h2 class="section-title">Pasig Environmental Initiatives</h2>

     <section class="initiative" id="ice-hub-section">
        <div class="initiative-image">
          <img src="../imgs/ice-hub.png" alt="ICE Hub meeting" />
        </div>
        <div class="initiative-text">
          <h3>Innovation for Circular Economy (ICE) Hub</h3>
          <p>
            The Innovation for Circular Economy (ICE) Hub serves as Pasig City’s
            pioneering center for circular solutions. Established in partnership
            with the United Nations Development Programme (UNDP) and supported
            by the Government of Japan and the European Union, the hub acts as
            an incubator for eco-friendly enterprises.
          </p>
          <p>
            Here, waste is not discarded — it is redesigned, repurposed, and
            revalued. The ICE Hub provides facilities for recycling, upcycling,
            composting, and small-scale green manufacturing. It also supports
            training for local entrepreneurs, especially women and community
            organizations, to turn waste materials into sustainable products and
            new income streams.
          </p>
        </div>
        <p class="highlight">
          Beyond reducing landfill dependency, the hub proves that
          sustainability can also drive business growth — showing how “green is
          good for business.”
        </p>
      </div>
    </section>

    <section class="initiative garbage-section" id="garbage-section">
      <h3 class="initiative-title">Garbage Collection by Administration</h3>

      <div class="garbage-images">
        <img src="../imgs/garbage1.jpg" alt="Garbage collection truck" />
        <img src="../imgs/garbage2.jpg" alt="Pasig garbage truck close-up" />
      </div>

      <div class="garbage-stats">
        <div class="stat-box">2,000 cu.m./day</div>
        <div class="stat-box">July, 2023 - Present</div>
      </div>

      <div class="garbage-description">
        <p>
          Clean streets and efficient collection systems are the backbone of a
          livable city. Pasig’s Garbage Collection by Administration program
          modernizes how waste is collected, hauled, and disposed of across its
          29 barangays.
        </p>

        <p>
          Under the supervision of the Solid Waste Management Office (SWMO), the
          city ensures that garbage collection is timely, complete, and
          compliant with environmental regulations. This effort not only keeps
          neighborhoods clean but also prevents uncollected waste from entering
          drainage systems and the Pasig River, one of the city’s most vital
          waterways.
        </p>

        <p>
          This initiative reflects the city’s commitment to a systematic,
          data-driven approach to cleanliness — where every truck route,
          schedule, and ton collected counts toward a larger environmental goal.
        </p>
      </div>
    </section>

    <section class="initiative wacs-section" id="wacs-section">
      <div class="wacs-header">
        <div class="wacs-box red">
          <img src="../imgs/wacs1.jpg" alt="WACS Data Analysis" />
        </div>
        <div class="wacs-title">
          <h3>Waste Analysis<br />and<br />Characterization Study (WACS)</h3>
        </div>
        <div class="wacs-box navy">
          <img src="../imgs/wacs2.jpg" alt="WACS Data Analysis" />
        </div>
      </div>

      <p class="wacs-intro">
        Knowledge is power — especially when managing waste. Through the Waste
        Analysis and Characterization Study (WACS), Pasig City examines the
        composition and sources of its garbage to guide smarter decisions.
      </p>

      <div class="wacs-body">
        <div class="wacs-box green">
          <img src="../imgs/wacs3.jpg" alt="Waste sorting process" />
        </div>

        <div class="wacs-text">
          <p>
            The study categorizes waste into residential, commercial,
            institutional, and industrial streams, measuring types such as
            plastics, organic waste, and recyclables.
          </p>
          <p>
            These insights inform policies for segregation, recycling targets,
            and circular economy planning.
          </p>
        </div>

        <div class="wacs-box olive">
          <img src="../imgs/wacs4.jpg" alt="Waste analysis operations" />
        </div>
      </div>
    </section>

    <section class="initiative walastik-section" id="walastik-section">
      <div class="walastik-header">
        <h3>“Walastik na Pasig” – Flexible Plastic Collection</h3>
      </div>

      <div class="walastik-body">
        <div class="walastik-text">
          <p>
            To tackle one of the city’s most persistent pollutants — plastic
            waste — Pasig City teamed up with Unilever Philippines and Cemex for
            the “Walastik na Pasig” program.
          </p>

          <p>
            The initiative encourages households to collect flexible plastic
            packaging, such as sachets and wrappers, and bring them to partner
            junk shops for incentives. This simple yet powerful approach
            empowers communities to become active participants in waste
            reduction, turning what was once considered trash into valuable
            material for recycling and co-processing.
          </p>

          <p>
            By diverting plastics away from waterways and landfills, “Walastik
            na Pasig” embodies the city’s message: small actions, when done
            together, create big change.
          </p>

          <a
            href="https://www.unilever.com.ph/news/2021/plastic-collection-in-partnership/"
            class="read-more-btn"
            target="_blank"
            rel="noopener noreferrer"
          >
            read more
          </a>
        </div>

        <div class="walastik-image">
          <img src="../imgs/walastik.jpg" alt="Walastik na Pasig program" />
        </div>
      </div>
    </section>

    <a href="#top" class="back-to-top" id="backToTopBtn" title="Go to top">↑</a>
    <script>
      const backToTopBtn = document.getElementById("backToTopBtn");

      window.addEventListener("scroll", () => {
        if (window.scrollY > 300) {
          backToTopBtn.classList.add("show");
        } else {
          backToTopBtn.classList.remove("show");
        }
      });
    </script>
  </body>
</html>
