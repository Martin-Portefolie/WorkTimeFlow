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
        ['key'=>'name',    'label'=>"What name should the client have?"],
        ['key'=>'email',   'label'=>"Client email?"],
        ['key'=>'phone',   'label'=>"Client phone?"],
        ['key'=>'contact', 'label'=>"Contact person?"],
        ['key'=>'address', 'label'=>"Street address?"],
        ['key'=>'postal',  'label'=>"Postal code?"],
        ['key'=>'city',    'label'=>"City?"],
        ['key'=>'country', 'label'=>"Country?"],
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

        $limit = (isset($args[0]) && ctype_digit($args[0])) ? (int) $args[0] : 50;
        $limit = max(1, min(100, $limit));
        $q     = isset($flags['q']) ? (string) $flags['q'] : null;

        $rows = $this->clients->fetchListRowsSearched($limit, $q);
        if (!$rows) {
            return ['output' => 'No clients found.', 'success' => true];
        }

        // Normalize & escape (but keep plain text for width calc)
        $data = array_map(function(array $r) {
            return [
                'id'      => (string)($r['id'] ?? ''),
                'name'    => (string)($r['name'] ?? ''),
                'email'   => (string)($r['contactEmail'] ?? ''),
                'city'    => (string)($r['city'] ?? ''),
                'country' => (string)($r['country'] ?? ''),
            ];
        }, $rows);

        // Compute dynamic widths (with sane minimums)
        $w = [
            'id'      => max(2,  strlen('ID'),      ...array_map(fn($r)=>mb_strlen($r['id']),      $data)),
            'name'    => max(18, strlen('Name'),    ...array_map(fn($r)=>mb_strlen($r['name']),    $data)),
            'email'   => max(20, strlen('Email'),   ...array_map(fn($r)=>mb_strlen($r['email']),   $data)),
            'city'    => max(12, strlen('City'),    ...array_map(fn($r)=>mb_strlen($r['city']),    $data)),
            'country' => max(10, strlen('Country'), ...array_map(fn($r)=>mb_strlen($r['country']), $data)),
        ];

        // Header + divider
        $header = sprintf(
            '%s | %s | %s | %s | %s',
            $this->mb_pad('ID',      $w['id']),
            $this->mb_pad('Name',    $w['name']),
            $this->mb_pad('Email',   $w['email']),
            $this->mb_pad('City',    $w['city']),
            $this->mb_pad('Country', $w['country']),
        );
        $divider = str_repeat('-', mb_strlen($header));

        // Rows
        $lines = [];
        foreach ($data as $r) {
            $lines[] = sprintf(
                '%s | %s | %s | %s | %s',
                $this->mb_pad($r['id'],      $w['id']),
                $this->mb_pad($r['name'],    $w['name']),
                $this->mb_pad($r['email'] ?: '—',   $w['email']),
                $this->mb_pad($r['city']  ?: '—',   $w['city']),
                $this->mb_pad($r['country'] ?: '—', $w['country']),
            );
        }

        // Wrap in <pre> so spacing is preserved; escape everything once at the end
        $table = implode("\n", [$header, $divider, ...$lines]);
        $html  = '<pre class="text-[11px] leading-[1.25] text-zinc-400">'. $this->h($table) .'</pre>';


        return ['output' => $html, 'success' => true];
    }

    /** HTML-escape helper */
    private function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /** Multibyte-safe right-pad to fixed width (spaces) */
    private function mb_pad(string $text, int $width): string
    {
        $len = mb_strlen($text);
        if ($len >= $width) return $text;
        return $text . str_repeat(' ', $width - $len);
    }


    /**
     * clients:add --name="Acme A/S" [--email= --phone= --contact= --address= --postal= --city= --country=]
     */
    public function addCommand(array $tokens): array
    {
        $userId = $this->currentUserKey();
        $this->wiz->set($userId, ['mode'=>self::WZ_ADD,'step'=>0,'data'=>[]]);

        // return a prompt response WITH await=true
        return $this->formatPrompt(self::ADD_STEPS[0]['label']);
    }

    /** Handle interactive input if a wizard is active for the current user */
    public function handleInteractive(string $rawInput): ?array
    {
        $userId = $this->currentUserKey();
        $state  = $this->wiz->get($userId);
        if (!$state) return null; // no wizard active

        $t = trim($rawInput);

        // If a command is typed while wizard is active, restart add wizard
        if ($t === 'clients:add' || $t === 'c.a') {
            $this->wiz->set($userId, ['mode'=>self::WZ_ADD,'step'=>0,'data'=>[]]);
            return $this->formatPrompt(self::ADD_STEPS[0]['label']); // await: true
        }

        // Allow cancel
        if ($t === 'cancel') {
            $this->wiz->clear($userId);
            return ['output' => 'Canceled.', 'success' => true]; // no await
        }

        // Continue the wizard normally
        return match ($state['mode'] ?? null) {
            self::WZ_ADD => $this->handleAddStep($state, $t, $userId),
            default      => null,
        };
    }


    // ----------------------------- Internals --------------------------------

    private function handleAddStep(array $state, string $rawInput, string $userId): array
    {
        $i     = (int)($state['step'] ?? 0);
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
            return $this->formatPrompt($steps[$i]['label']); // await: true
        }

        // --- All answers collected: create the client
        $data = $state['data'] ?? [];
        $c = new Client();
        if (array_key_exists('name', $data))    $c->setName((string)$data['name']);
        if (array_key_exists('email', $data))   $c->setContactEmail($data['email']);
        if (array_key_exists('phone', $data))   $c->setContactPhone($data['phone']);
        if (array_key_exists('contact', $data)) $c->setContactPerson($data['contact']);
        if (array_key_exists('address', $data)) $c->setAdress($data['address']);
        if (array_key_exists('postal', $data))  $c->setPostalCode($data['postal']);
        if (array_key_exists('city', $data))    $c->setCity($data['city']);
        if (array_key_exists('country', $data)) $c->setCountry($data['country']);

        $this->em->persist($c);
        $this->em->flush();

        // Clear wizard and return a final message (NO await)
        $this->wiz->clear($userId);

        $summary = sprintf(
            "Client created (#%d).\n- name: %s\n- email: %s\n- phone: %s\n- contact: %s\n- address: %s\n- postal: %s\n- city: %s\n- country: %s",
            $c->getId(),
            $this->safe($data['name']    ?? '—'),
            $this->safe($data['email']   ?? '—'),
            $this->safe($data['phone']   ?? '—'),
            $this->safe($data['contact'] ?? '—'),
            $this->safe($data['address'] ?? '—'),
            $this->safe($data['postal']  ?? '—'),
            $this->safe($data['city']    ?? '—'),
            $this->safe($data['country'] ?? '—'),
        );

        return ['output' => nl2br($summary), 'success' => true]; // <- no await
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

    private function safe(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private function formatPrompt(string $label): array
    {
        return [
            'output' => sprintf(
                '<div class="space-y-1">
               <div class="text-zinc-300">%s</div>
               <div class="text-zinc-500 text-xs">Press Enter to leave empty, or type <span class="term-cmd">cancel</span> to abort.</div>
             </div>',
                $this->safe($label)
            ),
            'success' => true,
            'await' => true, // <-- tells the UI we expect free-form input next
        ];
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
