<?php

namespace App\Controller\Admin;

use App\Entity\Company;
use App\Form\CompanyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;

final class CompanyController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/company', name: 'admin_company', methods: ['GET', 'POST'])]
    public function index(Request $request, SluggerInterface $slugger): Response
    {
        # 1 Create new Company if it doesn't exist
        $company = $this->entityManager->getRepository(Company::class)->find(1);

        if (!$company) {
            $company = new Company();
            $this->entityManager->persist($company);
            $this->entityManager->flush();
        }

        # 2 Convert existing rates JSON into an array for the form
        $existingRates = [];
        $rates = $company->getRates();

        if (!empty($rates) && is_array($rates)) {
            foreach ($rates as $key => $value) {
                $existingRates[] = ['key' => $key, 'value' => $value];
            }
        }

        # 3 Create Form
        $form = $this->createForm(CompanyType::class, $company);
        $form->get('rates')->setData($existingRates); // Pre-fill form with existing rates
        $form->handleRequest($request);

        # 4 Form validation and submission
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $logoFile */
            $logoFile = $form->get('logoFile')->getData();

            if ($logoFile) {
                $filesystem = new Filesystem();
                $targetDirectory = $this->getParameter('logos_directory');

                // Delete old logo if it exists
                if ($company->getLogo()) {
                    $oldLogoPath = $targetDirectory . '/' . basename($company->getLogo());
                    if ($filesystem->exists($oldLogoPath)) {
                        $filesystem->remove($oldLogoPath);
                    }
                }

                // Generate unique filename
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $logoFile->guessExtension();

                // Move the file to assets/images/logo/
                $filesystem->mkdir($targetDirectory);
                $logoFile->move($targetDirectory, $newFilename);

                // Save the relative path
                $company->setLogo($this->getParameter('logos_public_path') . '/' . $newFilename);
            }

            # 5 Process rates as key-value pairs
            $submittedRates = $form->get('rates')->getData();
            $rates = [];
            foreach ($submittedRates as $rate) {
                if (!empty($rate['key']) && !empty($rate['value'])) {
                    $rates[$rate['key']] = $rate['value'];
                }
            }

            $company->setRates($rates);


            $this->entityManager->flush();

            $this->addFlash('success', 'Company updated successfully.');

            return $this->redirectToRoute('admin_company');
        }

        return $this->render('admin/company/index.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }
}
