<?php

/*
|--------------------------------------------------------------------------
| Contenido del escaparate
|--------------------------------------------------------------------------
|
| Contenido de arranque de la portada. La Etapa 5 lo mueve a la base de datos
| para que los carruseles y los accesos de categoria se administren desde el
| panel con orden, vigencia y vista previa; hasta entonces vive aqui para no
| dejarlo escrito dentro de las plantillas.
|
| Las imagenes apuntan a la biblioteca de medios de la tienda actual, igual que
| las de los productos. Antes de apuntar el dominio a esta aplicacion conviene
| descargarlas al almacenamiento local para no depender del sitio anterior.
|
*/

return [

    'banners' => [
        [
            'image' => 'https://chutamax.com/wp-content/uploads/2023/11/CHUTAMAX-BANNER-PROTE-1536x560.jpg',
            'alt' => 'Proteinas en oferta',
            'url' => '#productos',
        ],
        [
            'image' => 'https://chutamax.com/wp-content/uploads/2023/11/CHUTAMAX-BANNER-CREATINAS-1536x560.jpg',
            'alt' => 'Creatinas',
            'url' => '#productos',
        ],
        [
            'image' => 'https://chutamax.com/wp-content/uploads/2023/11/CHUTAMAX-BANNER-PRE-1536x560.jpg',
            'alt' => 'Pre entrenos',
            'url' => '#productos',
        ],
        [
            'image' => 'https://chutamax.com/wp-content/uploads/2023/11/CHUTAMAX-BANNER-VITAMINAS-1536x560.jpg',
            'alt' => 'Vitaminas',
            'url' => '#productos',
        ],
    ],

    /*
     * Accesos rapidos a las categorias mas buscadas. Se resuelven contra las
     * categorias reales por su slug; las que no existan simplemente no se
     * muestran, para que la portada no ofrezca enlaces rotos.
     */
    'category_shortcuts' => [
        'proteina',
        'aminoacidos',
        'creatina',
        'quemadores-y-termogenicos',
        'vitaminas-y-minerales',
        'glucosamina',
        'oxido-nitrico',
        'glutamina',
    ],

    'how_to_buy' => [
        [
            'title' => 'Selecciona tu producto',
            'text' => 'Escoge lo que necesitas y agregalo al carrito.',
        ],
        [
            'title' => 'Realiza el pago',
            'text' => 'Captura tus datos de envio y elige tu metodo de pago.',
        ],
        [
            'title' => 'Recibe tu pedido',
            'text' => 'Te llega a la comodidad de tu casa en 2 a 3 dias habiles.',
        ],
    ],

    'contact' => [
        'address' => 'Morelos 948 Local 8, entre Miguel Aleman y Sinaloa. Cd. Obregon, Sonora, Mexico. C.P. 85040.',
        'whatsapp' => '(644) 200 7076',
    ],

];
