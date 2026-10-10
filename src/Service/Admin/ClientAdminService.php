<?php

namespace App\Service\Admin;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClientAdminService
{
    private const FIELDS = [
        'name' => 'setName', 'contactPerson' => 'setContactPerson',
        'contactEmail' => 'setContactEmail', 'contactPhone' => 'setContactPhone',
        'adress' => 'setAdress', 'postalCode' => 'setPostalCode',
        'city' => 'setCity', 'country' => 'setCountry',
    ];

    public function __construct(
        private readonly ClientRepository $clients,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function list(): array
    {
        return $this->clients->fetchListRowsSearched(25, null);
    }

    public function update(Client $client, array $data): void
    {
        $this->apply($client, $data);
        $this->em->flush();
    }

    public function create(array $data): Client
    {
        foreach (['name', 'contactPerson', 'contactEmail', 'contactPhone'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException('client.invalid.' . $field);
            }
        }
        $client = new Client();
        $this->apply($client, $data);
        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    private function apply(Client $client, array $data): void
    {
        $values = [];
        foreach (self::FIELDS as $field => $setter) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (!is_string($data[$field])) {
                throw new \InvalidArgumentException('client.invalid.' . $field);
            }
            $value = trim($data[$field]);
            $valid = mb_strlen($value) <= 255;
            if (in_array($field, ['name', 'contactPerson'], true)) {
                $valid = $valid && mb_strlen($value) >= 2;
            } elseif ($field === 'contactEmail') {
                $valid = $valid && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                $value = mb_strtolower($value);
            } elseif ($field === 'contactPhone') {
                $digits = preg_replace('/\D/', '', $value);
                $valid = mb_strlen($value) <= 20 && preg_match('/^\+?[0-9 ().-]+$/D', $value)
                    && strlen($digits) >= 7 && strlen($digits) <= 15;
            }
            if (!$valid) {
                throw new \InvalidArgumentException('client.invalid.' . $field);
            }
            $values[$field] = $value === '' ? null : $value;
        }
        if (!$values) {
            throw new \InvalidArgumentException('client.nothing_to_update');
        }

        // Validate every submitted field before changing the managed entity.
        foreach ($values as $field => $value) {
            $client->{self::FIELDS[$field]}($value);
        }
    }
}
