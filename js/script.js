document.addEventListener("DOMContentLoaded", function () {
  // Mobile menu toggle
  const mobileMenuButton = document.getElementById("mobile-menu");
  const navList = document.getElementById("nav-list");

  if (mobileMenuButton && navList) {
    mobileMenuButton.addEventListener("click", function () {
      navList.classList.toggle("active");
      const isExpanded = navList.classList.contains("active");
      mobileMenuButton.setAttribute("aria-expanded", isExpanded);
      mobileMenuButton.innerHTML = isExpanded
        ? '<i class="fas fa-times"></i>'
        : '<i class="fas fa-bars"></i>';
    });
  }

  // Header scroll effect
  const mainHeader = document.getElementById("main-header");
  if (mainHeader) {
    const triggerHeight = 50; // Pixels to scroll before changing header
    // Initial check in case page is loaded already scrolled
    if (window.scrollY > triggerHeight) {
      mainHeader.classList.add("header-scrolled");
      mainHeader.classList.remove("main-header-transparent");
    } else {
      mainHeader.classList.remove("header-scrolled");
      mainHeader.classList.add("main-header-transparent");
    }
    // Add scroll listener
    window.addEventListener("scroll", function () {
      if (window.scrollY > triggerHeight) {
        mainHeader.classList.add("header-scrolled");
        mainHeader.classList.remove("main-header-transparent");
      } else {
        mainHeader.classList.remove("header-scrolled");
        mainHeader.classList.add("main-header-transparent");
      }
    });
  }

  // --- Animated Number Counters ---
  const statsSection = document.getElementById("impact-stats");
  const counters = document.querySelectorAll(".stat-counter");
  const animationDuration = 2000; // 2 seconds

  const animateCounters = () => {
    counters.forEach((counter) => {
      const target = +counter.dataset.target; // Get target number from data attribute
      const increment = target / (animationDuration / 16); // Calculate increment for ~60fps

      let current = 0;
      const updateCounter = () => {
        current += increment;
        if (current < target) {
          counter.innerText = Math.ceil(current).toLocaleString(); // Add commas for larger numbers
          requestAnimationFrame(updateCounter);
        } else {
          counter.innerText = target.toLocaleString();
        }
      };
      requestAnimationFrame(updateCounter);
    });
  };

  if (statsSection && counters.length > 0) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          // When the stats section is intersecting with the viewport
          if (entry.isIntersecting) {
            animateCounters();
            // Stop observing once the animation has been triggered
            observer.unobserve(statsSection);
          }
        });
      },
      {
        threshold: 0.5, // Trigger when 50% of the section is visible
      }
    );

    // Start observing the stats section
    observer.observe(statsSection);
  }

  // --- AOS Initialization ---
  // This part should be here or after the AOS script is loaded in footer.php
  if (
    typeof AOS !== "undefined" &&
    !document.body.classList.contains("aos-initialized")
  ) {
    AOS.init({
      duration: 800, // Animation duration
      once: true, // Animate only once
      offset: 100, // Offset (in px) from the original trigger point
      delay: 100, // Delay in ms
    });
  }

  // Back to Top Button
  const backToTopButton = document.getElementById("backToTopBtn");

  window.onscroll = function () {
    scrollFunction();
  };

  function scrollFunction() {
    if (backToTopButton) {
      if (
        document.body.scrollTop > 100 ||
        document.documentElement.scrollTop > 100
      ) {
        backToTopButton.style.display = "block";
      } else {
        backToTopButton.style.display = "none";
      }
    }
  }
  // This function will be called when the button is clicked
  window.scrollToTop = function () {
    // Make it globally accessible from HTML
    document.body.scrollTop = 0; // For Safari
    document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
  };
  // Add more client-side scripts here as needed
  // e.g., form validation, AJAX calls for dynamic content later
});

// In js/script.js
// In js/script.js

document.addEventListener("DOMContentLoaded", function () {
  // ... (your existing mobile menu, header scroll, AOS init code) ...

  const welcomeToast = document.getElementById("advancedWelcomeMessage");
  let welcomeToastTimeout; // To store the timeout ID

  if (
    welcomeToast &&
    sessionStorage.getItem("welcomeMessageDismissed_geetanjali") !== "true"
  ) {
    // Make it visible with an animation after a short delay
    setTimeout(() => {
      welcomeToast.classList.add("show");
    }, 100); // Delay to allow initial CSS to apply

    // Auto-dismiss after 5 seconds from when it becomes visible
    welcomeToastTimeout = setTimeout(() => {
      dismissWelcomeMessage(); // Call the dismiss function
    }, 5100); // 5000ms for display + 100ms initial delay
  } else if (
    welcomeToast &&
    sessionStorage.getItem("welcomeMessageDismissed_geetanjali") === "true"
  ) {
    // If already dismissed in this session, ensure it's not displayed
    welcomeToast.style.display = "none";
  }

  // If the close button is clicked, clear the auto-dismiss timeout
  const closeButton = welcomeToast
    ? welcomeToast.querySelector(".close-notification")
    : null;
  if (closeButton) {
    closeButton.addEventListener("click", () => {
      if (welcomeToastTimeout) {
        clearTimeout(welcomeToastTimeout);
      }
      // The dismissWelcomeMessage function is already called by the button's onclick attribute
    });
  }
});

