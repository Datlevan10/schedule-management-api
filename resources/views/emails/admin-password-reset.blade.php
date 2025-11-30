<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset by Administrator</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .password-box {
            background-color: #fff;
            border: 2px solid #4CAF50;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .footer {
            background-color: #f1f1f1;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            border: 1px solid #ddd;
            border-top: none;
            font-size: 12px;
            color: #666;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Password Reset Notification</h1>
    </div>

    <div class="content">
        <h2>Hello {{ $user->name }},</h2>
        
        <p>Your account password has been reset by an administrator for the following reason:</p>
        
        <p><strong>Administrator:</strong> {{ $admin->name }} ({{ $admin->email }})</p>
        <p><strong>Reset Date:</strong> {{ $reset_date->format('F j, Y \a\t g:i A') }}</p>
        
        <div class="password-box">
            <strong>Your New Password:</strong><br>
            {{ $new_password }}
        </div>

        <div class="warning">
            <strong>⚠️ Security Notice:</strong>
            <ul>
                <li>Please log in with this new password immediately</li>
                <li>Change your password to something memorable after logging in</li>
                <li>Keep this password secure and do not share it with anyone</li>
                <li>Delete this email after you have successfully changed your password</li>
            </ul>
        </div>

        <p>To log in with your new password:</p>
        <ol>
            <li>Go to the login page</li>
            <li>Enter your email: <strong>{{ $user->email }}</strong></li>
            <li>Enter the new password provided above</li>
            <li>Immediately change your password in your profile settings</li>
        </ol>

        <p>If you did not request this password reset or have any security concerns, please contact our support team immediately.</p>
        
        <p>Best regards,<br>
        Schedule Management System<br>
        Administrator Team</p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>If you have any questions, please contact our support team.</p>
        <p>&copy; {{ date('Y') }} Schedule Management System. All rights reserved.</p>
    </div>
</body>
</html>