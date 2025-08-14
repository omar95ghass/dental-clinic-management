// Receptionist Dashboard JavaScript
// Handles all functionality for the receptionist interface

// Global variables
let currentPatient = null;
let patients = [];

// Initialize the dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    loadPatients();
    setupEventListeners();
});

// Initialize dashboard components
function initializeDashboard() {
    console.log('Receptionist Dashboard initialized');
    
    // Set up tab switching
    setupTabSwitching();
    
    // Load initial data
    loadPatients();
}

// Setup event listeners
function setupEventListeners() {
    // Add patient button
    const addPatientBtn = document.getElementById('addPatientBtn');
    if (addPatientBtn) {
        addPatientBtn.addEventListener('click', showAddPatientModal);
    }
    
    // Search functionality
    const searchInput = document.getElementById('patientSearch');
    if (searchInput) {
        searchInput.addEventListener('input', handlePatientSearch);
    }
    
    // Modal close buttons
    const closeButtons = document.querySelectorAll('.close-modal');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', closeModal);
    });
    
    // Form submissions
    const addPatientForm = document.getElementById('addPatientForm');
    if (addPatientForm) {
        addPatientForm.addEventListener('submit', handleAddPatient);
    }
}

// Setup tab switching functionality
function setupTabSwitching() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabButtons.forEach(btn => btn.classList.remove('active-tab'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active-tab');
            const targetContent = document.getElementById(targetTab);
            if (targetContent) {
                targetContent.classList.add('active');
            }
            
            // Load specific data based on tab
            switch(targetTab) {
                case 'patients-tab':
                    loadPatients();
                    break;
                case 'appointments-tab':
                    loadAppointments();
                    break;
                case 'settings-tab':
                    loadSettings();
                    break;
            }
        });
    });
}

// Load patients from API
async function loadPatients() {
    try {
        const response = await fetch('../api/patients.php');
        const data = await response.json();
        
        if (Array.isArray(data)) {
            patients = data;
            displayPatients(patients);
        } else {
            console.error('Invalid patients data received:', data);
            displayPatients([]);
        }
    } catch (error) {
        console.error('Error loading patients:', error);
        showNotification('خطأ في تحميل بيانات المرضى', 'error');
        displayPatients([]);
    }
}

