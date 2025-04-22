<?php

require_once "FszNotenVerwaltung.class.php";
require_once "FszNotenVerwaltungExcelExporter.class.php";


class FszNotenVerwaltungPlugin extends StudIPPlugin implements StandardPlugin
{
    public $template_factory, $seminar_id, $config, $displayname;
    public $fsz = false;


    const GETTEXT_DOMAIN = 'FszNotenVerwaltung';

    public function __construct()
    {
        parent::__construct();

        bindtextdomain(static::GETTEXT_DOMAIN, $this->getPluginPath() . '/locale');
        bind_textdomain_codeset(static::GETTEXT_DOMAIN, 'UTF-8');


    }

    public function getInfoTemplate($course_id)
    {

    }

    public function getIconNavigation($course_id, $last_visit, $user_id)
    {
        return null;
    }

    public function getNotificationObjects($course_id, $since, $user_id)
    {

    }

    public function getMetadata()
    {
        $meta = parent::getMetadata();
        $meta['displayname'] = $meta['displayname'] ? $this->_($meta['displayname']) : $this->_('Notenverwaltung');
        $meta['description'] = $this->fsz ? $this->_('Notenverwaltung für das Leibniz Language Centre') : $this->_('Notenverwaltung');
        return $meta;
    }

    public function getTabNavigation($course_id)
    {
        $this->seminar_id = $course_id;
        $meta = $this->getMetadata();
        $this->displayname = $meta['displayname'] ?: $this->_('Notenverwaltung');
        $this->template_factory = new Flexi_TemplateFactory(
            __DIR__ . '/templates/'
        );
        $this->config = new FszNotenVerwaltungConfig($this->seminar_id);
        if ($this->config->conf['TITLE']) {
            $this->displayname = $this->config->conf['TITLE'];
        }
        if ($this->isVisible()) {
            $navigation = new Navigation(
                $this->displayname,
                PluginEngine::getURL(
                    $this,
                    ['action' => 'main']
                )
            );
            $navigation2 = new Navigation($this->displayname);
            $navigation2->setUrl(
                PluginEngine::getUrl($this, ['action' => 'main'])
            );
            $navigation->addSubnavigation('main', $navigation2);
            if ($GLOBALS['perm']->have_studip_perm('dozent', $this->seminar_id)) {
                $config_nav = new Navigation($this->_("Einstellungen"));
                $config_nav->setUrl(
                    PluginEngine::getUrl($this, ['action' => 'config'])
                );
                $navigation->addSubnavigation('config', $config_nav);
            }

            return [get_class($this) => $navigation];
        }
    }


    public function perform($unconsumed_path)
    {
        $args = explode('/', $unconsumed_path);
        $action = $args[0] !== '' ? array_shift($args).'_action' : 'show_action';

        if (!method_exists($this, $action)) {
            throw new Exception('unbekannte Plugin-Aktion: ' . $action);
        }

        call_user_func_array(array($this, $action), $args);
    }

    public function isVisible()
    {
        return $GLOBALS['perm']->have_studip_perm('dozent', $this->seminar_id) || $this->config->conf['VISIBLE'] || $this->config->conf['PART_VISIBLE'];
    }

