import './bootstrap';

// Impor Livewire ke Vite
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// (Opsional) Alpine.js secara global
window.Alpine = Alpine;

Livewire.start();