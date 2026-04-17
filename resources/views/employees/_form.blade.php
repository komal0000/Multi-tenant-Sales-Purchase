<div class="space-y-4">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $employee?->party?->name ?? '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
        <input id="phone" name="phone" type="text" value="{{ old('phone', $employee?->party?->phone ?? '') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
    </div>

    <div>
        <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
        <textarea id="address" name="address" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('address', $employee?->party?->address ?? '') }}</textarea>
    </div>

    <div>
        <label for="salary" class="block text-sm font-medium text-gray-700">Base Salary</label>
        <input id="salary" name="salary" type="number" step="0.01" min="0" value="{{ old('salary', $employee ? number_format((float) $employee->salary, 2, '.', '') : '0') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
    </div>
</div>
