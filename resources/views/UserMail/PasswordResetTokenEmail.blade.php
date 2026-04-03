<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Token</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
        }
        h1 {
            color: #333333;
        }
        p {
            color: #555555;
            font-size: 16px;
        }
        .token {
            display: inline-block;
            margin: 20px 0;
            padding: 15px 20px;
            font-size: 16px;
            background-color: #f0f0f0;
            border-radius: 8px;
            color: #111111;
            font-weight: bold;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Your Password</h1>
        <p>Use the token below to reset your password.</p>
        <div class="token">{{ $token }}</div>
        <p>This token expires based on your app password reset settings.</p>
    </div>
</body>
</html>
