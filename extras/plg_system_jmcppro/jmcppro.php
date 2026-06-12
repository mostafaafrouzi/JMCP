<?php

/**
 * JMCP Pro companion plugin (structure reference — not included in com_jmcp package).
 * Install separately to activate Pro tier via JMCP_PRO_VERSION constant.
 *
 * @package     JMCP Pro
 * @copyright   Copyright (C) 2026 JMCP Team
 * @license     GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

if (!defined('JMCP_PRO_VERSION')) {
    define('JMCP_PRO_VERSION', '1.0.0-dev');
}

/**
 * System plugin that activates JMCP Pro features in com_jmcp.
 */
class PlgSystemJmcppro extends CMSPlugin
{
    protected $autoloadLanguage = true;
}