// Display patients in the table
function displayPatients(patientsData) {
    const patientsTableBody = document.getElementById('patientsTableBody');
    if (!patientsTableBody) return;
    
    if (patientsData.length === 0) {
        patientsTableBody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-8 text-gray-400">
                    لا يوجد مرضى حالياً
                </td>
            </tr>
        `;
        return;
    }
    
    patientsTableBody.innerHTML = patientsData.map(patient => `
        <tr class="hover:bg-white hover:bg-opacity-10 transition-colors cursor-pointer" onclick="selectPatient(${patient.id})">
            <td class="px-6 py-4">${patient.first_name} ${patient.father_name} ${patient.last_name}</td>
            <td class="px-6 py-4">${patient.phone_number}</td>
            <td class="px-6 py-4">${patient.address || 'غير محدد'}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 rounded-full text-xs ${patient.visit_status === 'first_time' ? 'bg-green-500' : 'bg-blue-500'}">
                    ${patient.visit_status === 'first_time' ? 'أول مرة' : 'مراجعة'}
                </span>
            </td>
            <td class="px-6 py-4">
                <div class="flex space-x-2">
                    <button onclick="editPatient(${patient.id})" class="text-blue-400 hover:text-blue-300">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deletePatient(${patient.id})" class="text-red-400 hover:text-red-300">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button onclick="showPatientActions(${patient.id})" class="text-green-400 hover:text-green-300">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Handle patient search
function handlePatientSearch(event) {
    const searchTerm = event.target.value.toLowerCase();
    
    if (searchTerm === '') {
        displayPatients(patients);
        return;
    }
    
    const filteredPatients = patients.filter(patient => 
        patient.first_name.toLowerCase().includes(searchTerm) ||
        patient.father_name.toLowerCase().includes(searchTerm) ||
        patient.last_name.toLowerCase().includes(searchTerm) ||
        patient.phone_number.includes(searchTerm)
    );
    
    displayPatients(filteredPatients);
}

// Show add patient modal
function showAddPatientModal() {
    const modal = document.getElementById('addPatientModal');
    if (modal) {
        modal.classList.remove('hidden');
        // Reset form
        const form = document.getElementById('addPatientForm');
        if (form) {
            form.reset();
        }
    }
}

// Handle add patient form submission
async function handleAddPatient(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const patientData = {
        first_name: formData.get('first_name'),
        father_name: formData.get('father_name'),
        last_name: formData.get('last_name'),
        date_of_birth: formData.get('date_of_birth'),
        phone_number: formData.get('phone_number'),
        address: formData.get('address'),
        visit_status: formData.get('visit_status')
    };
    
    try {
        const response = await fetch('../api/patients.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(patientData)
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showNotification('تم إضافة المريض بنجاح', 'success');
            closeModal();
            loadPatients(); // Reload patients list
        } else {
            showNotification(result.message || 'خطأ في إضافة المريض', 'error');
        }
    } catch (error) {
        console.error('Error adding patient:', error);
        showNotification('خطأ في إضافة المريض', 'error');
    }
}

// Select patient
function selectPatient(patientId) {
    currentPatient = patients.find(p => p.id === patientId);
    if (currentPatient) {
        highlightSelectedPatient(patientId);
        showNotification(`تم اختيار المريض: ${currentPatient.first_name} ${currentPatient.father_name} ${currentPatient.last_name}`, 'info');
    }
}

// Highlight selected patient
function highlightSelectedPatient(patientId) {
    // Remove previous selection
    const previousSelected = document.querySelector('.selected-patient');
    if (previousSelected) {
        previousSelected.classList.remove('selected-patient');
    }
    
    // Add selection to current patient
    const patientRow = document.querySelector(`tr[onclick="selectPatient(${patientId})"]`);
    if (patientRow) {
        patientRow.classList.add('selected-patient');
    }
}

// Edit patient
function editPatient(patientId) {
    const patient = patients.find(p => p.id === patientId);
    if (patient) {
        // Populate edit form with patient data
        populateEditForm(patient);
        showEditPatientModal();
    }
}

// Delete patient
async function deletePatient(patientId) {
    if (!confirm('هل أنت متأكد من حذف هذا المريض؟')) {
        return;
    }
    
    try {
        const response = await fetch(`../api/patients.php?id=${patientId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showNotification('تم حذف المريض بنجاح', 'success');
            loadPatients(); // Reload patients list
        } else {
            showNotification(result.message || 'خطأ في حذف المريض', 'error');
        }
    } catch (error) {
        console.error('Error deleting patient:', error);
        showNotification('خطأ في حذف المريض', 'error');
    }
}

// Show patient actions (payments, appointments, prescriptions)
function showPatientActions(patientId) {
    const patient = patients.find(p => p.id === patientId);
    if (patient) {
        currentPatient = patient;
        showPatientActionsModal();
    }
}

// Show patient actions modal
function showPatientActionsModal() {
    const modal = document.getElementById('patientActionsModal');
    if (modal) {
        modal.classList.remove('hidden');
        
        // Update modal content with patient info
        const patientNameElement = document.getElementById('selectedPatientName');
        if (patientNameElement && currentPatient) {
            patientNameElement.textContent = `${currentPatient.first_name} ${currentPatient.father_name} ${currentPatient.last_name}`;
        }
        
        // Load patient balance
        loadPatientBalance();
    }
}

// Load patient balance
async function loadPatientBalance() {
    if (!currentPatient) return;
    
    try {
        const response = await fetch(`../api/financial.php?action=get_patient_balance&patient_id=${currentPatient.id}`);
        const data = await response.json();
        
        const balanceElement = document.getElementById('patientBalance');
        if (balanceElement) {
            balanceElement.textContent = data.balance || '0';
        }
    } catch (error) {
        console.error('Error loading patient balance:', error);
    }
}

// Add payment functionality
async function addPayment() {
    if (!currentPatient) {
        showNotification('يرجى اختيار مريض أولاً', 'warning');
        return;
    }
    
    // Show payment modal
    showPaymentModal();
}

// Show payment modal
function showPaymentModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fixed inset-0 flex items-center justify-center z-50 modal-overlay';
    modal.innerHTML = `
        <div class="glass-card p-8 w-full max-w-lg mx-4 text-right relative">
            <h3 class="text-2xl font-bold mb-6">إضافة دفعة</h3>
            <button class="close-modal absolute top-4 left-4 text-gray-300 hover:text-white transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <form id="paymentForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">المريض</label>
                    <input type="text" value="${currentPatient.first_name} ${currentPatient.father_name} ${currentPatient.last_name}" class="w-full p-3 rounded-lg glass-card text-white" readonly>
                </div>
                
                <div>
                    <label for="paymentAmount" class="block text-sm font-medium mb-1">المبلغ (ل.س)</label>
                    <input type="number" id="paymentAmount" name="amount" class="w-full p-3 rounded-lg glass-card text-white" required min="0" step="0.01">
                </div>
                
                <div>
                    <label for="paymentMethod" class="block text-sm font-medium mb-1">طريقة الدفع</label>
                    <select id="paymentMethod" name="payment_method" class="w-full p-3 rounded-lg glass-card text-white">
                        <option value="cash">نقداً</option>
                        <option value="card">بطاقة ائتمان</option>
                        <option value="bank_transfer">تحويل بنكي</option>
                        <option value="check">شيك</option>
                    </select>
                </div>
                
                <div>
                    <label for="paymentNotes" class="block text-sm font-medium mb-1">ملاحظات</label>
                    <textarea id="paymentNotes" name="notes" rows="3" class="w-full p-3 rounded-lg glass-card text-white"></textarea>
                </div>
                
                <div class="flex space-x-4 space-x-reverse">
                    <button type="submit" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-lg transition-all">
                        إضافة الدفعة
                    </button>
                    <button type="button" onclick="closeAccountFull()" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-lg transition-all">
                        إغلاق الحساب
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.querySelector('#paymentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const paymentData = {
            patient_id: currentPatient.id,
            amount: formData.get('amount'),
            payment_method: formData.get('payment_method'),
            notes: formData.get('notes')
        };
        
        try {
            const response = await fetch('../api/financial.php?action=add_payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(paymentData)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showNotification('تم إضافة الدفعة بنجاح', 'success');
                modal.remove();
                loadPatientBalance(); // Refresh balance
            } else {
                showNotification(result.error || 'خطأ في إضافة الدفعة', 'error');
            }
        } catch (error) {
            console.error('Error adding payment:', error);
            showNotification('خطأ في إضافة الدفعة', 'error');
        }
    });
}

// Close account functionality
async function closeAccountFull() {
    if (!currentPatient) {
        showNotification('يرجى اختيار مريض أولاً', 'warning');
        return;
    }
    
    if (!confirm('هل أنت متأكد من إغلاق الحساب بالكامل؟ سيتم دفع كامل المبلغ المتبقي.')) {
        return;
    }
    
    try {
        const response = await fetch('../api/financial.php?action=close_account', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                patient_id: currentPatient.id,
                payment_method: 'cash' // Default to cash
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showNotification(`تم إغلاق الحساب بنجاح. المبلغ المدفوع: ${result.amount_paid} ل.س`, 'success');
            loadPatientBalance(); // Refresh balance
            
            // Close any open modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.remove());
        } else {
            showNotification(result.error || 'خطأ في إغلاق الحساب', 'error');
        }
    } catch (error) {
        console.error('Error closing account:', error);
        showNotification('خطأ في إغلاق الحساب', 'error');
    }
}

// Load appointments
async function loadAppointments() {
    try {
        const response = await fetch('../api/appointments.php');
        const data = await response.json();
        
        displayAppointments(data);
    } catch (error) {
        console.error('Error loading appointments:', error);
        showNotification('خطأ في تحميل المواعيد', 'error');
    }
}

// Display appointments
function displayAppointments(appointments) {
    const appointmentsContainer = document.getElementById('appointmentsContainer');
    if (!appointmentsContainer) return;
    
    if (!Array.isArray(appointments) || appointments.length === 0) {
        appointmentsContainer.innerHTML = '<p class="text-center text-gray-400">لا توجد مواعيد</p>';
        return;
    }
    
    appointmentsContainer.innerHTML = appointments.map(appointment => `
        <div class="glass-card p-4 mb-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-semibold">${appointment.patient_name}</h3>
                    <p class="text-sm text-gray-300">${appointment.appointment_date} - ${appointment.appointment_time}</p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="editAppointment(${appointment.id})" class="text-blue-400 hover:text-blue-300">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteAppointment(${appointment.id})" class="text-red-400 hover:text-red-300">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// Close modal
function closeModal() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.add('hidden');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 left-4 p-4 rounded-lg text-white z-50 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Load settings
function loadSettings() {
    console.log('Loading settings...');
    // This will be implemented in the settings phase
}

// Add appointment functionality
async function addAppointment() {
    if (!currentPatient) {
        showNotification('يرجى اختيار مريض أولاً', 'warning');
        return;
    }
    
    // Show appointment modal
    showAppointmentModal();
}

// Show appointment modal
function showAppointmentModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fixed inset-0 flex items-center justify-center z-50 modal-overlay';
    modal.innerHTML = `
        <div class="glass-card p-8 w-full max-w-lg mx-4 text-right relative">
            <h3 class="text-2xl font-bold mb-6">إضافة موعد</h3>
            <button class="close-modal absolute top-4 left-4 text-gray-300 hover:text-white transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <form id="appointmentForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">المريض</label>
                    <input type="text" value="${currentPatient.first_name} ${currentPatient.father_name} ${currentPatient.last_name}" class="w-full p-3 rounded-lg glass-card text-white" readonly>
                </div>
                
                <div>
                    <label for="appointmentDate" class="block text-sm font-medium mb-1">تاريخ الموعد</label>
                    <input type="date" id="appointmentDate" name="appointment_date" class="w-full p-3 rounded-lg glass-card text-white" required min="${new Date().toISOString().split('T')[0]}">
                </div>
                
                <div>
                    <label for="appointmentTime" class="block text-sm font-medium mb-1">وقت الموعد</label>
                    <select id="appointmentTime" name="appointment_time" class="w-full p-3 rounded-lg glass-card text-white" required>
                        <option value="">اختر الوقت</option>
                        <option value="09:00:00">09:00 صباحاً</option>
                        <option value="09:30:00">09:30 صباحاً</option>
                        <option value="10:00:00">10:00 صباحاً</option>
                        <option value="10:30:00">10:30 صباحاً</option>
                        <option value="11:00:00">11:00 صباحاً</option>
                        <option value="11:30:00">11:30 صباحاً</option>
                        <option value="12:00:00">12:00 ظهراً</option>
                        <option value="14:00:00">02:00 مساءً</option>
                        <option value="14:30:00">02:30 مساءً</option>
                        <option value="15:00:00">03:00 مساءً</option>
                        <option value="15:30:00">03:30 مساءً</option>
                        <option value="16:00:00">04:00 مساءً</option>
                        <option value="16:30:00">04:30 مساءً</option>
                        <option value="17:00:00">05:00 مساءً</option>
                    </select>
                </div>
                
                <div>
                    <label for="appointmentNotes" class="block text-sm font-medium mb-1">ملاحظات</label>
                    <textarea id="appointmentNotes" name="notes" rows="3" class="w-full p-3 rounded-lg glass-card text-white" placeholder="ملاحظات إضافية حول الموعد"></textarea>
                </div>
                
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-lg transition-all">
                    حجز الموعد
                </button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.querySelector('#appointmentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const appointmentData = {
            patient_id: currentPatient.id,
            appointment_date: formData.get('appointment_date'),
            appointment_time: formData.get('appointment_time'),
            notes: formData.get('notes')
        };
        
        try {
            const response = await fetch('../api/appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(appointmentData)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showNotification('تم حجز الموعد بنجاح', 'success');
                modal.remove();
                loadAppointments(); // Refresh appointments if on appointments tab
            } else {
                showNotification(result.error || 'خطأ في حجز الموعد', 'error');
            }
        } catch (error) {
            console.error('Error adding appointment:', error);
            showNotification('خطأ في حجز الموعد', 'error');
        }
    });
}

function printPrescription() {
    if (!currentPatient) {
        showNotification('يرجى اختيار مريض أولاً', 'warning');
        return;
    }
    // Implementation will be added in prescription printing phase
    showNotification('ميزة طباعة الوصفات ستكون متاحة قريباً', 'info');
}

