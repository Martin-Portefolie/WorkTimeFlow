<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Project;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class ClientRowTest extends TestCase
{
    private function twig(): Environment
    {
        $root = dirname(__DIR__, 2);
        $translator = new Translator('da');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', $root . '/translations/messages.da.yaml', 'da');
        $twig = new Environment(new FilesystemLoader($root . '/templates'), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $args = []): string => '/' . $route . (isset($args['id']) ? '/' . $args['id'] : '')));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => $id));
        return $twig;
    }

    public function testListHasEditableFieldsAndAnInertDraftTemplate(): void
    {
        $html = $this->twig()->render('gui/admin/_clients_list.html.twig', ['data' => ['rows' => [
            ['id' => 7, 'name' => 'Existing', 'contactPerson' => 'Alice', 'contactEmail' => 'alice@example.test', 'contactPhone' => '+4512345678', 'adress' => 'Street 12', 'postalCode' => '0123', 'city' => 'Aarhus', 'country' => 'Danmark', 'projects' => [['id' => 9, 'name' => 'Website', 'archived' => false], ['id' => 10, 'name' => 'Support', 'archived' => true]]],
        ]]]);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);
        self::assertSame(8, $xpath->query('//input[@form="client_row_7"]')->length);
        self::assertSame('Alice', $xpath->evaluate('string(//input[@form="client_row_7" and @name="contactPerson"]/@value)'));
        self::assertSame('0123', $xpath->evaluate('string(//input[@form="client_row_7" and @name="postalCode"]/@value)'));
        self::assertSame(1, $xpath->query('//template//tr[@data-client-row="new"]')->length);
        self::assertSame(0, $xpath->query('//tbody/tr[@data-client-row="new"]')->length);
        self::assertSame('/admin_client_panel/7', $xpath->evaluate('string(//form[@id="client_row_7"]/@action)'));
        self::assertSame('row', $xpath->evaluate('string(//form[@id="client_row_7"]/input[@name="_view"]/@value)'));
        self::assertSame('Website', $xpath->evaluate('string(//button[@data-command="p.s 9"]/span[1])'));
        self::assertSame('2', $xpath->evaluate('string(//button[@aria-haspopup="dialog"])'));
        self::assertSame(1, $xpath->query('//dialog//section[1]//button[@data-command="p.s 9"]')->length);
        self::assertSame(1, $xpath->query('//dialog//section[2]//button[@data-command="p.s 10"]')->length);
        self::assertSame(0, $xpath->query('//dialog//section[1]//button[@data-command="p.s 10"]')->length);
        self::assertSame('Support', $xpath->evaluate('string(//button[@data-command="p.s 10"]/span[1])'));
    }

    public function testSavedClientResponseIsAParseableRowWithUpdateAction(): void
    {
        $client = (new Client())->setName('A "quoted" client')->setContactPerson('Alice')
            ->setContactEmail('alice@example.test')->setContactPhone('+4512345678');
        (new \ReflectionProperty(Client::class, 'id'))->setValue($client, 42);
        $project = (new Project())->setName('Website');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, 9);
        $client->addProject($project);
        $html = $this->twig()->render('gui/admin/_client_row_response.html.twig', ['client' => $client, 'saved' => true]);
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);
        self::assertSame(1, $xpath->query('//table/tbody/tr[@data-controller="client-form" and @data-client-row="42"]')->length);
        self::assertSame('/admin_client_panel/42', $xpath->evaluate('string(//form/@action)'));
        self::assertSame('A "quoted" client', $xpath->evaluate('string(//input[@name="name"]/@value)'));
        self::assertSame('Gemt.', $xpath->evaluate('string(//p[@role="status"])'));
        self::assertSame('Website', $xpath->evaluate('string(//button[@data-command="p.s 9"]/span[1])'));
    }
}
