<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="color: #0056b3;">Password Reset Request</h2>
        <p>Hello {{ $name }},</p>
        <p>We received a request to reset your password for your FinZ Admin account.</p>
        <p>You can reset your password by clicking the link below:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" style="background-color: #0056b3; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">Reset Password</a>
        </p>
        <p>This link will expire in {{ $expires }}.</p>
        <p>If you did not request a password reset, no further action is required.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 0.8em; color: #888;">If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:</p>
        <p style="font-size: 0.8em; color: #888; word-break: break-all;">{{ $resetUrl }}</p>
    </div>
</body>
</html>
