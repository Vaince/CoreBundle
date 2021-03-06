<?php

namespace Claroline\CoreBundle\Listener\Tool;

use JMS\DiExtraBundle\Annotation as DI;
use Claroline\CoreBundle\Event\DisplayToolEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @DI\Service("workspace_role_tool_config_listener", scope="request")
 */
class UserListener
{
    /**
     * @DI\InjectParams({
     *     "request" = @DI\Inject("request"),
     *     "ed"      = @DI\Inject("http_kernel"),
     * })
     */
    public function __construct(Request $request, HttpKernelInterface $httpKernel)
    {
        $this->request = $request;
        $this->httpKernel = $httpKernel;
    }

    /**
     * @DI\Observe("open_tool_workspace_users")
     *
     * @param DisplayToolEvent $event
     */
    public function onDisplay(DisplayToolEvent $event)
    {
        $subRequest = $this->request->duplicate(
            array(),
            null,
            array(
                '_controller' => 'ClarolineCoreBundle:Tool\Roles:usersList',
                'workspace' => $event->getWorkspace(),
                'page' => 1,
                'search' => ''
            )
        );
        $response = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        $event->setContent($response->getContent());
    }
}
