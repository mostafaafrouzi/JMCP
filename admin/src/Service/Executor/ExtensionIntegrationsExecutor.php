<?php

declare(strict_types=1);

namespace Joomla\Component\Jmcp\Administrator\Service\Executor;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Jmcp\Administrator\Service\IntegrationDetector;

class ExtensionIntegrationsExecutor
{
    private IntegrationDetector $detector;

    public function __construct(?IntegrationDetector $detector = null)
    {
        $this->detector = $detector ?? new IntegrationDetector();
    }

    public function akeebaListBackups(array $params): array
    {
        $this->assert('akeebabackup');
        $path = JPATH_ROOT . '/administrator/components/com_akeebabackup/backup';
        $files = [];

        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $f) {
                if (str_ends_with($f, '.jpa') || str_ends_with($f, '.zip')) {
                    $files[] = ['filename' => $f, 'size' => filesize($path . '/' . $f)];
                }
            }
        }

        return ['backups' => $files];
    }

    public function akeebaCreateBackup(array $params): array
    {
        $this->assert('akeebabackup');
        $php = PHP_BINARY ?: 'php';
        $cli = escapeshellarg(JPATH_ROOT . '/cli/joomla.php');
        $profile = (int) ($params['profile'] ?? 1);
        $cmd = escapeshellarg($php) . ' ' . $cli . ' akeeba:backup --profile=' . $profile;
        exec($cmd . ' 2>&1', $output, $code);

        if ($code !== 0) {
            return [
                'exit_code' => $code,
                'output'    => implode("\n", $output),
                'message'   => 'Akeeba CLI backup failed. Try: php cli/joomla.php akeeba:backup',
                'cli'       => 'akeeba:backup --profile=' . $profile,
            ];
        }

        return [
            'exit_code' => $code,
            'output'    => implode("\n", $output),
            'message'   => 'Akeeba backup started/completed via CLI.',
        ];
    }

    public function admintoolsSecurityStatus(array $params): array
    {
        $this->assert('admintools');
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $prefix = $db->getPrefix();

        $status = ['installed' => true, 'features' => []];

        if (in_array($prefix . 'admintools_wafexceptions', $tables, true)) {
            $status['features'][] = 'WAF';
        }
        if (in_array($prefix . 'admintools_ipblock', $tables, true)) {
            $query = $db->getQuery(true)->select('COUNT(*)')->from('#__admintools_ipblock');
            $status['blocked_ips'] = (int) $db->setQuery($query)->loadResult();
        }

        return $status;
    }

    public function sh404sefListUrls(array $params): array
    {
        $this->assert('sh404sef');
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['id', 'oldurl', 'newurl', 'rank'])
            ->from('#__sh404sef_urls')
            ->order('id DESC');
        $db->setQuery($query, 0, (int) ($params['limit'] ?? 50));
        return ['urls' => $db->loadAssocList() ?: []];
    }

    public function sh404sefCreateRedirect(array $params): array
    {
        $this->assert('sh404sef');
        $db = Factory::getDbo();
        $row = new \stdClass();
        $row->oldurl  = (string) ($params['old_url'] ?? '');
        $row->newurl  = (string) ($params['new_url'] ?? '');
        $row->rank    = 0;
        $row->dateadd = Factory::getDate()->toSql();
        $db->insertObject('#__sh404sef_urls', $row);
        return ['id' => (int) $db->insertid(), 'message' => 'Redirect created.'];
    }

    public function jceListProfiles(array $params): array
    {
        $this->assert('jce');
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select(['id', 'name', 'description', 'published'])->from('#__jce_profiles')->order('ordering');
        return ['profiles' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function rsformListForms(array $params): array
    {
        $this->assert('rsform');
        $db = Factory::getDbo();
        $query = $db->getQuery(true)->select(['FormId AS id', 'FormTitle AS title', 'Published AS published'])->from('#__rsform_forms');
        return ['forms' => $db->setQuery($query)->loadAssocList() ?: []];
    }

    public function rsformListSubmissions(array $params): array
    {
        $this->assert('rsform');
        $formId = (int) ($params['form_id'] ?? 0);
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select(['SubmissionId AS id', 'FormId', 'DateSubmitted'])
            ->from('#__rsform_submissions')
            ->where('FormId = ' . $formId)
            ->order('SubmissionId DESC');
        $db->setQuery($query, 0, (int) ($params['limit'] ?? 25));
        return ['submissions' => $db->loadAssocList() ?: []];
    }

    public function exportRsformSubmissions(array $params): array
    {
        $this->assert('rsform');
        $formId = (int) ($params['form_id'] ?? 0);
        if ($formId <= 0) {
            throw new \RuntimeException('form_id is required.');
        }

        $db = Factory::getDbo();
        $submissions = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__rsform_submissions')
                ->where('FormId = ' . $formId)->order('SubmissionId DESC')
        )->loadAssocList() ?: [];

        $values = $db->setQuery(
            $db->getQuery(true)->select('*')->from('#__rsform_submission_values')
                ->where('FormId = ' . $formId)
        )->loadAssocList() ?: [];

        $bySubmission = [];
        foreach ($values as $val) {
            $sid = (int) ($val['SubmissionId'] ?? 0);
            $bySubmission[$sid][] = $val;
        }

        $export = [];
        foreach ($submissions as $sub) {
            $sid = (int) $sub['SubmissionId'];
            $export[] = [
                'submission' => $sub,
                'values'     => $bySubmission[$sid] ?? [],
            ];
        }

        $format = strtolower((string) ($params['format'] ?? 'json'));
        if ($format === 'csv' && $export !== []) {
            $lines = ['submission_id,field_name,value'];
            foreach ($export as $row) {
                foreach ($row['values'] as $v) {
                    $lines[] = implode(',', [
                        (int) ($v['SubmissionId'] ?? 0),
                        '"' . str_replace('"', '""', (string) ($v['FieldName'] ?? '')) . '"',
                        '"' . str_replace('"', '""', (string) ($v['FieldValue'] ?? '')) . '"',
                    ]);
                }
            }
            return ['form_id' => $formId, 'format' => 'csv', 'content' => implode("\n", $lines)];
        }

        return ['form_id' => $formId, 'format' => 'json', 'submissions' => $export, 'count' => count($export)];
    }

    public function acymailingListLists(array $params): array
    {
        $this->assert('acymailing');
        $db = Factory::getDbo();
        $tables = $db->getTableList() ?: [];
        $prefix = $db->getPrefix();
        $table = in_array($prefix . 'acym_list', $tables, true) ? '#__acym_list' : '#__acymailing_lists';

        $query = $db->getQuery(true)->select('*')->from($table)->order('1 DESC');
        $db->setQuery($query, 0, 50);
        return ['lists' => $db->loadAssocList() ?: []];
    }

    private function assert(string $key): void
    {
        if (!$this->detector->isInstalled($key)) {
            throw new \RuntimeException("Integration '{$key}' is not installed.");
        }
    }
}