// Make this function globally accessible or ensure it's defined before use
function dismissWelcomeMessage() {
  const welcomeMessage = document.getElementById("advancedWelcomeMessage");
  // Check if it's already dismissing to prevent multiple calls from auto-dismiss and manual click
  if (welcomeMessage && !welcomeMessage.classList.contains("dismissing")) {
    welcomeMessage.classList.remove("show"); // Remove show to start exit animation
    welcomeMessage.classList.add("dismissing");

    setTimeout(() => {
      if (welcomeMessage.parentNode) {
        welcomeMessage.style.display = "none";
        // welcomeMessage.remove(); // Optionally remove from DOM
      }
    }, 500); // This duration should match your CSS exit animation duration

    sessionStorage.setItem("welcomeMessageDismissed_geetanjali", "true");
  }
}

// Inside geetanjali_website/js/script.js

document.addEventListener("DOMContentLoaded", function () {
  // ... (your existing script.js code for mobile menu, header scroll, AOS init) ...

  // --- Student Profile Page Tab Navigation ---
  const profileNavContainer = document.querySelector(
    ".std-profile-sidebar .profile-nav"
  );

  if (profileNavContainer) {
    // Only run this script if we are on a page with the profile navigation
    const navLinks = profileNavContainer.querySelectorAll(".profile-nav-link");
    const profileSections = document.querySelectorAll(
      ".std-profile-main-content .profile-section"
    );

    // console.log('Profile Nav Links Found:', navLinks.length);
    // console.log('Profile Sections Found:', profileSections.length);

    function showSection(targetId) {
      // console.log('Attempting to show section:', targetId);
      let sectionFound = false;
      profileSections.forEach((section) => {
        if (section.id === targetId) {
          section.style.display = "block";
          section.classList.add("active-section"); // For potential styling
          // Trigger AOS for newly displayed section if using AOS per section
          if (typeof AOS !== "undefined") {
            // AOS.refreshHard(); // or AOS.refresh() if elements are just un-hidden
          }
          sectionFound = true;
        } else {
          section.style.display = "none";
          section.classList.remove("active-section");
        }
      });

      if (!sectionFound) {
        // console.error('Section with ID ' + targetId + ' not found.');
      }

      navLinks.forEach((link) => {
        // Check if the link's data-target matches the targetId
        if (link.dataset.target === targetId) {
          link.classList.add("active");
        } else {
          link.classList.remove("active");
        }
      });
    }

    // Function to handle initial section display based on URL hash
    function handleInitialSection() {
      let initialSectionId = "view-profile-section"; // Default section
      if (window.location.hash) {
        const hashId = window.location.hash.substring(1); // e.g., "edit-profile" from #edit-profile
        const targetSectionFromHash = document.getElementById(
          hashId + "-section"
        ); // e.g., "edit-profile-section"

        if (targetSectionFromHash) {
          initialSectionId = targetSectionFromHash.id;
          // console.log('Initial section from hash:', initialSectionId);
        } else {
          // console.log('Hash found but no matching section ID:', hashId + '-section', ". Defaulting to view-profile-section");
        }
      } else {
        // console.log('No hash found. Defaulting to view-profile-section');
      }
      showSection(initialSectionId);
    }

    // Initial setup
    handleInitialSection();

    // Add click event listeners to navigation links
    navLinks.forEach((link) => {
      link.addEventListener("click", function (event) {
        const targetSectionId = this.dataset.target; // e.g., "edit-profile-section"

        // Check if it's an internal page link (starts with #) or a full URL
        const href = this.getAttribute("href");

        if (targetSectionId && document.getElementById(targetSectionId)) {
          if (href && href.startsWith("#")) {
            // It's an anchor link for tab switching
            event.preventDefault(); // Prevent default jump for internal anchors
            showSection(targetSectionId);
            // Update URL hash without causing a page jump
            history.pushState(
              null,
              null,
              "#" + targetSectionId.replace("-section", "")
            );
          }
          // If it's a full URL (like "Back to Dashboard"), the default browser navigation will occur
        } else if (href && !href.startsWith("#")) {
          // It's a normal link to another page, let it proceed
          return true;
        } else {
          // console.warn('Target section or ID not found for link:', this);
        }
      });
    });

    // Listen for hash changes (e.g., browser back/forward buttons)
    window.addEventListener("hashchange", handleInitialSection, false);
  }
});

