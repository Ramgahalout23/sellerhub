<?php

namespace Database\Seeders;

use App\Models\CustomField;
use Illuminate\Database\Seeder;

class CustomFieldSeeder extends Seeder
{
    public function run(): void
    {
        // --- Product custom fields ---
        $productFields = [
            ['name' => 'Packing Charge', 'slug' => 'packing_charge', 'field_type' => 'number', 'sort_order' => 1],
            ['name' => 'Weight (grams)', 'slug' => 'weight_grams', 'field_type' => 'number', 'sort_order' => 2],
            ['name' => 'HSN Code', 'slug' => 'hsn_code', 'field_type' => 'text', 'sort_order' => 3],
            ['name' => 'Color Variant', 'slug' => 'color_variant', 'field_type' => 'text', 'sort_order' => 4],
            ['name' => 'Size', 'slug' => 'size', 'field_type' => 'select', 'options' => ['values' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size']], 'sort_order' => 5],
        ];

        foreach ($productFields as $field) {
            CustomField::updateOrCreate(
                ['slug' => $field['slug']],
                array_merge($field, ['entity_type' => 'product', 'is_active' => true])
            );
        }

        // --- Sale charge custom fields ---
        $chargeFields = [
            ['name' => 'Shipping Charge', 'slug' => 'sale_shipping_charge', 'field_type' => 'number', 'sort_order' => 1],
            ['name' => 'GST', 'slug' => 'sale_gst', 'field_type' => 'number', 'sort_order' => 2],
            ['name' => 'Ads Cost', 'slug' => 'ads_cost', 'field_type' => 'number', 'sort_order' => 3],
            ['name' => 'Platform Commission', 'slug' => 'platform_commission', 'field_type' => 'number', 'sort_order' => 4],
            ['name' => 'Payment Gateway Fee', 'slug' => 'payment_gateway_fee', 'field_type' => 'number', 'sort_order' => 5],
        ];

        foreach ($chargeFields as $field) {
            CustomField::updateOrCreate(
                ['slug' => $field['slug']],
                array_merge($field, ['entity_type' => 'sale_charge', 'is_active' => true])
            );
        }

        // --- Return charge custom fields ---
        $returnFields = [
            ['name' => 'Return Shipping Charge', 'slug' => 'return_shipping_charge', 'field_type' => 'number', 'sort_order' => 1],
            ['name' => 'RTO Handling Fee', 'slug' => 'rto_handling_fee', 'field_type' => 'number', 'sort_order' => 2],
            ['name' => 'Damage Assessment', 'slug' => 'damage_assessment', 'field_type' => 'number', 'sort_order' => 3],
            ['name' => 'Restocking Fee', 'slug' => 'restocking_fee', 'field_type' => 'number', 'sort_order' => 4],
        ];

        foreach ($returnFields as $field) {
            CustomField::updateOrCreate(
                ['slug' => $field['slug']],
                array_merge($field, ['entity_type' => 'return_charge', 'is_active' => true])
            );
        }
    }
}
