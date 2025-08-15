// Complete Doctor Dashboard with all functionality
class DoctorDashboard {
    constructor() {
        this.currentPatient = null;
        this.currentSession = null;
        this.modalToothSelector = null;
        this.treatmentTypes = [];
        this.drugs = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupToothSelector();
        this.loadInitialData();
        
        // Expose modal close helpers for HTML inline handlers
        window.closeHistoryModal = () => this.closeModal('historyModal');
        window.closeSessionDetailsModal = () => this.closeModal('sessionDetailsModal');
        window.closeFinancialModal = () => this.closeModal('financialModal');
        window.closeTreatmentModal = () => this.closeModal('treatmentModal');
        window.closeAddSessionModal = () => this.closeModal('addSessionModal');
        window.closeAddTreatmentModal = () => this.closeModal('addTreatmentModal');
        window.closeAddPrescriptionModal = () => this.closeModal('addPrescriptionModal');
        window.logout = () => this.logout();
    }

    async loadInitialData() {
        try {
            // Load treatment types and drugs in background
            await Promise.all([
                this.loadTreatmentTypes(),
                this.loadDrugs()
            ]);
        } catch (error) {
            console.error('Error loading initial data:', error);
        }
    }

    bindEvents() {
        // Patient search
        const searchInput = document.getElementById('patientSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => this.searchPatients(e.target.value), 300));
            searchInput.addEventListener('focus', () => {
                if (searchInput.value.length >= 2) {
                    this.searchPatients(searchInput.value);
                }
            });
        }

        // Add session button
        const addSessionBtn = document.getElementById('addSessionBtn');
        if (addSessionBtn) {
            addSessionBtn.addEventListener('click', () => this.showAddSessionModal());
        }

        // Add treatment button
        const addTreatmentBtn = document.getElementById('addTreatmentBtn');
        if (addTreatmentBtn) {
            addTreatmentBtn.addEventListener('click', () => this.showAddTreatmentModal());
        }

        // Add prescription button
        const addPrescriptionBtn = document.getElementById('addPrescriptionBtn');
        if (addPrescriptionBtn) {
            addPrescriptionBtn.addEventListener('click', () => this.showAddPrescriptionModal());
        }

        // Quick actions duplicates
        const openHistoryQuick = document.getElementById('openHistoryBtnQuick');
        if (openHistoryQuick) openHistoryQuick.addEventListener('click', () => this.openHistoryModal());
        const openFinancialQuick = document.getElementById('openFinancialBtnQuick');
        if (openFinancialQuick) openFinancialQuick.addEventListener('click', () => this.openFinancialModal());

        // Modal close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal[style*="flex"]');
                if (openModal) {
                    openModal.style.display = 'none';
                }
            }
        });

        // Clear search results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#patientSearch') && !e.target.closest('#patientSearchResults')) {
                this.clearPatientResults();
            }
        });
    }

    setupToothSelector() {
        const toothContainer = document.getElementById('toothSelector');
        if (toothContainer && typeof ToothSelector !== 'undefined') {
            console.log('Setting up tooth selector...');
            this.toothSelector = new ToothSelector('toothSelector', {
                onSelectionChange: (selectedTeeth) => {
                    console.log('Selected teeth:', selectedTeeth);
                    this.updateSessionSummary();
                }
            });
        } else {
            console.error('ToothSelector not found or ToothSelector class not defined');
        }
    }

    async searchPatients(query) {
        const term = typeof query === 'string' ? query : '';
        if (!term || term.length < 2) {
            this.clearPatientResults();
            return;
        }

        try {
            const response = await fetch(`../api/patients.php?action=search&term=${encodeURIComponent(term)}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const patients = await response.json();
            this.displayPatientResults(patients);
        } catch (error) {
            console.error('Error searching patients:', error);
            this.showNotification('خطأ في البحث عن المرضى', 'error');
        }
    }

    displayPatientResults(patients) {
        const resultsContainer = document.getElementById('patientSearchResults');
        if (!resultsContainer) return;

        if (patients.length === 0) {
            resultsContainer.innerHTML = '<div class="p-4 text-center text-gray-500">لا توجد نتائج</div>';
            return;
        }
        
        const html = patients.map(patient => `
            <div class="patient-result-item p-3 border-b border-gray-200 hover:bg-gray-50 cursor-pointer" 
                 data-patient-id="${patient.id}">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="font-medium text-gray-900">
                            ${patient.first_name} ${patient.father_name} ${patient.last_name}
                        </div>
                        <div class="text-sm text-gray-500">${patient.phone_number}</div>
                    </div>
                    <div class="text-xs text-gray-400">
                        ${patient.visit_status === 'first_time' ? 'أول مرة' : 'مراجعة'}
                    </div>
                </div>
            </div>
        `).join('');

        resultsContainer.innerHTML = html;

        // Add click events to patient results
        resultsContainer.querySelectorAll('.patient-result-item').forEach(item => {
            item.addEventListener('click', () => {
                const patientId = item.dataset.patientId;
                this.selectPatient(patientId, patients.find(p => p.id == patientId));
            });
        });
    }

    clearPatientResults() {
        const resultsContainer = document.getElementById('patientSearchResults');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
    }

    async selectPatient(patientId, patientData) {
        this.currentPatient = patientData;
        this.clearPatientResults();
        
        // Update patient display
        this.updatePatientDisplay(patientData);
        
        // Load patient sessions
        await this.loadPatientSessions(patientId);
        
        // Show patient actions
        this.showPatientActions();
        
        // Update session summary
        this.updateSessionSummary();
    }

    updatePatientDisplay(patient) {
        const patientInfo = document.getElementById('patientInfo');
        if (patientInfo) {
            patientInfo.innerHTML = `
                <div class="bg-white p-4 rounded-lg shadow">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                        ${patient.first_name} ${patient.father_name} ${patient.last_name}
                    </h3>
                    <div class="text-sm text-gray-600">
                        <div>رقم الهاتف: ${patient.phone_number}</div>
                        <div>العنوان: ${patient.address || 'غير محدد'}</div>
                        <div>تاريخ الميلاد: ${patient.date_of_birth || 'غير محدد'}</div>
                        <div>نوع الزيارة: ${patient.visit_status === 'first_time' ? 'أول مرة' : 'مراجعة'}</div>
                    </div>
                </div>
            `;
            patientInfo.classList.remove('hidden');
        }
    }

    async loadPatientSessions(patientId) {
        try {
            console.log('Loading patient sessions for:', patientId);
            const response = await fetch(`../api/sessions.php?action=get_patient_sessions&patient_id=${patientId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const sessions = await response.json();
            this.displayPatientSessions(sessions);
        } catch (error) {
            console.error('Error loading patient sessions:', error);
            this.showNotification('خطأ في تحميل جلسات المريض', 'error');
        }
    }

    displayPatientSessions(sessions) {
        const sessionsContainer = document.getElementById('patientSessions');
        if (!sessionsContainer) return;

        if (!Array.isArray(sessions) || sessions.length === 0) {
            sessionsContainer.innerHTML = `
                <h3 class="text-lg font-bold text-gray-800 mb-4">الجلسات السابقة</h3>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-4"></i>
                    <div>لا توجد جلسات سابقة</div>
                </div>
            `;
            return;
        }

        const html = `
            <h3 class="text-lg font-bold text-gray-800 mb-4">الجلسات السابقة</h3>
            ${sessions.map(session => `
                <div class="session-item bg-white p-4 rounded-lg shadow mb-4 cursor-pointer hover:shadow-md transition-shadow"
                     data-session-id="${session.id}">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-gray-900">
                                جلسة بتاريخ: ${new Date(session.session_date).toLocaleDateString('ar-SA')}
                            </div>
                            <div class="text-sm text-gray-600">
                                الطبيب: ${session.doctor_name || 'غير محدد'}
                            </div>
                            ${session.session_notes ? `<div class="text-sm text-gray-500 mt-1">${session.session_notes}</div>` : ''}
                        </div>
                        <div class="flex space-x-2">
                            <button class="btn-view-session text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-eye ml-1"></i>عرض
                            </button>
                            <button class="btn-edit-session text-green-600 hover:text-green-800 text-sm">
                                <i class="fas fa-edit ml-1"></i>تعديل
                            </button>
                        </div>
                    </div>
                </div>
            `).join('')}
        `;

        sessionsContainer.innerHTML = html;
        this.bindSessionListEvents();
    }

    showPatientActions() {
        const actionsContainer = document.getElementById('patientActions');
        if (actionsContainer) {
            actionsContainer.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button id="addSessionBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        <i class="fas fa-plus ml-2"></i>
                        إضافة جلسة جديدة
                    </button>
                    <button id="openHistoryBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        <i class="fas fa-history ml-2"></i>
                        سجل المعالجات
                    </button>
                    <button class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        <i class="fas fa-paperclip ml-2"></i>
                        الملفات المرفقة
                    </button>
                    <button id="openFinancialBtn" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        <i class="fas fa-file-invoice-dollar ml-2"></i>
                        الملف المالي
                    </button>
                </div>
            `;
            actionsContainer.classList.remove('hidden');

            // Re-bind events for the new buttons
            const addSessionBtn = actionsContainer.querySelector('#addSessionBtn');
            if (addSessionBtn) {
                addSessionBtn.addEventListener('click', () => this.showAddSessionModal());
            }
            const historyBtn = actionsContainer.querySelector('#openHistoryBtn');
            if (historyBtn) {
                historyBtn.addEventListener('click', () => this.openHistoryModal());
            }
            const financialBtn = actionsContainer.querySelector('#openFinancialBtn');
            if (financialBtn) {
                financialBtn.addEventListener('click', () => this.openFinancialModal());
            }
        }
    }

    showAddSessionModal() {
        if (!this.currentPatient) {
            this.showNotification('يرجى اختيار مريض أولاً', 'error');
            return;
        }
        const modal = document.getElementById('addSessionModal');
        if (modal) {
            modal.style.display = 'flex';
            // Clear previous notes
            const notesInput = document.getElementById('sessionNotes');
            if (notesInput) notesInput.value = '';
        }
    }

    async createSession() {
        if (!this.currentPatient) {
            this.showNotification('يرجى اختيار مريض أولاً', 'error');
            return;
        }

        const sessionNotes = document.getElementById('sessionNotes')?.value || '';
        const doctorId = 1; // TODO: Get from logged in user

        try {
            const response = await fetch('../api/sessions.php?action=create_session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    patient_id: this.currentPatient.id,
                    doctor_id: doctorId,
                    session_notes: sessionNotes
                })
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || 'خطأ في إنشاء الجلسة');
            }
            
            const result = await response.json();
            
            this.showNotification('تم إنشاء الجلسة بنجاح', 'success');
            this.closeModal('addSessionModal');
            await this.loadPatientSessions(this.currentPatient.id);
            this.currentSession = result.session_id;
            this.updateSessionSummary();
        } catch (error) {
            console.error('Error creating session:', error);
            this.showNotification(error.message || 'خطأ في إنشاء الجلسة', 'error');
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Add missing modal functions
    showAddTreatmentModal() {
        if (!this.currentSession) {
            this.showNotification('يرجى إنشاء جلسة أولاً', 'error');
            return;
        }
        const modal = document.getElementById('addTreatmentModal');
        if (modal) {
            modal.style.display = 'flex';
            this.loadTreatmentTypes();
            const selectedTeethDisplay = document.getElementById('selectedTeethDisplay');
            const selectedFromMain = this.getSelectedTeethFromMain();
            if (selectedTeethDisplay) {
                selectedTeethDisplay.textContent = selectedFromMain.length ? selectedFromMain.join(', ') : 'لا توجد أسنان مختارة';
            }
        }
    }

    showAddPrescriptionModal() {
        if (!this.currentSession) {
            this.showNotification('يرجى إنشاء جلسة أولاً', 'error');
            return;
        }
        const modal = document.getElementById('addPrescriptionModal');
        if (modal) {
            modal.style.display = 'flex';
            this.loadDrugs();
        }
    }

    // Load treatment types from API and populate the select
    async loadTreatmentTypes() {
        try {
            const response = await fetch('../api/settings.php?action=get_treatment_types');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const types = await response.json();
            this.treatmentTypes = Array.isArray(types) ? types : [];
            this.populateTreatmentTypesSelect();
        } catch (error) {
            console.error('Error loading treatment types:', error);
            this.showNotification('خطأ في تحميل أنواع المعالجات', 'error');
        }
    }

    populateTreatmentTypesSelect() {
        const select = document.getElementById('treatmentType');
        const costInput = document.getElementById('treatmentCost');
        if (!select) return;

        select.innerHTML = '<option value="">اختيار نوع المعالجة</option>';
        this.treatmentTypes.forEach(type => {
            const option = document.createElement('option');
            option.value = type.id;
            option.textContent = type.name;
            option.dataset.defaultCost = type.default_cost || 0;
            select.appendChild(option);
        });

        select.onchange = () => {
            const selectedOption = select.options[select.selectedIndex];
            const defaultCost = selectedOption ? Number(selectedOption.dataset.defaultCost || 0) : 0;
            if (costInput) costInput.value = defaultCost;
        };
    }

    // Read selected teeth from the main dashboard selector
    getSelectedTeethFromMain() {
        if (this.toothSelector && typeof this.toothSelector.getSelectedTeeth === 'function') {
            return this.toothSelector.getSelectedTeeth().map(t => parseInt(t, 10)).filter(Boolean);
        }
        return [];
    }

    // Add treatment(s) to current session
    async addTreatment() {
        if (!this.currentSession) {
            this.showNotification('يرجى إنشاء جلسة أولاً', 'error');
            return;
        }

        const selectedTeeth = this.getSelectedTeethFromMain();
        if (selectedTeeth.length === 0) {
            this.showNotification('لم يتم اختيار أي سن', 'error');
            return;
        }

        const treatmentTypeId = parseInt((document.getElementById('treatmentType') || {}).value || '');
        if (!treatmentTypeId) {
            this.showNotification('يرجى اختيار نوع المعالجة', 'error');
            return;
        }

        const cost = Number((document.getElementById('treatmentCost') || {}).value || 0);
        const additionalCost = Number((document.getElementById('additionalCost') || {}).value || 0);
        const discount = Number((document.getElementById('treatmentDiscount') || {}).value || 0);
        const notes = (document.getElementById('treatmentNotes') || {}).value || null;

        try {
            for (const toothNumber of selectedTeeth) {
                const resp = await fetch('../api/sessions.php?action=add_treatment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: this.currentSession,
                        tooth_number: toothNumber,
                        treatment_type_id: treatmentTypeId,
                        cost: cost,
                        additional_cost: additionalCost,
                        discount: discount,
                        notes: notes
                    })
                });
                if (!resp.ok) {
                    const err = await resp.json().catch(() => ({}));
                    throw new Error(err.error || 'فشل حفظ المعالجة');
                }
            }

            // Mark treated in main chart
            if (this.toothSelector && typeof this.toothSelector.markAsTreatedMultiple === 'function') {
                this.toothSelector.markAsTreatedMultiple(selectedTeeth);
            }

            this.showNotification('تمت إضافة المعالجة بنجاح', 'success');
            this.closeModal('addTreatmentModal');
            this.updateSessionSummary();
        } catch (error) {
            console.error('Error adding treatment:', error);
            this.showNotification(error.message || 'خطأ في إضافة المعالجة', 'error');
        }
    }

    // Load drugs and populate prescription form
    async loadDrugs() {
        try {
            const response = await fetch('../api/settings.php?action=get_drugs');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const drugs = await response.json();
            this.drugs = Array.isArray(drugs) ? drugs : [];

            const drugSelect = document.getElementById('prescriptionDrug');
            const dosageSelect = document.getElementById('prescriptionDosage');
            if (!drugSelect || !dosageSelect) return;

            drugSelect.innerHTML = '<option value="">اختر الدواء</option>';
            this.drugs.forEach(drug => {
                const option = document.createElement('option');
                option.value = drug.id;
                option.textContent = drug.name;
                option.dataset.dosageOptions = drug.dosage_options;
                drugSelect.appendChild(option);
            });

            const updateDosages = () => {
                const selectedOption = drugSelect.options[drugSelect.selectedIndex];
                let options = [];
                try {
                    options = selectedOption && selectedOption.dataset.dosageOptions
                        ? (typeof selectedOption.dataset.dosageOptions === 'string' 
                            ? JSON.parse(selectedOption.dataset.dosageOptions) 
                            : selectedOption.dataset.dosageOptions)
                        : [];
                } catch (_) { options = []; }

                dosageSelect.innerHTML = '<option value="">اختر الجرعة</option>';
                options.forEach(opt => {
                    const o = document.createElement('option');
                    o.value = opt;
                    o.textContent = opt;
                    dosageSelect.appendChild(o);
                });
            };

            updateDosages();
            drugSelect.onchange = updateDosages;
        } catch (error) {
            console.error('Error loading drugs:', error);
            this.showNotification('خطأ في تحميل قائمة الأدوية', 'error');
        }
    }

    // Add prescription to current session
    async addPrescription() {
        if (!this.currentSession) {
            this.showNotification('يرجى إنشاء جلسة أولاً', 'error');
            return;
        }

        const drugId = parseInt((document.getElementById('prescriptionDrug') || {}).value || '');
        const dosage = (document.getElementById('prescriptionDosage') || {}).value || '';
        if (!drugId || !dosage) {
            this.showNotification('يرجى اختيار الدواء والجرعة', 'error');
            return;
        }

        try {
            const resp = await fetch('../api/sessions.php?action=add_prescription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: this.currentSession, drug_id: drugId, dosage, is_printed: false })
            });
            if (!resp.ok) {
                const err = await resp.json().catch(() => ({}));
                throw new Error(err.error || 'فشل إضافة الوصفة');
            }
            this.showNotification('تمت إضافة الوصفة بنجاح', 'success');
            this.closeModal('addPrescriptionModal');
            this.updateSessionSummary();
        } catch (error) {
            console.error('Error adding prescription:', error);
            this.showNotification(error.message || 'خطأ في إضافة الوصفة', 'error');
        }
    }

    bindSessionListEvents() {
        const container = document.getElementById('patientSessions');
        if (!container) return;
        container.querySelectorAll('.session-item').forEach(item => {
            const sessionId = item.getAttribute('data-session-id');
            const viewBtn = item.querySelector('.btn-view-session');
            const editBtn = item.querySelector('.btn-edit-session');
            if (viewBtn) viewBtn.addEventListener('click', (e) => { e.stopPropagation(); this.openSessionDetails(sessionId); });
            if (editBtn) editBtn.addEventListener('click', (e) => { e.stopPropagation(); this.editSession(sessionId); });
        });
    }

    async openSessionDetails(sessionId) {
        try {
            const [detailsRes, treatmentsRes, prescriptionsRes] = await Promise.all([
                fetch(`../api/sessions.php?action=get_session_details&session_id=${sessionId}`),
                fetch(`../api/sessions.php?action=get_session_treatments&session_id=${sessionId}`),
                fetch(`../api/sessions.php?action=get_session_prescriptions&session_id=${sessionId}`)
            ]);
            
            if (!detailsRes.ok || !treatmentsRes.ok || !prescriptionsRes.ok) {
                throw new Error('فشل في تحميل تفاصيل الجلسة');
            }
            
            const details = await detailsRes.json();
            const treatments = await treatmentsRes.json();
            const prescriptions = await prescriptionsRes.json();

            const container = document.getElementById('sessionDetailsContent');
            if (container) {
                container.innerHTML = `
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <div class="font-semibold text-gray-800 mb-1">المريض:</div>
                            <div class="text-gray-700">${details.patient_name} - ${details.phone_number || ''}</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="font-semibold text-gray-800 mb-2">المعالجات</div>
                                ${Array.isArray(treatments) && treatments.length ? treatments.map(t => `
                                    <div class="flex items-center justify-between py-2 border-b border-gray-200 text-sm">
                                        <div>
                                            <div class="font-medium text-gray-900">${t.treatment_type_name}</div>
                                            <div class="text-gray-600">سن: ${t.tooth_number} — تكلفة: ${t.cost} — خصم: ${t.discount || 0}%</div>
                                            ${t.notes ? `<div class="text-gray-500">ملاحظات: ${t.notes}</div>` : ''}
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <button class="btn-edit-treatment text-green-600 hover:text-green-800" data-id="${t.id}"><i class="fas fa-edit"></i></button>
                                            <button class="btn-delete-treatment text-red-600 hover:text-red-800" data-id="${t.id}"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                `).join('') : '<div class="text-gray-500">لا توجد معالجات</div>'}
                            </div>
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="font-semibold text-gray-800 mb-2">الوصفات</div>
                                ${Array.isArray(prescriptions) && prescriptions.length ? prescriptions.map(p => `
                                    <div class="py-2 border-b border-gray-200 text-sm">
                                        <div class="font-medium text-gray-900">${p.drug_name}</div>
                                        <div class="text-gray-600">الجرعة: ${p.dosage}</div>
                                    </div>
                                `).join('') : '<div class="text-gray-500">لا توجد وصفات</div>'}
                            </div>
                        </div>
                    </div>
                `;
            }

            // Bind treatment actions
            container.querySelectorAll('.btn-delete-treatment').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    if (!confirm('هل أنت متأكد من حذف هذه المعالجة؟')) return;
                    try {
                        const resp = await fetch(`../api/sessions.php?action=delete_treatment&treatment_id=${id}`, { method: 'DELETE' });
                        if (!resp.ok) throw new Error();
                        this.showNotification('تم حذف المعالجة', 'success');
                        this.openSessionDetails(sessionId);
                    } catch (_) {
                        this.showNotification('فشل حذف المعالجة', 'error');
                    }
                });
            });
            container.querySelectorAll('.btn-edit-treatment').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    const newCost = prompt('تعديل التكلفة:');
                    if (newCost === null) return;
                    const newAdd = prompt('تعديل التكلفة الإضافية:', '0');
                    if (newAdd === null) return;
                    const newDisc = prompt('تعديل الخصم (%):', '0');
                    if (newDisc === null) return;
                    const newNotes = prompt('تعديل الملاحظات:') ?? '';
                    try {
                        const resp = await fetch('../api/sessions.php?action=update_treatment', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                treatment_id: parseInt(id, 10),
                                cost: Number(newCost) || 0,
                                additional_cost: Number(newAdd) || 0,
                                discount: Number(newDisc) || 0,
                                notes: newNotes
                            })
                        });
                        if (!resp.ok) throw new Error();
                        this.showNotification('تم تحديث المعالجة', 'success');
                        this.openSessionDetails(sessionId);
                    } catch (_) {
                        this.showNotification('فشل تحديث المعالجة', 'error');
                    }
                });
            });

            const modal = document.getElementById('sessionDetailsModal');
            if (modal) modal.style.display = 'flex';
        } catch (error) {
            console.error('Error loading session details:', error);
            this.showNotification('خطأ في تحميل تفاصيل الجلسة', 'error');
        }
    }

    async editSession(sessionId) {
        try {
            const res = await fetch(`../api/sessions.php?action=get_session_details&session_id=${sessionId}`);
            if (!res.ok) throw new Error();
            const details = await res.json();
            const newNotes = prompt('تعديل ملاحظات الجلسة:', details.session_notes || '');
            if (newNotes === null) return;
            const resp = await fetch('../api/sessions.php?action=update_session', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: parseInt(sessionId, 10), session_notes: newNotes })
            });
            if (!resp.ok) throw new Error();
            this.showNotification('تم تحديث الجلسة', 'success');
            if (this.currentPatient) await this.loadPatientSessions(this.currentPatient.id);
        } catch (error) {
            console.error('Error updating session:', error);
            this.showNotification('فشل تحديث الجلسة', 'error');
        }
    }

    openHistoryModal() {
        if (!this.currentPatient) {
            this.showNotification('يرجى اختيار مريض أولاً', 'error');
            return;
        }
        const modal = document.getElementById('historyModal');
        const content = document.getElementById('historyContent');
        if (content) content.innerHTML = '<div class="text-center text-gray-500">جاري التحميل...</div>';
        if (modal) modal.style.display = 'flex';
        fetch(`../api/sessions.php?action=get_patient_sessions&patient_id=${this.currentPatient.id}`)
            .then(r => r.json())
            .then(sessions => {
                if (!content) return;
                if (!Array.isArray(sessions) || sessions.length === 0) {
                    content.innerHTML = '<div class="text-gray-500">لا يوجد سجل</div>';
                    return;
                }
                content.innerHTML = sessions.map(s => `
                    <div class="p-3 border-b">
                        <div class="font-semibold text-gray-800">${new Date(s.session_date).toLocaleDateString('ar-SA')}</div>
                        ${s.session_notes ? `<div class=\"text-gray-600\">${s.session_notes}</div>` : ''}
                        <button class="mt-2 text-blue-600 hover:text-blue-800 text-sm" onclick="doctorDashboard.openSessionDetails(${s.id})">
                            <i class="fas fa-eye ml-1"></i> عرض التفاصيل
                        </button>
                    </div>
                `).join('');
            })
            .catch(err => {
                console.error(err);
                if (content) content.innerHTML = '<div class="text-red-500">خطأ في تحميل السجل</div>';
            });
    }

    openFinancialModal() {
        if (!this.currentPatient) {
            this.showNotification('يرجى اختيار مريض أولاً', 'error');
            return;
        }
        const modal = document.getElementById('financialModal');
        if (modal) modal.style.display = 'flex';
        this.loadFinancialData(this.currentPatient.id);
    }

    async loadFinancialData(patientId) {
        try {
            const [balRes, paysRes, invRes] = await Promise.all([
                fetch(`../api/financial.php?action=get_patient_balance&patient_id=${patientId}`),
                fetch(`../api/financial.php?action=get_patient_payments&patient_id=${patientId}`),
                fetch(`../api/financial.php?action=get_patient_invoices&patient_id=${patientId}`)
            ]);
            
            const balance = await balRes.json();
            const payments = await paysRes.json();
            const invoices = await invRes.json();

            const balanceEl = document.getElementById('patientBalance');
            if (balanceEl) balanceEl.textContent = `${(balance && balance.balance) ? balance.balance : 0} ل.س`;

            const paysEl = document.getElementById('patientPayments');
            if (paysEl) {
                paysEl.innerHTML = Array.isArray(payments) && payments.length ? payments.map(p => `
                    <div class="flex justify-between border-b py-2">
                        <span>${new Date(p.created_at).toLocaleDateString('ar-SA')}</span>
                        <span>${p.amount} ل.س</span>
                    </div>
                `).join('') : '<p class="text-gray-500 text-center">لا توجد دفعات مسجلة.</p>';
            }

            const invEl = document.getElementById('patientInvoices');
            if (invEl) {
                invEl.innerHTML = Array.isArray(invoices) && invoices.length ? invoices.map(i => `
                    <div class="flex justify-between border-b py-2">
                        <span>${new Date(i.created_at).toLocaleDateString('ar-SA')}</span>
                        <span>${i.total_amount || 0} ل.س</span>
                    </div>
                `).join('') : '<p class="text-gray-500 text-center">لا توجد فواتير مسجلة.</p>';
            }
        } catch (error) {
            console.error('Error loading financial data:', error);
            this.showNotification('خطأ في تحميل الملف المالي', 'error');
        }
    }

    updateSessionSummary() {
        const summaryContainer = document.getElementById('sessionSummary');
        if (!summaryContainer) return;

        if (!this.currentPatient) {
            summaryContainer.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-calendar-plus text-4xl mb-4"></i>
                    <div>لا توجد جلسة نشطة</div>
                </div>
            `;
            return;
        }

        const selectedTeeth = this.getSelectedTeethFromMain();
        const patientName = `${this.currentPatient.first_name} ${this.currentPatient.father_name} ${this.currentPatient.last_name}`;

        summaryContainer.innerHTML = `
            <div class="space-y-4">
                <div class="bg-blue-50 p-3 rounded-lg">
                    <div class="font-semibold text-blue-800">المريض الحالي:</div>
                    <div class="text-blue-700">${patientName}</div>
                </div>
                ${this.currentSession ? `
                    <div class="bg-green-50 p-3 rounded-lg">
                        <div class="font-semibold text-green-800">الجلسة النشطة:</div>
                        <div class="text-green-700">جلسة رقم ${this.currentSession}</div>
                    </div>
                ` : `
                    <div class="bg-yellow-50 p-3 rounded-lg">
                        <div class="font-semibold text-yellow-800">تحذير:</div>
                        <div class="text-yellow-700">لا توجد جلسة نشطة</div>
                    </div>
                `}
                ${selectedTeeth.length > 0 ? `
                    <div class="bg-purple-50 p-3 rounded-lg">
                        <div class="font-semibold text-purple-800">الأسنان المختارة:</div>
                        <div class="text-purple-700">${selectedTeeth.join(', ')}</div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    logout() {
        if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
            // Clear any stored data
            this.currentPatient = null;
            this.currentSession = null;
            
            // Redirect to login page
            window.location.href = '../index.html';
        }
    }
}