// Add this within your existing js/script.js file,
// preferably inside the main DOMContentLoaded listener.

document.addEventListener("DOMContentLoaded", function () {
  // --- Existing JS code (mobile menu, header scroll, AOS, welcome toast, profile tabs, etc.) ---
  // ... (make sure your previous JS code is here) ...

  // --- Gallery Page Specific JavaScript ---
  const galleryPageContainer = document.querySelector(
    ".gallery-page-container"
  );

  if (galleryPageContainer) {
    // Only run gallery scripts if on the gallery page

    // 1. Collapsible Filter Panel Toggle
    const toggleFiltersButton = document.getElementById("toggleGalleryFilters");
    const galleryFilterPanel = document.getElementById("galleryFilterPanel");
    const filterToggleText = toggleFiltersButton
      ? toggleFiltersButton.querySelector(".toggle-text")
      : null;
    const filterToggleArrow = toggleFiltersButton
      ? toggleFiltersButton.querySelector(".toggle-arrow")
      : null;

    if (toggleFiltersButton && galleryFilterPanel) {
      // Check if panel should be open initially based on its class (set by PHP if filters are active)
      if (galleryFilterPanel.classList.contains("open")) {
        galleryFilterPanel.style.maxHeight =
          galleryFilterPanel.scrollHeight + "px";
        galleryFilterPanel.style.paddingTop = "20px"; // Match CSS
        galleryFilterPanel.style.paddingBottom = "25px"; // Match CSS
        galleryFilterPanel.style.borderWidth = "1px";
        galleryFilterPanel.style.marginBottom = "2.5rem";
        if (filterToggleText)
          filterToggleText.textContent = "Hide Filters / Refine View";
        if (filterToggleArrow)
          filterToggleArrow.style.transform = "rotate(180deg)";
        toggleFiltersButton.setAttribute("aria-expanded", "true");
      } else {
        // Ensure it's properly hidden if not 'open' initially
        galleryFilterPanel.style.maxHeight = "0";
        galleryFilterPanel.style.paddingTop = "0";
        galleryFilterPanel.style.paddingBottom = "0";
        galleryFilterPanel.style.borderWidth = "0";
        galleryFilterPanel.style.marginBottom = "0";
        if (filterToggleText)
          filterToggleText.textContent = "Show Filters / Refine View";
        if (filterToggleArrow)
          filterToggleArrow.style.transform = "rotate(0deg)";
        toggleFiltersButton.setAttribute("aria-expanded", "false");
      }

      toggleFiltersButton.addEventListener("click", function (event) {
        event.preventDefault();
        galleryFilterPanel.classList.toggle("open");

        if (galleryFilterPanel.classList.contains("open")) {
          // Expanding
          galleryFilterPanel.style.maxHeight =
            galleryFilterPanel.scrollHeight + "px";
          galleryFilterPanel.style.paddingTop = "20px"; // Match initial CSS padding
          galleryFilterPanel.style.paddingBottom = "25px"; // Match initial CSS padding
          galleryFilterPanel.style.borderWidth = "1px";
          galleryFilterPanel.style.marginBottom = "2.5rem";
          if (filterToggleText)
            filterToggleText.textContent = "Hide Filters / Refine View";
          if (filterToggleArrow)
            filterToggleArrow.style.transform = "rotate(180deg)";
          this.setAttribute("aria-expanded", "true");
        } else {
          // Collapsing
          galleryFilterPanel.style.maxHeight = "0";
          galleryFilterPanel.style.paddingTop = "0";
          galleryFilterPanel.style.paddingBottom = "0";
          galleryFilterPanel.style.borderWidth = "0";
          galleryFilterPanel.style.marginBottom = "0"; // Collapse margin as well
          if (filterToggleText)
            filterToggleText.textContent = "Show Filters / Refine View";
          if (filterToggleArrow)
            filterToggleArrow.style.transform = "rotate(0deg)";
          this.setAttribute("aria-expanded", "false");
        }
      });
    }

    // 2. "Load More" Button (Conceptual AJAX - for now, it's a normal link)
    const loadMoreButton = document.getElementById("loadMoreGallery");
    if (loadMoreButton) {
      loadMoreButton.addEventListener("click", function (event) {
        // If you want to implement AJAX loading:
        // event.preventDefault();
        // const nextPageUrl = this.href;
        // console.log('Load More clicked. Next page URL:', nextPageUrl);
        // Add your AJAX logic here to fetch content from nextPageUrl
        // and append it to .gallery-grid-main.
        // Then, update this button's href or hide it if no more pages.
        // For now, it will just follow the link for traditional pagination.
        // If using AJAX, you'd also want to hide the full pagination controls.
      });
    }

    // 3. "Like" Button Functionality (Conceptual AJAX)
    const likeButtons = document.querySelectorAll(".btn-like-gallery-item");
    likeButtons.forEach((button) => {
      button.addEventListener("click", function (event) {
        event.preventDefault(); // If it's a link, or to stop other actions
        const imageId = this.dataset.imageId;
        const icon = this.querySelector("i");
        const countSpan = this.querySelector("span");
        let currentLikes = parseInt(countSpan.textContent);

        // This is a conceptual toggle. Backend would handle actual like/unlike.
        if (this.classList.contains("liked")) {
          // Conceptual: Send AJAX request to unlike
          // On success:
          this.classList.remove("liked");
          icon.classList.remove("fas"); // Solid heart
          icon.classList.add("far"); // Outline heart
          currentLikes--;
          // console.log('Unliked image ID:', imageId);
        } else {
          // Conceptual: Send AJAX request to like
          // On success:
          this.classList.add("liked");
          icon.classList.remove("far");
          icon.classList.add("fas");
          currentLikes++;
          // console.log('Liked image ID:', imageId);
        }
        countSpan.textContent = currentLikes;

        // alert('Liking image ' + imageId + ' (conceptual). AJAX call would go here.');
      });
    });
  } // End of galleryPageContainer check
}); // End of DOMContentLoaded

