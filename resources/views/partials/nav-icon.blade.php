@php
    $name = $name ?? 'dashboard';
    $class = $class ?? 'h-5 w-5';
@endphp

@switch($name)
    @case('dashboard')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 4h7v7H4zM13 4h7v5h-7zM4 13h5v7H4zM11 11h9v9h-9z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('parties')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M16 19a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="10" cy="8" r="3"/>
            <path d="M22 19a4 4 0 0 0-3-3.87M16 4.13a3 3 0 0 1 0 5.74" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('accounts')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="6" width="18" height="12" rx="2"/>
            <path d="M3 10h18M16 14h2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('items')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m12 3 8 4.5-8 4.5-8-4.5 8-4.5Z" stroke-linejoin="round"/>
            <path d="M4 7.5V16.5L12 21l8-4.5V7.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 12v9" stroke-linecap="round"/>
        </svg>
        @break

    @case('expense-categories')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M20.59 13.41 13.4 20.6a2 2 0 0 1-2.82 0L3 13V3h10l7.59 7.59a2 2 0 0 1 0 2.82Z" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="7.5" cy="7.5" r="1.25"/>
        </svg>
        @break

    @case('employees')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="4" y="3" width="16" height="18" rx="2"/>
            <circle cx="12" cy="9" r="2.2"/>
            <path d="M8.5 16c.9-1.5 2-2.3 3.5-2.3s2.6.8 3.5 2.3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('sales')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 16 10 10l4 4 6-6" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14 8h6v6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('purchases')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="9" cy="19" r="1.5"/>
            <circle cx="17" cy="19" r="1.5"/>
            <path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L22 7H7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('payments')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="2.5" y="6" width="19" height="12" rx="2"/>
            <path d="M2.5 10h19M7 14h3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('employee-salaries')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="2.5" y="5" width="19" height="14" rx="2"/>
            <path d="M7 9h10M7 12h10M7 15h6" stroke-linecap="round"/>
        </svg>
        @break

    @case('cashbook')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v18H6.5A2.5 2.5 0 0 0 4 23V5.5Z" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 7h8M8 11h8M8 15h5" stroke-linecap="round"/>
        </svg>
        @break

    @case('profit-loss')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 4v14M6 8h12M4 8l2 4 2-4M16 8l2 4 2-4M8 20h8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('settings')
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 8.5A3.5 3.5 0 1 0 12 15.5A3.5 3.5 0 1 0 12 8.5z"/>
            <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 0 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 0 1-4 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 0 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 0 1 0-4h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 0 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2h.1a1 1 0 0 0 .5-.9V4a2 2 0 0 1 4 0v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 0 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.5H20a2 2 0 0 1 0 4h-.1a1 1 0 0 0-.9.6Z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @default
        <svg class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="8"/>
        </svg>
@endswitch
