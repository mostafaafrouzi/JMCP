<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\Jmcp\Administrator\Service\PendingChangesService;
use Joomla\Component\Jmcp\Administrator\Service\ToolRegistry;

class PendingController extends BaseController
{
    public function approve(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_jmcp')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jmcp', false));
            return;
        }

        $id = $this->input->getInt('id');
        $service = new PendingChangesService();
        $registry = new ToolRegistry();
        (new \Joomla\Component\Jmcp\Administrator\Service\ToolExecutorRegistry())
            ->register($registry, \Joomla\CMS\Component\ComponentHelper::getParams('com_jmcp'));

        try {
            $service->approve($id, fn(string $tool, array $args) => $registry->execute($tool, $args));
            $this->app->enqueueMessage(Text::sprintf('COM_JMCP_PENDING_APPROVED', $id), 'success');
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_jmcp&view=dashboard#pending', false));
    }

    public function reject(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_jmcp')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_jmcp', false));
            return;
        }

        $id = $this->input->getInt('id');
        $reason = $this->input->getString('reason', '');

        try {
            (new PendingChangesService())->reject($id, $reason);
            $this->app->enqueueMessage(Text::sprintf('COM_JMCP_PENDING_REJECTED', $id), 'warning');
        } catch (\Throwable $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_jmcp&view=dashboard#pending', false));
    }
}
