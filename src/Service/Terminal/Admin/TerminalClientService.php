<?php

namespace App\Service\Terminal\Admin;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class TerminalClientService
{
    private const VIEW = 'terminals/admin/terminal_commands/clients.html.twig';
    private const WIZARD_MODE = 'clients:add';

    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly TerminalStateService $state,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    // -----------------------------------------------------
    // List Clients
    // -----------------------------------------------------

    public function list(array $flags = []): array
    {
        $q = isset($flags['q']) ? trim((string) $flags['q']) : null;
        $limit = isset($flags['limit']) ? max(1, (int) $flags['limit']) : 25;

        $rows = $this->clientRepository->fetchListRowsSearched($limit, $q ?: null);

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'list',
                'resource' => 'clients',
                'title' => 'Clients',
                'description' => 'Clients available to admin commands.',
                'columns' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'name', 'label' => 'Name'],
                    ['key' => 'city', 'label' => 'City'],
                    ['key' => 'country', 'label' => 'Country'],
                    ['key' => 'contactPerson', 'label' => 'Contact'],
                    ['key' => 'contactEmail', 'label' => 'Email'],
                    ['key' => 'contactPhone', 'label' => 'Phone'],
                    ['key' => 'projectsCount', 'label' => 'Projects'],
                ],
                'rows' => $rows,
            ],
        ];
    }

    // -----------------------------------------------------
    // Show Client
    // -----------------------------------------------------
    // TODO:
    // Projects in client show should become clickable UI actions.
    // Clicking project should run: projects:show <id> / p.s <id>
    // Clicking "Projects" header/count should run: projects:list --client=<id>
    // Terminal output and right-side UI object should both update from same command result.
    public function show(array $args = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing client identifier',
                'Usage: clients:show <id|name|email>',
            );
        }

        $client = $this->clientRepository->findOneByIdNameOrEmail($identifier);

        if (!$client) {
            return $this->error(
                'Client not found',
                sprintf('No client found for "%s".', $identifier),
            );
        }

        return $this->clientShowPayload('Client', $client);
    }

    // -----------------------------------------------------
    // Add Client Wizard
    // -----------------------------------------------------

    public function add(array $flags = []): array
    {
        $userId = $this->currentUserId();

        $this->state->set($userId, [
            'mode' => self::WIZARD_MODE,
            'step' => 1,
            'data' => [],
        ]);

        return $this->prompt(
            step: 1,
            total: 7,
            label: 'Enter client name',
            example: 'Acme ApS',
        );
    }

    // -----------------------------------------------------
    // Update Client
    // -----------------------------------------------------

    public function update(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing client identifier',
                'Usage: clients:update <id|name|email> --name=<name> --email=<email> --phone=<phone> --contact=<person> --address=<address> --postal-code=<code> --city=<city> --country=<country>',
            );
        }

        $client = $this->clientRepository->findOneByIdNameOrEmail($identifier);

        if (!$client) {
            return $this->error(
                'Client not found',
                sprintf('No client found for "%s".', $identifier),
            );
        }

        $changed = false;

        if (isset($flags['name'])) {
            $name = trim((string) $flags['name']);

            if (mb_strlen($name) < 2) {
                return $this->error('Invalid client name', 'Client name must be at least 2 characters.');
            }

            $client->setName($name);
            $changed = true;
        }

        if (isset($flags['email']) || isset($flags['contact-email'])) {
            $email = trim((string) ($flags['email'] ?? $flags['contact-email']));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Invalid email', sprintf('"%s" is not a valid email address.', $email));
            }

            $client->setContactEmail(mb_strtolower($email));
            $changed = true;
        }

        if (isset($flags['phone']) || isset($flags['contact-phone'])) {
            $phone = trim((string) ($flags['phone'] ?? $flags['contact-phone']));

            if (mb_strlen($phone) < 2) {
                return $this->error('Invalid phone', 'Phone must be at least 2 characters.');
            }

            $client->setContactPhone($phone);
            $changed = true;
        }

        if (isset($flags['contact']) || isset($flags['contact-person'])) {
            $contactPerson = trim((string) ($flags['contact'] ?? $flags['contact-person']));

            if (mb_strlen($contactPerson) < 2) {
                return $this->error('Invalid contact person', 'Contact person must be at least 2 characters.');
            }

            $client->setContactPerson($contactPerson);
            $changed = true;
        }

        if (isset($flags['address']) || isset($flags['adress'])) {
            $client->setAdress($this->nullableString($flags['address'] ?? $flags['adress']));
            $changed = true;
        }

        if (isset($flags['postal-code']) || isset($flags['postalCode'])) {
            $client->setPostalCode($this->nullableString($flags['postal-code'] ?? $flags['postalCode']));
            $changed = true;
        }

        if (isset($flags['city'])) {
            $client->setCity($this->nullableString($flags['city']));
            $changed = true;
        }

        if (isset($flags['country'])) {
            $client->setCountry($this->nullableString($flags['country']));
            $changed = true;
        }

        if (!$changed) {
            return $this->error(
                'Nothing to update',
                'Use one or more flags: --name= --email= --phone= --contact= --address= --postal-code= --city= --country=',
            );
        }

        $this->em->flush();

        return $this->clientShowPayload('Client updated', $client);
    }

    // -----------------------------------------------------
    // Delete Client
    // -----------------------------------------------------

    public function delete(array $args = [], array $flags = []): array
    {
        $identifier = $args[0] ?? null;

        if (!$identifier) {
            return $this->error(
                'Missing client identifier',
                'Usage: clients:delete <id|name|email> --force',
            );
        }

        if (!isset($flags['force'])) {
            return $this->error(
                'Delete requires confirmation',
                sprintf('Run: clients:delete %s --force', $identifier),
            );
        }

        $client = $this->clientRepository->findOneByIdNameOrEmail($identifier);

        if (!$client) {
            return $this->error(
                'Client not found',
                sprintf('No client found for "%s".', $identifier),
            );
        }

        if ($client->getProjects()->count() > 0) {
            return $this->error(
                'Client has projects',
                'Client cannot be deleted while projects are attached.',
            );
        }

        $name = $client->getName();

        $this->em->remove($client);
        $this->em->flush();

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'delete',
                'title' => 'Client deleted',
                'message' => sprintf('Client "%s" was deleted.', $name),
            ],
        ];
    }

    // -----------------------------------------------------
    // Interactive Wizard Handler
    // -----------------------------------------------------

    public function handleInteractive(string $raw): ?array
    {
        $userId = $this->currentUserId();
        $state = $this->state->get($userId);

        if (!$state || ($state['mode'] ?? null) !== self::WIZARD_MODE) {
            return null;
        }

        $raw = trim($raw);
        $lower = mb_strtolower($raw);

        if ($lower === 'cancel') {
            $this->state->clear($userId);

            return [
                'success' => false,
                'output' => 'Client creation cancelled.',
                'await' => false,
            ];
        }

        if (in_array($lower, ['c.a', 'clients:add'], true)) {
            $this->state->clear($userId);

            return $this->add();
        }

        return $this->handleAddWizard($userId, $state, $raw);
    }

    // -----------------------------------------------------
    // Handle Add Client Wizard
    // -----------------------------------------------------

    private function handleAddWizard(string $userId, array $state, string $input): array
    {
        $step = (int) ($state['step'] ?? 1);
        $data = $state['data'] ?? [];

        if ($step === 1) {
            $name = trim($input);

            if (mb_strlen($name) < 2) {
                return $this->prompt(1, 7, 'Enter client name', 'Acme ApS', 'Client name must be at least 2 characters.');
            }

            $data['name'] = $name;
            $this->saveWizardStep($userId, 2, $data);

            return $this->prompt(2, 7, 'Enter contact person', 'Jane Doe');
        }

        if ($step === 2) {
            $contactPerson = trim($input);

            if (mb_strlen($contactPerson) < 2) {
                return $this->prompt(2, 7, 'Enter contact person', 'Jane Doe', 'Contact person must be at least 2 characters.');
            }

            $data['contactPerson'] = $contactPerson;
            $this->saveWizardStep($userId, 3, $data);

            return $this->prompt(3, 7, 'Enter contact email', 'client@example.com');
        }

        if ($step === 3) {
            $email = trim($input);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->prompt(3, 7, 'Enter contact email', 'client@example.com', 'Invalid email address.');
            }

            $data['contactEmail'] = mb_strtolower($email);
            $this->saveWizardStep($userId, 4, $data);

            return $this->prompt(4, 7, 'Enter contact phone', '+45 12 34 56 78');
        }

        if ($step === 4) {
            $phone = trim($input);

            if (mb_strlen($phone) < 2) {
                return $this->prompt(4, 7, 'Enter contact phone', '+45 12 34 56 78', 'Phone must be at least 2 characters.');
            }

            $data['contactPhone'] = $phone;
            $this->saveWizardStep($userId, 5, $data);

            return $this->prompt(5, 7, 'Enter address. Leave empty to skip', 'Main Street 1');
        }

        if ($step === 5) {
            $data['adress'] = $this->nullableString($input);
            $this->saveWizardStep($userId, 6, $data);

            return $this->prompt(6, 7, 'Enter postal code and city. Leave empty to skip', '2100 Copenhagen');
        }

        if ($step === 6) {
            $postalAndCity = trim($input);
            $data['postalCode'] = null;
            $data['city'] = null;

            if ($postalAndCity !== '') {
                if (preg_match('/^(\S+)\s+(.+)$/', $postalAndCity, $matches)) {
                    $data['postalCode'] = $matches[1];
                    $data['city'] = trim($matches[2]);
                } else {
                    $data['city'] = $postalAndCity;
                }
            }

            $this->saveWizardStep($userId, 7, $data);

            return [
                'success' => true,
                'await' => true,
                'view' => self::VIEW,
                'vars' => [
                    'view' => 'show',
                    'title' => 'Confirm client creation',
                    'description' => 'Type "yes" or "y" to confirm. Any other input aborts.',
                    'fields' => [
                        ['label' => 'Name', 'value' => $data['name']],
                        ['label' => 'Contact person', 'value' => $data['contactPerson']],
                        ['label' => 'Email', 'value' => $data['contactEmail']],
                        ['label' => 'Phone', 'value' => $data['contactPhone']],
                        ['label' => 'Address', 'value' => $data['adress'] ?? null],
                        ['label' => 'Postal code', 'value' => $data['postalCode'] ?? null],
                        ['label' => 'City', 'value' => $data['city'] ?? null],
                    ],
                ],
            ];
        }

        if ($step === 7) {
            if (!in_array(mb_strtolower($input), ['yes', 'y'], true)) {
                $this->state->clear($userId);

                return [
                    'success' => false,
                    'output' => 'Client creation aborted.',
                ];
            }

            $client = new Client();
            $client->setName($data['name']);
            $client->setContactPerson($data['contactPerson']);
            $client->setContactEmail($data['contactEmail']);
            $client->setContactPhone($data['contactPhone']);
            $client->setAdress($data['adress'] ?? null);
            $client->setPostalCode($data['postalCode'] ?? null);
            $client->setCity($data['city'] ?? null);
            $client->setCountry($data['country'] ?? null);

            $this->em->persist($client);
            $this->em->flush();
            $this->state->clear($userId);

            return $this->clientShowPayload('Client created', $client);
        }

        $this->state->clear($userId);

        return [
            'success' => false,
            'output' => 'Wizard state became invalid.',
        ];
    }

    // -----------------------------------------------------
    // Client Show Payload
    // -----------------------------------------------------

    private function clientShowPayload(string $title, Client $client): array
    {
        $projects = $client->getProjects()->toArray();

        $fields = [
            ['label' => 'ID', 'value' => $client->getId()],
            ['label' => 'Name', 'value' => $client->getName()],
            ['label' => 'Contact person', 'value' => $client->getContactPerson()],
            ['label' => 'Email', 'value' => $client->getContactEmail()],
            ['label' => 'Phone', 'value' => $client->getContactPhone()],
            ['label' => 'Address', 'value' => $client->getAdress()],
            ['label' => 'Postal code', 'value' => $client->getPostalCode()],
            ['label' => 'City', 'value' => $client->getCity()],
            ['label' => 'Country', 'value' => $client->getCountry()],
            ['label' => 'Projects', 'value' => sprintf('(%d)', count($projects))],
        ];

        if (count($projects) > 0) {
            foreach ($projects as $project) {
                $fields[] = [
                    'label' => sprintf('project: %s', $project->getId()),
                    'value' => $project->getName() ?: '—',
                ];
            }
        } else {
            $fields[] = [
                'label' => '',
                'value' => 'none',
            ];
        }

        return [
            'success' => true,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'show',
                'resource' => 'clients',
                'title' => $title,
                'fields' => $fields,
            ],
        ];
    }

    // -----------------------------------------------------
    // Prompt Helper
    // -----------------------------------------------------

    private function prompt(
        int $step,
        int $total,
        string $label,
        ?string $example = null,
        ?string $error = null,
    ): array {
        return [
            'success' => $error === null,
            'await' => true,
            'view' => 'terminals/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars' => [
                'step' => $step,
                'total' => $total,
                'label' => $label,
                'example' => $example,
                'error' => $error,
            ],
        ];
    }

    // -----------------------------------------------------
    // Error Helper
    // -----------------------------------------------------

    private function error(string $title, string $message): array
    {
        return [
            'success' => false,
            'view' => self::VIEW,
            'vars' => [
                'view' => 'error',
                'title' => $title,
                'message' => $message,
            ],
        ];
    }

    // -----------------------------------------------------
    // Save Wizard Step
    // -----------------------------------------------------

    private function saveWizardStep(string $userId, int $step, array $data): void
    {
        $this->state->set($userId, [
            'mode' => self::WIZARD_MODE,
            'step' => $step,
            'data' => $data,
        ]);
    }

    // -----------------------------------------------------
    // Current User ID
    // -----------------------------------------------------

    private function currentUserId(): string
    {
        return $this->security->getUser()?->getUserIdentifier() ?? 'guest';
    }

    // -----------------------------------------------------
    // Nullable String Helper
    // -----------------------------------------------------

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
