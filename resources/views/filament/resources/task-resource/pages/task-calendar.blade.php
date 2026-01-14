<x-filament-panels::page>
    @push('styles')
        <!-- FullCalendar CSS -->
        <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/main.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.15/main.min.css" rel="stylesheet" />
    @endpush

    <div class="space-y-6">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button 
                    type="button"
                    wire:click="previousMonth"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    ← Sebelumnya
                </button>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->locale('id')->translatedFormat('F Y') }}
                </h2>
                <button 
                    type="button"
                    wire:click="nextMonth"
                    class="px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    Selanjutnya →
                </button>
                <button 
                    type="button"
                    wire:click="goToToday"
                    class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm"
                >
                    Hari Ini
                </button>
            </div>
            <a 
                href="{{ \App\Filament\Resources\TaskResource::getUrl('create') }}"
                class="px-5 py-2.5 text-sm font-medium text-white bg-primary-600 dark:bg-primary-500 border border-transparent rounded-lg hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm"
            >
                + Tambah Pekerjaan
            </a>
        </div>

        <!-- FullCalendar Container -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-lg p-4">
            <div 
                id="task-calendar" 
                data-events="{{ json_encode($this->getFullCalendarEvents()) }}"
                wire:ignore
                style="min-height: 600px;"
            ></div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-6 text-sm pt-4 border-t-2 border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #fde68a; border-color: #fbbf24;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Pending</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #fbbf24; border-color: #f59e0b;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">In Progress</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #86efac; border-color: #4ade80;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Completed</span>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded border-2" style="background-color: #fca5a5; border-color: #f87171;"></div>
                <span class="text-gray-700 dark:text-gray-300 font-medium">Cancelled</span>
            </div>
        </div>
    </div>

    <script>
        // Load scripts sequentially
        function loadScript(src, callback) {
            const script = document.createElement('script');
            script.src = src;
            script.onload = callback;
            script.onerror = function() {
                console.error('Failed to load script:', src);
            };
            document.head.appendChild(script);
        }

        function initTaskCalendar() {
            if (window.taskCalendar) {
                return;
            }
            
            const calendarEl = document.getElementById('task-calendar');
            if (!calendarEl) {
                console.error('Calendar element not found');
                return;
            }
            
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar is not defined');
                return;
            }
            
            try {
                const events = JSON.parse(calendarEl.dataset.events || '[]');
                
                window.taskCalendar = new FullCalendar.Calendar(calendarEl, {
                    plugins: [FullCalendar.dayGridPlugin, FullCalendar.interactionPlugin],
                    locale: 'id',
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    firstDay: 1,
                    height: 'auto',
                    events: events,
                    eventDisplay: 'block',
                    eventColor: function(info) {
                        const status = info.event.extendedProps.status;
                        const statusColors = {
                            'pending': '#fde68a',
                            'in_progress': '#fbbf24',
                            'completed': '#86efac',
                            'cancelled': '#fca5a5'
                        };
                        return statusColors[status] || statusColors['pending'];
                    },
                    eventTextColor: function(info) {
                        const status = info.event.extendedProps.status;
                        const textColors = {
                            'pending': '#78350f',
                            'in_progress': '#78350f',
                            'completed': '#14532d',
                            'cancelled': '#7f1d1d'
                        };
                        return textColors[status] || textColors['pending'];
                    },
                    eventClick: function(info) {
                        const url = info.event.extendedProps.editUrl;
                        if (url) {
                            window.location.href = url;
                        }
                    },
                    eventDidMount: function(info) {
                        info.el.style.borderRadius = '0.5rem';
                        info.el.style.borderWidth = '2px';
                        info.el.style.fontWeight = '600';
                        info.el.style.padding = '0.5rem';
                        info.el.style.cursor = 'pointer';
                    }
                });
                
                window.taskCalendar.render();
                console.log('FullCalendar initialized successfully');
            } catch (error) {
                console.error('Error initializing FullCalendar:', error);
            }
        }

        // Load scripts in order
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/main.min.js', function() {
                    loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.15/main.min.js', function() {
                        loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.15/main.min.js', function() {
                            loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/id.global.min.js', function() {
                                setTimeout(initTaskCalendar, 100);
                            });
                        });
                    });
                });
            });
        } else {
            loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/main.min.js', function() {
                loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.15/main.min.js', function() {
                    loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.15/main.min.js', function() {
                        loadScript('https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/id.global.min.js', function() {
                            setTimeout(initTaskCalendar, 100);
                        });
                    });
                });
            });
        }
        
        // Handle Livewire updates
        document.addEventListener('livewire:init', function() {
            if (window.Livewire) {
                Livewire.hook('morph.updated', () => {
                    setTimeout(() => {
                        const calendarEl = document.getElementById('task-calendar');
                        if (calendarEl && window.taskCalendar) {
                            try {
                                const events = JSON.parse(calendarEl.dataset.events || '[]');
                                window.taskCalendar.removeAllEvents();
                                window.taskCalendar.addEventSource(events);
                                const currentDate = new Date({{ $this->currentYear }}, {{ $this->currentMonth }} - 1, 1);
                                window.taskCalendar.gotoDate(currentDate);
                            } catch (error) {
                                console.error('Error updating calendar:', error);
                            }
                        }
                    }, 200);
                });
            }
        });
    </script>

    <style>
        /* FullCalendar Custom Styles */
        .fc {
            font-family: inherit;
        }
        
        .fc-toolbar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: rgb(17, 24, 39);
        }
        
        .dark .fc-toolbar-title {
            color: rgb(243, 244, 246);
        }
        
        .fc-button {
            background-color: white;
            border-color: rgb(209, 213, 219);
            color: rgb(55, 65, 81);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }
        
        .dark .fc-button {
            background-color: rgb(31, 41, 55);
            border-color: rgb(55, 65, 75);
            color: rgb(209, 213, 219);
        }
        
        .fc-button:hover {
            background-color: rgb(249, 250, 251);
        }
        
        .dark .fc-button:hover {
            background-color: rgb(55, 65, 75);
        }
        
        .fc-button-primary:not(:disabled).fc-button-active {
            background-color: rgb(217, 119, 6);
            border-color: rgb(217, 119, 6);
            color: white;
        }
        
        .fc-daygrid-day {
            background-color: white;
            min-height: 120px;
        }
        
        .dark .fc-daygrid-day {
            background-color: rgb(31, 41, 55);
        }
        
        .fc-daygrid-day-number {
            color: rgb(17, 24, 39);
            font-weight: 600;
        }
        
        .dark .fc-daygrid-day-number {
            color: rgb(243, 244, 246);
        }
        
        .fc-day-today {
            background-color: rgb(254, 252, 232) !important;
        }
        
        .dark .fc-day-today {
            background-color: rgb(41, 37, 36) !important;
        }
        
        .fc-col-header-cell {
            background-color: rgb(249, 250, 251);
            border-color: rgb(229, 231, 235);
        }
        
        .dark .fc-col-header-cell {
            background-color: rgb(17, 24, 27);
            border-color: rgb(55, 65, 75);
        }
        
        .fc-col-header-cell-cushion {
            color: rgb(55, 65, 81);
            font-weight: 700;
        }
        
        .dark .fc-col-header-cell-cushion {
            color: rgb(209, 213, 219);
        }
        
        .fc-event {
            border-radius: 0.5rem;
            border-width: 2px;
            font-weight: 600;
            padding: 0.5rem;
            cursor: pointer;
        }
        
        .fc-event:hover {
            opacity: 0.9;
        }
    </style>
</x-filament-panels::page>
