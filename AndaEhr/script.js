// script.js - Works with AndaEhr.php
const API_BASE = 'AndaEhr.php';

// Application State
const state = {
    currentPatientId: null,
    currentConsultationId: null,
    currentUser: null,
    currentPage: 'dashboard'
};

// Initialize the app
document.addEventListener('DOMContentLoaded', function() {
    console.log('Script.js loaded - Anda EHR System');
    
    // Initialize based on current page
    initializeCurrentPage();
    
    // Setup all event listeners
    setupPatientForm();
    setupConsultationForm();
    setupTableEventListeners();
    setupModalHandlers();
    setupPageNavigation();
    setupContactNumberFormatting();
    
    // Initialize charts
    initializeCharts();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
});

// Initialize current page based on URL
function initializeCurrentPage() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchTerm = urlParams.get('search');
    
    // If there's a search term, we should be on patients page
    if (searchTerm) {
        state.currentPage = 'patients';
        showPage('patients');
    }
    
    // Also check if we're on a specific page from sidebar click
    const currentActivePage = document.querySelector('.page.active');
    if (currentActivePage) {
        const pageId = currentActivePage.id.replace('-page', '');
        if (pageId) {
            state.currentPage = pageId;
            
            // Update sidebar active state
            document.querySelectorAll('.sidebar-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-page') === pageId) {
                    item.classList.add('active');
                }
            });
        }
    }
}

// Setup patient form (for modal)
function setupPatientForm() {
    const patientForm = document.getElementById('patient-form');
    if (patientForm) {
        // Calculate age when DOB changes
        const dobInput = document.getElementById('patient-dob');
        const ageInput = document.getElementById('patient-age');
        
        if (dobInput && ageInput) {
            dobInput.addEventListener('change', function() {
                if (this.value) {
                    const dob = new Date(this.value);
                    const today = new Date();
                    let age = today.getFullYear() - dob.getFullYear();
                    const monthDiff = today.getMonth() - dob.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }
                    
                    ageInput.value = age;
                } else {
                    ageInput.value = '';
                }
            });
        }
        
        // Validate form before submission
        patientForm.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'red';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            // Validate contact number
            const contactInput = document.getElementById('patient-contact');
            if (contactInput && contactInput.value) {
                const contactRegex = /^09[0-9]{9}$/;
                const cleanNumber = contactInput.value.replace(/\D/g, '');
                
                if (!contactRegex.test(cleanNumber)) {
                    isValid = false;
                    contactInput.style.borderColor = 'red';
                    alert('Please enter a valid 11-digit Philippine mobile number starting with "09" (e.g., 09123456789)');
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // Ensure contact number is clean before submission
            if (contactInput) {
                contactInput.value = contactInput.value.replace(/\D/g, '');
            }
            
            return true;
        });
    }
}

// Setup consultation form
function setupConsultationForm() {
    const consultationForm = document.getElementById('consultation-form');
    if (consultationForm) {
        // Set current date/time as default
        const now = new Date();
        const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        const dateInput = document.getElementById('consultation-date');
        if (dateInput) dateInput.value = localDateTime;
        
        // Setup BMI calculation
        setupBMICalculation();
    }
}

// Setup table event listeners
function setupTableEventListeners() {
    // Archive patient buttons
    document.querySelectorAll('.archive-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row click
            const patientId = this.getAttribute('data-patient-id');
            document.getElementById('archive-patient-id').value = patientId;
            document.getElementById('archive-confirm-modal').style.display = 'flex';
        });
    });
    
    // Restore patient buttons
    document.querySelectorAll('.restore-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row click
            const patientId = this.getAttribute('data-patient-id');
            if (confirm('Are you sure you want to restore this patient?')) {
                submitForm('restore_patient', patientId);
            }
        });
    });
    
    // Consultation buttons
    document.querySelectorAll('.consult-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent row click
            const patientId = this.getAttribute('data-patient-id');
            const patientName = this.closest('tr').querySelector('td:nth-child(2)').textContent;
            if (patientId && patientName) {
                openConsultationModal(patientId, patientName);
            }
        });
    });
    
    // Make patient rows clickable for history
    document.querySelectorAll('.patient-table tbody tr.clickable').forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on action buttons
            if (e.target.closest('.action-btn')) {
                return;
            }
            
            const patientId = this.getAttribute('data-patient-id');
            if (patientId) {
                loadPatientHistory(patientId);
            }
        });
    });
}

