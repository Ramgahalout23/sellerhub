<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Rajesh Traders',
                'contact_person' => 'Rajesh Kumar',
                'phone' => '9876543210',
                'email' => 'rajesh@traders.in',
                'address' => 'Shop 45, Sadar Bazaar, Delhi - 110006',
                'notes' => 'Main supplier for electronics accessories',
                'is_active' => true,
            ],
            [
                'name' => 'Patel Wholesale Hub',
                'contact_person' => 'Mahesh Patel',
                'phone' => '9123456780',
                'email' => 'mahesh@patelhub.com',
                'address' => '23, Manek Chowk, Ahmedabad - 380001',
                'notes' => 'Home & kitchen items supplier',
                'is_active' => true,
            ],
            [
                'name' => 'Shree Ganesh Traders',
                'contact_person' => 'Suresh Sharma',
                'phone' => '9988776655',
                'email' => 'suresh@sgtraders.in',
                'address' => '12, Lajpat Nagar Market, New Delhi - 110024',
                'notes' => 'Clothing and fashion accessories',
                'is_active' => true,
            ],
            [
                'name' => 'Balaji Enterprises',
                'contact_person' => 'Anil Agarwal',
                'phone' => '9871234567',
                'email' => 'anil@balajienterprise.com',
                'address' => '67, Sadar Bazaar, Jaipur - 302001',
                'notes' => 'Budget phone cases and covers',
                'is_active' => true,
            ],
            [
                'name' => 'Sri Krishna Industries',
                'contact_person' => 'Venkat Reddy',
                'phone' => '9445566778',
                'email' => 'venkat@skindustries.in',
                'address' => '34, Ameerpet, Hyderabad - 500016',
                'notes' => 'Stationery and office supplies — currently inactive',
                'is_active' => false,
            ],
        ];

        foreach ($suppliers as $data) {
            Supplier::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