    public function show_action()
    {
        if (!$this->isVisible()) return;
        if ($this->fsz && !isset($this->config->conf['UNICERT']) && stripos(Course::findCurrent()->name, 'unicert') !== false) {
            $this->config->conf['UNICERT'] = 1;
            $this->config->store();
        }
        $this->is_unicert = $this->config->conf['UNICERT'];
        ob_start();
        if ($GLOBALS['perm']->have_studip_perm('dozent', $this->seminar_id)) {
            if (in_array($_REQUEST['action'], array('send_excel_sheet','send_export_excel_sheet'))) {
                $user_ids = Request::optionArray('export');
                $tmpfile = basename($_REQUEST['action'] == 'send_excel_sheet' ? $this->createExcelSheet($user_ids) : $this->createExportExcelSheet($user_ids));
                if ($tmpfile) {
                    $download_link = FileManager::getDownloadURLForTemporaryFile(
                        $tmpfile,
                        "Notenvergabe.xls"
                    );
                    header('Location: ' . $download_link);
                    page_close();
                    die;
                }
            } else  if($_REQUEST['action'] == 'send_export') {
                $member_mat_nr = [];
                $course_members = [];
                $k_niveaus = SimpleCollection::createFromArray(
                    DataField::findBySQL("name LIKE 'Kompetenzniveau%' AND object_type='user' ORDER BY name"))
                    ->toGroupedArray('datafield_id', 'name', function($e) {return current(current($e));});

                $captions = ['username', 'Vorname', 'Nachname', 'Matrikelnummer', 'Email', 'Studiengang'];
                $captions = array_merge($captions, array_values($k_niveaus));

                $rows = [];
                $cms = CourseMember::findByCourseAndStatus(
                    $this->seminar_id,
                    'autor'
                );

                foreach ($cms as $cm) {
                    $komp = array();
                    foreach ($k_niveaus as $id => $k) {
                        $komp[] = new DatafieldEntryModel(
                            [
                                $id,
                                $cm->user_id,
                                '',
                                ''
                            ]);
                    }
                    $mat_nr = new DatafieldEntryModel(
                        [
                            MATRIKELNUMMER_ID,
                            $cm->user_id,
                            '',
                            ''
                        ]
                    );
                    //Add data to CSV string:
                    $data = [$cm->username,
                        $cm->vorname,
                        $cm->nachname,
                        $mat_nr->content,
                        $cm->email,
                        join(';', $cm->user->studycourses->findBy('studycourse_name', '', '<>')->pluck('studycourse_name'))
                    ];
                    foreach ($komp as $k) {
                        $data[] = $k->content;
                    }
                    $rows[] = $data;
                }

                $tmpname = md5(uniqid('tmp'));
                if (array_to_csv($rows, $GLOBALS['TMP_PATH'].'/'.$tmpname, $captions)) {
                    header('Location: ' . FileManager::getDownloadURLForTemporaryFile($tmpname, FileManager::cleanFileName('Teilnehmende_' . Course::findCurrent()->name . '_' . date('Y-m-d') . '.csv')));

                    page_close();
                    die();
                }
            } else if ($_REQUEST['action'] == 'config') {
                Navigation::activateItem('/course/' . get_class($this) . '/config');
                $this->displayConfigPage();
            } else {
                Navigation::activateItem('/course/' . get_class($this) . '/main');
                $this->displayMainPage();
            }
        } else {
            Navigation::activateItem('/course/' . get_class($this) . '/main');
            $this->displayStudentInfoPage();
        }
        PageLayout::setTitle(
            Context::getHeaderLine() . ' - ' . $this->displayname
        );
        $layout = $GLOBALS['template_factory']->open('layouts/base.php');
        $layout->content_for_layout = ob_get_clean();
        echo $layout->render();
    }

    public function displayConfigPage()
    {
        $msg = array();
        if(Request::submitted('save') || Request::submitted('add_mark')) {
            if (Request::submitted('title')) {
                $this->config->conf['TITLE'] = trim(Request::get('title'));
            }
            $this->config->conf['VISIBLE'] = ($_REQUEST['visible'] == 'complete');
            $this->config->conf['PART_VISIBLE'] = ($_REQUEST['visible'] == 'part');
            $this->config->conf['UNICERT'] = Request::int('unicert');

            foreach (Request::getArray('marks') as $id => $data) {
                $this->config->marks[$id]['desc'] = trim($data['desc']);
                $this->config->marks[$id]['weight'] = abs((int)$data['weight']);
                $weight += (int)$data['weight'];
            }
            if (Request::submitted('add_mark')) {
                $this->config->marks[$this->config->getNewId()] = array(
                    'desc' => '',
                    'weight' => ($weight < 100 ? 100 - $weight : 0)
                );
                if ($weight > 100) {
                    PageLayout::postInfo(
                        $this->_("Die Summe der Gewichtung überschreitet 100%!")
                    );
                }
            }
            if (Request::submitted('save')) {
                if ($weight == 0) {
                    PageLayout::postMessage(MessageBox::error($this->_("Die Summe der Gewichtung darf nicht Null sein! Bitte korrigieren Sie die Werte.")));
                } else {
                    if ($this->config->store()) {
                        PageLayout::postMessage(MessageBox::info($this->_("Die Daten wurden gespeichert.")));
                        header("Location: " . PluginEngine::getURL($this, array('action' => 'config') ));
                        page_close();
                        die();
                    } else {
                        PageLayout::postMessage(MessageBox::error($this->_("Ein Fehler ist aufgetreten. Die Werte konnten nicht gespeichert werden.")));
                    }
                }
            }
        }
        if (strlen(Request::get('delete_mark'))) {
            if ($this->config->deleteMark(Request::get('delete_mark'))) {
                PageLayout::postMessage(MessageBox::info($this->_("Die Einzelnote wurde gelöscht.")));
            }
        }
        if (!count($this->config->marks)) {
            $this->config->marks[$this->config->getNewId()] = array('desc' => '', 'weight' => 100);
        }
        $template = $this->template_factory->open('config');
        $template->set_attribute('msg', $msg);
        $template->set_attribute('plugin', $this);
        $template->set_attribute('config', $this->config);
        echo $template->render();
    }

