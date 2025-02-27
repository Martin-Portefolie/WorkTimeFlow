<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/company', name: 'app_company_index', methods: ['GET'])]
    public function index(): Response
    {


        // Ensure the user is linked to a company
        $company  = $this->entityManager->getRepository(Company::class)->findAll();

        return $this->render('admin/company/index.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/admin/company/create', name: 'app_company_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['name'], $data['logo'], $data['rates'])) {
            return $this->json(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        $company = new Company();
        $company->setName($data['name']);
        $company->setLogo($data['logo']);
        $company->setRates($data['rates']);

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return $this->json(['message' => 'Company created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/admin/company/{id}', name: 'app_company_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $company = $this->entityManager->getRepository(Company::class)->find($id);

        if (!$company) {
            return $this->json(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $company->getId(),
            'name' => $company->getName(),
            'logo' => $company->getLogo(),
            'rates' => $company->getRates(),
        ]);
    }

    #[Route('/admin/company/{id}/edit', name: 'app_company_edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $company = $this->entityManager->getRepository(Company::class)->find($id);

        if (!$company) {
            return $this->json(['error' => 'Company not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $company->setName($data['name']);
        }
        if (isset($data['logo'])) {
            $company->setLogo($data['logo']);
        }
        if (isset($data['rates'])) {
            $company->setRates($data['rates']);
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Company updated successfully']);
    }


}
