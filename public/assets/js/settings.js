// Settings management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize settings interface
    initializeSettings();
});

let currentSettingsTab = 'users';

function initializeSettings() {
    // Load initial tab
    showSettingsTab('users');
    
    // Set up tab switching
    const tabButtons = document.querySelectorAll('.settings-tab-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const tabId = btn.dataset.tab;
            showSettingsTab(tabId);
        });
    });
}

function showSettingsTab(tabId) {
    currentSettingsTab = tabId;
    
    // Update active tab button
    document.querySelectorAll('.settings-tab-btn').forEach(btn => {
        btn.classList.remove('active-tab');
    });
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active-tab');
    
    // Show appropriate content
    const contentArea = document.getElementById('settingsContent');
    
    switch(tabId) {
        case 'users':
            loadUsersTab(contentArea);
            break;
        case 'treatments':
            loadTreatmentsTab(contentArea);
            break;
        case 'drugs':
            loadDrugsTab(contentArea);
            break;
        case 'clinic':
            loadClinicTab(contentArea);
            break;
        case 'system':
            loadSystemTab(contentArea);
            break;
        default:
            contentArea.innerHTML = '<p class="text-center text-gray-400">التبويب غير موجود</p>';
    }
}

// Users management
async function loadUsersTab(container) {
    container.innerHTML = `
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold">إدارة المستخدمين</h3>
            <button onclick="showAddUserModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition-all">
                <i class="fas fa-plus ml-2"></i>
                إضافة مستخدم
            </button>
        </div>
        
        <div class="glass-card p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-right">
                    <thead>
                        <tr class="text-gray-300 border-b-2 border-white/20">
                            <th class="py-3 px-4 font-semibold">اسم المستخدم</th>
                            <th class="py-3 px-4 font-semibold">الدور</th>
                            <th class="py-3 px-4 font-semibold">تاريخ الإنشاء</th>
                            <th class="py-3 px-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr><td colspan="4" class="text-center py-4">جاري التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    await loadUsers();
}

async function loadUsers() {
    try {
        const response = await fetch('../api/settings.php?action=get_users');
        const users = await response.json();
        
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = '';
        
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">لا يوجد مستخدمين</td></tr>';
            return;
        }
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.className = 'border-b border-white/10 hover:bg-white/10 transition-colors';
            row.innerHTML = `
                <td class="py-3 px-4">${user.username}</td>
                <td class="py-3 px-4">${user.role === 'admin' ? 'مدير' : user.role === 'doctor' ? 'طبيب' : 'سكرتيرة'}</td>
                <td class="py-3 px-4">${new Date(user.created_at).toLocaleDateString('ar-SA')}</td>
                <td class="py-3 px-4">
                    <button onclick="editUser(${user.id})" class="text-blue-400 hover:text-blue-200 ml-2">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteUser(${user.id}, '${user.username}')" class="text-red-400 hover:text-red-200">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Error loading users:', error);
        showNotification('خطأ في تحميل المستخدمين', 'error');
    }
}

function showAddUserModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fixed inset-0 flex items-center justify-center z-50 modal-overlay';
    modal.innerHTML = `
        <div class="glass-card p-8 w-full max-w-lg mx-4 text-right relative">
            <h3 class="text-2xl font-bold mb-6">إضافة مستخدم جديد</h3>
            <button class="close-modal absolute top-4 left-4 text-gray-300 hover:text-white transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <form id="addUserForm" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium mb-1">اسم المستخدم</label>
                    <input type="text" id="username" name="username" class="w-full p-3 rounded-lg glass-card text-white" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium mb-1">كلمة المرور</label>
                    <input type="password" id="password" name="password" class="w-full p-3 rounded-lg glass-card text-white" required>
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium mb-1">الدور</label>
                    <select id="role" name="role" class="w-full p-3 rounded-lg glass-card text-white" required>
                        <option value="">اختر الدور</option>
                        <option value="admin">مدير</option>
                        <option value="doctor">طبيب</option>
                        <option value="receptionist">سكرتيرة</option>
                    </select>
                </div>
                
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-lg transition-all">
                    إضافة المستخدم
                </button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add event listeners
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.remove();
    });
    
    modal.querySelector('#addUserForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const userData = {
            username: formData.get('username'),
            password: formData.get('password'),
            role: formData.get('role')
        };
        
        try {
            const response = await fetch('../api/settings.php?action=add_user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showNotification('تم إضافة المستخدم بنجاح', 'success');
                modal.remove();
                loadUsers(); // Refresh users list
            } else {
                showNotification(result.error || 'خطأ في إضافة المستخدم', 'error');
            }
        } catch (error) {
            console.error('Error adding user:', error);
            showNotification('خطأ في إضافة المستخدم', 'error');
        }
    });
}

async function deleteUser(userId, username) {
    if (!confirm(`هل أنت متأكد من حذف المستخدم "${username}"؟`)) {
        return;
    }
    
    try {
        const response = await fetch(`../api/settings.php?action=delete_user&id=${userId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showNotification('تم حذف المستخدم بنجاح', 'success');
            loadUsers(); // Refresh users list
        } else {
            showNotification(result.error || 'خطأ في حذف المستخدم', 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showNotification('خطأ في حذف المستخدم', 'error');
    }
}