    public function displayMainPage()
    {
        PageLayout::addSqueezePackage('tablesorter');

        $msg = array();
        $fsz = new FszNotenVerwaltung($this->seminar_id);
        $fsz->fsz = $this->fsz;
        $seminar = Seminar::GetInstance($this->seminar_id);

        $fsz->unicert_marks = [
            'UCHV' => $this->_('Hörverstehen'),
            'UCLV' => $this->_('Leseverstehen'),
            'UCSA' => $this->_('Schriftlicher Ausdruck'),
            'UCMA' => $this->_('Mündlicher Ausdruck')
            ];
        if (Request::submitted('set_visible')) {
            $this->config->conf['INVISIBLE_WITHOUT_NOTE'] = Request::int('set_visible');
            $this->config->store();
        }
        if(Request::submitted('save')) {
            foreach (Request::getArray('usermark') as $key => $value) {
                [$user_id, $config_id] = explode('-', $key);
                $value = $value !== '' ? (float)abs(str_replace(',', '.', $value)) : '';
                if ($value !== '' || ($value === '' && $fsz->getValue($user_id, $config_id, 'chdate'))) {
                    $fsz->setValue($user_id, $config_id, 'value', $value);
                }
            }

            //FSZ
            if ($this->fsz) {
                foreach (Request::getArray('schein') as $key => $value) {
                    $fsz->setValueSchein($key, $value);
                }
                foreach (Request::getArray('niveau') as $key => $value) {
                    $fsz->setValueNiveau($key, $value);
                }
                foreach (Request::getArray('erfolg') as $key => $value) {
                    $fsz->setValueErfolg($key, $value);
                }
                foreach (array_keys($fsz->unicert_marks) as $ucert) {
                    foreach (Request::getArray($ucert) as $key => $value) {
                        $value = $value !== '' ? (float)abs(str_replace(',', '.', $value)) : '';
                        $fsz->setValueUnicert($key, $ucert, $value);
                    }
                }
            }
            if ($fsz->store()) {
                PageLayout::postInfo($this->_("Die Daten wurden gespeichert."));
            }
        }
        if ($this->fsz) {
            $this->admin_role = 'FSZ-Admin';
            $sql = "SELECT DISTINCT Vorname,Nachname,username,Email,user_id
                    FROM auth_user_md5
                    JOIN roles_user ON userid = user_id
                    JOIN roles USING(roleid)
                    WHERE rolename = ?
                    ORDER BY Nachname, Vorname";
            $statement = DBManager::get()->prepare($sql);
            $statement->execute(array($this->admin_role));

            $admins = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $userdata) {
                $fsz_admins[] = $userdata['username'];
            }
        }
        if (Request::submitted('set_niveau_default') && count($fsz->members) && !Request::submitted('no_niveau_default')) {
            foreach ($fsz->members as $user_data) {
                $fsz->setValueNiveau($user_data['user_id'], $_REQUEST['niveau_default']);
            }
            if ($fsz->store()) {
                PageLayout::postMessage(MessageBox::info($this->_("Niveau wurde für alle Teilnehmer gesetzt.")));
            }
        }
        if (!count($fsz->members)) {
            PageLayout::postMessage(MessageBox::info($this->_("In dieser Veranstaltung sind noch keine Teilnehmer eingetragen!.")));
        }
        if (!count($fsz->config->marks) && !$this->is_unicert) {
            PageLayout::postMessage(MessageBox::info($this->_("Sie haben noch keine Einzelnoten festgelegt. Das können sie unter \"Einstellungen\" tun.")));
        }
        $niveaus = array();
        if (!Request::submitted('no_niveau_default')) {
            foreach(array_keys($fsz->members) as $user_id) {
                $niveaus[] = $fsz->getValueNiveau($user_id);
            }
        }
        $default_niveau = count(array_unique($niveaus)) === 1 ? array_pop(array_unique($niveaus)) : null;
        $template = $this->template_factory->open('main');
        $template->set_attribute('msg', $msg);
        $template->set_attribute('plugin', $this);
        $template->set_attribute('fsz', $fsz);
        $template->set_attribute('default_niveau', $default_niveau);
        $template->set_attribute('seminar', $seminar);
        $template->set_attribute('fsz_admins', $fsz_admins);
        echo $template->render();
        // set up sidebar
        $actions = new ActionsWidget();
        if (!$this->config->conf['INVISIBLE_WITHOUT_NOTE']) {
            $actions->addLink(
                $this->_('Teilnehmer ohne Bewertung ausblenden'),
                PluginEngine::getLink($this, array('action' => 'main', 'set_visible' => 1)),
                Icon::create('visibility-visible', 'clickable')
            );
        } else {
            $actions->addLink(
                $this->_('Teilnehmer ohne Bewertung einblenden'),
                PluginEngine::getLink($this, array('action' => 'main', 'set_visible' => 0)),
                Icon::create('visibility-invisible', 'clickable')
            );
        }
        if ($this->fsz) {
            $actions->addLink(
                $this->_('Teilnehmerexport mit Kompetenzniveau'),
                PluginEngine::getLink($this, array('action' => 'send_export')),
                Icon::create('file-excel', 'clickable'),
                ['data-exportlink' => 1]
            );
        }