// Setup modal handlers
function setupModalHandlers() {
    // Add patient buttons
    const addPatientBtn = document.getElementById('add-patient-btn');
    const addPatientBtn2 = document.getElementById('add-patient-btn-2');
    const addFirstPatientBtn = document.getElementById('add-first-patient');
    
    if (addPatientBtn) {
        addPatientBtn.addEventListener('click', function() {
            document.getElementById('patient-modal-overlay').style.display = 'flex';
            resetPatientForm();
        });
    }
    
    if (addPatientBtn2) {
        addPatientBtn2.addEventListener('click', function() {
            document.getElementById('patient-modal-overlay').style.display = 'flex';
            resetPatientForm();
        });
    }
    
    if (addFirstPatientBtn) {
        addFirstPatientBtn.addEventListener('click', function() {
            document.getElementById('patient-modal-overlay').style.display = 'flex';
            resetPatientForm();
        });
    }
    
    // Close patient modal
    document.getElementById('close-patient-modal')?.addEventListener('click', function() {
        document.getElementById('patient-modal-overlay').style.display = 'none';
    });
    
    document.getElementById('cancel-patient-btn')?.addEventListener('click', function() {
        document.getElementById('patient-modal-overlay').style.display = 'none';
    });
    
    // Close archive modal
    document.getElementById('close-archive-modal')?.addEventListener('click', function() {
        document.getElementById('archive-confirm-modal').style.display = 'none';
    });
    
    document.getElementById('cancel-archive-btn')?.addEventListener('click', function() {
        document.getElementById('archive-confirm-modal').style.display = 'none';
    });
    
    // Close consultation modal
    document.getElementById('close-consultation-modal')?.addEventListener('click', function() {
        document.getElementById('consultation-modal-overlay').style.display = 'none';
    });
    
    document.getElementById('cancel-consultation-btn')?.addEventListener('click', function() {
        document.getElementById('consultation-modal-overlay').style.display = 'none';
    });
    
    // Close patient history modal
    document.getElementById('close-history-modal')?.addEventListener('click', function() {
        document.getElementById('patient-history-modal').style.display = 'none';
    });
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.style.display = 'none';
            });
        }
    });
}

// Setup page navigation
function setupPageNavigation() {
    // Sidebar navigation
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            state.currentPage = page;
            showPage(page);
            
            // Update active states
            document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Clear search functionality
    window.clearSearch = function() {
        // Check if we're on patients page
        if (state.currentPage === 'patients') {
            // Remove search parameter
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        } else {
            // If not on patients page, go to patients page first
            showPage('patients');
        }
    };
    
    // Setup search form handling
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            // Ensure we stay on patients page when searching
            state.currentPage = 'patients';
            showPage('patients');
        });
    }
}

// Setup contact number formatting
function setupContactNumberFormatting() {
    const contactInput = document.getElementById('patient-contact');
    if (contactInput) {
        contactInput.addEventListener('input', function() {
            // Remove all non-numeric characters
            let value = this.value.replace(/\D/g, '');
            
            // Limit to 11 digits (Philippine mobile numbers)
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            // DON'T add dashes - just show plain numbers
            this.value = value;
        });
        
        // Also validate on blur
        contactInput.addEventListener('blur', function() {
            let value = this.value.replace(/\D/g, '');
            
            // Ensure it starts with 09 and is exactly 11 digits
            if (value.length > 0) {
                if (!value.startsWith('09')) {
                    alert('Philippine mobile numbers should start with "09"');
                    this.focus();
                } else if (value.length !== 11) {
                    alert('Please enter exactly 11 digits for the contact number');
                    this.focus();
                }
            }
        });
    }
}

