<?php
namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function __construct(protected UserRepository $userRepo)
    {
    }

    public function updateProfile($user, array $data)
    {
        // منع تعديل الاسم، الإيميل، أو الهاتف إذا كان هناك نشاط نشط
        if ($user->hasActiveActivity()) {
            $restrictedFields = ['name', 'email', 'phone_number'];
            foreach ($restrictedFields as $field) {
                if (isset($data[$field]) && $data[$field] !== $user->$field) {
                    throw ValidationException::withMessages([
                        $field => 'Cannot update identity fields while having active auctions or bids.'
                    ]);
                }
            }
        }

        // معالجة رفع الصورة إذا وجدت
        if (isset($data['avatar']) && $data['avatar'] instanceof \Illuminate\Http\UploadedFile) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $data['avatar']->store('avatars', 'public');
        }

        return $this->userRepo->update($user, $data);
    }

    public function changePassword($user, $oldPassword, $newPassword)
    {
        if (!Hash::check($oldPassword, $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => 'The provided password does not match our records.'
            ]);
        }
        return $this->userRepo->update($user, ['password' => Hash::make($newPassword)]);
    }

}