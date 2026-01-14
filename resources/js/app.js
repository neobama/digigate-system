import './bootstrap';
import '../css/app.css';

// FullCalendar imports
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

// Initialize FullCalendar if element exists
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('task-calendar');
    if (calendarEl) {
        const calendar = new Calendar(calendarEl, {
            plugins: [dayGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            locale: 'id',
            firstDay: 1, // Monday
            height: 'auto',
            events: JSON.parse(calendarEl.dataset.events || '[]'),
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
                // Add custom styling
                info.el.style.borderRadius = '0.5rem';
                info.el.style.borderWidth = '2px';
                info.el.style.fontWeight = '600';
                info.el.style.padding = '0.5rem';
            }
        });
        
        calendar.render();
        
        // Handle Livewire updates
        if (window.Livewire) {
            Livewire.on('taskCalendarUpdated', (data) => {
                calendar.removeAllEvents();
                calendar.addEventSource(JSON.parse(data.events || '[]'));
            });
        }
    }
});