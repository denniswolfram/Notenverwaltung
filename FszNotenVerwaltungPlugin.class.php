<?php

require_once "FszNotenVerwaltung.class.php";
require_once "FszNotenVerwaltungExcelExporter.class.php";

class FszNotenVerwaltungPlugin extends StudIPPlugin implements StandardPlugin
{
    private Flexi_TemplateFactory $template_factory;
    private string $seminar_id;
    private FszNotenVerwaltungConfig $config;
    private string $displayname;
    private bool $fsz = false;

    private const GETTEXT_DOMAIN = 'FszNotenVerwaltung';

    public function __construct()
    {
        parent::__construct();

        bindtextdomain(static::GETTEXT_DOMAIN, $this->getPluginPath() . '/locale');
        bind_textdomain_codeset(static::GETTEXT_DOMAIN, 'UTF-8');
    }

    public function getInfoTemplate(string $course_id): ?Template
    {
        return null; // Placeholder for future implementation
    }

    public function getIconNavigation(string $course_id, $last_visit, string $user_id): ?Navigation
    {
        return null;
    }

    public function getNotificationObjects(string $course_id, $since, string $user_id): array
    {
        return []; // Placeholder for future implementation
    }

    public function getMetadata(): array
    {
        $meta = parent::getMetadata();
        $meta['displayname'] = $meta['displayname'] ? $this->_($meta['displayname']) : $this->_('Notenverwaltung');
        $meta['description'] = $this->fsz
            ? $this->_('Notenverwaltung für das Leibniz Language Centre')
            : $this->_('Notenverwaltung');
        return $meta;
    }

    public function getTabNavigation(string $course_id): ?array
    {
        $this->seminar_id = $course_id;
        $meta = $this->getMetadata();
        $this->displayname = $meta['displayname'] ?: $this->_('Notenverwaltung');

        $this->template_factory = new Flexi_TemplateFactory(__DIR__ . '/templates/');
        $this->config = new FszNotenVerwaltungConfig($this->seminar_id);

        if ($this->config->conf['TITLE']) {
            $this->displayname = $this->config->conf['TITLE'];
        }

        if ($this->isVisible()) {
            $navigation = new Navigation($this->displayname, PluginEngine::getURL($this, ['action' => 'main']));
            $navigation2 = new Navigation($this->displayname);
            $navigation2->setUrl(PluginEngine::getUrl($this, ['action' => 'main']));
            $navigation->addSubnavigation('main', $navigation2);

            if ($GLOBALS['perm']->have_studip_perm('dozent', $this->seminar_id)) {
                $config_nav = new Navigation($this->_("Einstellungen"));
                $config_nav->setUrl(PluginEngine::getUrl($this, ['action' => 'config']));
                $navigation->addSubnavigation('config', $config_nav);
            }

            return [get_class($this) => $navigation];
        }

        return null;
    }

    public function perform(string $unconsumed_path): void
    {
        $args = explode('/', $unconsumed_path);
        $action = ($args[0] !== '' ? array_shift($args) : 'show') . '_action';

        if (!method_exists($this, $action)) {
            throw new Exception('Unbekannte Plugin-Aktion: ' . $action);
        }

        call_user_func_array([$this, $action], $args);
    }

    public function isVisible(): bool
    {
        return $GLOBALS['perm']->have_studip_perm('dozent', $this->seminar_id)
            || $this->config->conf['VISIBLE']
            || $this->config->conf['PART_VISIBLE'];
    }

    public function show_action(): void
    {
        if (!$this->isVisible()) {
            return;
        }

        if ($this->fsz && !isset($this->config->conf['UNICERT']) && stripos(Course::findCurrent()->name, 'unicert') !== false) {
            $this->config->conf['UNICERT'] = 1;
            $this->config->store();
        }

        $this->is_unicert = $this->config->conf['UNICERT'];
        ob_start();

        if ($GLOBALS['perm']->have_studip_perm('dozent', $this->seminar_id)) {
            $this->handleDozentActions();
        } else {
            $this->displayStudentInfoPage();
        }

        PageLayout::setTitle(Context::getHeaderLine() . ' - ' . $this->displayname);
        $layout = $GLOBALS['template_factory']->open('layouts/base.php');
        $layout->content_for_layout = ob_get_clean();
        echo $layout->render();
    }

    private function handleDozentActions(): void
    {
        if (isset($_REQUEST['action'])) {
            switch ($_REQUEST['action']) {
                case 'send_excel_sheet':
                case 'send_export_excel_sheet':
                    $user_ids = Request::optionArray('export');
                    $tmpfile = basename($_REQUEST['action'] === 'send_excel_sheet'
                        ? $this->createExcelSheet($user_ids)
                        : $this->createExportExcelSheet($user_ids));

                    if ($tmpfile) {
                        $download_link = FileManager::getDownloadURLForTemporaryFile($tmpfile, "Notenvergabe.xls");
                        header('Location: ' . $download_link);
                        page_close();
                        die;
                    }
                    break;

                case 'send_export':
                    $this->handleExport();
                    break;

                case 'config':
                    Navigation::activateItem('/course/' . get_class($this) . '/config');
                    $this->displayConfigPage();
                    break;

                default:
                    Navigation::activateItem('/course/' . get_class($this) . '/main');
                    $this->displayMainPage();
            }
        }
    }

    private function handleExport(): void
    {
        // Implementation of the export functionality
    }

    public function displayConfigPage(): void
    {
        // Implementation of the configuration page
    }

    public function displayMainPage(): void
    {
        // Implementation of the main page for managing grades
    }

    public function displayStudentInfoPage(): void
    {
        $msg = [];
        $fsz = new FszNotenVerwaltung($this->seminar_id);
        $fsz->fsz = $this->fsz;

        if (is_null($fsz->getFinalGrade($GLOBALS['user']->id)) && !$this->config->conf['PART_VISIBLE']) {
            PageLayout::postMessage(MessageBox::info($this->_("Ihre Gesamtnote steht noch nicht fest.")));
        } elseif (!$fsz->hasValues($GLOBALS['user']->id) && !$fsz->getValueErfolg($GLOBALS['user']->id) && !$fsz->getValueNiveau($GLOBALS['user']->id)) {
            PageLayout::postMessage(MessageBox::info($this->_("Es wurde für Sie noch keine Teilnote eingetragen.")));
        }

        $template = $this->template_factory->open('main_student');
        $template->set_attribute('plugin', $this);
        $template->set_attribute('user_id', $GLOBALS['user']->id);
        $template->set_attribute('msg', $msg);
        $template->set_attribute('fsz', $fsz);
        echo $template->render();
    }

    public function createExcelSheet(array $user_ids = []): string
    {
        $fsz = new FszNotenVerwaltung($this->seminar_id);
        $fsz->fsz = $this->fsz;
        return $fsz->createExcelSheet($user_ids);
    }

    public function createExportExcelSheet(array $user_ids = []): string
    {
        $fsz = new FszNotenVerwaltungExcelExporter([$this->seminar_id]);
        return $fsz->createExcelSheet($user_ids);
    }

    public function _(string $string): string
    {
        $result = static::GETTEXT_DOMAIN === null
            ? $string
            : dcgettext(static::GETTEXT_DOMAIN, $string, LC_MESSAGES);

        if ($result === $string) {
            $result = _($string);
        }

        if (func_num_args() > 1) {
            $arguments = array_slice(func_get_args(), 1);
            $result = vsprintf($result, $arguments);
        }

        return $result;
    }
}
