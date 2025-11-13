<?php
namespace App\Service\Terminal\Admin;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Service\Terminal\ArgsParser;
use App\Service\Terminal\TerminalStateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class ClientTerminalService
{
    private const WZ_ADD = 'clients:add';

    // The sequence of prompts (each accepts empty as null)
    private const ADD_STEPS = [
        ['key'=>'name',    'label'=>"What name should the client have?",   'example'=>"Hest Test A/S"],
        ['key'=>'email',   'label'=>"Client email?",                       'example'=>"contact@HTEST.com"],
        ['key'=>'phone',   'label'=>"Client phone?",                       'example'=>"+45 12 34 56 78"],
        ['key'=>'contact', 'label'=>"Contact person?",                     'example'=>"Mr Hest"],
        ['key'=>'address', 'label'=>"Street address?",                     'example'=>"Test St 12"],
        ['key'=>'postal',  'label'=>"Postal code?",                        'example'=>"8000"],
        ['key'=>'city',    'label'=>"City?",                               'example'=>"Aarhus"],
        ['key'=>'country', 'label'=>"Country?",                            'example'=>"Denmark"],
    ];
    public function __construct(
        private ClientRepository $clients,
        private EntityManagerInterface $em,
        private TerminalStateService $wiz,
        private Security $security,
    ) {}


    public function listCommand(array $tokens): array
    {
        ['args' => $args, 'flags' => $flags] = ArgsParser::parse($tokens);

        $limit = (isset($args[0]) && ctype_digit($args[0])) ? (int)$args[0] : 50;
        $limit = max(1, min(100, $limit));
        $q     = isset($flags['q']) ? (string)$flags['q'] : null;

        $rows = $this->clients->fetchListRowsSearched($limit, $q);

        $rows = array_map(static function(array $r): array {
            return [
                'id'            => (string)($r['id'] ?? ''),
                'name'          => (string)($r['name'] ?? ''),
                'city'          => (string)($r['city'] ?? ''),
                'country'       => (string)($r['country'] ?? ''),
                'projectsCount' => (int)($r['projectsCount'] ?? 0),
                'contactPerson' => (string)($r['contactPerson'] ?? ''),
                'email'         => (string)($r['contactEmail'] ?? ''),
                'phone'         => (string)($r['contactPhone'] ?? ''),
            ];
        }, $rows ?? []);

        return [
            'view'    => 'terminal/admin/terminal_commands/_clients_list_admin.html.twig',
            'vars'    => ['rows' => $rows, 'limit' => $limit, 'q' => $q],
            'success' => true,
        ];
    }


    public function showCommand(array $tokens): array
    {
        if (count($tokens) !== 1) {
            return ['output' => 'Usage: clients:show <id|name|email>', 'success' => false];
        }

        $key = (string) $tokens[0];
        $c = $this->clients->findOneByIdNameOrEmail($key);
        if (!$c) {
            return ['output' => 'Client not found.', 'success' => false];
        }

        return [
            'view'    => 'terminal/admin/terminal_commands/_clients_show_admin.html.twig',
            'vars'    => ['client' => $c],
            'success' => true,
        ];
    }

    /**
     * clients:add --name="Acme A/S" [--email= --phone= --contact= --address= --postal= --city= --country=]
     */
    public function addCommand(array $tokens): array
    {
        $userId = $this->currentUserKey();
        $this->wiz->set($userId, [
            'mode' => self::WZ_ADD,
            'step' => 0,
            'data' => [],
        ]);

        $s     = self::ADD_STEPS[0];
        $total = count(self::ADD_STEPS);

        return [
            'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
            'vars'    => [
                'label'   => $s['label'],
                'example' => $s['example'] ?? null,
                'step'    => 1,        // human-friendly step number
                'total'   => $total,
            ],
            'success' => true,
            'await'   => true,
        ];
    }

    /** Handle interactive input if a wizard is active for the current user */
    public function handleInteractive(string $rawInput): ?array
    {
        $userId = $this->currentUserKey();
        $state  = $this->wiz->get($userId);
        if (!$state) {
            return null; // no wizard active for this user
        }

        $t = trim($rawInput);

        // Allow restarting the wizard while inside it
        if ($t === 'clients:add' || $t === 'c.a') {
            $this->wiz->set($userId, [
                'mode' => self::WZ_ADD,
                'step' => 0,
                'data' => [],
            ]);

            $s     = self::ADD_STEPS[0];
            $total = count(self::ADD_STEPS);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $s['label'],
                    'example' => $s['example'] ?? null,
                    'step'    => 1,
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // Allow cancel
        if ($t === 'cancel') {
            $this->wiz->clear($userId);
            return ['output' => 'Canceled.', 'success' => true];
        }

        // Otherwise, continue the active wizard
        return match ($state['mode'] ?? null) {
            self::WZ_ADD => $this->handleAddStep($state, $t, $userId),
            default      => null,
        };
    }



    // ----------------------------- Internals --------------------------------

    private function handleAddStep(array $state, string $rawInput, string $userId): array
    {
        $i     = (int) ($state['step'] ?? 0);
        $steps = self::ADD_STEPS;

        // Save previous answer (empty => null)
        if ($i < count($steps)) {
            $key = $steps[$i]['key'];
            $val = ($rawInput === '') ? null : trim($rawInput);
            $state['data'][$key] = $val;
            $i++;
        }

        // More questions? ask next (await: true)
        if ($i < count($steps)) {
            $state['step'] = $i;
            $this->wiz->set($userId, $state);

            $s     = $steps[$i];
            $total = count($steps);

            return [
                'view'    => 'terminal/admin/terminal_commands/partials/_step-by-step_prompt.html.twig',
                'vars'    => [
                    'label'   => $s['label'],
                    'example' => $s['example'] ?? null,
                    'step'    => $i + 1,   // 0-based index -> human step
                    'total'   => $total,
                ],
                'success' => true,
                'await'   => true,
            ];
        }

        // --- All answers collected: create the client
        $data = $state['data'] ?? [];

        $c = new Client();
        $c->setName((string)($data['name'] ?? ''));
        $c->setContactEmail($data['email'] ?? null);
        $c->setContactPhone($data['phone'] ?? null);
        $c->setContactPerson($data['contact'] ?? null);
        $c->setAdress($data['address'] ?? null);
        $c->setPostalCode($data['postal'] ?? null);
        $c->setCity($data['city'] ?? null);
        $c->setCountry($data['country'] ?? null);

        $this->em->persist($c);
        $this->em->flush();

        // Clear wizard
        $this->wiz->clear($userId);

        return [
            'view'    => 'terminal/admin/terminal_commands/_clients_created_summary.html.twig',
            'vars'    => [
                'id'      => $c->getId(),
                'name'    => $data['name']    ?? null,
                'email'   => $data['email']   ?? null,
                'phone'   => $data['phone']   ?? null,
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'postal'  => $data['postal']  ?? null,
                'city'    => $data['city']    ?? null,
                'country' => $data['country'] ?? null,
            ],
            'success' => true,
            // no 'await' → wizard finished
        ];
    }



    // ---------------------------- Utilities ---------------------------------

    private function currentUserKey(): string
    {
        $user = $this->security->getUser();
        // Use something stable per-user; fallback to session id if needed
        return method_exists($user, 'getUserIdentifier')
            ? (string)$user->getUserIdentifier()
            : 'guest';
    }





    /**
     * clients:update <id|name|email> [--name= --email= --phone= --contact= --address= --postal= --city= --country=]
     */
    public function updateCommand(array $tokens): array
    {
        if (!isset($tokens[0])) {
            return ['output' => 'Usage: clients:update <id|name|email> [--name=... --city=... etc.]', 'success' => false];
        }

        $key = array_shift($tokens);
        [, $flags] = array_values(ArgsParser::parse($tokens));

        $c = $this->clients->findOneByIdNameOrEmail((string)$key);
        if (!$c) return ['output' => 'Client not found.', 'success' => false];

        $changed = [];

        $map = [
            'name'    => 'setName',
            'email'   => 'setContactEmail',
            'phone'   => 'setContactPhone',
            'contact' => 'setContactPerson',
            'address' => 'setAdress',
            'postal'  => 'setPostalCode',
            'city'    => 'setCity',
            'country' => 'setCountry',
        ];

        foreach ($map as $flag => $setter) {
            if (array_key_exists($flag, $flags)) {
                $c->{$setter}((string)$flags[$flag]);
                $changed[] = $flag;
            }
        }

        if (!$changed) {
            return ['output' => 'Nothing to update.', 'success' => false];
        }

        $this->em->flush();

        return ['output' => 'Updated: '.implode(', ', $changed), 'success' => true];
    }

    /**
     * clients:delete <id|name|email>
     */
    public function deleteCommand(array $tokens): array
    {
        if (!isset($tokens[0])) {
            return ['output' => 'Usage: clients:delete <id|name|email>', 'success' => false];
        }

        $c = $this->clients->findOneByIdNameOrEmail((string)$tokens[0]);
        if (!$c) return ['output' => 'Client not found.', 'success' => false];

        $name = $c->getName();
        $id   = $c->getId();

        $this->em->remove($c);
        $this->em->flush();

        return ['output' => sprintf('Client id=%d (%s) deleted.', $id, htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8')), 'success' => true];
    }
}