// Show specific page
function showPage(pageId) {
    // Hide all pages
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    
    // Show selected page
    const pageElement = document.getElementById(`${pageId}-page`);
    if (pageElement) {
        pageElement.classList.add('active');
        state.currentPage = pageId;
        
        // If showing patients page, focus on search input
        if (pageId === 'patients') {
            setTimeout(() => {
                const searchInput = document.getElementById('patient-search');
                if (searchInput) {
                    searchInput.focus();
                    // If there's a search term, select it for easy editing
                    if (searchInput.value) {
                        searchInput.select();
                    }
                }
            }, 100);
        }
    }
    
    // Update active sidebar item
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.remove('active');
    });
    
    const activeItem = document.querySelector(`.sidebar-item[data-page="${pageId}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
    }
}

// Submit form helper
function submitForm(action, patientId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = API_BASE;
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    if (patientId) {
        const patientInput = document.createElement('input');
        patientInput.type = 'hidden';
        patientInput.name = 'patient_id';
        patientInput.value = patientId;
        form.appendChild(patientInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Reset patient form
function resetPatientForm() {
    const form = document.getElementById('patient-form');
    if (form) {
        form.reset();
        
        // Reset age field
        const ageInput = document.getElementById('patient-age');
        if (ageInput) ageInput.value = '';
        
        // Set today's date as max for DOB
        const today = new Date().toISOString().split('T')[0];
        const dobInput = document.getElementById('patient-dob');
        if (dobInput) {
            dobInput.setAttribute('max', today);
            dobInput.value = ''; // Clear DOB
        }
        
        // Set default date registered to today
        const registeredInput = document.getElementById('patient-registered');
        if (registeredInput) registeredInput.value = today;
        
        // Clear contact field
        const contactInput = document.getElementById('patient-contact');
        if (contactInput) contactInput.value = '';
    }
}

// Open consultation modal
function openConsultationModal(patientId, patientName) {
    state.currentPatientId = patientId;
    
    const modal = document.getElementById('consultation-modal-overlay');
    const modalTitle = document.getElementById('consultation-modal-title');
    
    if (modal && modalTitle) {
        modalTitle.innerHTML = `<i class="fas fa-stethoscope"></i> New Consultation for ${patientName}`;
        
        // Set patient ID in form
        const patientIdInput = document.getElementById('consultation-patient-id');
        if (patientIdInput) {
            patientIdInput.value = patientId;
        }
        
        // Set current datetime
        const now = new Date();
        const currentDateTime = now.toISOString().slice(0, 19).replace('T', ' ');
        const datetimeDisplay = document.getElementById('current-consultation-datetime');
        const datetimeInput = document.getElementById('consultation-datetime');
        
        if (datetimeDisplay) {
            datetimeDisplay.textContent = now.toLocaleDateString('en-US', { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        if (datetimeInput) {
            datetimeInput.value = currentDateTime;
        }
        
        // Reset consultation form
        const consultationForm = document.getElementById('consultation-form');
        if (consultationForm) consultationForm.reset();
        
        // Hide BMI display
        const bmiDisplay = document.getElementById('bmi-display');
        if (bmiDisplay) bmiDisplay.style.display = 'none';
        
        // Show modal
        modal.style.display = 'flex';
        
        // Focus on first field
        setTimeout(() => {
            const firstInput = modal.querySelector('input');
            if (firstInput) firstInput.focus();
        }, 100);
    }
}

// Load patient history
async function loadPatientHistory(patientId) {
    try {
        // Show loading state
        document.getElementById('history-patient-unique-id').textContent = 'Loading...';
        document.getElementById('history-patient-date-registered').textContent = 'Loading...';
        
        // Get patient info
        const response = await fetch(`${API_BASE}?get_patient_info=true&patient_id=${encodeURIComponent(patientId)}`);
        const data = await response.json();
        
        if (data.success && data.patient) {
            const patient = data.patient;
            state.currentPatientId = patientId;
            
            // Update patient info display
            updatePatientHistoryDisplay(patient);
            
            // Load consultation history
            await loadConsultationHistory(patientId);
            
            // Show history modal
            document.getElementById('patient-history-modal').style.display = 'flex';
        } else {
            showError('Patient not found');
        }
    } catch (error) {
        console.error('Error loading patient history:', error);
        showError('Failed to load patient information');
    }
}

// Update patient history display
function updatePatientHistoryDisplay(patient) {
    // Format dates
    const dob = new Date(patient.dob);
    const formattedDOB = dob.toLocaleDateString('en-US', { 
        month: 'long', 
        day: 'numeric', 
        year: 'numeric' 
    });
    
    const dateRegistered = new Date(patient.date_registered);
    const formattedDateRegistered = dateRegistered.toLocaleDateString('en-US', { 
        month: 'long', 
        day: 'numeric', 
        year: 'numeric' 
    });
    
    // Update modal title
    document.getElementById('patient-history-title').innerHTML = 
        `<i class="fas fa-history"></i> ${patient.full_name}'s Consultation History`;
    
    // Update patient info grid
    document.getElementById('history-patient-info-grid').innerHTML = `
        <div class="patient-info-item">
            <div class="patient-info-label">
                <i class="fas fa-user"></i> FULL NAME
            </div>
            <div class="patient-info-value">${patient.full_name}</div>
        </div>
        <div class="patient-info-item">
            <div class="patient-info-label">
                <i class="fas fa-home"></i> ADDRESS
            </div>
            <div class="patient-info-value">${patient.address}</div>
        </div>
        <div class="patient-info-item">
            <div class="patient-info-label">
                <i class="fas fa-calendar"></i> DATE OF BIRTH
            </div>
            <div class="patient-info-value">${formattedDOB}</div>
        </div>
        <div class="patient-info-item">
            <div class="patient-info-label">
                <i class="fas fa-birthday-cake"></i> AGE
            </div>
            <div class="patient-info-value">${patient.age} years</div>
        </div>
        <div class="patient-info-item">
            <div class="patient-info-label">
                <i class="fas fa-venus-mars"></i> SEX
            </div>
            <div class="patient-info-value">${patient.sex}</div>
        </div>
        <div class="patient-info-item">
            <div class="patient-info-label">
                <i class="fas fa-phone"></i> CONTACT NUMBER
            </div>
            <div class="patient-info-value">${patient.contact}</div>
        </div>
    `;
    
    // Update unique ID and date registered
    document.getElementById('history-patient-unique-id').textContent = patient.unique_id || 'N/A';
    document.getElementById('history-patient-date-registered').textContent = formattedDateRegistered;
}

