<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Nico
 * Date: 04/07/13
 * Time: 15:33
 * To change this template use File | Settings | File Templates.
 */

namespace Icap\WikiBundle\Controller;


use Claroline\CoreBundle\Entity\User;
use Icap\WikiBundle\Entity\Wiki;
use Claroline\CoreBundle\Library\Resource\ResourceCollection;
use Icap\WikiBundle\Entity\Section;
use Icap\WikiBundle\Entity\Contribution;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class ContributionController extends Controller{
	/**
     * @Route(
     *      "/{wikiId}/section/{sectionId}/contribution/{contributionId}",
     *      requirements = {
     *          "wikiId" = "\d+", 
     *          "sectionId" = "\d+",
     *			"contributionId" = "\d+"
     *      },
     *      name="icap_wiki_contribution_view"
     * )
     * @ParamConverter("wiki", class="IcapWikiBundle:Wiki", options={"id" = "wikiId"})
     * @ParamConverter("user", options={"authenticatedUser" = true})
     * @Template()
     */
    public function viewAction(Wiki $wiki, User $user, $sectionId, $contributionId) 
    {
        $this->checkAccess("OPEN", $wiki);

        $section = $this->getSection($wiki, $sectionId);
        $collection = $collection = new ResourceCollection(array($wiki->getResourceNode()));

        if ($section->getVisible() === true || $this->isUserGranted('EDIT', $wiki, $collection)) {
        	$contribution = $this->getContribution($section, $contributionId);

            return array(
                'wiki' => $wiki,
                'contribution' => $contribution,
                'section' => $section,
                'workspace' => $wiki->getResourceNode()->getWorkspace()
            );
        }
        else {
            throw new AccessDeniedException($collection->getErrorsForDisplay());
        }
    }

    /**
     * @Route(
     *      "/{wikiId}/section/{sectionId}/activecontribution/{contributionId}",
     *      requirements = {
     *          "wikiId" = "\d+", 
     *          "sectionId" = "\d+",
     *			"contributionId" = "\d+"
     *      },
     *      name="icap_wiki_contribution_active"
     * )
     * @ParamConverter("wiki", class="IcapWikiBundle:Wiki", options={"id" = "wikiId"})
     * @ParamConverter("user", options={"authenticatedUser" = true})
     * @Template()
     */
    public function activeAction(Wiki $wiki, User $user, $sectionId, $contributionId) 
    {
        $this->checkAccess("OPEN", $wiki);
        $section = $this->getSection($wiki, $sectionId);
        $collection = $collection = new ResourceCollection(array($wiki->getResourceNode()));

        if ($section->getVisible() === true || $this->isUserGranted('EDIT', $wiki, $collection)) {
        	$contribution = $this->getContribution($section, $contributionId);
        	$section->setActiveContribution($contribution);
        	$em = $this->getDoctrine()->getManager();
        	$em->persist($section);
        	$em->flush();

        	return $this->redirect(
                    $this->generateUrl(
                        'icap_wiki_section_history',
                        array(
                            'wikiId' => $wiki->getId(),
                        	'sectionId' => $section->getId()
                        )
                    )
                );
        }
        else {
            throw new AccessDeniedException($collection->getErrorsForDisplay());
        }
    }

    /**
     * @Route(
     *      "/{wikiId}/section/{sectionId}/versions/compare",
     *      requirements = {
     *          "wikiId" = "\d+", 
     *          "sectionId" = "\d+"
     *      },
     *      name="icap_wiki_compare_contributions"
     * )
     * @ParamConverter("wiki", class="IcapWikiBundle:Wiki", options={"id" = "wikiId"})
     * @ParamConverter("user", options={"authenticatedUser" = true})
     * @Template()
     */
    public function compareAction(Request $request, Wiki $wiki, User $user, $sectionId) 
    {
        $this->checkAccess("OPEN", $wiki);
        $section = $this->getSection($wiki, $sectionId);
        $collection = $collection = new ResourceCollection(array($wiki->getResourceNode()));
        if ($section->getVisible() === true || $this->isUserGranted('EDIT', $wiki, $collection)) {
        	$oldid = $request->query->get('oldid');
        	$diff = $request->query->get('diff');
        	if ($oldid !== null && $diff !== null) {
        		$repo = $this->get('icap.wiki.contribution_repository');
        		$contributions = $repo->findyBySectionAndIds($section, array($oldid, $diff));
        		if (count($contributions) == 2) {
        			return array(
		                'wiki' => $wiki,
		                'contributions' => $contributions,
		                'section' => $section,
		                'workspace' => $wiki->getResourceNode()->getWorkspace()
		            );    
        		}
        		else {
        			throw new NotFoundHttpException();
        		}
        	}
        	else {
        		throw new MissingOptionsException('Missing parameters',array());
        	}
        		
        }
        else {
            throw new AccessDeniedException($collection->getErrorsForDisplay());
        }
    }
}