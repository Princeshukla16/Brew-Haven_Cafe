<?php
// contact.php
require_once 'header.php';
require_once 'config.php';

$success_message = '';
$error_message = '';

// Handle contact form submission
if ($_POST && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Save contact message to database
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$name, $email, $phone, $subject, $message])) {
            $success_message = "Thank you for your message! We'll get back to you within 24 hours.";
            
            // Clear form fields
            $_POST = array();
        } else {
            $error_message = "Sorry, there was an error sending your message. Please try again.";
        }
    }
}

// Create contact_messages table if it doesn't exist
$database = new Database();
$db = $database->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>

<section class="contact-section">
    <div class="container">
        <div class="section-title">
            <h2>Contact Us</h2>
            <p>We'd love to hear from you. Get in touch with BrewHaven Cafe!</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="contact-container">
            <div class="contact-info">
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-details">
                        <h4>Our Location</h4>
                        <p>123 Coffee Lane, Brigade Road<br>Bengaluru, Karnataka 560001</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="contact-details">
                        <h4>Phone Number</h4>
                        <p>+91 80 1234 5678</p>
                        <p>+91 98765 43210 (WhatsApp)</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-details">
                        <h4>Email Address</h4>
                        <p>hello@brewhavenindia.com</p>
                        <p>reservations@brewhavenindia.com</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-details">
                        <h4>Opening Hours</h4>
                        <div class="hours-list">
                            <div class="hour-item">
                                <span>Monday - Friday:</span>
                                <span>7:00 AM - 11:00 PM</span>
                            </div>
                            <div class="hour-item">
                                <span>Saturday:</span>
                                <span>7:00 AM - 12:00 AM</span>
                            </div>
                            <div class="hour-item">
                                <span>Sunday:</span>
                                <span>7:00 AM - 11:00 PM</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="social-links-contact">
                    <h4>Follow Us</h4>
                    <div class="social-icons">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-google"></i></a>
                    </div>
                </div>
            </div>

            <div class="contact-form-container">
                <div class="contact-form-header">
                    <h3>Send us a Message</h3>
                    <p>Have questions about our menu, reservations, or events? We're here to help!</p>
                </div>

                <form method="POST" class="contact-form">
                    <input type="hidden" name="contact_submit" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" class="form-control" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Reservation Help" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Reservation Help') ? 'selected' : ''; ?>>Reservation Help</option>
                                <option value="Catering Services" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Catering Services') ? 'selected' : ''; ?>>Catering Services</option>
                                <option value="Feedback" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Feedback') ? 'selected' : ''; ?>>Feedback</option>
                                <option value="Complaint" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Complaint') ? 'selected' : ''; ?>>Complaint</option>
                                <option value="Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Partnership') ? 'selected' : ''; ?>>Partnership</option>
                                <option value="Career Opportunities" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Career Opportunities') ? 'selected' : ''; ?>>Career Opportunities</option>
                                <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control" rows="6" required 
                                  placeholder="Tell us how we can help you..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>

                    <div class="form-submit">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Map Section -->
        <div class="map-section">
            <h3>Find Us</h3>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3888.881235041071!2d77.59431431482133!3d12.908690190893104!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bae151e3b9c7d7f%3A0x2d0c4b7f0b8c8b8c!2sBrigade%20Road%2C%20Bengaluru%2C%20Karnataka!5e0!3m2!1sen!2sin!4v1630918037678!5m2!1sen!2sin" 
                    width="100%" 
                    height="400" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h3>Frequently Asked Questions</h3>
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        <h4>Do you take reservations?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes! We highly recommend making reservations, especially during weekends and evenings. You can book a table through our website or by calling us directly.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>Is there parking available?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>We have limited valet parking available. There are also several paid parking lots within walking distance of our cafe.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>Do you offer catering services?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, we provide catering for events and corporate functions. Please contact us with your requirements for a customized quote.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <h4>Are you wheelchair accessible?</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Absolutely! Our cafe is fully wheelchair accessible with ramps and spacious seating arrangements.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .contact-section {
        padding: 60px 0;
        background: linear-gradient(135deg, #f8f5f0 0%, #ffffff 100%);
    }

    .contact-container {
        display: grid;
        grid-template-columns: 1fr 1.2fr;
        gap: 50px;
        margin-bottom: 50px;
    }

    .contact-info {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        height: fit-content;
    }

    .contact-item {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #f0f0f0;
    }

    .contact-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .contact-icon {
        width: 60px;
        height: 60px;
        background: var(--accent);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--primary);
        flex-shrink: 0;
    }

    .contact-details h4 {
        color: var(--primary);
        margin-bottom: 8px;
        font-size: 1.2rem;
    }

    .contact-details p {
        color: var(--gray);
        line-height: 1.6;
    }

    .hours-list {
        width: 100%;
    }

    .hour-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        padding: 5px 0;
    }

    .hour-item span:first-child {
        font-weight: 500;
        color: var(--dark);
    }

    .hour-item span:last-child {
        color: var(--gray);
    }

    .social-links-contact {
        margin-top: 30px;
    }

    .social-links-contact h4 {
        margin-bottom: 15px;
        color: var(--primary);
    }

    .social-icons {
        display: flex;
        gap: 15px;
    }

    .social-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 1.2rem;
    }

    .social-link:hover {
        background: var(--secondary);
        transform: translateY(-3px);
    }

    .contact-form-container {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .contact-form-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .contact-form-header h3 {
        color: var(--primary);
        margin-bottom: 10px;
    }

    .contact-form-header p {
        color: var(--gray);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--dark);
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(111, 78, 55, 0.1);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }

    .form-submit {
        text-align: center;
        margin-top: 30px;
    }

    .btn-primary {
        padding: 15px 40px;
        font-size: 1.1rem;
        background: var(--primary);
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-primary:hover {
        background: #5a3e2c;
        transform: translateY(-2px);
    }

    .map-section {
        margin: 60px 0;
        text-align: center;
    }

    .map-section h3 {
        color: var(--primary);
        margin-bottom: 20px;
        font-size: 2rem;
    }

    .map-container {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .faq-section {
        margin-top: 60px;
    }

    .faq-section h3 {
        text-align: center;
        color: var(--primary);
        margin-bottom: 30px;
        font-size: 2rem;
    }

    .faq-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .faq-item {
        background: white;
        border-radius: 10px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .faq-question {
        padding: 20px 25px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .faq-question:hover {
        background: #f8f5f0;
    }

    .faq-question h4 {
        color: var(--primary);
        margin: 0;
        font-size: 1.1rem;
    }

    .faq-question i {
        color: var(--primary);
        transition: transform 0.3s ease;
    }

    .faq-answer {
        padding: 0 25px;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .faq-item.active .faq-answer {
        padding: 0 25px 20px;
        max-height: 200px;
    }

    .faq-item.active .faq-question i {
        transform: rotate(180deg);
    }

    .faq-answer p {
        color: var(--gray);
        line-height: 1.6;
        margin: 0;
    }

    /* Responsive Design */
    @media (max-width: 968px) {
        .contact-container {
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .contact-info, .contact-form-container {
            padding: 30px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .contact-section {
            padding: 40px 0;
        }
        
        .contact-item {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
        
        .contact-icon {
            align-self: center;
        }
        
        .social-icons {
            justify-content: center;
        }
        
        .hour-item {
            flex-direction: column;
            text-align: center;
            gap: 5px;
        }
    }

    @media (max-width: 480px) {
        .contact-info, .contact-form-container {
            padding: 20px;
        }
        
        .contact-form-header h3 {
            font-size: 1.5rem;
        }
        
        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<script>
    // FAQ Accordion functionality
    document.querySelectorAll('.faq-question').forEach(question => {
        question.addEventListener('click', () => {
            const faqItem = question.parentElement;
            faqItem.classList.toggle('active');
        });
    });

    // Form validation
    document.querySelector('.contact-form').addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let valid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.style.borderColor = 'var(--danger)';
            } else {
                field.style.borderColor = '';
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });

    // Real-time validation
    document.querySelectorAll('.form-control').forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = 'var(--success)';
            } else {
                this.style.borderColor = '';
            }
        });
    });
</script>

<?php include 'footer.php'; ?>