<?php
session_start();

// Configuration
$API_BASE = 'http://localhost:3001/api';

// Handle form submission
$error = '';
$success = '';

if ($_POST) {
    if (isset($_POST['register'])) {
        // Regular registration
        $data = [
            'email' => $_POST['email'] ?? '',
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'firstName' => $_POST['firstName'] ?? '',
            'lastName' => $_POST['lastName'] ?? ''
        ];
        
        // Validate password confirmation
        if ($data['password'] !== ($_POST['confirmPassword'] ?? '')) {
            $error = 'Passwords do not match';
        } else {
            // Call API to register user
            $response = callAPI('POST', $API_BASE . '/auth/register', $data);
            
            if ($response && isset($response['token'])) {
                $success = 'Account created successfully! Please check your email for verification.';
                // Optionally auto-login the user
                $_SESSION['token'] = $response['token'];
                $_SESSION['user'] = $response['user'];
            } else {
                $error = $response['error'] ?? 'Registration failed. Please try again.';
            }
        }
    }
}

function callAPI($method, $url, $data = null) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data ? json_encode($data) : null
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return json_decode($response, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SmokeoutNYC</title>
    <meta name="description" content="Create your SmokeoutNYC account to track smoke shops and stay updated on Operation Smokeout">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .oauth-btn {
            transition: all 0.3s ease;
        }
        .oauth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .password-strength {
            height: 4px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-xl font-bold text-gray-900">
                        <i class="fas fa-store-slash text-red-600 mr-2"></i>
                        SmokeoutNYC
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-home mr-1"></i>Home
                    </a>
                    <a href="search.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-search mr-1"></i>Search
                    </a>
                    <a href="login.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-sign-in-alt mr-1"></i>Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 bg-blue-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-plus text-white text-xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Create your account
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Join SmokeoutNYC to track smoke shops and stay informed
                </p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle text-red-400 mr-3 mt-0.5"></i>
                        <div class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <i class="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                        <div class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- OAuth2 Providers -->
            <div class="space-y-3">
                <h3 class="text-center text-sm font-medium text-gray-700 mb-4">Sign up with</h3>
                
                <button onclick="signupWithProvider('google')" 
                        class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                </button>

                <button onclick="signupWithProvider('facebook')" 
                        class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fab fa-facebook text-blue-600 text-xl mr-3"></i>
                    Continue with Facebook
                </button>

                <button onclick="signupWithProvider('microsoft')" 
                        class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fab fa-microsoft text-blue-500 text-xl mr-3"></i>
                    Continue with Microsoft
                </button>

                <button onclick="signupWithProvider('twitter')" 
                        class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fab fa-twitter text-blue-400 text-xl mr-3"></i>
                    Continue with Twitter
                </button>
            </div>

            <!-- Divider -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-gray-50 text-gray-500">Or continue with email</span>
                </div>
            </div>

            <!-- Registration Form -->
            <form class="mt-8 space-y-6" method="POST">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="firstName" class="block text-sm font-medium text-gray-700">
                                First Name
                            </label>
                            <input id="firstName" name="firstName" type="text" 
                                   class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="John"
                                   value="<?php echo htmlspecialchars($_POST['firstName'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="lastName" class="block text-sm font-medium text-gray-700">
                                Last Name
                            </label>
                            <input id="lastName" name="lastName" type="text" 
                                   class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Doe"
                                   value="<?php echo htmlspecialchars($_POST['lastName'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            Username <span class="text-gray-400">(optional)</span>
                        </label>
                        <input id="username" name="username" type="text" 
                               class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="johndoe"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        <p class="mt-1 text-xs text-gray-500">Choose a unique username for your profile</p>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input id="email" name="email" type="email" required 
                               class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="john@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required 
                                   class="form-input mt-1 block w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="••••••••"
                                   onkeyup="checkPasswordStrength(this.value)">
                            <button type="button" onclick="togglePassword('password')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="password-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="password-strength bg-gray-200 rounded-full" id="password-strength"></div>
                            <p class="mt-1 text-xs text-gray-500" id="password-feedback">
                                Password must be at least 6 characters long
                            </p>
                        </div>
                    </div>

                    <div>
                        <label for="confirmPassword" class="block text-sm font-medium text-gray-700">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="confirmPassword" name="confirmPassword" type="password" required 
                                   class="form-input mt-1 block w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="••••••••">
                            <button type="button" onclick="togglePassword('confirmPassword')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="confirmPassword-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Terms and Privacy -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" required
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms" class="text-gray-700">
                            I agree to the 
                            <a href="#" class="text-blue-600 hover:text-blue-500">Terms of Service</a>
                            and 
                            <a href="#" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" name="register" 
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Create Account
                    </button>
                </div>

                <div class="text-center">
                    <span class="text-sm text-gray-600">
                        Already have an account?
                        <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                            Sign in here
                        </a>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <script>
        // OAuth2 signup functions
        function signupWithProvider(provider) {
            const providers = {
                google: '<?php echo $API_BASE; ?>/auth/google',
                facebook: '<?php echo $API_BASE; ?>/auth/facebook',
                microsoft: '#', // Placeholder - would need Microsoft OAuth setup
                twitter: '#'    // Placeholder - would need Twitter OAuth setup
            };
            
            if (providers[provider] && providers[provider] !== '#') {
                window.location.href = providers[provider];
            } else {
                alert(`${provider.charAt(0).toUpperCase() + provider.slice(1)} authentication is not yet configured.`);
            }
        }

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength');
            const feedback = document.getElementById('password-feedback');
            
            let score = 0;
            let feedback_text = '';
            
            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            switch (score) {
                case 0:
                case 1:
                    strengthBar.className = 'password-strength bg-red-400 rounded-full';
                    strengthBar.style.width = '20%';
                    feedback_text = 'Very weak password';
                    break;
                case 2:
                case 3:
                    strengthBar.className = 'password-strength bg-orange-400 rounded-full';
                    strengthBar.style.width = '40%';
                    feedback_text = 'Weak password';
                    break;
                case 4:
                    strengthBar.className = 'password-strength bg-yellow-400 rounded-full';
                    strengthBar.style.width = '60%';
                    feedback_text = 'Moderate password';
                    break;
                case 5:
                    strengthBar.className = 'password-strength bg-green-400 rounded-full';
                    strengthBar.style.width = '80%';
                    feedback_text = 'Strong password';
                    break;
                case 6:
                    strengthBar.className = 'password-strength bg-green-500 rounded-full';
                    strengthBar.style.width = '100%';
                    feedback_text = 'Very strong password';
                    break;
                default:
                    strengthBar.className = 'password-strength bg-gray-200 rounded-full';
                    strengthBar.style.width = '0%';
                    feedback_text = 'Password must be at least 6 characters long';
            }
            
            feedback.textContent = feedback_text;
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const terms = document.getElementById('terms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Please accept the Terms of Service and Privacy Policy');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }
        });
    </script>
</body>
</html>
