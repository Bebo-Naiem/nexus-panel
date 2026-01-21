<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-primary mb-2">Nexus Panel</h1>
            <p class="text-slate-400">Game Server Management System</p>
        </div>
        
        <!-- Login Card -->
        <div class="glass rounded-2xl p-8 shadow-2xl border border-slate-700">
            <h2 class="text-2xl font-bold text-center mb-6">Welcome Back</h2>
            
            <form id="loginForm" class="space-y-4">
                <input type="hidden" name="action" value="login">
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Username or Email</label>
                    <input type="text" name="username" required 
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                    <input type="password" name="password" required 
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
                
                <button type="submit" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-primary to-secondary text-white font-medium rounded-lg hover:opacity-90 transition duration-200">
                    Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-slate-400">Don't have an account?</p>
                <button id="showRegister" class="text-primary hover:text-secondary font-medium mt-1">
                    Create Account
                </button>
            </div>
        </div>
        
        <!-- Register Card (Hidden by default) -->
        <div id="registerCard" class="glass rounded-2xl p-8 shadow-2xl border border-slate-700 mt-6 hidden">
            <h2 class="text-2xl font-bold text-center mb-6">Create Account</h2>
            
            <form id="registerForm" class="space-y-4">
                <input type="hidden" name="action" value="register">
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                    <input type="text" name="username" required 
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                    <input type="email" name="email" required 
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full px-4 py-3 bg-slate-800 border border-slate-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-white placeholder-slate-400">
                </div>
                
                <button type="submit" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-secondary to-primary text-white font-medium rounded-lg hover:opacity-90 transition duration-200">
                    Create Account
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-slate-400">Already have an account?</p>
                <button id="showLogin" class="text-primary hover:text-secondary font-medium mt-1">
                    Sign In
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const showRegister = document.getElementById('showRegister');
    const showLogin = document.getElementById('showLogin');
    const registerCard = document.getElementById('registerCard');
    const loginCard = loginForm.parentElement;
    
    showRegister.addEventListener('click', () => {
        loginCard.classList.add('hidden');
        registerCard.classList.remove('hidden');
    });
    
    showLogin.addEventListener('click', () => {
        registerCard.classList.add('hidden');
        loginCard.classList.remove('hidden');
    });
    
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(loginForm);
        
        const result = await apiCall('login', {
            username: formData.get('username'),
            password: formData.get('password')
        });
        
        if (result.success) {
            showToast('Login successful!', 'success');
            window.location.reload();
        }
    });
    
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(registerForm);
        
        const result = await apiCall('register', {
            username: formData.get('username'),
            email: formData.get('email'),
            password: formData.get('password')
        });
        
        if (result.success) {
            showToast('Account created successfully!', 'success');
            setTimeout(() => {
                registerCard.classList.add('hidden');
                loginCard.classList.remove('hidden');
            }, 1500);
        }
    });
});
</script>