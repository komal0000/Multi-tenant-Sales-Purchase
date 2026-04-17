window.bsDateConfig = window.bsDateConfig || { years: [], months: [], monthMap: [] };

function bsDateSelector(name, initialValue = null) {
    const config = window.bsDateConfig || {};
    const years = (config.years || []).map((year) => String(year));
    const months = config.months || {};
    const monthMap = config.monthMap || {};
    const weekdays = config.weekdays || ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const startEnglishDate = String(config.startEnglishDate || '1943-04-14');
    const startNepaliYear = Number(config.startNepaliYear || (years[0] || 2000));
    const today = String(config.today || '');

    const epochDate = new Date(`${startEnglishDate}T00:00:00Z`);
    const epochWeekday = Number.isNaN(epochDate.getTime()) ? 3 : epochDate.getUTCDay();

    return {
        name,
        year: '',
        month: '',
        day: '',
        viewYear: years[years.length - 1] || '',
        viewMonth: '01',
        inputValue: '',
        isOpen: false,
        years,
        months,
        monthMap,
        weekdays,
        today,
        calendarDays: [],
        init() {
            if (initialValue) {
                if (!this.applyDateString(String(initialValue), true)) {
                    this.generateCalendar();
                }
            } else {
                this.generateCalendar();
            }
        },
        toggleCalendar() {
            if (this.isOpen) {
                this.closeCalendar();
                return;
            }

            this.openCalendar();
        },
        openCalendar() {
            if (this.year && this.month) {
                this.viewYear = this.year;
                this.viewMonth = this.month;
            } else {
                const parsedToday = this.parseDate(this.today);
                if (parsedToday) {
                    this.viewYear = String(parsedToday.year);
                    this.viewMonth = this.pad(parsedToday.month);
                }
            }

            this.generateCalendar();
            this.isOpen = true;
        },
        closeCalendar() {
            this.isOpen = false;
        },
        handleInput() {
            const parsed = this.parseDate(this.inputValue);
            if (!parsed) {
                this.year = '';
                this.month = '';
                this.day = '';

                return;
            }

            this.setSelectedDate(parsed.year, parsed.month, parsed.day, true, false);
        },
        syncFromSelectors() {
            this.generateCalendar();
        },
        prevMonth() {
            if (!this.viewYear || !this.viewMonth) {
                return;
            }

            let targetYear = Number(this.viewYear);
            let targetMonth = Number(this.viewMonth) - 1;

            if (targetMonth <= 0) {
                targetMonth = 12;
                targetYear -= 1;
            }

            if (!this.monthMap[String(targetYear)]) {
                return;
            }

            this.viewYear = String(targetYear);
            this.viewMonth = this.pad(targetMonth);
            this.generateCalendar();
        },
        nextMonth() {
            if (!this.viewYear || !this.viewMonth) {
                return;
            }

            let targetYear = Number(this.viewYear);
            let targetMonth = Number(this.viewMonth) + 1;

            if (targetMonth > 12) {
                targetMonth = 1;
                targetYear += 1;
            }

            if (!this.monthMap[String(targetYear)]) {
                return;
            }

            this.viewYear = String(targetYear);
            this.viewMonth = this.pad(targetMonth);
            this.generateCalendar();
        },
        selectDay(dayValue) {
            if (!dayValue || !this.viewYear || !this.viewMonth) {
                return;
            }

            this.setSelectedDate(Number(this.viewYear), Number(this.viewMonth), Number(dayValue), true, true);
        },
        setToday() {
            const parsed = this.parseDate(this.today);
            if (!parsed) {
                return;
            }

            this.setSelectedDate(parsed.year, parsed.month, parsed.day, true, true);
        },
        clearDate() {
            this.year = '';
            this.month = '';
            this.day = '';
            this.inputValue = '';
            this.closeCalendar();
        },
        setSelectedDate(year, month, day, updateView = true, closeAfter = false) {
            this.year = String(year);
            this.month = this.pad(month);
            this.day = this.pad(day);

            if (updateView) {
                this.viewYear = this.year;
                this.viewMonth = this.month;
            }

            this.inputValue = this.formattedDate;
            this.generateCalendar();

            if (closeAfter) {
                this.closeCalendar();
            }
        },
        applyDateString(value, updateView = true) {
            const parsed = this.parseDate(value);
            if (!parsed) {
                return false;
            }

            this.setSelectedDate(parsed.year, parsed.month, parsed.day, updateView, false);
            return true;
        },
        parseDate(value) {
            const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!match) {
                return null;
            }

            const year = Number(match[1]);
            const month = Number(match[2]);
            const day = Number(match[3]);

            if (!this.monthMap[String(year)] || month < 1 || month > 12) {
                return null;
            }

            const daysInMonth = this.getDaysInMonth(year, month);
            if (day < 1 || day > daysInMonth) {
                return null;
            }

            return { year, month, day };
        },
        getDaysInMonth(year, month) {
            const monthsOfYear = this.monthMap[String(year)] || [];
            return Number(monthsOfYear[month - 1] || 0);
        },
        getFirstWeekday(year, month) {
            let elapsedDays = 0;

            for (let bsYear = startNepaliYear; bsYear < year; bsYear += 1) {
                const monthsOfYear = this.monthMap[String(bsYear)] || [];
                for (const monthDays of monthsOfYear) {
                    elapsedDays += Number(monthDays || 0);
                }
            }

            const monthsOfCurrentYear = this.monthMap[String(year)] || [];
            for (let bsMonth = 1; bsMonth < month; bsMonth += 1) {
                elapsedDays += Number(monthsOfCurrentYear[bsMonth - 1] || 0);
            }

            return (epochWeekday + elapsedDays) % 7;
        },
        generateCalendar() {
            this.calendarDays = [];

            const year = Number(this.viewYear);
            const month = Number(this.viewMonth);
            if (!year || !month) {
                return;
            }

            const daysInMonth = this.getDaysInMonth(year, month);
            if (!daysInMonth) {
                return;
            }

            const firstWeekday = this.getFirstWeekday(year, month);
            const cells = [];

            for (let index = 0; index < firstWeekday; index += 1) {
                cells.push(0);
            }

            for (let date = 1; date <= daysInMonth; date += 1) {
                cells.push(date);
            }

            while (cells.length % 7 !== 0) {
                cells.push(0);
            }

            while (cells.length) {
                this.calendarDays.push(cells.splice(0, 7));
            }
        },
        monthLabel(monthValue) {
            return this.months[String(Number(monthValue))] || `Month ${monthValue}`;
        },
        pad(value) {
            return String(value).padStart(2, '0');
        },
        isToday(dayValue) {
            if (!dayValue || !this.today) {
                return false;
            }

            const parsedToday = this.parseDate(this.today);
            if (!parsedToday) {
                return false;
            }

            return Number(this.viewYear) === parsedToday.year
                && Number(this.viewMonth) === parsedToday.month
                && Number(dayValue) === parsedToday.day;
        },
        isSelected(dayValue) {
            if (!dayValue || !this.year || !this.month || !this.day) {
                return false;
            }

            return Number(this.viewYear) === Number(this.year)
                && Number(this.viewMonth) === Number(this.month)
                && Number(dayValue) === Number(this.day);
        },
        get formattedDate() {
            if (!this.year || !this.month || !this.day) {
                return '';
            }

            return `${this.year}-${this.pad(this.month)}-${this.pad(this.day)}`;
        },
    };
}

window.bsDateSelector = bsDateSelector;

document.addEventListener('alpine:init', () => {
    window.bsDateSelector = bsDateSelector;
});

document.addEventListener('DOMContentLoaded', () => {
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        window.jQuery('select.select2').select2({ width: '100%' });
    }

    // Safely scroll active sidebar items into view on load
    setTimeout(() => {
        const activeSidebarItems = document.querySelectorAll('aside .is-active');
        activeSidebarItems.forEach((item) => {
            const scrollContainer = item.closest('.overflow-y-auto');
            if (scrollContainer) {
                const containerRect = scrollContainer.getBoundingClientRect();
                const itemRect = item.getBoundingClientRect();

                // If the item is not fully visible within the container viewport
                if (itemRect.top < containerRect.top || itemRect.bottom > containerRect.bottom) {
                    // Compute absolute center coordinate target
                    const scrollTarget = scrollContainer.scrollTop + (itemRect.top - containerRect.top) - (containerRect.height / 2) + (itemRect.height / 2);
                    
                    scrollContainer.scrollTo({
                        top: scrollTarget,
                        behavior: 'smooth'
                    });
                }
            }
        });
    }, 150); // Delay ensures the browser layout computations have perfectly settled
});