// Ensure this is inside your main: document.addEventListener('DOMContentLoaded', function() { ... });

// --- Certificate Modal Logic ---
const certificateModal = document.getElementById("certificateModal");
const certificateViewer = document.getElementById("certificateViewer");
const certificateModalTitleElem = document.getElementById(
  "certificateModalTitle"
); // Renamed variable
const openCertificateButtons = document.querySelectorAll(
  ".open-certificate-modal-button"
);
const closeCertificateButton = document.getElementById(
  "closeCertificateModalBtn"
); // Targeting by new ID
const certificateModalError = document.getElementById("certificateModalError");
const certificateDirectLinkModal = document.getElementById(
  "certificateDirectLinkModal"
);

function openCertificateModal(link, title) {
  if (certificateModal && certificateViewer && certificateModalTitleElem) {
    certificateModalTitleElem.textContent = title || "Certificate Preview";
    certificateViewer.src = ""; // Clear previous src
    certificateViewer.style.display = "block";
    certificateModalError.style.display = "none";

    // Small delay to ensure src is cleared before setting new one, can help with iframe refresh issues
    setTimeout(() => {
      certificateViewer.src = link;
    }, 50);

    certificateViewer.onload = function () {
      // This might not be reliable for all cross-origin iframe content checks
      console.log(
        "Certificate iframe loaded (or at least onload event fired)."
      );
    };
    certificateViewer.onerror = function () {
      console.error("Error loading certificate in iframe:", link);
      certificateViewer.style.display = "none";
      certificateModalError.style.display = "block";
      if (certificateDirectLinkModal) certificateDirectLinkModal.href = link;
    };

    certificateModal.style.display = "flex";
    certificateModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open-custom"); // Use a more specific class
  } else {
    console.error("Certificate modal elements not found!");
  }
}

function closeCertModal() {
  if (certificateModal && certificateViewer) {
    certificateModal.style.display = "none";
    certificateModal.setAttribute("aria-hidden", "true");
    certificateViewer.src = ""; // Important to stop content & free resources
    document.body.classList.remove("modal-open-custom");
  }
}

if (openCertificateButtons.length > 0) {
  openCertificateButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const link = this.dataset.certificateLink;
      const title = this.dataset.certificateTitle;
      if (link) {
        openCertificateModal(link, title);
      } else {
        console.error("Certificate link data attribute not found on button.");
        alert("Could not find the certificate link for this item.");
      }
    });
  });
} else if (document.querySelector(".certificates-list")) {
  // If on certificates page but no buttons found
  console.warn(
    "No '.open-certificate-modal-button' elements found on the page, but '.certificates-list' exists."
  );
}

if (closeCertificateButton) {
  closeCertificateButton.addEventListener("click", closeCertModal);
}

if (certificateModal) {
  certificateModal.addEventListener("click", function (event) {
    // Close if clicked on the overlay (modal background)
    if (event.target === certificateModal) {
      closeCertModal();
    }
  });
  // Close modal with Escape key
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && certificateModal.style.display === "flex") {
      closeCertModal();
    }
  });
}
// --- End Certificate Modal Logic ---

// ... (any other JavaScript in your DOMContentLoaded) ...
