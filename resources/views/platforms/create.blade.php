@extends('layouts.app')
@section('title', 'Add Platform')

@section('content')
<div class="max-w-3xl">
    <x-card>
        <form action="{{ route('platforms.store') }}" method="POST" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform Name *</label>
                    <input type="text" name="name" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('name') }}" required placeholder="e.g. Amazon">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Slug</label>
                    <input type="text" name="slug" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('slug') }}" placeholder="auto-generated">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Charge Structure</label>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Commission %</label><input type="number" name="charge_structure[commission_percent]" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ old('charge_structure.commission_percent', 0) }}" step="0.01"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Shipping (₹)</label><input type="number" name="charge_structure[shipping_fee]" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ old('charge_structure.shipping_fee', 0) }}" step="0.01"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Closing (₹)</label><input type="number" name="charge_structure[closing_fee]" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ old('charge_structure.closing_fee', 0) }}" step="0.01"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">GST %</label><input type="number" name="charge_structure[gst_percent]" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ old('charge_structure.gst_percent', 0) }}" step="0.01"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">Other (₹)</label><input type="number" name="charge_structure[other_fee]" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none" value="{{ old('charge_structure.other_fee', 0) }}" step="0.01"></div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                <select name="is_active" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ old('is_active') == 0 ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="lg:static fixed bottom-16 left-0 right-0 lg:bottom-auto lg:left-auto lg:right-auto z-40 lg:z-auto bg-white lg:bg-transparent">
                <div class="flex items-center gap-3 border-t border-gray-100 pt-5 pb-4 lg:pb-5 px-4 lg:px-0 shadow-[0_-2px_10px_rgba(0,0,0,0.06)] lg:shadow-none">
                    <button type="submit" class="touch-target rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">Create Platform</button>
                    <a href="{{ route('platforms.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                </div>
            </div>
        </form>
    </x-card>
</div>
@endsection
