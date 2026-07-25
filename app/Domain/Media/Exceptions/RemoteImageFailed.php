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
    public function __construct(
        public readonly string $url,
        string $reason,
    ) {
        parent::__construct($reason);
    }
}
