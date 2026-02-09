/* =====================================================
   DC Metro Construction - Main JavaScript
   ===================================================== */

document.addEventListener('DOMContentLoaded', function() {
    // Header scroll effect
    const header = document.getElementById('header');

    function handleScroll() {
        if (window.scrollY > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }

    window.addEventListener('scroll', handleScroll);
    handleScroll(); // Check on load

    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');

    if (mobileMenuBtn && navMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                mobileMenuBtn.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Close menu when clicking on a link
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mobileMenuBtn.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
    }

    // Animated counter
    const counters = document.querySelectorAll('[data-count]');

    function animateCounter(counter) {
        const target = parseInt(counter.getAttribute('data-count'));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 16);
    }

    // Intersection Observer for counters
    if (counters.length > 0) {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => counterObserver.observe(counter));
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const headerHeight = header.offsetHeight;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;

                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // Scroll-triggered animations
    const animatedElements = document.querySelectorAll('.service-card, .value-card, .team-card, .testimonial-card, .why-feature, .timeline-item');

    if (animatedElements.length > 0) {
        const animationObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('animate-fade-in-up');
                    }, index * 100);
                    animationObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        animatedElements.forEach(el => {
            el.style.opacity = '0';
            animationObserver.observe(el);
        });
    }

    // Project filtering (for projects page)
    const filterBtns = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');

    if (filterBtns.length > 0 && galleryItems.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active button
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                galleryItems.forEach(item => {
                    if (filter === 'all' || item.getAttribute('data-category') === filter) {
                        item.style.display = 'block';
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.style.transform = 'scale(1)';
                        }, 50);
                    } else {
                        item.style.opacity = '0';
                        item.style.transform = 'scale(0.8)';
                        setTimeout(() => {
                            item.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });
    }

    // Form validation and submission
    const contactForm = document.getElementById('contactForm');

    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Basic validation
            const name = document.getElementById('name');
            const email = document.getElementById('email');
            const phone = document.getElementById('phone');
            const message = document.getElementById('message');

            let isValid = true;

            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(el => el.remove());
            document.querySelectorAll('.form-group input, .form-group textarea').forEach(el => {
                el.style.borderColor = '';
            });

            // Validate name
            if (!name.value.trim()) {
                showError(name, 'Please enter your name');
                isValid = false;
            }

            // Validate email
            if (!email.value.trim()) {
                showError(email, 'Please enter your email');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showError(email, 'Please enter a valid email address');
                isValid = false;
            }

            // Validate phone (optional but if entered, should be valid)
            if (phone.value.trim() && !isValidPhone(phone.value)) {
                showError(phone, 'Please enter a valid phone number');
                isValid = false;
            }

            // Validate message
            if (!message.value.trim()) {
                showError(message, 'Please enter your message');
                isValid = false;
            }

            if (isValid) {
                // Submit form to PHP handler
                const submitBtn = contactForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Sending...';
                submitBtn.disabled = true;

                // Create FormData object
                const formData = new FormData(contactForm);

                // Send to PHP handler
                fetch('send-quote.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Show success message
                    const successMsg = document.createElement('div');
                    successMsg.className = 'form-success';
                    successMsg.style.cssText = 'background-color: #22c55e; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;';
                    successMsg.textContent = data.message || 'Thank you for your message! We will get back to you soon.';
                    contactForm.insertBefore(successMsg, contactForm.firstChild);

                    // Reset form
                    contactForm.reset();
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;

                    // Remove success message after 5 seconds
                    setTimeout(() => {
                        successMsg.remove();
                    }, 5000);
                })
                .catch(error => {
                    // Show error message
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'form-error-msg';
                    errorMsg.style.cssText = 'background-color: #ef4444; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;';
                    errorMsg.textContent = 'There was an error sending your message. Please try again or call us directly.';
                    contactForm.insertBefore(errorMsg, contactForm.firstChild);

                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;

                    // Remove error message after 5 seconds
                    setTimeout(() => {
                        errorMsg.remove();
                    }, 5000);
                });
            }
        });
    }

    function showError(input, message) {
        input.style.borderColor = '#ef4444';
        const error = document.createElement('div');
        error.className = 'form-error';
        error.style.cssText = 'color: #ef4444; font-size: 0.875rem; margin-top: 5px;';
        error.textContent = message;
        input.parentElement.appendChild(error);
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidPhone(phone) {
        return /^[\d\s\-\(\)\+]+$/.test(phone) && phone.replace(/\D/g, '').length >= 10;
    }

    // Parallax effect for hero and CTA sections
    const parallaxElements = document.querySelectorAll('.hero, .cta-section');

    if (parallaxElements.length > 0) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;

            parallaxElements.forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.bottom > 0 && rect.top < window.innerHeight) {
                    const speed = 0.5;
                    el.style.backgroundPositionY = `${scrolled * speed}px`;
                }
            });
        });
    }

    // Lazy loading for images
    const lazyImages = document.querySelectorAll('img[data-src]');

    if (lazyImages.length > 0) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });

        lazyImages.forEach(img => imageObserver.observe(img));
    }

    // Testimonial slider (simple auto-scroll for mobile)
    const testimonialSlider = document.querySelector('.testimonials-slider');

    if (testimonialSlider && window.innerWidth < 992) {
        let currentSlide = 0;
        const slides = testimonialSlider.querySelectorAll('.testimonial-card');
        const totalSlides = slides.length;

        // Auto advance slides
        setInterval(() => {
            currentSlide = (currentSlide + 1) % totalSlides;
            testimonialSlider.scrollTo({
                left: slides[currentSlide].offsetLeft - 20,
                behavior: 'smooth'
            });
        }, 5000);
    }

    // Back to top button
    const backToTop = document.createElement('button');
    backToTop.innerHTML = '&uarr;';
    backToTop.className = 'back-to-top';
    backToTop.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background-color: var(--secondary, #f97316);
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    document.body.appendChild(backToTop);

    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 500) {
            backToTop.style.opacity = '1';
            backToTop.style.visibility = 'visible';
        } else {
            backToTop.style.opacity = '0';
            backToTop.style.visibility = 'hidden';
        }
    });

    // Active navigation highlighting
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-menu a:not(.btn)');

    function highlightNav() {
        const scrollPosition = window.scrollY + 200;

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.offsetHeight;
            const sectionId = section.getAttribute('id');

            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${sectionId}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }

    if (sections.length > 0) {
        window.addEventListener('scroll', highlightNav);
    }
});