// Treatment types management
async function loadTreatmentsTab(container) {
    container.innerHTML = `
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold">إدارة أنواع المعالجات</h3>
            <button onclick="showAddTreatmentModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition-all">
                <i class="fas fa-plus ml-2"></i>
                إضافة نوع معالجة
            </button>
        </div>
        
        <div class="glass-card p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-right">
                    <thead>
                        <tr class="text-gray-300 border-b-2 border-white/20">
                            <th class="py-3 px-4 font-semibold">اسم المعالجة</th>
                            <th class="py-3 px-4 font-semibold">الوصف</th>
                            <th class="py-3 px-4 font-semibold">التكلفة الافتراضية</th>
                            <th class="py-3 px-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="treatmentsTableBody">
                        <tr><td colspan="4" class="text-center py-4">جاري التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    await loadTreatmentTypes();
}

async function loadTreatmentTypes() {
    try {
        const response = await fetch('../api/settings.php?action=get_treatment_types');
        const treatments = await response.json();
        
        const tbody = document.getElementById('treatmentsTableBody');
        tbody.innerHTML = '';
        
        if (treatments.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">لا يوجد أنواع معالجات</td></tr>';
            return;
        }
        
        treatments.forEach(treatment => {
            const row = document.createElement('tr');
            row.className = 'border-b border-white/10 hover:bg-white/10 transition-colors';
            row.innerHTML = `
                <td class="py-3 px-4">${treatment.name}</td>
                <td class="py-3 px-4">${treatment.description || 'لا يوجد'}</td>
                <td class="py-3 px-4">${treatment.default_cost} ل.س</td>
                <td class="py-3 px-4">
                    <button onclick="editTreatment(${treatment.id})" class="text-blue-400 hover:text-blue-200 ml-2">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteTreatment(${treatment.id}, '${treatment.name}')" class="text-red-400 hover:text-red-200">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Error loading treatment types:', error);
        showNotification('خطأ في تحميل أنواع المعالجات', 'error');
    }
}

// Drugs management
async function loadDrugsTab(container) {
    container.innerHTML = `
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold">إدارة الأدوية</h3>
            <button onclick="showAddDrugModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition-all">
                <i class="fas fa-plus ml-2"></i>
                إضافة دواء
            </button>
        </div>
        
        <div class="glass-card p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full text-right">
                    <thead>
                        <tr class="text-gray-300 border-b-2 border-white/20">
                            <th class="py-3 px-4 font-semibold">اسم الدواء</th>
                            <th class="py-3 px-4 font-semibold">خيارات الجرعة</th>
                            <th class="py-3 px-4 font-semibold">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="drugsTableBody">
                        <tr><td colspan="3" class="text-center py-4">جاري التحميل...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    await loadDrugs();
}

async function loadDrugs() {
    try {
        const response = await fetch('../api/settings.php?action=get_drugs');
        const drugs = await response.json();
        
        const tbody = document.getElementById('drugsTableBody');
        tbody.innerHTML = '';
        
        if (drugs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4">لا يوجد أدوية</td></tr>';
            return;
        }
        
        drugs.forEach(drug => {
            const dosageOptions = drug.dosage_options ? JSON.parse(drug.dosage_options).join(', ') : 'لا يوجد';
            
            const row = document.createElement('tr');
            row.className = 'border-b border-white/10 hover:bg-white/10 transition-colors';
            row.innerHTML = `
                <td class="py-3 px-4">${drug.name}</td>
                <td class="py-3 px-4">${dosageOptions}</td>
                <td class="py-3 px-4">
                    <button onclick="editDrug(${drug.id})" class="text-blue-400 hover:text-blue-200 ml-2">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteDrug(${drug.id}, '${drug.name}')" class="text-red-400 hover:text-red-200">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
    } catch (error) {
        console.error('Error loading drugs:', error);
        showNotification('خطأ في تحميل الأدوية', 'error');
    }
}

// Clinic info management
async function loadClinicTab(container) {
    container.innerHTML = `
        <div class="mb-6">
            <h3 class="text-2xl font-bold">معلومات العيادة</h3>
        </div>
        
        <div class="glass-card p-6">
            <form id="clinicInfoForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="clinicName" class="block text-sm font-medium mb-1">اسم العيادة</label>
                        <input type="text" id="clinicName" name="name" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                    
                    <div>
                        <label for="doctorName" class="block text-sm font-medium mb-1">اسم الطبيب</label>
                        <input type="text" id="doctorName" name="doctor_name" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                    
                    <div>
                        <label for="specialization" class="block text-sm font-medium mb-1">التخصص</label>
                        <input type="text" id="specialization" name="specialization" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                    
                    <div>
                        <label for="clinicPhone" class="block text-sm font-medium mb-1">رقم الهاتف</label>
                        <input type="text" id="clinicPhone" name="phone" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                    
                    <div>
                        <label for="clinicEmail" class="block text-sm font-medium mb-1">البريد الإلكتروني</label>
                        <input type="email" id="clinicEmail" name="email" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                </div>
                
                <div>
                    <label for="clinicAddress" class="block text-sm font-medium mb-1">العنوان</label>
                    <textarea id="clinicAddress" name="address" rows="3" class="w-full p-3 rounded-lg glass-card text-white"></textarea>
                </div>
                
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition-all">
                    حفظ معلومات العيادة
                </button>
            </form>
        </div>
    `;
    
    await loadClinicInfo();
}

