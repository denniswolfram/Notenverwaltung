<?php
require_once "FszNotenVerwaltungConfig.class.php";


class FszNotenVerwaltung
{
    var $seminar_id;
    var $data = array();
    var $members = array();
    var $num_grades = 0;
    var $num_schein = 0;
    var $num_niveau = 0;
    var $num_erfolg = 0;
    var $unicert_marks = [
        'UCHV' => 'Hörverstehen',
        'UCLV' => 'Leseverstehen',
        'UCSA' => 'Schriftlicher Ausdruck',
        'UCMA' => 'Mündlicher Ausdruck'
    ];

    function __construct($seminar_id)
    {
        $this->seminar_id = $seminar_id;
        $seminar = new Seminar($seminar_id);
        $this->members = SimpleCollection::createFromArray($seminar->getMembers('autor'))
                                         ->orderBy('Nachname, Vorname')
                                         ->getArrayCopy();
        $this->config = new FszNotenVerwaltungConfig($this->seminar_id);
        $this->is_unicert = $this->config->conf['UNICERT'];
        $this->restore();
    }

    function restore()
    {
        $db = DbManager::get();
        $this->data = array();
        $c = 0;
        $cs = 0;
        $cn = 0;
        $ce = 0;
        $data = $db->fetchAll("SELECT su.user_id, fsz_c.id as config_id, fsz_n.value, changed_by_user_id, fsz_n.chdate FROM seminar_user su
                    LEFT JOIN fsz_notenverwaltung_config fsz_c ON  fsz_c.seminar_id = su.seminar_id AND fsz_c.name LIKE 'MARK%'
                    LEFT JOIN fsz_notenverwaltung_noten fsz_n ON fsz_n.config_id = fsz_c.id AND fsz_n.user_id=su.user_id
                    WHERE su.seminar_id=? AND su.status='autor' ", array($this->seminar_id));
        foreach ($data as $row) {
            $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
            if($row['chdate']) ++$c;
        }
        foreach($db->fetchAll("SELECT * FROM fsz_notenverwaltung_noten WHERE config_id=?", array(md5('schein' . $this->seminar_id))) as $row) {
            $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
            if($row['chdate']) ++$cs;
        }
        foreach($db->fetchAll("SELECT * FROM fsz_notenverwaltung_noten WHERE config_id=?", array(md5('niveau' . $this->seminar_id))) as $row) {
            $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
            if($row['chdate']) ++$cn;
        }
        foreach($db->fetchAll("SELECT * FROM fsz_notenverwaltung_noten WHERE config_id=?", array(md5('erfolg' . $this->seminar_id))) as $row) {
            $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
            if($row['chdate']) ++$ce;
        }
        foreach (array_keys($this->unicert_marks) as $ucert) {
            foreach($db->fetchAll("SELECT * FROM fsz_notenverwaltung_noten WHERE config_id=?", array(md5($ucert . $this->seminar_id))) as $row) {
                $this->data[$row['user_id'] . '-' . $row['config_id']] = $row;
                if($row['chdate']) ++${'c' . $ucert};
            }
        }
        $this->num_grades = $c;
        $this->num_schein = $cs;
        $this->num_niveau = $cn;
        $this->num_erfolg = $ce;
        foreach (array_keys($this->unicert_marks) as $ucert) {
            $this->{'num_' . $ucert} = ${'c' . $ucert};
        }
    }

    function getValue($user_id, $config_id, $field)
    {
        return $this->data[$user_id . '-' . $config_id][$field];
    }

    function setValue($user_id, $config_id, $field, $value)
    {
        return ($this->data[$user_id . '-' . $config_id][$field] = $value);
    }

    function getValueUnicert($user_id, $unicert)
    {
        $config_id = md5($unicert . $this->seminar_id);
        return $this->data[$user_id . '-' . $config_id]['value'];
    }

    function setValueUnicert($user_id, $unicert, $value)
    {
        $config_id = md5($unicert . $this->seminar_id);
        $this->data[$user_id . '-' . $config_id]['user_id'] = $user_id;
        $this->data[$user_id . '-' . $config_id]['config_id'] = $config_id;
        return ($this->data[$user_id . '-' . $config_id]['value'] = $value);
    }

    function getValueSchein($user_id)
    {
        $config_id = md5('schein' . $this->seminar_id);
        $schein = $this->data[$user_id . '-' . $config_id]['value'];
        if(!is_null($schein)) return $schein;
        else return(!in_array($this->getGradeAsWord($this->getFinalGrade($user_id)), array('','nicht bestanden')) ? 'LB' : '');
    }

    function setValueSchein($user_id, $value)
    {
        $config_id = md5('schein' . $this->seminar_id);
        $this->data[$user_id . '-' . $config_id]['user_id'] = $user_id;
        $this->data[$user_id . '-' . $config_id]['config_id'] = $config_id;
        return ($this->data[$user_id . '-' . $config_id]['value'] = $value);
    }

    function getValueNiveau($user_id)
    {
        $config_id = md5('niveau' . $this->seminar_id);
        $n = $this->data[$user_id . '-' . $config_id]['value'];
        return (string)$n;
    }

    function setValueNiveau($user_id, $value)
    {
        $config_id = md5('niveau' . $this->seminar_id);
        $this->data[$user_id . '-' . $config_id]['user_id'] = $user_id;
        $this->data[$user_id . '-' . $config_id]['config_id'] = $config_id;
        return ($this->data[$user_id . '-' . $config_id]['value'] = $value);
    }

    function getValueErfolg($user_id)
    {
        $config_id = md5('erfolg' . $this->seminar_id);
        $n = $this->data[$user_id . '-' . $config_id]['value'];
        return (int)$n;
    }

    function setValueErfolg($user_id, $value)
    {
        $config_id = md5('erfolg' . $this->seminar_id);
        $this->data[$user_id . '-' . $config_id]['user_id'] = $user_id;
        $this->data[$user_id . '-' . $config_id]['config_id'] = $config_id;
        return ($this->data[$user_id . '-' . $config_id]['value'] = $value);
    }

    function getFinalGrade($user_id)
    {
        $final = 0;
        $c = 0;
        if ($this->is_unicert) {
            foreach (array_keys($this->unicert_marks) as $ucert) {
                $one = $this->getValueUnicert($user_id, $ucert);
                if ($one === null || $one === '') continue;
                $one = $one !== '' ? (float)abs(str_replace(',', '.', $one)) : '';
                if ($one >= 5) return 5;
                $final += $one * 100;
                $c += 100;
            }
            if ($c < 200) return null;
        }
        if (!$c) {
            foreach ($this->config->marks as $mid => $mark) {
                $one = $this->getValue($user_id, $mid, 'value');
                if ($one === null || $one === '') return null;
                $final += $one * $mark['weight'];
                $c += $mark['weight'];
            }
        }
        if ($c) {
            if ($this->fsz) {
                return $this->getFSZFinalGrade($final / $c);
            } else {
                return round($final / $c,1);
            }
        }
        else return null;
    }

    function hasValues($user_id)
    {
        foreach($this->config->marks as $mid => $mark) {
            if ($this->getValue($user_id, $mid, 'value') !== null) return true;
        }
        foreach (array_keys($this->unicert_marks) as $ucert) {
            if ($this->getValueUnicert($user_id, $ucert)) return true;
        }

        return false;
    }

    function getFSZFinalGrade($grade)
    {
        $ret = null;
        switch (true) {
            case ($grade < 1.15):
                $ret = 1;
                break;
            case ($grade < 1.5):
                $ret = 1.3;
                break;
            case ($grade < 1.85):
                $ret = 1.7;
                break;
            case ($grade < 2.15):
                $ret = 2;
                break;
            case ($grade < 2.5):
                $ret = 2.3;
                break;
            case ($grade < 2.85):
                $ret = 2.7;
                break;
            case ($grade < 3.15):
                $ret = 3;
                break;
            case ($grade < 3.5):
                $ret = 3.3;
                break;
            case ($grade < 3.85):
                $ret = 3.7;
                break;
            case ($grade < 4.15):
                $ret = 4;
                break;
            case ($grade < 4.5):
                $ret = 4.3;
                break;
            case ($grade):
                $ret = $grade;
                break;
        }
        return $ret;
    }

    function getGradeAsWord($grade){
        $ret = '';
        switch (true) {
            case ($grade >= 1 && $grade <= 1.3):
                $ret = 'sehr gut';
                break;
            case ($grade > 1.3 && $grade <= 2.3):
                $ret = 'gut';
                break;
            case ($grade > 2.3 && $grade <= 3.3):
                $ret = 'befriedigend';
                break;
            case ($grade > 3.3 && $grade <= 4.3):
                $ret = 'ausreichend';
                break;
            case ($grade > 4.3):
                $ret = 'nicht bestanden';
                break;
        }
        return $ret;
    }

    function store()
    {
        $db = DbManager::get();
        $ret = 0;
        foreach($this->data as $key => $row){
            if(!is_null($row['value'])){
                if($row['chdate']){
                    $ok = $db->execute("UPDATE fsz_notenverwaltung_noten SET value=? WHERE user_id=? AND config_id=?",
                                       array($row['value'], $row['user_id'], $row['config_id']));
                    if($ok){
                        $db->execute("UPDATE fsz_notenverwaltung_noten SET changed_by_user_id=?,chdate=UNIX_TIMESTAMP() WHERE user_id=? AND config_id=?",
                                     array($GLOBALS['user']->id, $row['user_id'], $row['config_id']));
                    }
                } else {
                    $ok = $db->execute("INSERT INTO  fsz_notenverwaltung_noten (value,user_id,config_id,changed_by_user_id,mkdate,chdate) VALUES(?,?,?,?,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())",
                                       array($row['value'], $row['user_id'], $row['config_id'], $GLOBALS['user']->id));
                }
                $ret += $ok;
            }
        }
        if($ret) $this->restore();
        return $ret;
    }

    function createExcelSheet($user_ids = array())
    {
        require_once "vendor/write_excel/OLEwriter.php";
        require_once "vendor/write_excel/BIFFwriter.php";
        require_once "vendor/write_excel/Worksheet.php";
        require_once "vendor/write_excel/Workbook.php";

        $tempfile = null;
        $seminar = Seminar::GetInstance($this->seminar_id);
        $semester = SemesterData::GetInstance();
        $one_semester = $semester->getSemesterDataByDate($seminar->getSemesterStartTime());

        $tmpfile = $GLOBALS['TMP_PATH'] . '/' . md5(uniqid('write_excel',1));
        // Creating a workbook
        $workbook = new Workbook($tmpfile);
        $head_format = $workbook->addformat();
        $head_format->set_size(12);
        $head_format->set_bold();
        $head_format->set_align("left");
        $head_format->set_align("vcenter");

        $head_format_merged = $workbook->addformat();
        $head_format_merged->set_size(12);
        $head_format_merged->set_bold();
        $head_format_merged->set_align("left");
        $head_format_merged->set_align("vcenter");
        $head_format_merged->set_merge();
        $head_format_merged->set_text_wrap();

        $caption_format = $workbook->addformat();
        $caption_format->set_size(10);
        $caption_format->set_align("left");
        $caption_format->set_align("vcenter");
        $caption_format->set_bold();
        //$caption_format->set_text_wrap();

        $data_format = $workbook->addformat();
        $data_format->set_size(10);
        $data_format->set_align("left");
        $data_format->set_align("vcenter");

        $caption_format_merged = $workbook->addformat();
        $caption_format_merged->set_size(10);
        $caption_format_merged->set_merge();
        $caption_format_merged->set_align("left");
        $caption_format_merged->set_align("vcenter");
        $caption_format_merged->set_bold();

        // Creating the first worksheet
        $worksheet1 = $workbook->addworksheet(_("Notenverwaltung"));

        if (!$this->is_unicert) {
            $max_col = 8 + count($this->config->marks);
        } else {
            $max_col = 7 + count($this->unicert_marks);
        }
        foreach (range(1,$max_col) as $c) {
            $worksheet1->write_blank(0,$c,$head_format);
        }
        foreach (range(1,$max_col) as $c) {
            $worksheet1->write_blank(1,$c,$head_format);
        }
        foreach (range(0,$max_col) as $c) {
            $worksheet1->set_column(1, $c, 15);
        }

        $worksheet1->set_row(0, 20);
        $worksheet1->write_string(
            0,
            0,
            iconv(
                'utf-8',
                'cp1252',
                _("Notenvergabe") . ' - ' . $seminar->getName()
              . ' ('.$one_semester['name'].')'
            ),
            $head_format
        );
        $worksheet1->set_row(1, 20);

        $row = 2;
        $c = 0;

        $worksheet1->write_string(
            $row,
            $c++,
            iconv(
                'utf-8',
                'cp1252',
                _("Nachname")
            ),
            $caption_format
        );
        $worksheet1->write_string(
            $row,
            $c++,
            iconv(
                'utf-8',
                'cp1252',
                _("Vorname")
            ),
            $caption_format
        );
        $worksheet1->write_string(
            $row,
            $c++,
            iconv(
                'utf-8',
                'cp1252',
                _("Email")
            ),
            $caption_format
        );
        if ($this->fsz) {
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    _("Niveau")
                ),
                $caption_format
            );
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    _("Schein")
                ),
                $caption_format
            );
            if (!$this->is_unicert) {
                $worksheet1->write_string(
                    $row,
                    $c++,
                    iconv(
                        'utf-8',
                        'cp1252',
                        _("mit Erfolg bestanden")
                    ),
                    $caption_format
                );
            }
        }
        if (!$this->is_unicert) {
            foreach ($this->config->marks as $mark) {
                $worksheet1->write_string(
                    $row,
                    $c++,
                    iconv(
                        'utf-8',
                        'cp1252',
                        $mark['desc'] ? $mark['desc'] : (($this->fsz ? $c - 5 : $c - 2) . '. Einzelnote' . ' (' . $mark['weight'] . '%)')
                    ),
                    $caption_format
                );
            }
        } else {
            foreach ($this->unicert_marks as $mark => $mark_desc) {
                $worksheet1->write_string(
                    $row,
                    $c++,
                    iconv(
                        'utf-8',
                        'cp1252',
                        $mark_desc
                    ),
                    $caption_format
                );
            }
        }
        $worksheet1->write_string(
            $row,
            $c++,
            iconv(
                'utf-8',
                'cp1252',
                _("Gesamtnote")
            ),
            $caption_format
        );
        $worksheet1->write_string(
            $row,
            $c++,
            iconv(
                'utf-8',
                'cp1252',
                _("Geändert am")
            ),
            $caption_format
        );
        $worksheet1->write_string(
            $row,
            $c++,
            iconv(
                'utf-8',
                'cp1252',
                _("Geändert von")
            ),
            $caption_format
        );

            ++$row;

        foreach ($this->members as $user_id => $user_data) {
            if (count($user_ids) && !in_array($user_id, $user_ids)) continue;
            $final = $this->getFinalGrade($user_id);
            if(!($final!==null || $this->getValueSchein($user_id) || $this->getValueErfolg($user_id))) continue;
            $c = 0;
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    FszNotenVerwaltung::FormatName($user_data['Nachname'])
                ),
                $data_format
            );
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    FszNotenVerwaltung::FormatName($user_data['Vorname'])
                ),
                $data_format
            );
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    $user_data['Email']
                ),
                $data_format
            );

            if ($this->fsz) {
                $worksheet1->write_string(
                    $row,
                    $c++,
                    iconv(
                        'utf-8',
                        'cp1252',
                        $this->getValueNiveau($user_id)
                    ),
                    $data_format
                );
                $worksheet1->write_string(
                    $row,
                    $c++,
                    iconv(
                        'utf-8',
                        'cp1252',
                        $this->getValueSchein($user_id)
                    ),
                    $data_format
                );
                if (!$this->is_unicert) {
                    $worksheet1->write_string(
                        $row,
                        $c++,
                        iconv(
                            'utf-8',
                            'cp1252',
                            $this->getValueErfolg($user_id) ? 'x' : ''
                        ),
                        $data_format
                    );
                }
            }
            $changed = array();
            if (!$this->is_unicert) {
                foreach (array_keys($this->config->marks) as $mid) {
                    $worksheet1->write(
                        $row,
                        $c++,
                        str_replace('.', ',',
                            $this->getValue($user_id, $mid, 'value')
                        ),
                        $data_format
                    );
                }
            } else {
                foreach (array_keys($this->unicert_marks) as $unicert) {
                    $worksheet1->write(
                        $row,
                        $c++,
                        str_replace('.', ',',
                            $this->getValueUnicert($user_id, $unicert)
                        ),
                        $data_format
                    );
                }
            }
            if ($final !== null) {
                echo $final = round($final,1);
            } else {
                $final = '';
            }
            if ($this->getValueErfolg($user_id)) {
                $final = _("mit Erfolg bestanden");
            }
            krsort($changed, SORT_NUMERIC);
            [$last_changed, $last_changed_user_id] = array_values($this->getLastChanged($user_id));
            $worksheet1->write(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    $final
                ),
                $data_format
            );
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    ($last_changed > 0 ? date("d.m.Y G:i", $last_changed) : '')
                ),
                $data_format
            );
            $worksheet1->write_string(
                $row,
                $c++,
                iconv(
                    'utf-8',
                    'cp1252',
                    ($last_changed_user_id ? get_fullname($last_changed_user_id) : '')
                ),
                $data_format
            );
                                                        ++$row;
        }
        $workbook->close();
        return $tmpfile;
    }

    function getStatistics()
    {
        $ret = array();
        if(count($this->members)){
            $c = 0;
            $cs = 0;
            $cf = array();
            $changed = array();
            foreach($this->members as $user_id => $user_data){
                $final = $this->getFinalGrade($user_id);
                if(!($final || $this->getValueSchein($user_id))) continue;
                if($final) {
                    $cf[] = $final;
                           ++$c;
                }
                if($this->getValueSchein($user_id)) ++$cs;
                if(count($this->config->marks)){
                    foreach(array_keys($this->config->marks) as $mid) {
                        if ($this->getValue($user_id, $mid, 'chdate')){
                            $changed[$this->getValue($user_id, $mid, 'chdate')] = $this->getValue($user_id, $mid, 'changed_by_user_id');
                        }
                    }
                }
                if($this->getValue($user_id, md5('schein' . $this->seminar_id), 'chdate')){
                    $changed[$this->getValue($user_id, md5('schein' . $this->seminar_id), 'chdate')] = $this->getValue($user_id, md5('schein' . $this->seminar_id), 'changed_by_user_id');
                }
            }
            krsort($changed, SORT_NUMERIC);
            [$last_changed, $last_changed_user_id] = each($changed);
            $ret['count'] = $c;
            $ret['last_changed'] = $last_changed;
            if(count($cf)) $ret['average'] = round((array_sum($cf) / count($cf)),1);
            $ret['members'] = count($this->members);
            $ret['schein'] = $cs;
        }
        return $ret;
    }

    function FormatName($name=NULL)
    {
        return trim($name);
    }

    function getLastChanged($user_id)
    {
        $changed = array();
        if (count($this->config->marks)) {
            foreach (array_keys($this->config->marks) as $mid) {
                if ($this->getValue($user_id, $mid, 'chdate')) {
                    $changed[$this->getValue($user_id, $mid, 'chdate')] = $this->getValue($user_id, $mid, 'changed_by_user_id');
                }
            }
        }
        if ($this->getValue($user_id, md5('schein' . $this->seminar_id), 'chdate')) {
            $changed[$this->getValue($user_id, md5('schein' . $this->seminar_id), 'chdate')] = $this->getValue($user_id, md5('schein' . $this->seminar_id), 'changed_by_user_id');
        }
        if ($this->getValue($user_id, md5('erfolg' . $this->seminar_id), 'chdate')) {
            $changed[$this->getValue($user_id, md5('erfolg' . $this->seminar_id), 'chdate')] = $this->getValue($user_id, md5('erfolg' . $this->seminar_id), 'changed_by_user_id');
        }
        if ($this->getValue($user_id, md5('niveau' . $this->seminar_id), 'chdate')) {
            $changed[$this->getValue($user_id, md5('niveau' . $this->seminar_id), 'chdate')] = $this->getValue($user_id, md5('niveau' . $this->seminar_id), 'changed_by_user_id');
        }
        krsort($changed, SORT_NUMERIC);
        [$last_changed, $last_changed_user_id] = each($changed);
        return compact('last_changed', 'last_changed_user_id');
    }
}
