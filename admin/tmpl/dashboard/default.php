<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\Jmcp\Administrator\View\Dashboard\HtmlView $this */

HTMLHelper::_('bootstrap.tab');
$token = HTMLHelper::_('form.token');
$tokenUrl = Session::getFormToken() . '=1';
?>
<?php if ($this->productionWarning) : ?>
<div class="alert alert-danger">
    <strong><?php echo Text::_('COM_JMCP_PRODUCTION_WARNING_TITLE'); ?></strong>
    <?php echo Text::_('COM_JMCP_PRODUCTION_WARNING_BODY'); ?>
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="jmcpTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#overview"><?php echo Text::_('COM_JMCP_TAB_OVERVIEW'); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#abilities" id="abilities"><?php echo Text::_('COM_JMCP_TAB_ABILITIES'); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pending" id="pending"><?php echo Text::_('COM_JMCP_TAB_PENDING'); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#audit" id="audit"><?php echo Text::_('COM_JMCP_TAB_AUDIT'); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tier"><?php echo Text::_('COM_JMCP_TAB_TIER'); ?></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#connect"><?php echo Text::_('COM_JMCP_TAB_CONNECT'); ?></a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="overview">
        <div class="alert alert-info">
            <div class="mb-1"><strong><?php echo Text::_('COM_JMCP_DASHBOARD_MCP_ENDPOINT'); ?></strong></div>
            <code id="jmcp-sse-url"><?php echo $this->escape($this->sseUrl); ?></code>
            <button type="button" class="btn btn-sm btn-secondary ms-2" onclick="jmcpCopy('jmcp-sse-url')"><?php echo Text::_('COM_JMCP_DASHBOARD_COPY'); ?></button>
        </div>
        <div class="alert alert-secondary">
            <div class="mb-1"><strong><?php echo Text::_('COM_JMCP_DASHBOARD_HEALTH_ENDPOINT'); ?></strong></div>
            <code id="jmcp-health-url"><?php echo $this->escape($this->healthUrl); ?></code>
            <button type="button" class="btn btn-sm btn-secondary ms-2" onclick="jmcpCopy('jmcp-health-url')"><?php echo Text::_('COM_JMCP_DASHBOARD_COPY'); ?></button>
        </div>
        <div class="row mb-3">
            <?php
            $overviewCards = [
                'total' => 'COM_JMCP_DASHBOARD_CARD_TOTAL',
                'last_24h' => 'COM_JMCP_DASHBOARD_CARD_24H',
                'last_7d' => 'COM_JMCP_DASHBOARD_CARD_7D',
                'error_rate' => 'COM_JMCP_DASHBOARD_CARD_ERROR_RATE',
            ];
            foreach ($overviewCards as $key => $labelKey) :
                $val = (string) ($this->summary[$key] ?? 0);
                if ($key === 'error_rate') { $val .= '%'; }
            ?>
            <div class="col-md-3">
                <div class="card text-center"><div class="card-body">
                    <div class="h3"><?php echo $this->escape($val); ?></div>
                    <div class="small text-muted"><?php echo Text::_($labelKey); ?></div>
                </div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-header"><?php echo Text::_('COM_JMCP_DASHBOARD_TOP_TOOLS'); ?></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <?php foreach ($this->topTools as $row) : ?>
                    <tr><td><code><?php echo $this->escape($row['tool_name']); ?></code></td><td class="text-end"><?php echo (int) $row['count']; ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="abilities">
        <form action="<?php echo $this->abilitiesSaveUrl; ?>" method="post">
            <?php echo $token; ?>
            <p><?php echo Text::sprintf('COM_JMCP_DASHBOARD_ABILITIES_DESC', $this->totalTools); ?></p>
            <div class="mb-2 d-flex flex-wrap gap-2 align-items-center">
                <input type="search" id="jmcp-ability-search" class="form-control form-control-sm" style="max-width:280px;" placeholder="<?php echo Text::_('COM_JMCP_ABILITY_SEARCH'); ?>" oninput="jmcpFilterAbilities(this.value)">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="jmcpToggleAll(true)"><?php echo Text::_('COM_JMCP_SELECT_ALL'); ?></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="jmcpToggleAll(false)"><?php echo Text::_('COM_JMCP_SELECT_NONE'); ?></button>
            </div>
            <div class="table-responsive" style="max-height:480px;overflow:auto;">
                <table class="table table-sm table-striped">
                    <thead><tr>
                        <th width="40"></th>
                        <th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_TOOL'); ?></th>
                        <th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_RISK'); ?></th>
                        <th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_TIER'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($this->toolPolicies as $policy) :
                        $name = $policy['name'];
                        $checked = $policy['enabled'] ? 'checked' : '';
                        $proBadge = ($policy['pro_only'] ?? false) ? '<span class="badge bg-warning text-dark">Pro</span>' : '';
                        $disabled = !($policy['available'] ?? true) ? 'disabled title="Pro required"' : '';
                    ?>
                    <tr class="jmcp-ability-row" data-name="<?php echo $this->escape(strtolower($name)); ?>">
                        <td>
                            <input type="checkbox" class="form-check-input jmcp-tool-cb" name="enabled_tools[]" value="<?php echo $this->escape($name); ?>" <?php echo $checked . ' ' . $disabled; ?> />
                            <input type="hidden" name="all_tools[]" value="<?php echo $this->escape($name); ?>" />
                        </td>
                        <td><code><?php echo $this->escape($name); ?></code> <?php echo $proBadge; ?></td>
                        <td><?php echo $this->escape($policy['risk']); ?></td>
                        <td><?php echo $this->escape($policy['tier']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-success mt-2"><?php echo Text::_('JSAVE'); ?></button>
        </form>
    </div>

    <div class="tab-pane fade" id="pending">
        <table class="table table-sm">
            <thead><tr><th>ID</th><th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_TOOL'); ?></th><th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_TIME'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if (empty($this->pendingChanges)) : ?>
                <tr><td colspan="4"><?php echo Text::_('COM_JMCP_DASHBOARD_NO_DATA'); ?></td></tr>
            <?php else : foreach ($this->pendingChanges as $change) : ?>
                <tr>
                    <td><?php echo (int) $change->id; ?></td>
                    <td><code><?php echo $this->escape($change->tool_name); ?></code><br><small><?php echo $this->escape($change->description); ?></small></td>
                    <td><?php echo $this->escape($change->created); ?></td>
                    <td>
                        <a class="btn btn-sm btn-success" href="<?php echo Route::_('index.php?option=com_jmcp&task=pending.approve&id=' . (int) $change->id . '&' . $tokenUrl); ?>"><?php echo Text::_('COM_JMCP_APPROVE'); ?></a>
                        <a class="btn btn-sm btn-danger" href="<?php echo Route::_('index.php?option=com_jmcp&task=pending.reject&id=' . (int) $change->id . '&' . $tokenUrl); ?>"><?php echo Text::_('COM_JMCP_REJECT'); ?></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="tab-pane fade" id="audit">
        <table class="table table-sm">
            <thead><tr><th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_TIME'); ?></th><th><?php echo Text::_('COM_JMCP_DASHBOARD_COL_TOOL'); ?></th><th>Action</th><th>Dry-run</th></tr></thead>
            <tbody>
            <?php foreach ($this->auditLog as $row) : ?>
                <tr>
                    <td><?php echo $this->escape($row->created); ?></td>
                    <td><code><?php echo $this->escape($row->tool_name); ?></code></td>
                    <td><?php echo $this->escape($row->action); ?></td>
                    <td><?php echo (int) $row->dry_run ? Text::_('JYES') : Text::_('JNO'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="tab-pane fade" id="tier">
        <div class="card mb-3">
            <div class="card-body">
                <h4><?php echo Text::_('COM_JMCP_TIER_STATUS'); ?></h4>
                <p><strong><?php echo Text::_('COM_JMCP_TIER_CURRENT'); ?>:</strong>
                    <span class="badge <?php echo $this->isPro ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $this->escape($this->licenseStatus['tier'] ?? 'free'); ?></span>
                </p>
                <?php if (!$this->isPro) : ?>
                    <p class="text-muted"><?php echo Text::_('COM_JMCP_TIER_FREE_DESC'); ?></p>
                    <p><?php echo Text::_('COM_JMCP_TIER_PRO_ACTIVATE'); ?></p>
                    <ul>
                        <li><code>define('JMCP_PRO_VERSION', '1.0.0');</code> <?php echo Text::_('COM_JMCP_TIER_VIA_CONSTANT'); ?></li>
                        <li><?php echo Text::_('COM_JMCP_TIER_VIA_PLUGIN'); ?>: <code>plg_system_jmcppro</code></li>
                        <li><?php echo Text::_('COM_JMCP_TIER_VIA_LICENSE'); ?></li>
                    </ul>
                <?php else : ?>
                    <p class="text-success"><?php echo Text::sprintf('COM_JMCP_TIER_PRO_ACTIVE', $this->escape((string) ($this->licenseStatus['pro_version'] ?? ''))); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="connect">
        <pre class="bg-light p-3" id="jmcp-claude-config"><?php echo $this->escape($this->claudeConfig); ?></pre>
        <button type="button" class="btn btn-primary btn-sm mb-3" onclick="jmcpCopy('jmcp-claude-config')"><?php echo Text::_('COM_JMCP_DASHBOARD_COPY'); ?></button>
        <pre class="bg-light p-3" id="jmcp-cursor-config"><?php echo $this->escape($this->cursorConfig); ?></pre>
        <button type="button" class="btn btn-primary btn-sm" onclick="jmcpCopy('jmcp-cursor-config')"><?php echo Text::_('COM_JMCP_DASHBOARD_COPY'); ?></button>
    </div>
</div>

<script>
function jmcpCopy(id) {
    const el = document.getElementById(id);
    navigator.clipboard.writeText(el.tagName === 'PRE' ? el.textContent : el.textContent.trim());
}
function jmcpToggleAll(state) {
    document.querySelectorAll('.jmcp-tool-cb:not([disabled])').forEach(cb => cb.checked = state);
}
function jmcpFilterAbilities(query) {
    const q = (query || '').toLowerCase();
    document.querySelectorAll('.jmcp-ability-row').forEach(row => {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
}
if (location.hash) {
    const tab = document.querySelector('a[href="' + location.hash + '"]');
    if (tab) { new bootstrap.Tab(tab).show(); }
}
</script>