async function loadClinicInfo() {
    try {
        const response = await fetch('../api/settings.php?action=get_clinic_info');
        const info = await response.json();
        
        // Fill form with clinic info
        document.getElementById('clinicName').value = info.name || '';
        document.getElementById('doctorName').value = info.doctor_name || '';
        document.getElementById('specialization').value = info.specialization || '';
        document.getElementById('clinicPhone').value = info.phone || '';
        document.getElementById('clinicEmail').value = info.email || '';
        document.getElementById('clinicAddress').value = info.address || '';
        
        // Add form submit handler
        document.getElementById('clinicInfoForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const clinicData = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../api/settings.php?action=update_clinic_info', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(clinicData)
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    showNotification('تم حفظ معلومات العيادة بنجاح', 'success');
                } else {
                    showNotification(result.error || 'خطأ في حفظ معلومات العيادة', 'error');
                }
            } catch (error) {
                console.error('Error saving clinic info:', error);
                showNotification('خطأ في حفظ معلومات العيادة', 'error');
            }
        });
        
    } catch (error) {
        console.error('Error loading clinic info:', error);
        showNotification('خطأ في تحميل معلومات العيادة', 'error');
    }
}

// System settings management
async function loadSystemTab(container) {
    container.innerHTML = `
        <div class="mb-6">
            <h3 class="text-2xl font-bold">إعدادات النظام</h3>
        </div>
        
        <div class="glass-card p-6">
            <form id="systemSettingsForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="appointmentDuration" class="block text-sm font-medium mb-1">مدة الموعد (بالدقائق)</label>
                        <input type="number" id="appointmentDuration" name="appointment_duration" class="w-full p-3 rounded-lg glass-card text-white" min="15" max="120">
                    </div>
                    
                    <div>
                        <label for="workingHoursStart" class="block text-sm font-medium mb-1">بداية ساعات العمل</label>
                        <input type="time" id="workingHoursStart" name="working_hours_start" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                    
                    <div>
                        <label for="workingHoursEnd" class="block text-sm font-medium mb-1">نهاية ساعات العمل</label>
                        <input type="time" id="workingHoursEnd" name="working_hours_end" class="w-full p-3 rounded-lg glass-card text-white">
                    </div>
                    
                    <div>
                        <label for="currency" class="block text-sm font-medium mb-1">العملة</label>
                        <select id="currency" name="currency" class="w-full p-3 rounded-lg glass-card text-white">
                            <option value="SYP">ليرة سورية (SYP)</option>
                            <option value="USD">دولار أمريكي (USD)</option>
                            <option value="EUR">يورو (EUR)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sessionTimeout" class="block text-sm font-medium mb-1">انتهاء الجلسة (بالدقائق)</label>
                        <input type="number" id="sessionTimeout" name="session_timeout" class="w-full p-3 rounded-lg glass-card text-white" min="30" max="480">
                    </div>
                    
                    <div>
                        <label for="backupFrequency" class="block text-sm font-medium mb-1">تكرار النسخ الاحتياطي</label>
                        <select id="backupFrequency" name="backup_frequency" class="w-full p-3 rounded-lg glass-card text-white">
                            <option value="daily">يومياً</option>
                            <option value="weekly">أسبوعياً</option>
                            <option value="monthly">شهرياً</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition-all">
                    حفظ إعدادات النظام
                </button>
            </form>
        </div>
    `;
    
    await loadSystemSettings();
}

async function loadSystemSettings() {
    try {
        const response = await fetch('../api/settings.php?action=get_system_settings');
        const settings = await response.json();
        
        // Fill form with system settings
        Object.keys(settings).forEach(key => {
            const element = document.querySelector(`[name="${key}"]`);
            if (element) {
                element.value = settings[key];
            }
        });
        
        // Add form submit handler
        document.getElementById('systemSettingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const settingsData = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../api/settings.php?action=update_system_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(settingsData)
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    showNotification('تم حفظ إعدادات النظام بنجاح', 'success');
                } else {
                    showNotification(result.error || 'خطأ في حفظ إعدادات النظام', 'error');
                }
            } catch (error) {
                console.error('Error saving system settings:', error);
                showNotification('خطأ في حفظ إعدادات النظام', 'error');
            }
        });
        
    } catch (error) {
        console.error('Error loading system settings:', error);
        showNotification('خطأ في تحميل إعدادات النظام', 'error');
    }
}

// Utility functions
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg text-white max-w-sm ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