        $actions->addLink(
            $this->_('Download der Noten als Excel Datei'),
            PluginEngine::getLink($this, array('action' => 'send_excel_sheet')),
            Icon::create('file-excel', 'clickable'),
            ['data-exportlink' => 1]
        );

        if ($this->fsz) {
            $actions->addLink(
                $this->_('Download der Noten als LLC Export Datei'),
                PluginEngine::getLink($this, array('action' => 'send_export_excel_sheet')),
                Icon::create('file-excel', 'clickable'),
                ['data-exportlink' => 1]
            );
        }

        Sidebar::get()->addWidget($actions);
    }

    public function displayStudentInfoPage()
    {
        $msg = array();
        $fsz = new FszNotenVerwaltung($this->seminar_id);
        $fsz->fsz = $this->fsz;
        if(is_null($fsz->getFinalGrade($GLOBALS['user']->id)) AND ! $this->config->conf['PART_VISIBLE']){
            PageLayout::postMessage(MessageBox::info($this->_("Ihre Gesamtnote steht noch nicht fest.")));
        } else {
            if (! $fsz->hasValues($GLOBALS['user']->id) AND ! $fsz->getValueErfolg($GLOBALS['user']->id) AND ! $fsz->getValueNiveau($GLOBALS['user']->id)) {
                PageLayout::postMessage(MessageBox::info($this->_("Es wurde für Sie noch keine Teilnote eingetragen.")));
            }
        }
        $template = $this->template_factory->open('main_student');
        $template->set_attribute('plugin', $this);
        $template->set_attribute('user_id', $GLOBALS['user']->id);
        $template->set_attribute('msg', $msg);
        $template->set_attribute('fsz', $fsz);
        echo $template->render();
    }

    public function createExcelSheet($user_ids = array())
    {
        $fsz = new FszNotenVerwaltung($this->seminar_id);
        $fsz->fsz = $this->fsz;
        return $fsz->createExcelSheet($user_ids);
    }

    public function createExportExcelSheet($user_ids = array())
    {
        $fsz = new FszNotenVerwaltungExcelExporter(array($this->seminar_id));
        return $fsz->createExcelSheet($user_ids);
    }

    /**
     * Plugin localization for a single string.
     * This method supports sprintf()-like execution if you pass additional
     * parameters.
     *
     * @param String $string String to translate
     * @return translated string
     */
    public function _($string)
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

    /**
     * Plugin localization for plural strings.
     * This method supports sprintf()-like execution if you pass additional
     * parameters.
     *
     * @param String $string0 String to translate (singular)
     * @param String $string1 String to translate (plural)
     * @param mixed  $n       Quantity factor (may be an array or array-like)
     * @return translated string
     */
    public function _n($string0, $string1, $n)
    {
        if (is_array($n)) {
            $n = count($n);
        }

        $result = static::GETTEXT_DOMAIN === null
            ? $string0
            : dngettext(static::GETTEXT_DOMAIN, $string0, $string1, $n);
        if ($result === $string0 || $result === $string1) {
            $result = ngettext($string0, $string1, $n);
        }

        if (func_num_args() > 3) {
            $arguments = array_slice(func_get_args(), 3);
            $result = vsprintf($result, $arguments);
        }

        return $result;
    }
}
