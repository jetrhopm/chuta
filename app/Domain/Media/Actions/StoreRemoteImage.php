<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Exceptions\RemoteImageFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga una imagen remota al almacenamiento local.
 *
 * Se usa para dejar de depender del sitio anterior: una vez descargados los
 * medios, el catalogo funciona aunque ese sitio deje de responder.
 *
 * El archivo se valida antes de guardarlo. No basta con la extension de la URL
 * ni con el encabezado que declare el servidor: se comprueba que los bytes sean
 * de verdad una imagen y de ahi se deduce el formato.
 */
class StoreRemoteImage
{
    /**
     * Formatos aceptados, con la extension que les corresponde.
     */
    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_AVIF => 'avif',
    ];

    private const MAX_BYTES = 8 * 1024 * 1024;

    private const TIMEOUT_SECONDS = 20;

    /**
     * @param  string  $directory  Carpeta destino dentro del disco publico.
     * @return string Ruta relativa guardada, por ejemplo "products/ab12cd.webp".
     *
     * @throws RemoteImageFailed
     */
    public function handle(string $url, string $directory = 'products', string $disk = 'public'): string
    {
        $bytes = $this->fetch($url);

        [$extension, $width, $height] = $this->inspect($bytes, $url);

        // El nombre sale del contenido, no de la URL: evita nombres previsibles y
        // hace que dos productos que comparten la misma foto no la dupliquen.
        $name = substr(hash('sha256', $bytes), 0, 40).'.'.$extension;
        $path = trim($directory, '/').'/'.$name;

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            $storage->put($path, $bytes);
        }

        unset($bytes);

        // Se devuelven las dimensiones por si quien llama quiere registrarlas,
        // aunque hoy solo se usa la ruta.
        unset($width, $height);

        return $path;
    }

    /**
     * @throws RemoteImageFailed
     */
    private function fetch(string $url): string
    {
        $requestUrl = $this->normalizeUrl($url);

        if (! filter_var($requestUrl, FILTER_VALIDATE_URL) || ! str_starts_with($requestUrl, 'http')) {
            throw new RemoteImageFailed($url, 'La direccion no es una URL valida.');
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                // Un reintento y nada mas: con miles de imagenes, insistir de mas
                // convierte una descarga larga en una que no termina.
                ->retry(2, 500, throw: false)
                ->get($requestUrl);
        } catch (ConnectionException $exception) {
            throw new RemoteImageFailed($url, 'No se pudo conectar con el servidor de la imagen.');
        }

        if (! $response->successful()) {
            throw new RemoteImageFailed($url, "El servidor respondio {$response->status()}.");
        }

        $bytes = $response->body();

        if ($bytes === '') {
            throw new RemoteImageFailed($url, 'La respuesta llego vacia.');
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new RemoteImageFailed($url, 'La imagen pesa mas de lo permitido.');
        }

        return $bytes;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return preg_replace_callback(
            '/[^\x21-\x7E]/u',
            fn (array $match): string => rawurlencode($match[0]),
            $url,
        ) ?? $url;
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     *
     * @throws RemoteImageFailed
     */
    private function inspect(string $bytes, string $url): array
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw new RemoteImageFailed($url, 'El archivo descargado no es una imagen.');
        }

        $type = $info[2] ?? null;

        if (! array_key_exists($type, self::ALLOWED)) {
            throw new RemoteImageFailed($url, 'El formato de la imagen no esta permitido.');
        }

        return [self::ALLOWED[$type], (int) $info[0], (int) $info[1]];
    }
}
