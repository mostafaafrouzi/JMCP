<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\Jmcp\Administrator\Service\AbilityHubService;

class AbilitiesController extends BaseController
{
    public function save(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_jmcp')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jmcp', false));
            return;
        }

        $enabledTools = $this->input->get('enabled_tools', [], 'array');
        $allTools     = $this->input->get('all_tools', [], 'array');

        $enabledTools = array_map('strval', $enabledTools);
        $allTools     = array_map('strval', $allTools);

        $disabled = array_values(array_diff($allTools, $enabledTools));

        $hub = new AbilityHubService(ComponentHelper::getParams('com_jmcp'));
        $hub->saveDisabledTools($disabled);

        $this->app->enqueueMessage(Text::sprintf('COM_JMCP_ABILITIES_SAVED', count($enabledTools), count($disabled)), 'success');
        $this->setRedirect(Route::_('index.php?option=com_jmcp&view=dashboard#abilities', false));
    }
}
