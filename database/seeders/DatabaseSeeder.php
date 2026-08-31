<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear permission cache
        try {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore cache backend issues (e.g. missing Redis)
        }

        $roles = [
            'admin',
            'technical_manager',
            'financial_manager',
            'technical_expert',
            'financial_expert',
            'expert',
            'viewer',
        ];

        foreach ($roles as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $permissions = [
            'dashboard.view',
            'report.view',
            'settings.manage',
            'case.view', 'case.create', 'case.update', 'case.delete',
            'case.override_status', 'case.force_close',
            'case.mark_as_won', 'case.mark_as_lost',
            'task.create', 'task.assign', 'task.view_all',
            'contact.view_confidential_notes', 'contact.manage_confidential_notes',
            'document.approve_revision', 'document.delete', 'receivable.manage_payments',
            'template.view', 'template.create', 'template.edit', 'template.delete', 'template.set_default',
            'module.manage', 'user.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::findByName('admin');
        $adminRole->syncPermissions(Permission::all());

        $managerPerms = Permission::whereNotIn('name', [
            'settings.manage', 'module.manage', 'user.manage',
        ])->get();
        Role::findByName('technical_manager')->syncPermissions($managerPerms);
        Role::findByName('financial_manager')->syncPermissions($managerPerms);

        // Experts: basic case + task visibility (no create task, no reports, no dashboard by default)
        $expertPerms = Permission::whereIn('name', [
            'case.view', 'case.create', 'case.update',
            'contact.view_confidential_notes',
        ])->get();
        Role::findByName('technical_expert')->syncPermissions($expertPerms);
        Role::findByName('financial_expert')->syncPermissions($expertPerms);
        Role::findByName('expert')->syncPermissions($expertPerms);

        // Managers also get dashboard + report
        foreach (['technical_manager', 'financial_manager'] as $rn) {
            $r = Role::findByName($rn);
            $r->givePermissionTo(['dashboard.view', 'report.view', 'task.create', 'task.assign', 'task.view_all', 'case.override_status']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدیر سیستم',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        $modules = [
            ['key' => 'core', 'name' => 'پرونده‌ها و گردش‌کار', 'is_core' => true, 'is_enabled' => true],
            ['key' => 'kanban', 'name' => 'کانبان', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'tasks', 'name' => 'وظایف', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'email', 'name' => 'ایمیل', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'documents', 'name' => 'اسناد', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'finance', 'name' => 'مالی', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'contacts', 'name' => 'مخاطبان', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'reports', 'name' => 'گزارش‌گیری', 'is_core' => false, 'is_enabled' => true],
            ['key' => 'templates', 'name' => 'قالب‌ها', 'is_core' => false, 'is_enabled' => true],
        ];

        foreach ($modules as $mod) {
            DB::table('modules')->updateOrInsert(
                ['key' => $mod['key']],
                array_merge($mod, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        foreach (['case', 'technical_proposal', 'financial_proposal', 'invoice'] as $type) {
            DB::table('number_sequences')->updateOrInsert(
                ['type' => $type],
                ['last_number' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        (new TemplateProposalSeeder())->run();

        $this->command->info('Seed OK. Login: admin@example.com / password');
    }
}
