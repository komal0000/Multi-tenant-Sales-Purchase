@props([
    'name',
    'label',
    'value' => null,
])

<div x-data="bsDateSelector('{{ $name }}', @js($value))" class="bs-date-picker">
    <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" :value="formattedDate">

    <div class="relative mt-1" @keydown.escape.window="closeCalendar()">
        <div class="fixed inset-0 z-[140] bg-gray-900/40 sm:hidden" x-show="isOpen" x-transition.opacity @click="closeCalendar()" x-cloak></div>
        <div class="relative">
            <input
                type="text"
                x-model="inputValue"
                @focus="openCalendar()"
                @click="openCalendar()"
                @input.debounce.200ms="handleInput()"
                placeholder="YYYY-MM-DD"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 pr-11 text-sm text-slate-700 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
            >
            <button
                type="button"
                class="absolute inset-y-0 right-1.5 inline-flex items-center rounded-lg px-2 text-slate-500 transition hover:bg-slate-100 hover:text-indigo-600"
                @click="toggleCalendar()"
                aria-label="Toggle datepicker"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3m8-3v3M4.75 9.25h14.5M6.5 4.75h11A1.75 1.75 0 0 1 19.25 6.5v11A1.75 1.75 0 0 1 17.5 19.25h-11A1.75 1.75 0 0 1 4.75 17.5v-11A1.75 1.75 0 0 1 6.5 4.75Z" />
                </svg>
            </button>
        </div>

        <div
            x-show="isOpen"
            x-transition.origin.top.left
            @click.outside="closeCalendar()"
            x-cloak
            class="bs-calendar-panel"
        >
            <div class="bs-calendar-head">
                <button type="button" class="bs-calendar-nav" @click="prevMonth()" aria-label="Previous month">&lt;</button>
                <div class="bs-calendar-selectors">
                    <select x-model="viewYear" @change="syncFromSelectors()" class="bs-calendar-select py-1.5 text-sm">
                        <template x-for="yearOption in years" :key="yearOption">
                            <option :value="String(yearOption)" x-text="yearOption"></option>
                        </template>
                    </select>
                    <select x-model="viewMonth" @change="syncFromSelectors()" class="bs-calendar-select py-1.5 text-sm">
                        <template x-for="monthOption in 12" :key="monthOption">
                            <option :value="pad(monthOption)" x-text="monthLabel(monthOption)"></option>
                        </template>
                    </select>
                </div>
                <button type="button" class="bs-calendar-nav" @click="nextMonth()" aria-label="Next month">&gt;</button>
            </div>

            <div class="bs-calendar-grid">
                <template x-for="weekday in weekdays" :key="weekday">
                    <div class="bs-calendar-weekday" x-text="weekday"></div>
                </template>

                <template x-for="(week, weekIndex) in calendarDays" :key="`week-${weekIndex}`">
                    <template x-for="(cellDay, dayIndex) in week" :key="`day-${weekIndex}-${dayIndex}`">
                        <button
                            type="button"
                            class="bs-calendar-day"
                            :class="{
                                'is-empty': !cellDay,
                                'is-today': isToday(cellDay),
                                'is-selected': isSelected(cellDay)
                            }"
                            :disabled="!cellDay"
                            @click="selectDay(cellDay)"
                            x-text="cellDay || ''"
                        ></button>
                    </template>
                </template>
            </div>

            <div class="bs-calendar-footer">
                <button type="button" class="bs-calendar-link" @click="setToday()">Today</button>
                <button type="button" class="bs-calendar-link" @click="clearDate()">Clear</button>
            </div>
        </div>
    </div>

</div>
