<?php
// reservations.php
require_once 'header.php';
require_once 'config.php';

// Safe output function
function safe_output($value, $default = '') {
    if ($value === null) {
        return htmlspecialchars($default);
    }
    return htmlspecialchars((string)$value);
}

$message = '';
$database = new Database();
$db = $database->getConnection();

// Get available time slots for today and future dates
$time_slots = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
    '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
    '18:00', '18:30', '19:00', '19:30', '20:00', '20:30',
    '21:00', '21:30', '22:00'
];

// Handle reservation submission
if ($_POST && isset($_POST['submit_reservation'])) {
    try {
        $customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : NULL;
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $reservation_date = $_POST['reservation_date'] ?? '';
        $reservation_time = $_POST['reservation_time'] ?? '';
        $party_size = intval($_POST['party_size'] ?? 1);
        $special_requests = trim($_POST['special_requests'] ?? '');
        
        // Validate required fields
        if (empty($customer_name) || empty($customer_phone) || empty($reservation_date) || empty($reservation_time)) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate date is not in the past
        $selected_datetime = strtotime($reservation_date . ' ' . $reservation_time);
        if ($selected_datetime < time()) {
            throw new Exception('Cannot make reservation for past date/time. Please select a future date and time.');
        }
        
        // Validate party size
        if ($party_size < 1 || $party_size > 20) {
            throw new Exception('Party size must be between 1 and 20 people.');
        }
        
        // If user is logged in, get their information
        if ($customer_id) {
            $stmt = $db->prepare("SELECT name, email, phone FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                // Use logged-in customer's information if not provided
                if (empty($customer_name)) $customer_name = $customer['name'];
                if (empty($customer_email)) $customer_email = $customer['email'];
                if (empty($customer_phone)) $customer_phone = $customer['phone'];
            }
        }
        
        // Check for existing reservation at same time
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_date = ? AND reservation_time = ? AND status IN ('pending', 'confirmed')");
        $check_stmt->execute([$reservation_date, $reservation_time]);
        $existing_reservations = $check_stmt->fetchColumn();
        
        if ($existing_reservations > 5) { // Limit to 5 concurrent reservations per time slot
            throw new Exception('This time slot is fully booked. Please choose a different time.');
        }
        
        // Insert reservation
        $query = "INSERT INTO reservations (customer_id, customer_name, customer_phone, customer_email, reservation_date, reservation_time, party_size, special_requests, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$customer_id, $customer_name, $customer_phone, $customer_email, $reservation_date, $reservation_time, $party_size, $special_requests])) {
            $reservation_id = $db->lastInsertId();
            
            // Try to add to reservation history (gracefully handle if table doesn't exist)
            try {
                $history_query = "INSERT INTO reservation_history (reservation_id, action, new_status, changed_by, notes) VALUES (?, 'created', 'pending', 'customer', 'Reservation created online')";
                $history_stmt = $db->prepare($history_query);
                $history_stmt->execute([$reservation_id]);
            } catch (Exception $history_error) {
                // Silently ignore history errors - reservation was still created successfully
                error_log("Reservation history error: " . $history_error->getMessage());
            }
            
            $message = '<div class="alert success">Reservation request submitted successfully! We will confirm your reservation shortly.</div>';
            
            // Clear form
            $_POST = array();
        } else {
            throw new Exception('Error making reservation. Please try again.');
        }
        
    } catch (Exception $e) {
        $message = '<div class="alert error">' . safe_output($e->getMessage()) . '</div>';
    }
}

