<?php if ($_SESSION['role'] !== 'admin'): ?>
<div class="text-center py-12">
    <h2 class="text-2xl font-bold text-red-400 mb-4">Access Denied</h2>
    <p class="text-slate-400">You don't have permission to access this page.</p>
</div>
<?php else: ?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Email Configuration</h1>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- SMTP Configuration -->
        <div class="glass rounded-xl border border-slate-700">
            <div class="p-6 border-b border-slate-700">
                <h2 class="text-xl font-semibold">SMTP Configuration</h2>
            </div>
            <div class="p-6">
                <form id="smtpForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">SMTP Host *</label>
                        <input type="text" id="smtpHost" name="smtp_host" required
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">SMTP Port *</label>
                            <input type="number" id="smtpPort" name="smtp_port" value="587" required
                                   class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Encryption</label>
                            <select id="smtpEncryption" name="smtp_encryption"
                                    class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">SMTP Username</label>
                        <input type="text" id="smtpUsername" name="smtp_username"
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">SMTP Password</label>
                        <input type="password" id="smtpPassword" name="smtp_password"
                               class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">From Email *</label>
                            <input type="email" id="fromEmail" name="from_email" required
                                   class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">From Name *</label>
                            <input type="text" id="fromName" name="from_name" value="Nexus Panel" required
                                   class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                        </div>
                    </div>
                    
                    <div class="flex space-x-3 pt-4">
                        <button type="button" id="testSmtpBtn" 
                                class="flex-1 py-2 px-4 bg-yellow-600 hover:bg-yellow-700 rounded-lg font-medium transition">
                            Test Connection
                        </button>
                        <button type="submit" 
                                class="flex-1 py-2 px-4 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Email Templates -->
        <div class="glass rounded-xl border border-slate-700">
            <div class="p-6 border-b border-slate-700 flex justify-between items-center">
                <h2 class="text-xl font-semibold">Email Templates</h2>
                <button onclick="openTemplateModal()" class="px-3 py-1 bg-primary hover:bg-opacity-90 rounded text-sm">
                    + New Template
                </button>
            </div>
            <div id="templatesContainer" class="p-6">
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    <p class="mt-4 text-slate-400">Loading templates...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div id="templateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="glass rounded-xl p-6 border border-slate-700 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold" id="templateModalTitle">Create Email Template</h3>
            <button onclick="closeTemplateModal()" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="templateForm">
            <input type="hidden" id="templateName" name="name">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Template Name *</label>
                <input type="text" id="templateNameInput" name="name" required
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Subject *</label>
                <input type="text" id="templateSubject" name="subject" required
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Body *</label>
                <textarea id="templateBody" name="body" rows="10" required
                          class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400"></textarea>
                <p class="mt-1 text-sm text-slate-500">Use {{variable_name}} for template variables</p>
            </div>
            
            <div class="flex items-center mb-6">
                <input type="checkbox" id="templateHtml" name="is_html" checked
                       class="w-4 h-4 text-primary bg-slate-800 border-slate-600 rounded focus:ring-primary">
                <label for="templateHtml" class="ml-2 text-sm text-slate-300">HTML Email</label>
                
                <input type="checkbox" id="templateActive" name="is_active" checked class="ml-6 w-4 h-4 text-primary bg-slate-800 border-slate-600 rounded focus:ring-primary">
                <label for="templateActive" class="ml-2 text-sm text-slate-300">Active</label>
            </div>
            
            <div class="flex space-x-3">
                <button type="button" onclick="closeTemplateModal()" 
                        class="flex-1 py-2 px-4 bg-slate-700 hover:bg-slate-600 rounded-lg font-medium transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 py-2 px-4 bg-primary hover:bg-opacity-90 rounded-lg font-medium transition">
                    Save Template
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    await Promise.all([
        loadSmtpConfig(),
        loadTemplates()
    ]);
});

async function loadSmtpConfig() {
    const result = await apiCall('get_smtp_config');
    
    if (result.success && result.config) {
        const config = result.config;
        document.getElementById('smtpHost').value = config.smtp_host || '';
        document.getElementById('smtpPort').value = config.smtp_port || '587';
        document.getElementById('smtpEncryption').value = config.smtp_encryption || 'tls';
        document.getElementById('smtpUsername').value = config.smtp_username || '';
        // Don't populate password field for security
        document.getElementById('fromEmail').value = config.from_email || '';
        document.getElementById('fromName').value = config.from_name || 'Nexus Panel';
    }
}

async function loadTemplates() {
    const result = await apiCall('list_email_templates');
    
    if (result.success) {
        const container = document.getElementById('templatesContainer');
        const templates = result.templates || [];
        
        if (templates.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-16 h-16 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-slate-300 mb-2">No templates found</h3>
                    <p class="text-slate-500">Create your first email template to get started.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="space-y-3">
                ${templates.map(template => `
                    <div class="flex items-center justify-between p-4 bg-slate-800 rounded-lg border border-slate-700">
                        <div>
                            <h4 class="font-medium">${template.name}</h4>
                            <p class="text-sm text-slate-400">${template.subject}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                ${template.is_active ? 'Active' : 'Inactive'} • ${new Date(template.created_at).toLocaleDateString()}
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="editTemplate('${template.name}')" 
                                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-sm">
                                Edit
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
}

document.getElementById('smtpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const result = await apiCall('save_smtp_config', Object.fromEntries(formData));
    
    if (result.success) {
        showToast('SMTP configuration saved successfully', 'success');
    }
});

document.getElementById('testSmtpBtn').addEventListener('click', async function() {
    const btn = this;
    const originalText = btn.textContent;
    
    btn.disabled = true;
    btn.textContent = 'Testing...';
    
    const result = await apiCall('test_smtp');
    
    if (result.success) {
        showToast('SMTP test successful!', 'success');
    }
    
    btn.disabled = false;
    btn.textContent = originalText;
});

document.getElementById('templateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const result = await apiCall('save_email_template', Object.fromEntries(formData));
    
    if (result.success) {
        showToast('Template saved successfully', 'success');
        closeTemplateModal();
        await loadTemplates();
    }
});

function openTemplateModal() {
    document.getElementById('templateModalTitle').textContent = 'Create Email Template';
    document.getElementById('templateForm').reset();
    document.getElementById('templateModal').classList.remove('hidden');
}

async function editTemplate(templateName) {
    const result = await apiCall('get_email_template', { template_name: templateName });
    
    if (result.success) {
        const template = result.template;
        document.getElementById('templateModalTitle').textContent = 'Edit Email Template';
        document.getElementById('templateNameInput').value = template.name;
        document.getElementById('templateSubject').value = template.subject;
        document.getElementById('templateBody').value = template.body;
        document.getElementById('templateHtml').checked = template.is_html == 1;
        document.getElementById('templateActive').checked = template.is_active == 1;
        document.getElementById('templateModal').classList.remove('hidden');
    }
}

function closeTemplateModal() {
    document.getElementById('templateModal').classList.add('hidden');
    document.getElementById('templateForm').reset();
}

// Close modal when clicking outside
document.getElementById('templateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTemplateModal();
    }
});
</script>

<?php endif; ?>