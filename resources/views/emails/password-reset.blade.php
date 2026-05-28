<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f5f7;
            color: #334155;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: none;
            -ms-text-size-adjust: none;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f5f7;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            padding: 32px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 40px 32px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .text {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background-color: #4f46e5;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            padding: 14px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2), 0 2px 4px -1px rgba(79, 70, 229, 0.1);
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #4338ca;
        }
        .expiry-alert {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .expiry-text {
            font-size: 14px;
            color: #b45309;
            margin: 0;
            font-weight: 500;
        }
        .security-note {
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
            margin-top: 32px;
        }
        .footer {
            padding: 32px;
            background-color: #f8fafc;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0 0 8px 0;
        }
        .footer a {
            color: #6366f1;
            text-decoration: none;
        }
        /* Fallback for email clients that strip style tags */
        @media only screen and (max-width: 600px) {
            .content {
                padding: 32px 20px !important;
            }
            .header {
                padding: 24px !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1>FinZ Admin</h1>
            </div>

            <!-- Content -->
            <div class="content">
                <p class="greeting">Hello {{ $name }},</p>
                
                <p class="text">
                    We received a request to reset the password for your FinZ Admin account. Please use the following 6-digit code to reset your password:
                </p>

                <!-- Code -->
                <div style="font-size: 32px; font-weight: bold; letter-spacing: 6px;
                            background-color: #f1f5f9; padding: 18px; text-align: center;
                            border-radius: 8px; margin: 24px 0; color: #4f46e5; border: 1px solid #e2e8f0;">
                    {{ $code }}
                </div>

                <!-- Expiry Alert -->
                <div class="expiry-alert">
                    <p class="expiry-text">
                        <strong>Important:</strong> This password reset code will expire in {{ $expires }} and can only be used once.
                    </p>
                </div>

                <p class="text">
                    If you did not request a password reset, no further action is required. Your account security is safe.
                </p>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>&copy; {{ date('Y') }} FinZ Admin. All rights reserved.</p>
                <p>This is an automated security email. Please do not reply to this message.</p>
            </div>
        </div>
    </div>
</body>
</html>
