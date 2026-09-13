<?php

namespace App\Domain\ErpSync\DTOs;

/**
 * Cliente tal como lo conoce el sistema de facturación del ERP
 * (`get_info_cliente_facturacion` / `save_new_data_client`).
 */
final readonly class ClientInfo
{
    public const TYPE_CLIENT_CONSUMIDOR_FINAL = 'CF';

    /**
     * @param  int  $idType  Tipo de identificación (`dniType` del checkout).
     * @param  string  $id  Cédula/RUC.
     */
    public function __construct(
        public int $idType,
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $address,
        public ?string $phone = null,
        public ?string $email = null,
        public string $typeClient = self::TYPE_CLIENT_CONSUMIDOR_FINAL,
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }
}
