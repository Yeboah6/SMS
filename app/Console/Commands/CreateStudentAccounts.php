<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\StudentAccountService;
use Illuminate\Console\Command;

class CreateStudentAccounts extends Command
{
    protected $signature = 'students:create-accounts';

    protected $description = 'Create login accounts (admission number + default password) for students who have none';

    public function handle(StudentAccountService $accounts): int
    {
        $count = 0;

        Student::query()->whereNull('user_id')->chunkById(100, function ($students) use ($accounts, &$count): void {
            foreach ($students as $student) {
                $accounts->createFor($student);
                $count++;
            }
        });

        $this->info("Created {$count} student account(s).");

        return self::SUCCESS;
    }
}
