/* =====================================================
   DC Metro Construction - Main JavaScript
   ===================================================== */

document.addEventListener('DOMContentLoaded', function() {
    const header = document.getElementById('header');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // --- Consolidated scroll handler (Phase 3.1) ---
    const parallaxElements = document.querySelectorAll('.hero, .cta-section');
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-menu a:not(.btn)');
    const backToTop = document.createElement('button');

    backToTop.textContent = '\u2191';
    backToTop.className = 'back-to-top';
    backToTop.setAttribute('aria-label', 'Back to top');
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
        transition: opacity 0.3s ease, visibility 0.3s ease;
        z-index: 999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    document.body.appendChild(backToTop);

    backToTop.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    let scrollTicking = false;

    function onScroll() {
        if (scrollTicking) return;
        scrollTicking = true;

        requestAnimationFrame(function() {
            const scrollY = window.scrollY;
            const pageYOffset = window.pageYOffset;

            // Header scroll effect (with null guard - Phase 1.5)
            if (header) {
                if (scrollY > 100) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            }

            // Parallax effect (with reduced motion check - Phase 2.3)
            if (!prefersReducedMotion && parallaxElements.length > 0) {
                parallaxElements.forEach(function(el) {
                    var rect = el.getBoundingClientRect();
                    if (rect.bottom > 0 && rect.top < window.innerHeight) {
                        el.style.backgroundPositionY = (pageYOffset * 0.5) + 'px';
                    }
                });
            }

            // Back to top visibility
            if (pageYOffset > 500) {
                backToTop.style.opacity = '1';
                backToTop.style.visibility = 'visible';
            } else {
                backToTop.style.opacity = '0';
                backToTop.style.visibility = 'hidden';
            }

            // Active nav highlighting
            if (sections.length > 0) {
                var scrollPosition = scrollY + 200;
                sections.forEach(function(section) {
                    var sectionTop = section.offsetTop;
                    var sectionHeight = section.offsetHeight;
                    var sectionId = section.getAttribute('id');
                    if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                        navLinks.forEach(function(link) {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === '#' + sectionId) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }

            scrollTicking = false;
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // Check on load

    // --- Mobile menu toggle (Phase 2.1 - aria-expanded) ---
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');

    if (mobileMenuBtn && navMenu) {
        function closeMenu() {
            mobileMenuBtn.classList.remove('active');
            navMenu.classList.remove('active');
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        mobileMenuBtn.addEventListener('click', function() {
            var isActive = navMenu.classList.toggle('active');
            this.classList.toggle('active');
            this.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            document.body.style.overflow = isActive ? 'hidden' : '';
        });

        document.addEventListener('click', function(e) {
            if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                closeMenu();
            }
        });

        navMenu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', closeMenu);
        });
    }

    // --- Animated counter (Phase 1.5 - isNaN guard) ---
    const counters = document.querySelectorAll('[data-count]');

    function animateCounter(counter) {
        var target = parseInt(counter.getAttribute('data-count'));
        if (isNaN(target)) { target = 0; }
        var duration = 2000;
        var step = target / (duration / 16);
        var current = 0;

        var timer = setInterval(function() {
            current += step;
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            } else {
                counter.textContent = Math.floor(current);
            }
        }, 16);
    }

    if (counters.length > 0) {
        var counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(function(counter) { counterObserver.observe(counter); });
    }

    // --- Smooth scroll for anchor links (Phase 1.5 - null guard on header) ---
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                var target = document.querySelector(href);
                if (target) {
                    var headerHeight = header ? header.offsetHeight : 0;
                    var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // --- Scroll-triggered animations (Phase 3.5 - opacity set via CSS now) ---
    const animatedElements = document.querySelectorAll('.service-card, .value-card, .team-card, .testimonial-card, .why-feature, .timeline-item');

    if (animatedElements.length > 0) {
        var animationObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry, index) {
                if (entry.isIntersecting) {
                    setTimeout(function() {
                        entry.target.classList.add('animate-fade-in-up');
                    }, index * 100);
                    animationObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        animatedElements.forEach(function(el) {
            animationObserver.observe(el);
        });
    }

    // --- Project filtering ---
    const filterBtns = document.querySelectorAll('.filter-btn');
    const galleryItems = document.querySelectorAll('.gallery-item');

    if (filterBtns.length > 0 && galleryItems.length > 0) {
        filterBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                filterBtns.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');

                var filter = this.getAttribute('data-filter');

                galleryItems.forEach(function(item) {
                    if (filter === 'all' || item.getAttribute('data-category') === filter) {
                        item.style.display = 'block';
                        setTimeout(function() {
                            item.style.opacity = '1';
                            item.style.transform = 'scale(1)';
                        }, 50);
                    } else {
                        item.style.opacity = '0';
                        item.style.transform = 'scale(0.8)';
                        setTimeout(function() {
                            item.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });
    }

    // --- Form validation and submission (Phase 1.3 - error handling, XSS, aria) ---
    const contactForm = document.getElementById('contactForm');

    if (contactForm) {
        // Test auto-fill: show button when ?test is in URL
        var testFillBtn = document.getElementById('testFillBtn');
        if (new URLSearchParams(window.location.search).has('test') && testFillBtn) {
            testFillBtn.style.display = 'inline-block';
            testFillBtn.addEventListener('click', function() {
                document.getElementById('name').value = 'John Test';
                document.getElementById('email').value = 'test@example.com';
                document.getElementById('phone').value = '(202) 555-0199';
                document.getElementById('company').value = 'Test Company LLC';
                document.getElementById('service').value = 'commercial';
                document.getElementById('budget').value = '100k-500k';
                document.getElementById('timeline').value = '1-3months';
                document.getElementById('message').value = 'This is a test submission from the auto-fill feature. Please ignore.';
            });
        }

        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();

            var name = document.getElementById('name');
            var email = document.getElementById('email');
            var phone = document.getElementById('phone');
            var message = document.getElementById('message');

            var isValid = true;

            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(function(el) { el.remove(); });
            var formMessage = document.getElementById('formMessage');
            formMessage.style.display = 'none';
            document.querySelectorAll('.form-group input, .form-group textarea').forEach(function(el) {
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

            // Validate phone
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
                var submitBtn = contactForm.querySelector('button[type="submit"]');
                var originalText = submitBtn.textContent;
                submitBtn.textContent = 'Sending...';
                submitBtn.disabled = true;
                submitBtn.setAttribute('aria-busy', 'true');

                var formData = new FormData(contactForm);

                fetch('send-quote.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.text().then(function(text) {
                        try {
                            var data = JSON.parse(text);
                            if (!response.ok) {
                                throw new Error(data.message || 'Server error');
                            }
                            return data;
                        } catch(e) {
                            if (!response.ok) throw new Error('Server error');
                            throw new Error('Invalid response from server');
                        }
                    });
                })
                .then(function(data) {
                    if (data.success) {
                        formMessage.style.backgroundColor = '#22c55e';
                        formMessage.textContent = data.message || 'Thank you for your message! We will get back to you soon.';
                        formMessage.style.display = 'block';

                        contactForm.reset();

                        formMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        setTimeout(function() {
                            formMessage.style.display = 'none';
                        }, 10000);
                    } else {
                        formMessage.style.backgroundColor = '#ef4444';
                        formMessage.textContent = data.message || 'There was an error sending your message. Please try again or call us directly.';
                        formMessage.style.display = 'block';

                        formMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        setTimeout(function() {
                            formMessage.style.display = 'none';
                        }, 10000);
                    }

                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    submitBtn.setAttribute('aria-busy', 'false');
                })
                .catch(function(error) {
                    formMessage.style.backgroundColor = '#ef4444';
                    formMessage.textContent = error.message || 'There was an error sending your message. Please try again or call us directly.';
                    formMessage.style.display = 'block';

                    formMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    submitBtn.setAttribute('aria-busy', 'false');

                    setTimeout(function() {
                        formMessage.style.display = 'none';
                    }, 10000);
                });
            }
        });
    }

    function showError(input, message) {
        input.style.borderColor = '#ef4444';
        var error = document.createElement('div');
        error.className = 'form-error';
        error.setAttribute('role', 'alert');
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

    // --- Lazy loading for images ---
    const lazyImages = document.querySelectorAll('img[data-src]');

    if (lazyImages.length > 0) {
        var imageObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });

        lazyImages.forEach(function(img) { imageObserver.observe(img); });
    }

    // --- Testimonial slider with visibility cleanup (Phase 3.4) ---
    const testimonialSlider = document.querySelector('.testimonials-slider');

    if (testimonialSlider && window.innerWidth < 992) {
        var currentSlide = 0;
        var slides = testimonialSlider.querySelectorAll('.testimonial-card');
        var totalSlides = slides.length;

        var sliderInterval = setInterval(function() {
            currentSlide = (currentSlide + 1) % totalSlides;
            testimonialSlider.scrollTo({
                left: slides[currentSlide].offsetLeft - 20,
                behavior: 'smooth'
            });
        }, 5000);

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(sliderInterval);
            } else {
                sliderInterval = setInterval(function() {
                    currentSlide = (currentSlide + 1) % totalSlides;
                    testimonialSlider.scrollTo({
                        left: slides[currentSlide].offsetLeft - 20,
                        behavior: 'smooth'
                    });
                }, 5000);
            }
        });
    }
});
