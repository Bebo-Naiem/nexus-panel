<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold">Profile Settings</h1>
    </div>
    
    <div class="glass rounded-xl p-6 border border-slate-700">
        <h2 class="text-xl font-semibold mb-6">Change Password</h2>
        
        <form id="profileForm" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Current Username</label>
                <div class="px-4 py-3 bg-slate-800 rounded-lg text-slate-300">
                    <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">New Password</label>
                <input type="password" name="new_password" required minlength="6"
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                <p class="mt-1 text-sm text-slate-500">Minimum 6 characters</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="6"
                       class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
            </div>
            
            <button type="submit" 
                    class="w-full py-3 px-4 bg-gradient-to-r from-primary to-secondary text-white font-medium rounded-lg hover:opacity-90 transition duration-200">
                Update Password
            </button>
        </form>
    </div>
    
    <div class="glass rounded-xl p-6 border border-slate-700">
        <h2 class="text-xl font-semibold mb-4">Account Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-slate-400">Username</p>
                <p class="font-medium"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></p>
            </div>
            <div>
                <p class="text-sm text-slate-400">Role</p>
                <p class="font-medium capitalize"><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    
    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(profileForm);
        
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword !== confirmPassword) {
            showToast('Passwords do not match', 'error');
            return;
        }
        
        const result = await apiCall('update_profile', {
            new_password: newPassword
        });
        
        if (result.success) {
            showToast('Password updated successfully!', 'success');
            profileForm.reset();
        }
    });
});
</script>