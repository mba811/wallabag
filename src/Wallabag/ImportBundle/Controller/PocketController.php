<?php

namespace Wallabag\ImportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class PocketController extends Controller
{
    /**
     * @Route("/pocket", name="import_pocket")
     */
    public function indexAction()
    {
        $pocket = $this->get('wallabag_import.pocket.import');
        $form = $this->createFormBuilder($pocket)
            ->add('read', CheckboxType::class, array(
                'label' => 'Mark all as read',
                'required' => false,
            ))
            ->getForm();

        return $this->render('WallabagImportBundle:Pocket:index.html.twig', [
            'import' => $this->get('wallabag_import.pocket.import'),
            'has_consumer_key' => '' == trim($this->get('craue_config')->get('pocket_consumer_key')) ? false : true,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/pocket/auth", name="import_pocket_auth")
     */
    public function authAction(Request $request)
    {
        $requestToken = $this->get('wallabag_import.pocket.import')
            ->getRequestToken($this->generateUrl('import', array(), UrlGeneratorInterface::ABSOLUTE_URL));

        $this->get('session')->set('import.pocket.code', $requestToken);
        $this->get('session')->set('read', $request->request->get('form')['read']);

        return $this->redirect(
            'https://getpocket.com/auth/authorize?request_token='.$requestToken.'&redirect_uri='.$this->generateUrl('import_pocket_callback', array(), UrlGeneratorInterface::ABSOLUTE_URL),
            301
        );
    }

    /**
     * @Route("/pocket/callback", name="import_pocket_callback")
     */
    public function callbackAction()
    {
        $message = 'Import failed, please try again.';
        $pocket = $this->get('wallabag_import.pocket.import');
        $markAsRead = $this->get('session')->get('read');
        $this->get('session')->remove('read');

        // something bad happend on pocket side
        if (false === $pocket->authorize($this->get('session')->get('import.pocket.code'))) {
            $this->get('session')->getFlashBag()->add(
                'notice',
                $message
            );

            return $this->redirect($this->generateUrl('import_pocket'));
        }

        if (true === $pocket->setMarkAsRead($markAsRead)->import()) {
            $summary = $pocket->getSummary();
            $message = 'Import summary: '.$summary['imported'].' imported, '.$summary['skipped'].' already saved.';
        }

        $this->get('session')->getFlashBag()->add(
            'notice',
            $message
        );

        return $this->redirect($this->generateUrl('homepage'));
    }
}
