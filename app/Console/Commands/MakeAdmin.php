<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * قبل هذا الأمر لم توجد طريقة لصناعة أول أدمن سوى تعديل قاعدة البيانات يدوياً.
 */
class MakeAdmin extends Command
{
    protected $signature = 'topbid:make-admin {email : بريد المستخدم المراد ترقيته}';

    protected $description = 'ترقية مستخدم موجود إلى صلاحية الأدمن';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("{$user->email} is already an admin.");

            return self::SUCCESS;
        }

        $user->update(['is_admin' => true]);

        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
