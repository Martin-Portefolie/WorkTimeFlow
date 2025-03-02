<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Form\ClientType;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/client', name: 'admin_client')]
    public function index(Request $request): Response
    {
        $searchTerm = $request->query->get('search');
        $queryBuilder = $this->entityManager->getRepository(Client::class)->createQueryBuilder('c');

        if ($searchTerm) {
            $queryBuilder->andWhere('c.name LIKE :search OR c.contactPerson LIKE :search OR c.contactEmail LIKE :search OR c.contactPhone LIKE :search')
                ->setParameter('search', '%'.$searchTerm.'%');
        }

        $pagerfanta = new Pagerfanta(new QueryAdapter($queryBuilder));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($request->query->getInt('page', 1));

        $clientdata = $this->entityManager->getRepository(Client::class)->findAll();
        $clientDataArray = [];

        foreach ($clientdata as $clients) {
            $clientDataArray[] = [
                'id' => $clients->getId(),
                'name' => $clients->getName(),
                'contact' => $clients->getContactPerson(),
                'email' => $clients->getContactEmail(),
                'phone' => $clients->getContactPhone(),
                'projects' => $clients->getProjects(),
            ];
        }

        return $this->render('admin/client/index.html.twig', [
            'controller_name' => 'ClientController',
            'clientDataArray' => $clientDataArray,
            'pager' => $pagerfanta,
        ]);
    }


    #[Route('/admin/client/update/{id}', name: 'admin_client_update', methods: ['POST'])]
    public function update(Request $request, int $id): Response
    {
        $client = $this->entityManager->getRepository(Client::class)->find($id);

        if (!$client) {
            throw $this->createNotFoundException('Client not found');
        }

        // Check if values exist in the request, otherwise keep existing values
        $client->setName($request->request->get('name', $client->getName()));
        $client->setContactPerson($request->request->get('contact_person', $client->getContactPerson()));
        $client->setContactEmail($request->request->get('email', $client->getContactEmail()));
        $client->setContactPhone($request->request->get('phone', $client->getContactPhone()));
        $client->setAdress($request->request->get('address', $client->getAdress())); // Correct spelling
        $client->setPostalCode($request->request->get('postalCode', $client->getPostalCode()));
        $client->setCity($request->request->get('city', $client->getCity()));
        $client->setCountry($request->request->get('country', $client->getCountry()));

        $this->entityManager->flush();

        return $this->redirectToRoute('admin_client');
    }



    #[Route('/admin/client/new', name: 'admin_client_new')]
    public function new(Request $request): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($client);
            $this->entityManager->flush();

            return $this->redirectToRoute('admin_client');
        }

        return $this->render('admin/client/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
