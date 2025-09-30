<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view products', 'create products', 'edit products', 'delete products',
            'view customers', 'create customers', 'edit customers', 'delete customers',
            'view staff', 'create staff', 'edit staff', 'delete staff',
            'create pos transaction',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Buat Roles (Peran) dan berikan izin yang sudah dibuat
        
        // Role untuk Kasir
        $kasirRole = Role::create(['name' => 'kasir']);
        $kasirRole->givePermissionTo('create pos transaction');

        // Role untuk Admin Cabang
        $adminCabangRole = Role::create(['name' => 'admin-cabang']);
        $adminCabangRole->givePermissionTo([
            'view products', 'create products', 'edit products',
            'view customers', 'create customers', 'edit customers',
            'create pos transaction',
            'view reports',
        ]);

        // Role untuk Super Admin (bisa segalanya)
        $superAdminRole = Role::create(['name' => 'super-admin']);
        // Berikan semua izin yang ada ke super-admin
        $superAdminRole->givePermissionTo(Permission::all());
    }
}