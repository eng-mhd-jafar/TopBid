# Authentication API (JWT)

Base URL: `/api`

## 1) Register

- **POST** `/auth/register`
- **Content-Type** `multipart/form-data`
- **Body**
| key | type | required | example |
|---|---|---|---|
| `name` | text | yes | `Ahmad Ali` |
| `email` | text | yes | `ahmad@example.com` |
| `password` | text | yes | `Password@123` |
| `password_confirmation` | text | yes | `Password@123` |
| `bio` | text | no | `Backend developer from Damascus` |
| `avatar` | file (jpg/jpeg/png, max 2MB) | no | `profile.jpg` |
| `phone_number` | text | no | `0999999999` |
- **Success** `201`

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Ahmad Ali",
      "email": "ahmad@example.com",
      "phone_number": "0999999999",
      "bio": "Backend developer from Damascus",
      "avatar": "http://localhost:8000/storage/avatars/xxx.jpg"
    }
  },
  "message": "Registered successfully. Please verify OTP sent to your email."
}
```

## 2) Verify OTP

- **POST** `/auth/verify-otp`
- **Body**

```json
{
  "email": "ahmad@example.com",
  "OTP": "1234"
}
```

- **Success** `200` (returns JWT)
- **Error** `422` when OTP invalid/expired.

## 3) Resend OTP

- **POST** `/auth/resend-otp`
- **Body**

```json
{
  "email": "ahmad@example.com"
}
```

- **Success** `200`

## 4) Login

- **POST** `/auth/login`
- **Body**

```json
{
  "email": "ahmad@example.com",
  "password": "Password@123"
}
```

- **Success** `200` (returns JWT)
- **Error** `401` invalid credentials or account not verified.

## 5) Refresh Session

- **POST** `/auth/refresh`
- **Headers**: لا يوجد شرط إرسال `Authorization`
- **Body**

```json
{
  "refresh_token": "REFRESH_TOKEN"
}
```

- **Success** `200` (يرجع `access_token` جديد و `refresh_token` جديد)

## 6) Forgot Password

- **POST** `/auth/forgot-password`
- **Body**

```json
{
  "email": "ahmad@example.com"
}
```

- **Success** `200`
- Always generic message to avoid exposing whether email exists.

## 7) Reset Password

- **POST** `/auth/reset-password`
- **Body**

```json
{
  "email": "ahmad@example.com",
  "token": "TOKEN_FROM_EMAIL",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}
```

- **Success** `200`
- **Error** `422` invalid reset data.

## 8) Change Password (Logged-in user)

- **PUT** `/me/password`
- **Headers** `Authorization: Bearer <jwt_token>`
- **Body**

```json
{
  "old_password": "OldPassword@123",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}
```

- **Success** `200`

## 9) JWT Session invalidation on password change

- After changing or resetting password, old JWTs become invalid automatically.
- User must login again with new password.

## 10) Rate limits

- `/auth/login` -> 5 requests/minute (per email + IP)
- `/auth/forgot-password` -> 3 requests/minute (per email + IP)
- `/auth/reset-password` -> 5 requests/minute (per email + IP)
- `/auth/verify-otp` and `/auth/resend-otp` use OTP limiter.
