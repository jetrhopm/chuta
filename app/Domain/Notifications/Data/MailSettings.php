<?php

namespace App\Domain\Notifications\Data;

/**
 * Configuracion de envio de correo, tipada.
 *
 * La contrasena se guarda cifrada y no viaja en este objeto salvo cuando hace
 * falta armar la conexion. Para la interfaz se entrega enmascarada.
 */
readonly class MailSettings
{
    public const GROUP = 'mail';

    public function __construct(
        public bool $enabled = false,
        public string $transport = 'smtp',
        public string $host = '',
        public int $port = 587,
        public string $username = '',
        public string $password = '',
        /**
         * tls, ssl o vacio para sin cifrado.
         */
        public string $encryption = 'tls',
        public string $fromAddress = '',
        public string $fromName = 'Chutamax',
        public int $timeout = 15,
        /**
         * A donde avisar de cada venta nueva. Vacio desactiva ese aviso.
         */
        public string $adminNotificationAddress = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'transport' => $this->transport,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'encryption' => $this->encryption,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'timeout' => $this->timeout,
            'admin_notification_address' => $this->adminNotificationAddress,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $defaults = new self;

        return new self(
            enabled: (bool) ($values['enabled'] ?? $defaults->enabled),
            transport: (string) ($values['transport'] ?? $defaults->transport),
            host: (string) ($values['host'] ?? $defaults->host),
            port: (int) ($values['port'] ?? $defaults->port),
            username: (string) ($values['username'] ?? $defaults->username),
            password: (string) ($values['password'] ?? $defaults->password),
            encryption: (string) ($values['encryption'] ?? $defaults->encryption),
            fromAddress: (string) ($values['from_address'] ?? $defaults->fromAddress),
            fromName: (string) ($values['from_name'] ?? $defaults->fromName),
            timeout: (int) ($values['timeout'] ?? $defaults->timeout),
            adminNotificationAddress: (string) ($values['admin_notification_address'] ?? $defaults->adminNotificationAddress),
        );
    }

    /**
     * Si hay lo minimo para poder enviar.
     *
     * Sin remitente el servidor de correo rechaza el mensaje, y sin host no hay a
     * donde conectarse.
     */
    public function isUsable(): bool
    {
        return $this->enabled
            && $this->host !== ''
            && $this->fromAddress !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toMailerConfig(): array
    {
        return [
            'transport' => $this->transport,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username === '' ? null : $this->username,
            'password' => $this->password === '' ? null : $this->password,
            'encryption' => $this->encryption === '' ? null : $this->encryption,
            'timeout' => $this->timeout,
        ];
    }
}
