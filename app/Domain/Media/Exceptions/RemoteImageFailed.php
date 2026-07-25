<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * Una imagen remota no se pudo descargar o no era valida.
 *
 * La descarga de medios recorre miles de archivos, asi que un fallo suelto no
 * puede abortar el proceso: quien llama atrapa esta excepcion, la cuenta y
 * sigue con el resto.
 */
class RemoteImageFailed extends RuntimeException
{
    /**
     * @param  bool  $permanent  Si la imagen ya no existe en el origen.
     *                           Un 404 o un archivo que no es imagen no van a
     *                           mejorar reintentando; un timeout o una conexion
     *                           caida si pueden, y por eso se tratan distinto:
     *                           en el primer caso conviene olvidar la direccion,
     *                           en el segundo conservarla.
     */
    public function __construct(
        public readonly string $url,
        string $reason,
        public readonly bool $permanent = false,
    ) {
        parent::__construct($reason);
    }
}
