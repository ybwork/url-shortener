<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Url;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use App\Form\UrlForm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use App\Service\UrlChecker;

class UrlController extends AbstractController
{
    private $urlCheckerService;
    private $urlModel;

    public function __construct(UrlChecker $urlChecker)
    {
        $this->urlCheckerService = $urlChecker;
        $this->urlModel = new Url();
    }

    /**
     * @Route("/urls", name="urls")
     */
    public function index()
    {
        $urls = $this->fetchUserUrls();

        return $this->generateHtmlPage('url/index.html.twig', ['urls' => $urls]);
    }

    public function fetchUserUrls()
    {
        return $this->getDoctrine()->getRepository(User::class)->find($this->getUser()->getId())->getUrls();
    }

    public function generateHtmlPage(string $templeateName, array $data=[])
    {
        return $this->render($templeateName, $data);
    }

    /**
     * @Route("/urls/create", name="urls_create")
     */
    public function create(Request $request)
    {
        $form = $this->generateForm();

        $form->handleRequest($request);

        $url = $this->getUrlFromForm($form);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isValidUrl($url)) {            
                $this->saveUrl();

                $this->createNotification('success', 'Url added!');

                return $this->redirectToRoute('urls');
            }

            $this->createNotification('error', 'Url is not work');
        }

        return $this->generateHtmlPage(
            'url/create.html.twig', 
            ['form' => $form->createView()]
        );
    }

    public function generateForm()
    {
        return $this->createForm(UrlForm::class, $this->urlModel);
    }

    public function getUrlFromForm($form)
    {
        return $form->get('name')->getData();
    }

    public function isValidUrl($url)
    {
        $httpCode = $this->urlCheckerService->check($url);

        if ($httpCode == 200) {
            return true;
        }

        return false;
    }

    public function saveUrl()
    {
        $this->urlModel->setUser($this->getUser());
        $this->urlModel->setIsShared(false);

        $em = $this->getDoctrine()->getManager();
        $em->persist($this->urlModel);
        $em->flush();
    }

    public function createNotification($name, $message)
    {
        return $this->addFlash($name, $message);
    }

    /**
     * @Route("/urls/share/{id}", name="urls_share")
     */
    public function share($id)
    {
        $this->shareUrl($id);

        return $this->redirectToRoute('urls');
    }

    public function shareUrl($id)
    {
        $em = $this->getDoctrine()->getManager();

        $url = $em->getRepository(Url::class)->find($id);

        if (!$url) {
            throw $this->createNotFoundException(
                'Url does not exists'
            );
        }

        $url->setIsShared(true);
        $em->flush();
    }

    /**
     * @Route("/urls/shared", name="urls_shared")
     */
    public function sharedUrls()
    {
        $urls = $this->fetchSharedUrls();

        return $this->generateHtmlPage('url/shared.html.twig', ['urls' => $urls]);
    }

    public function fetchSharedUrls()
    {
        return $this->getDoctrine()->getRepository(Url::class)->findBy(['is_shared' => true]);
    }
}