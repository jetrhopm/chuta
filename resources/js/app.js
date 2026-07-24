import './bootstrap';

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import Swiper from 'swiper';
import { A11y, Autoplay, Keyboard, Navigation, Pagination } from 'swiper/modules';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

// El escaparate usa Alpine solo para interacciones ligeras. Filament trae su
// propio Alpine en el panel, por eso este bundle no se carga alli.
window.Alpine = Alpine;

// SweetAlert2 con estilos neutros: los colores salen de los design tokens del
// tema activo, no de la paleta por defecto de la libreria.
window.Swal = Swal.mixin({
    buttonsStyling: false,
    customClass: {
        confirmButton: 'swal-confirm',
        cancelButton: 'swal-cancel',
        denyButton: 'swal-deny',
    },
});

window.Swiper = Swiper;
window.SwiperModules = { A11y, Autoplay, Keyboard, Navigation, Pagination };

Alpine.start();
