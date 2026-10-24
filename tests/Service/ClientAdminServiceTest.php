<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Service\Admin\ClientAdminService;
use App\Service\Terminal\Admin\TerminalClientService;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\Translator;

final class ClientAdminServiceTest extends TestCase
{
    private function client(): Client
    {
        return (new Client())->setName('Original')->setContactPerson('Alice')
            ->setContactEmail('alice@example.test')->setContactPhone('+45 12 34 56 78')
            ->setAdress('Old street')->setCity('Aarhus');
    }

    private function service(EntityManagerInterface $em): ClientAdminService
    {
        return new ClientAdminService($this->createStub(ClientRepository::class), $em);
    }

    public function testUpdateNormalizesValuesWithoutChangingUnsubmittedFields(): void
    {
        $client = $this->client();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $this->service($em)->update($client, [
            'name' => ' New client ', 'contactEmail' => ' CONTACT@EXAMPLE.TEST ',
            'contactPhone' => '+1 (212) 555-0100', 'adress' => ' ', 'postalCode' => ' 0123 ',
            '_token' => 'ignored',
        ]);
        self::assertSame('New client', $client->getName());
        self::assertSame('contact@example.test', $client->getContactEmail());
        self::assertSame('+1 (212) 555-0100', $client->getContactPhone());
        self::assertNull($client->getAdress());
        self::assertSame('0123', $client->getPostalCode());
        self::assertSame('Alice', $client->getContactPerson());
        self::assertSame('Aarhus', $client->getCity());
    }

    public static function invalidFields(): iterable
    {
        yield ['name', ''];
        yield ['name', str_repeat('a', 256)];
        yield ['contactEmail', 'invalid-email'];
        yield ['contactPhone', '123'];
        yield ['contactPhone', 'call 12345678'];
        yield ['contactPhone', '1234567890123456'];
        yield ['contactPhone', ['12345678']];
        yield ['contactPerson', ''];
        yield ['city', str_repeat('a', 256)];
    }

    public function testCreatesClientWithNormalizedDetails(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $persisted = null;
        $em->expects(self::once())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void { $persisted = $entity; },
        );
        $em->expects(self::once())->method('flush');
        $client = $this->service($em)->create([
            'name' => ' New client ', 'contactPerson' => ' Alice ',
            'contactEmail' => ' ALICE@EXAMPLE.TEST ', 'contactPhone' => '+45 12 34 56 78',
            'adress' => '', 'postalCode' => '0123', 'city' => 'Aarhus', 'country' => 'Danmark',
        ]);
        self::assertSame($persisted, $client);
        self::assertSame('New client', $client->getName());
        self::assertSame('Alice', $client->getContactPerson());
        self::assertSame('alice@example.test', $client->getContactEmail());
        self::assertSame('0123', $client->getPostalCode());
        self::assertNull($client->getAdress());
    }

    public function testCreateRejectsMissingRequiredFieldsBeforePersisting(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $this->expectExceptionMessage('client.invalid.contactEmail');
        $this->service($em)->create(['name' => 'New client', 'contactPerson' => 'Alice']);
    }

    public function testTerminalUpdateUsesSharedValidationAndReturnsTheUpdatedClientForGui(): void
    {
        $client = $this->client();
        $clients = $this->createStub(ClientRepository::class);
        $clients->method('findOneByIdNameOrEmail')->willReturn($client);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $terminal = new TerminalClientService(
            $clients, new TerminalStateService(new ArrayAdapter()), $this->createStub(Security::class),
            $em, new ClientAdminService($clients, $em), new Translator('en'),
        );
        $invalid = $terminal->update(['1'], ['name' => 'Changed', 'phone' => 'bad']);
        self::assertFalse($invalid['success']);
        self::assertSame('Original', $client->getName());

        $result = $terminal->update(['1'], ['contact-email' => ' NEW@EXAMPLE.TEST ', 'postal-code' => '0123']);
        self::assertTrue($result['success']);
        self::assertSame($client, $result['vars']['client']);
        self::assertSame('new@example.test', $client->getContactEmail());
        self::assertSame('0123', $client->getPostalCode());
    }

    #[DataProvider('invalidFields')]
    public function testInvalidInputDoesNotPartiallyMutateTheEntity(string $field, mixed $value): void
    {
        $client = $this->client();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        try {
            $this->service($em)->update($client, array_replace(['name' => 'Changed'], [$field => $value]));
            self::fail('Invalid input accepted');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('client.invalid.' . $field, $exception->getMessage());
            self::assertSame('Original', $client->getName());
            self::assertSame('alice@example.test', $client->getContactEmail());
            self::assertSame('+45 12 34 56 78', $client->getContactPhone());
        }
    }
}
