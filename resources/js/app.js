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

/**
 * Carrusel accesible del escaparate.
 *
 * Se pausa al interactuar y al pasar el puntero, se puede manejar con el
 * teclado y respeta a quien pidio menos movimiento en su sistema: en ese caso
 * no avanza solo, para que una animacion no bloquee la lectura.
 */
Alpine.data('carrusel', () => ({
    swiper: null,

    iniciar(el) {
        const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.swiper = new Swiper(el, {
            modules: [A11y, Autoplay, Keyboard, Navigation, Pagination],
            loop: true,
            speed: menosMovimiento ? 0 : 500,
            autoplay: menosMovimiento
                ? false
                : { delay: 6000, pauseOnMouseEnter: true, disableOnInteraction: true },
            keyboard: { enabled: true },
            a11y: {
                enabled: true,
                prevSlideMessage: 'Promocion anterior',
                nextSlideMessage: 'Promocion siguiente',
                paginationBulletMessage: 'Ir a la promocion {{index}}',
            },
            navigation: {
                prevEl: el.querySelector('.swiper-button-prev'),
                nextEl: el.querySelector('.swiper-button-next'),
            },
            pagination: {
                el: el.querySelector('.swiper-pagination'),
                clickable: true,
            },
        });
    },

    destroy() {
        this.swiper?.destroy();
    },
}));

Alpine.start();
