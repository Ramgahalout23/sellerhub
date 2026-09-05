@extends('layouts.app')
@section('title', 'Add Supplier')

@section('content')
<div class="max-w-3xl">
    <x-card>
        <form action="{{ route('suppliers.store') }}" method="POST" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Supplier Name *</label>
                    <input type="text" name="name" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Contact Person</label>
                    <input type="text" name="contact_person" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('contact_person') }}">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone</label>
                    <input type="text" name="phone" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" value="{{ old('email') }}">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Address</label>
                <textarea name="address" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" rows="2">{{ old('address') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="lg:static fixed bottom-16 left-0 right-0 lg:bottom-auto lg:left-auto lg:right-auto z-40 lg:z-auto bg-white lg:bg-transparent">
                <div class="flex items-center gap-3 border-t border-gray-100 pt-5 pb-4 lg:pb-5 px-4 lg:px-0 shadow-[0_-2px_10px_rgba(0,0,0,0.06)] lg:shadow-none">
                    <button type="submit" class="touch-target rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">Create Supplier</button>
                    <a href="{{ route('suppliers.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                </div>
            </div>
        </form>
    </x-card>
</div>
@endsection
