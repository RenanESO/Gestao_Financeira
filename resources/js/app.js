import './bootstrap';
import swipeRow from './swipe-row';
import themeColor from './theme-color';

// Alpine e fornecido pelo Livewire; o componente e registrado antes de iniciar.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('swipeRow', swipeRow);
    window.Alpine.data('themeColor', themeColor);
});