// Get user's previous reservations if logged in
$user_reservations = [];
if (isset($_SESSION['customer_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM reservations WHERE customer_id = ? ORDER BY reservation_date DESC, reservation_time DESC LIMIT 5");
        $stmt->execute([$_SESSION['customer_id']]);
        $user_reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silently fail - reservations will still work
        error_log("Error fetching user reservations: " . $e->getMessage());
    }
}
?>

<!-- The rest of your HTML and CSS remains exactly the same -->
<section class="reservation-section">
    <div class="container">
        <div class="section-title">
            <h2>Make a Reservation</h2>
            <p>Book your table at BrewHaven Cafe</p>
        </div>
        
        <?php echo $message; ?>
        
        <div class="reservation-layout">
            <!-- Reservation Form -->
            <div class="reservation-form-container">
                <h3><i class="fas fa-calendar-plus"></i> Book Your Table</h3>
                
                <form method="POST" id="reservationForm">
                    <input type="hidden" name="submit_reservation" value="1">
                    
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h4>Your Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_name">Full Name *</label>
                                <input type="text" id="customer_name" name="customer_name" class="form-control" 
                                       value="<?php echo safe_output($_POST['customer_name'] ?? ($_SESSION['customer_name'] ?? '')); ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="customer_phone">Phone Number *</label>
                                <input type="tel" id="customer_phone" name="customer_phone" class="form-control" 
                                       value="<?php echo safe_output($_POST['customer_phone'] ?? ($_SESSION['customer_phone'] ?? '')); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_email">Email Address</label>
                            <input type="email" id="customer_email" name="customer_email" class="form-control" 
                                   value="<?php echo safe_output($_POST['customer_email'] ?? ($_SESSION['customer_email'] ?? '')); ?>">
                            <small>We'll send confirmation to this email</small>
                        </div>
                    </div>
                    
                    <!-- Reservation Details -->
                    <div class="form-section">
                        <h4>Reservation Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="reservation_date">Date *</label>
                                <input type="date" id="reservation_date" name="reservation_date" class="form-control" 
                                       value="<?php echo safe_output($_POST['reservation_date'] ?? ''); ?>" 
                                       required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="reservation_time">Time *</label>
                                <select id="reservation_time" name="reservation_time" class="form-control" required>
                                    <option value="">Select a time</option>
                                    <?php foreach ($time_slots as $time): 
                                        $formatted_time = date('g:i A', strtotime($time));
                                        $selected = ($_POST['reservation_time'] ?? '') === $time ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo safe_output($time); ?>" <?php echo $selected; ?>>
                                            <?php echo safe_output($formatted_time); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="party_size">Party Size *</label>
                            <select id="party_size" name="party_size" class="form-control" required>
                                <option value="">Select party size</option>
                                <?php for ($i = 1; $i <= 20; $i++): 
                                    $selected = ($_POST['party_size'] ?? '') == $i ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo $selected; ?>>
                                        <?php echo $i; ?> <?php echo $i === 1 ? 'person' : 'people'; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <small>For parties larger than 20, please call us directly</small>
                        </div>
                    </div>
                    
                    <!-- Special Requests -->
                    <div class="form-section">
                        <h4>Additional Information</h4>
                        <div class="form-group">
                            <label for="special_requests">Special Requests</label>
                            <textarea id="special_requests" name="special_requests" class="form-control" rows="4" 
                                      placeholder="Any special requests, dietary restrictions, or occasion details..."><?php echo safe_output($_POST['special_requests'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-submit">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-calendar-check"></i> Book Reservation
                        </button>
                        <p class="form-note">
                            <small>You'll receive a confirmation call or email within 2 hours</small>
                        </p>
                    </div>
                </form>
            </div>
            
            <!-- Sidebar with Info and Previous Reservations -->
            <div class="reservation-sidebar">
                <!-- Info Card -->
                <div class="info-card">
                    <h4><i class="fas fa-info-circle"></i> Reservation Info</h4>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Operating Hours</strong>
                            <p>Mon-Sun: 9:00 AM - 10:00 PM</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <strong>Need Help?</strong>
                            <p>Call: (555) 123-4567</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Important</strong>
                            <p>Please arrive 5 minutes before your reservation time</p>
                        </div>
                    </div>
                </div>
                
                <!-- Previous Reservations (for logged-in users) -->
                <?php if (!empty($user_reservations)): ?>
                <div class="previous-reservations">
                    <h4><i class="fas fa-history"></i> Recent Reservations</h4>
                    <div class="reservations-list">
                        <?php foreach ($user_reservations as $res): ?>
                            <div class="reservation-item">
                                <div class="reservation-date">
                                    <?php echo date('M j', strtotime($res['reservation_date'])); ?> at 
                                    <?php echo date('g:i A', strtotime($res['reservation_time'])); ?>
                                </div>
                                <div class="reservation-details">
                                    <span class="party-size"><?php echo safe_output($res['party_size']); ?> people</span>
                                    <span class="status status-<?php echo safe_output($res['status']); ?>">
                                        <?php echo ucfirst(safe_output($res['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
/* Your existing CSS styles remain exactly the same */
.reservation-section {
    background: linear-gradient(135deg, #fdf5e6 0%, #fffaf0 100%);
    min-height: 100vh;
    padding: 40px 0;
}

.reservation-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 40px;
    max-width: 1200px;
    margin: 0 auto;
}

.reservation-form-container {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.reservation-form-container h3 {
    color: #8B4513;
    margin-bottom: 30px;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #e8e6e3;
}

.form-section h4 {
    color: #5a3e2c;
    margin-bottom: 20px;
    font-size: 1.2rem;
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
    font-weight: 600;
    color: #5a3e2c;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e8e6e3;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #8B4513;
    box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
}

.form-control::placeholder {
    color: #a8a8a8;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #6c757d;
    font-size: 0.85rem;
}

.form-submit {
    text-align: center;
    margin-top: 30px;
}

.btn-large {
    padding: 15px 30px;
    font-size: 1.1rem;
}

.form-note {
    margin-top: 15px;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Sidebar Styles */
.reservation-sidebar {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.info-card, .previous-reservations {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.info-card h4, .previous-reservations h4 {
    color: #8B4513;
    margin-bottom: 20px;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.info-item:last-child {
    margin-bottom: 0;
    border-bottom: none;
}

.info-item i {
    color: #8B4513;
    font-size: 1.2rem;
    margin-top: 2px;
}

.info-item strong {
    display: block;
    margin-bottom: 5px;
    color: #5a3e2c;
}

.info-item p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

/* Previous Reservations */
.reservation-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 3px solid #8B4513;
}

.reservation-date {
    font-weight: 600;
    color: #5a3e2c;
    margin-bottom: 5px;
}

.reservation-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.party-size {
    color: #6c757d;
}

.status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-confirmed { background: #d1ecf1; color: #0c5460; }
.status-seated { background: #d4edda; color: #155724; }
.status-completed { background: #c3e6cb; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-no_show { background: #e2e3e5; color: #383d41; }

/* Alert Styles */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    font-weight: 500;
    border-left: 4px solid;
}

.alert.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.alert.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .reservation-layout {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .reservation-sidebar {
        order: -1;
    }
}

@media (max-width: 768px) {
    .reservation-form-container {
        padding: 25px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .reservation-layout {
        gap: 20px;
    }
}

@media (max-width: 480px) {
    .reservation-section {
        padding: 20px 0;
    }
    
    .reservation-form-container {
        padding: 20px;
    }
    
    .reservation-form-container h3 {
        font-size: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('reservationForm');
    const dateInput = document.getElementById('reservation_date');
    const timeSelect = document.getElementById('reservation_time');
    
    // Set minimum date to today
    if (dateInput && !dateInput.value) {
        dateInput.min = new Date().toISOString().split('T')[0];
    }
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('customer_name').value.trim();
        const phone = document.getElementById('customer_phone').value.trim();
        const date = dateInput.value;
        const time = timeSelect.value;
        const partySize = document.getElementById('party_size').value;
        
        let isValid = true;
        let errorMessage = '';
        
        // Validate required fields
        if (!name) {
            isValid = false;
            errorMessage = 'Please enter your full name.';
        } else if (!phone) {
            isValid = false;
            errorMessage = 'Please enter your phone number.';
        } else if (!date) {
            isValid = false;
            errorMessage = 'Please select a reservation date.';
        } else if (!time) {
            isValid = false;
            errorMessage = 'Please select a reservation time.';
        } else if (!partySize) {
            isValid = false;
            errorMessage = 'Please select party size.';
        }
        
        // Validate date is not in the past
        if (date && time) {
            const selectedDateTime = new Date(date + 'T' + time);
            const now = new Date();
            
            if (selectedDateTime < now) {
                isValid = false;
                errorMessage = 'Cannot make reservation for past date/time. Please select a future date and time.';
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert(errorMessage);
            return false;
        }
    });
    
    // Phone number formatting
    const phoneInput = document.getElementById('customer_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 3 && value.length <= 6) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            } else if (value.length > 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{0,4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });
    }
    
    // Dynamic time slot availability (basic implementation)
    dateInput.addEventListener('change', function() {
        // In a real application, you would fetch available time slots from the server
        // For now, we'll just enable all time slots
        const options = timeSelect.options;
        for (let i = 1; i < options.length; i++) {
            options[i].disabled = false;
            options[i].style.color = '';
        }
    });
});
</script>

<?php include 'footer.php'; ?>