// Load consultation history
async function loadConsultationHistory(patientId) {
    try {
        const response = await fetch(`${API_BASE}?get_consultations=true&patient_id=${encodeURIComponent(patientId)}`);
        const data = await response.json();
        
        const container = document.getElementById('consultation-cards-container');
        
        if (data.success && data.consultations && data.consultations.length > 0) {
            let html = '';
            
            data.consultations.forEach(consultation => {
                // Format consultation date
                const consultationDate = new Date(consultation.consultation_date);
                const formattedDate = consultationDate.toLocaleDateString('en-US', { 
                    weekday: 'long',
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Create vital signs HTML
                const vitals = [];
                if (consultation.temperature) vitals.push(`<div class="vital-item"><div class="vital-label">Temperature</div><div class="vital-value">${consultation.temperature}<span class="vital-unit">°C</span></div></div>`);
                if (consultation.pulse) vitals.push(`<div class="vital-item"><div class="vital-label">Pulse</div><div class="vital-value">${consultation.pulse}<span class="vital-unit">bpm</span></div></div>`);
                if (consultation.respiratory_rate) vitals.push(`<div class="vital-item"><div class="vital-label">Respiratory Rate</div><div class="vital-value">${consultation.respiratory_rate}</div></div>`);
                if (consultation.blood_pressure) vitals.push(`<div class="vital-item"><div class="vital-label">Blood Pressure</div><div class="vital-value">${consultation.blood_pressure}</div></div>`);
                if (consultation.oxygen_saturation) vitals.push(`<div class="vital-item"><div class="vital-label">O₂ Saturation</div><div class="vital-value">${consultation.oxygen_saturation}<span class="vital-unit">%</span></div></div>`);
                if (consultation.weight) vitals.push(`<div class="vital-item"><div class="vital-label">Weight</div><div class="vital-value">${consultation.weight}<span class="vital-unit">kg</span></div></div>`);
                if (consultation.height) vitals.push(`<div class="vital-item"><div class="vital-label">Height</div><div class="vital-value">${consultation.height}<span class="vital-unit">cm</span></div></div>`);
                
                // Add BMI if calculated
                let bmiHtml = '';
                if (consultation.bmi) {
                    let bmiCategory = '';
                    let bmiColor = '';
                    if (consultation.bmi < 18.5) {
                        bmiCategory = 'Underweight';
                        bmiColor = '#3498db';
                    } else if (consultation.bmi < 25) {
                        bmiCategory = 'Normal weight';
                        bmiColor = '#27ae60';
                    } else if (consultation.bmi < 30) {
                        bmiCategory = 'Overweight';
                        bmiColor = '#f39c12';
                    } else {
                        bmiCategory = 'Obese';
                        bmiColor = '#e74c3c';
                    }
                    bmiHtml = `<div class="vital-item"><div class="vital-label">BMI</div><div class="vital-value" style="color: ${bmiColor};">${consultation.bmi} <span style="font-size: 0.85rem; color: #666;">(${bmiCategory})</span></div></div>`;
                }
                
                html += `
                    <div class="consultation-card">
                        <div class="consultation-header">
                            <div class="consultation-date">
                                <i class="fas fa-calendar-alt"></i> ${formattedDate}
                            </div>
                            <div class="consultation-id">Consultation #${consultation.id}</div>
                        </div>
                        
                        <div class="vitals-grid">
                            ${vitals.join('')}
                            ${bmiHtml}
                        </div>
                        
                        <div class="medical-info">
                            <h4 class="medical-info-title">
                                <i class="fas fa-sticky-note"></i> Doctor's Notes
                            </h4>
                            <div class="doctor-notes">
                                ${consultation.doctor_notes.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="no-consultations">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No Consultation History</h3>
                    <p>This patient has no recorded consultations yet.</p>
                    <button class="add-consultation-btn" id="add-first-consultation" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Add First Consultation
                    </button>
                </div>
            `;
            
            // Add event listener to the "Add First Consultation" button
            document.getElementById('add-first-consultation')?.addEventListener('click', function() {
                const patientName = document.querySelector('#patient-history-title')?.textContent?.replace("'s Consultation History", '') || 'Patient';
                openConsultationModal(state.currentPatientId, patientName);
                document.getElementById('patient-history-modal').style.display = 'none';
            });
        }
    } catch (error) {
        console.error('Error loading consultation history:', error);
        const container = document.getElementById('consultation-cards-container');
        container.innerHTML = `
            <div class="no-consultations">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Error Loading History</h3>
                <p>Failed to load consultation history. Please try again.</p>
            </div>
        `;
    }
}

// Show error message
function showError(message) {
    console.error('Error:', message);
    alert('Error: ' + message);
}

// Show success message
function showSuccess(message) {
    console.log('Success:', message);
    // You could implement a toast notification here
}

// Auto-calculate age on page load if DOB is filled
window.addEventListener('load', function() {
    const dobInput = document.getElementById('patient-dob');
    const ageInput = document.getElementById('patient-age');
    
    if (dobInput && dobInput.value && ageInput) {
        dobInput.dispatchEvent(new Event('change'));
    }
});

// Setup BMI calculation
function setupBMICalculation() {
    const weightInput = document.getElementById('consultation-weight');
    const heightInput = document.getElementById('consultation-height');
    const bmiDisplay = document.getElementById('bmi-display');
    const bmiValue = document.getElementById('bmi-value');
    const bmiCategory = document.getElementById('bmi-category');
    
    if (weightInput && heightInput && bmiDisplay && bmiValue && bmiCategory) {
        function calculateBMI() {
            const weight = parseFloat(weightInput.value);
            const height = parseFloat(heightInput.value);
            
            if (weight && height && height > 0) {
                // Convert height from cm to meters
                const heightM = height / 100;
                const bmi = weight / (heightM * heightM);
                const roundedBMI = bmi.toFixed(2);
                
                bmiValue.textContent = roundedBMI;
                bmiDisplay.style.display = 'block';
                
                // Determine BMI category
                let category = '';
                let color = '';
                
                if (bmi < 18.5) {
                    category = 'Underweight';
                    color = '#3498db';
                } else if (bmi < 25) {
                    category = 'Normal weight';
                    color = '#27ae60';
                } else if (bmi < 30) {
                    category = 'Overweight';
                    color = '#f39c12';
                } else {
                    category = 'Obese';
                    color = '#e74c3c';
                }
                
                bmiCategory.textContent = category;
                bmiCategory.style.color = color;
            } else {
                bmiDisplay.style.display = 'none';
            }
        }
        
        weightInput.addEventListener('input', calculateBMI);
        heightInput.addEventListener('input', calculateBMI);
    }
}

// Initialize charts
function initializeCharts() {
    // Initialize gender chart
    const genderCtx = document.getElementById('gender-chart');
    if (genderCtx) {
        try {
            // These variables should be defined in your PHP file
            // If they're not available, use default values
            const maleCount = window.maleCount || 0;
            const femaleCount = window.femaleCount || 0;
            const otherCount = window.otherCount || 0;
            
            // Only create chart if we have data
            if (maleCount + femaleCount + otherCount > 0) {
                new Chart(genderCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Male', 'Female', 'Other'],
                        datasets: [{
                            data: [maleCount, femaleCount, otherCount],
                            backgroundColor: [
                                '#3498db',
                                '#e74c3c',
                                '#f39c12'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            } else {
                // Show a message if no data
                genderCtx.closest('.chart-card').innerHTML += `
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <i class="fas fa-info-circle"></i> No gender data available
                    </div>
                `;
            }
        } catch (error) {
            console.error('Failed to initialize gender chart:', error);
            genderCtx.closest('.chart-card').innerHTML += `
                <div style="text-align: center; padding: 20px; color: #666;">
                    <i class="fas fa-exclamation-triangle"></i> Failed to load gender chart
                </div>
            `;
        }
    }
    
    // Initialize age distribution chart
    const ageCtx = document.getElementById('age-chart');
    if (ageCtx) {
        try {
            // Check if age distribution data is available
            if (window.ageDistribution && Array.isArray(window.ageDistribution)) {
                const ageLabels = window.ageLabels || ['0-17', '18-30', '31-45', '46-60', '60+'];
                
                new Chart(ageCtx, {
                    type: 'bar',
                    data: {
                        labels: ageLabels,
                        datasets: [{
                            label: 'Number of Patients',
                            data: window.ageDistribution,
                            backgroundColor: [
                                '#3498db',
                                '#2ecc71',
                                '#f39c12',
                                '#e74c3c',
                                '#9b59b6'
                            ],
                            borderColor: [
                                '#2980b9',
                                '#27ae60',
                                '#d35400',
                                '#c0392b',
                                '#8e44ad'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Patients by Age Group'
                            }
                        }
                    }
                });
            } else {
                // Show a message if no data
                ageCtx.closest('.chart-card').innerHTML += `
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <i class="fas fa-info-circle"></i> No age distribution data available
                    </div>
                `;
            }
        } catch (error) {
            console.error('Failed to initialize age chart:', error);
            ageCtx.closest('.chart-card').innerHTML += `
                <div style="text-align: center; padding: 20px; color: #666;">
                    <i class="fas fa-exclamation-triangle"></i> Failed to load age chart
                </div>
            `;
        }
    }
    
    // Initialize barangay distribution chart
    const barangayCtx = document.getElementById('barangay-chart');
    if (barangayCtx) {
        try {
            // Check if barangay distribution data is available
            if (window.barangayLabels && window.barangayData && 
                Array.isArray(window.barangayLabels) && Array.isArray(window.barangayData)) {
                
                new Chart(barangayCtx, {
                    type: 'bar',
                    data: {
                        labels: window.barangayLabels,
                        datasets: [{
                            label: 'Number of Patients',
                            data: window.barangayData,
                            backgroundColor: 'rgba(52, 152, 219, 0.7)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y', // Horizontal bar chart
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Patients by Barangay'
                            }
                        }
                    }
                });
            } else {
                // Show a message if no data
                barangayCtx.closest('.chart-card').innerHTML += `
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <i class="fas fa-info-circle"></i> No barangay data available
                    </div>
                `;
            }
        } catch (error) {
            console.error('Failed to initialize barangay chart:', error);
            barangayCtx.closest('.chart-card').innerHTML += `
                <div style="text-align: center; padding: 20px; color: #666;">
                    <i class="fas fa-exclamation-triangle"></i> Failed to load barangay chart
                </div>
            `;
        }
    }
}

// Make functions available globally
window.clearSearch = window.clearSearch || function() {
    // Check if we're on patients page
    if (state.currentPage === 'patients') {
        // Remove search parameter
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        window.location.href = url.toString();
    } else {
        // If not on patients page, go to patients page first
        showPage('patients');
    }
};

// Helper function to submit forms
window.submitForm = submitForm;

// Helper function to load patient history
window.loadPatientHistory = loadPatientHistory;