<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\Todo;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\NoReturn;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/admin/upload', name: 'admin_upload_index')]
    public function index(): Response
    {
        return $this->render('admin/upload/index.html.twig');
    }

    #[Route('/admin/upload/clients', name: 'admin_upload_clients', methods: ['POST'])]
    public function uploadClients(Request $request): Response
    {
        $uploadedFile = $request->files->get('client_docx');

        if (!$uploadedFile instanceof UploadedFile) {
            $this->addFlash('error', 'No file uploaded.');
            return $this->redirectToRoute('admin_upload_index');
        }

        if ($uploadedFile->getClientOriginalExtension() !== 'docx') {
            $this->addFlash('error', 'Invalid file type. Please upload a .docx file.');
            return $this->redirectToRoute('admin_upload_index');
        }

        $filePath = $uploadedFile->getPathname();
        $phpWord = IOFactory::load($filePath);

        $clientData = [];
        $keys = [
            "Company Name" => "name",
            "Address" => "adress",
            "City" => "city",
            "Postal Code" => "postalCode",
            "Country" => "country",
            "Contact Person" => "contactPerson",
            "Contact Email" => "contactEmail",
            "Contact Phone" => "contactPhone",
        ];

        // Extract text from the document
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        // Convert extracted text into an array of lines
        $lines = array_map('trim', explode("\n", trim($text)));

        // Loop through lines to extract key-value pairs
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2); // Split by the first occurrence of ":"
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                if (isset($keys[$key])) {
                    $clientData[$keys[$key]] = $value;
                }
            }
        }

        // Debugging: Log extracted data (optional)
        if (empty($clientData)) {
            $this->addFlash('error', 'No data extracted from document.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Validate extracted data
        if (empty($clientData['name']) || empty($clientData['contactEmail'])) {
            $this->addFlash('error', 'Invalid document format or missing required fields.');
            return $this->redirectToRoute('admin_upload_index');
        }

        // Create and save the Client entity
        $client = new Client();
        $client->setName($clientData['name']);
        $client->setAdress($clientData['adress'] ?? '');
        $client->setCity($clientData['city'] ?? '');
        $client->setPostalCode($clientData['postalCode'] ?? '');
        $client->setCountry($clientData['country'] ?? '');
        $client->setContactPerson($clientData['contactPerson'] ?? '');
        $client->setContactEmail($clientData['contactEmail']);
        $client->setContactPhone($clientData['contactPhone'] ?? '');

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $this->addFlash('success', 'Client uploaded successfully.');
        return $this->redirectToRoute('admin_upload_index');
    }


}
